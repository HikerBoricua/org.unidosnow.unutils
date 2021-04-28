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
  $spec['event_type']['api.required'] = 1; //ID of event type to activate and update registrations (type's title works too)
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
  $event_type = $params['event_type'];

  //Date objects bracketing the events of interest
  //(start - buffer <= now <= end + buffer) rewritten as (start <= now + buffer AND end >= now - buffer)
  $dt_to = date_create("now", timezone_open("America/New_York"));
  $dt_from = clone $dt_to;
  $dt_from->modify("+".$window_buffer." minutes");
  $dt_to->modify("-".$window_buffer." minutes");

  ob_start();
  printf("Dates from %s to %s\n", $dt_from->format('Y-m-d H:i:s'), $dt_to->format('Y-m-d H:i:s'));
  printf("Event type: %s\n", $event_type);
  print "\n";
  Civi::log()->debug(ob_get_clean());

  //Get events of interest
  $events = civicrm_api3('Event', 'get', [
    'sequential' => 1, //The values[] keys aren't useful
    'event_type_id' => $event_type,
    'start_date' => ['<=' => $dt_from->format('Y-m-d H:i:s')],
    'end_date' => ['>=' => $dt_to->format('Y-m-d H:i:s')],
  ]);

  ob_start();
  print "Events in Window:\n";
  print_r($events);
  print "\n";
  Civi::log()->debug(ob_get_clean());

  //Process any events
  foreach($events['values'] as $event) {

    /*ob_start();
    print "Current Event:\n";
    print_r($event);
    printf ("active? %s\n", $event['is_active']);
    printf ("id? %s\n", $event['id']);
    Civi::log()->debug(ob_get_clean());*/

    //If the event is disabled, activate it
    if (!$event['is_active']) {
      $result = civicrm_api3('Event', 'create', [
        'id' => $event['id'],
        'is_active' => 1,
      ]);
    }

    //Process event's participant records registered between (start - buffer) and end
    $switched = registered_to_attended($event['id'],
      date_create($event['start_date'])->modify("-".$window_buffer." minutes")->format('Y-m-d H:i:s'),
      $event['end_date']);

  }

  $returnValues = [];
  return civicrm_api3_create_success($returnValues, $params, 'Unjob', 'Attendevent');
}

function registered_to_attended($event, $from, $to) {
  //Change participant status from registered to attended if record created within the event's time window
  ob_start();
  printf("Event: %s\n", $event);
  printf("Dates from %s to %s\n", $from, $to);
  Civi::log()->debug(ob_get_clean());

  $registereds = civicrm_api3('Participant', 'get', [
    'sequential' => 1,
    'event_id' => $event,
    'status_id' => 'Registered',
    'register_date' => ['>=' => $from],
    // 'register_date' => ['<=' => $to], BETWEEN failed on syntax and adding this killed all register_date filtering
  ]);

  ob_start();
  print "Registered:\n";
  print_r($registereds);
  print "\n";
  Civi::log()->debug(ob_get_clean());
  
  foreach($registereds['values'] as $registered) {
    if (strtotime($registered['participant_register_date']) > strtotime($to)) continue; //Other half of the misfiring BETWEEN in api3
    civicrm_api3('Participant', 'create', [
      'id' => $registered['id'],
      'status_id' => "Attended",
    ]);

    ob_start();
    printf("%s to attended\n", $registered['display_name']);
    printf("Comparison %d > %d\n", strtotime($registered['participant_register_date']), strtotime($to));
    Civi::log()->debug(ob_get_clean());
  }

}



















  /*if (array_key_exists('magicword', $params) && $params['magicword'] == 'sesame') {
    $returnValues = array(
      // OK, return several data rows
      12 => ['id' => 12, 'name' => 'Twelve'],
      34 => ['id' => 34, 'name' => 'Thirty four'],
      56 => ['id' => 56, 'name' => 'Fifty six'],
    );
    // ALTERNATIVE: $returnValues = []; // OK, success
    // ALTERNATIVE: $returnValues = ["Some value"]; // OK, return a single value

    // Spec: civicrm_api3_create_success($values = 1, $params = [], $entity = NULL, $action = NULL)
    return civicrm_api3_create_success($returnValues, $params, 'Unjob', 'Attendevent');
  }
  else {
    throw new API_Exception('Everyone knows that the magicword is "sesame"', 'magicword_incorrect');
  } */

