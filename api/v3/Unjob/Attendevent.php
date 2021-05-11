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
  ]);

  /*ob_start();
  print "Events in Window:\n";
  print_r($events);
  print "\n";
  Civi::log()->debug(ob_get_clean());
  */

  //Process any events
  foreach($events['values'] as $event) {

    /*ob_start();
    print "Current Event:\n";
    print_r($event);
    printf ("active? %s\n", $event['is_active']);
    printf ("id? %s\n", $event['id']);
    Civi::log()->debug(ob_get_clean());
    */

    //If the event is type "waiting" waiting, start "recording"" attendance webforms
    if ($event['event_type_id'] == $waiting_type) {
      $result = civicrm_api3('Event', 'create', [
        'id' => $event['id'],
        'event_type_id' => $recording_type,
      ]);
    }

    //Process event's participant records registered between (start - buffer) and end
    $switched = registered_to_attended($event['id'],
      date_create($event['start_date'])->modify("-".$window_buffer." minutes")->format('Y-m-d H:i:s'),
      $event['end_date']);
    $job_return[$event['id']]['switched'] = $switched;

    //Count event's attendance
    $attended = count_attendance($event['id']);
    $job_return[$event['id']]['attended'] = $attended;
  }

  return civicrm_api3_create_success($job_return, $params, 'Unjob', 'Attendevent');
}

function registered_to_attended($event_id, $from, $to) {
  //Change participant status from registered to attended if record created within the event's time window

  /*ob_start();
  printf("Event: %s\n", $event_id);
  printf("Dates from %s to %s\n", $from, $to);
  Civi::log()->debug(ob_get_clean());
  */

  $registereds = civicrm_api3('Participant', 'get', [
    'sequential' => 1,
    'event_id' => $event_id,
    'status_id' => 'Registered',
    'custom_173' => ['>=' => $from], //Custom field where attendance form populates submission datetime as a string
  ]);

  /*ob_start();
  print "Registered:\n";
  print_r($registereds);
  print "\n";
  Civi::log()->debug(ob_get_clean());
  */
  
  $new_switched = 0;
  $all_switched = civicrm_api3('Event', 'get', [
    'sequential' => 1,
    'id' => $event_id,
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
  }

  $all_switched += $new_switched;
  civicrm_api3('Event', 'create', [
    'id' => $event_id,
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
