<?php
use CRM_Unutils_ExtensionUtil as E;

/**
 * Unjob.Attendevent API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_unjob_Attendevent_spec(&$spec) {
  $spec['cron_minutes']['api.required'] = 1; //Minutes between cron ticks, this job should run always
  $spec['waiting_type']['api.required'] = 1; //ID of event type to switch from to take registrations (type's title works too)
  $spec['recording_type']['api.required'] = 1; //ID that events switch to in order to take registrations, and remain in
}

/**
 * Unjob.Attendevent API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 *
 * @throws API_Exception
 */
function civicrm_api3_unjob_Attendevent($params) {
  $window_buffer = $params['cron_minutes'] + 5; //Attendance window opens minimum 5 minutes pre-start and stays open at least one cron cycle after end
  $waiting_type = $params['waiting_type'];
  $recording_type = $params['recording_type'];
  $job_return = []; //For the scheduled job's log

  //Date objects bracketing the events of interest
  //(start - buffer <= now <= end + buffer) rewritten as (start <= now + buffer AND end >= now - buffer)
  $dt_to = date_create("now", timezone_open("America/New_York"));
  $dt_from = clone $dt_to;
  $dt_from->modify("+".$window_buffer." minutes");
  $dt_to->modify("-".$window_buffer." minutes");

  /*ob_start();
  printf("Dates from %s to %s\n", $dt_from->format('Y-m-d H:i:s'), $dt_to->format('Y-m-d H:i:s'));
  printf("Event type: %s\n", $waiting_type);
  print "\n";
  Civi::log()->debug(ob_get_clean());
  */

  //Get events of interest
  $events = civicrm_api3('Event', 'get', [
    'sequential' => 1, //The values[] keys aren't useful
    'is_active' => 1,
    'event_type_id' => ['IN' => [$waiting_type, $recording_type]],
    'start_date' => ['<=' => $dt_from->format('Y-m-d H:i:s')],
    'end_date' => ['>=' => $dt_to->format('Y-m-d H:i:s')],
    'options' => ['limit' => 0]
  ]);

  /*ob_start();
  print "Events in Window:\n";
  print_r($events);
  print "\n";
  Civi::log()->debug(ob_get_clean());
  */

  //Process any events
  foreach($events['values'] as $event) {

ob_start();
    print "Current Event:\n";
    print_r($event);
Civi::log()->debug(ob_get_clean());
    

    //If the event is type "waiting" start "recording" attendance webforms
    if ($event['event_type_id'] == $waiting_type) {
      $result = civicrm_api3('Event', 'create', [
        'id' => $event['id'],
        'event_type_id' => $recording_type,
      ]);
    }

    //Process event's participant records registered between (start - buffer) and end
    $switched = registered_to_attended($event,
      date_create($event['start_date'])->modify("-".$window_buffer." minutes")->format('Y-m-d H:i:s'),
      $event['end_date']);
    $job_return[$event['id']]['switched'] = $switched;

    //Count event's attendance
    $attended = count_attendance($event['id']);
    $job_return[$event['id']]['attended'] = $attended;
  }

  return civicrm_api3_create_success($job_return, $params, 'Unjob', 'Attendevent');
}

function registered_to_attended($event, $from, $to) {
  //Change participant status from registered to attended if record created within the event's time window

  /*ob_start();
  printf("Event: %s\n", $event_id);
  printf("Dates from %s to %s\n", $from, $to);
  Civi::log()->debug(ob_get_clean());
  */

  $registereds = civicrm_api3('Participant', 'get', [
    'sequential' => 1, //To access values[0] later
    'event_id' => $event['id'],
    'status_id' => 'Registered',
    'custom_173' => ['>=' => $from], //Custom field where attendance form populates submission datetime as a string
  ]);

ob_start();
  print "Registered:\n";
  print_r($registereds);
  print "\n";
Civi::log()->debug(ob_get_clean());
  
  
  $new_switched = 0;
  $all_switched = civicrm_api3('Event', 'get', [
    'sequential' => 1,
    'id' => $event['id'],
    'return' => ["custom_170"], //Get webform submissions from previous runs to add this run
    ])['values'][0]['custom_170'] ?? 0;

  /*ob_start();
  print "Previous submissions:\n";
  print_r($all_switched);
  print "\n";
  Civi::log()->debug(ob_get_clean());
  */
  
  foreach($registereds['values'] as $registered) {
    if (strtotime($registered['custom_173']) > strtotime($to)) continue; //Instead of the misfiring use of BETWEEN in the participant get
      civicrm_api3('Participant', 'create', [
        'id' => $registered['id'],
        'status_id' => "Attended",
    ]);
    $new_switched += 1;

    /*ob_start();
    printf("%s to attended\n", $registered['display_name']);
    printf("Comparison %d > %d\n", strtotime($registered['participant_register_date']), strtotime($to));
    Civi::log()->debug(ob_get_clean());
    */

    //Evaluate group membership
    group_match($event, $registered);

  }

  $all_switched += $new_switched;
  civicrm_api3('Event', 'create', [
    'id' => $event['id'],
    'custom_170' => $all_switched, //Save the new count of webforms submitted
  ]);

  return $new_switched;
}

function count_attendance($event_id) {
  //Fill the corresponding event custom field with the number of individuals in "Attended" participant records
  $attended = 0;

  $attendees = civicrm_api3('Participant', 'get', [ //Not limiting count to particular roles in the event
    'sequential' => 1,
    'event_id' => $event_id,
    'status_id' => 'Attended',
    'options' => ['limit' => 0]
  ]);

  foreach($attendees['values'] as $attendee) {
    $attended += 1;
    if ($attendee['custom_171'] > 0) $attended += $attendee['custom_171']; //Additional attendees
  }

  civicrm_api3('Event', 'create', [
    'id' => $event_id,
    'custom_129' => $attended, //Total event attendance, will be updated throughout the event's time window
  ]);
  return $attended;
}

function group_match($event, $registered) {
  //If the event specifies a valid group of anticipated attendees, try and match this attendee to a member of the group

  if(($group_id = intval($event['custom_204'])) <= 0)
    return; //Group ID custom field

ob_start();
printf("Group id: %d\n", $group_id);
Civi::log()->debug(ob_get_clean());

  //Get array of contacts in group and children, indexed by contact id
  $contactsGroup = getNestedContacts($group_id);

  if(!is_array($contactsGroup)) //Ignore if failed to get a result
    return;

  if(sizeof($contactsGroup) == 0) //Ignore if the group is empty
    return;

ob_start();
  echo("contactsGroup\n");
  print_r($contactsGroup);
Civi::log()->debug(ob_get_clean());

  //Is the registered contact in the array? If so nothing to do, all's good
  if(array_key_exists($registered['contact_id'], $contactsGroup))
    return;
    
ob_start();
  echo("Registered not in group\n");
Civi::log()->debug(ob_get_clean());

  //Get registered's phone and email
  $phone_email = civicrm_api3('Contact', 'get', [
    'sequential' => 1,
    'return' => ["email", "phone"],
    'id' => $registered['contact_id'],
  ]);

  //Find contacts that match registered by either email or phone
  //Added 1st and last name match 10/17/23
  $matches = \Civi\Api4\Contact::getDuplicates()
   ->setDedupeRule('Phone_or_Email')
   ->addValue('phone_primary.phone_numeric', $phone_email['values'][0]['phone'])
   ->addValue('email_primary.email', $phone_email['values'][0]['email'])
   ->execute();
ob_start();
echo("Registered phone_email\n");
print_r($phone_email);
printf("%s matches\n", $matches->count());
print_r($matches);
Civi::log()->debug(ob_get_clean());

  //Only interested in matches in the group
  $groupMatch = [];
  if($matches->count() > 1) { //Should always match at least registered
    foreach ($matches as $match) {
      if(array_key_exists($match['id'], $contactsGroup))
        $groupMatch[$match['id']] = $contactsGroup[$match['id']];
    }
ob_start();  
    echo("groupMatch\n");
    print_r($groupMatch);
Civi::log()->debug(ob_get_clean());
  }
  
  $activityParams = ['values' => [
  'activity_type_id' => 1, //14 = Follow-up, 1 = Meeting
  'status_id' => 7, //Available
  'assignee_contact_id' => [$event['created_id']],
  'source_contact_id' => $registered['contact_id'],
  'target_contact_id' => array_keys($groupMatch),
  'subject' => 'Unexpected attendance',
  'details' => sprintf("Event: %s<br>\nAttendance form from %s (id %s)<br>\nContact is not in the event's group of \"Anticipated participants\" (id %s)<br>\nAction taken: ",
    $event['title'], $registered['display_name'],
    $registered['contact_id'], $group_id), 
  ]];

ob_start();  
    echo("activityParams\n");
    print_r($activityParams);
Civi::log()->debug(ob_get_clean());

  $newActivity = NULL;

  if(count($groupMatch) == 0) {
    $activityParams['values']['details'] .= "No action taken, didn't match any contacts in the group<br>\n";
    $newActivity = civicrm_api4('Activity', 'create', $activityParams);

  } elseif (count($groupMatch) > 1) {
    $activityParams['values']['details'] .= "No action taken, matched more than one \"With Contact(s)\" in the group<br>\n";
    $newActivity = civicrm_api4('Activity', 'create', $activityParams);

  } else {
  //One match in group
  //Reassign the participant record to this match
  $participantParams = [
    'where' => [['id', '=', $registered['id']]],
    'values' => ['contact_id' => array_keys($groupMatch)[0]]
  ];

ob_start();  
  echo("participantParams\n");
  print_r($participantParams);
Civi::log()->debug(ob_get_clean());

  civicrm_api4('Participant', 'update', $participantParams);
  $activityParams['values']['details'] .= "Attendance reassigned to the matching \"With Contact(s)\" in the group<br>\n";
  $newActivity = civicrm_api4('Activity', 'create', $activityParams);
  }
  
  if(isset($newActivity)) {
//APIs don't send emails to assignees, force one
        $assignee_contact = civicrm_api3('Contact', 'getsingle', [
          'id' => $activityParams['values']['assignee_contact_id'],
        ]);

        CRM_Case_BAO_Case::sendActivityCopy(NULL, $newActivity->single()['id'], [$assignee_contact['email'] => $assignee_contact], NULL, NULL);

  }

}

//Navigate group parent-child nesting to extract all contacts associated with a group
function getNestedContacts($groupId) {
    $contacts = []; //Results array: key => contact_id, value => string of groups
	
	$groupChildren = civicrm_api4('GroupNesting', 'get', [
	  'select' => [
        'child_group_id',
      ],
      'where' => [
        ['parent_group_id', '=', $groupId],
      ],
    ]);

ob_start();  
  printf("groupChildren of %d\n", $groupId);
  print_r($groupChildren);
Civi::log()->debug(ob_get_clean());

    //Recursion down group nesting hierarchy/tree	
    foreach ($groupChildren as $groupChild) {
      $childContacts = getNestedContacts($groupChild['child_group_id']);
	  foreach ($childContacts as $childContactId => $childContactString)
	    $contacts[$childContactId] .= $childContactString; 
	}

    //Any group, whether branch or leaf, may have contacts
    $groupContacts = civicrm_api4('GroupContact', 'get', [
      'select' => [
        'contact_id',
      ],
      'where' => [
        ['group_id', '=', $groupId],
        ['status', '=', 'Added'],
        ['contact_id.is_deleted', '=', FALSE],
      ],
    ]); 

    foreach ($groupContacts as $groupContact) {
	  $contacts[$groupContact['contact_id']] .= strval($groupId)." ";
    }
	
    return $contacts;
}
