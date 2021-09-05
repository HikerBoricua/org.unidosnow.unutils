<?php
use CRM_Unutils_ExtensionUtil as E;

/**
 * Unjob.Mentor2coach API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_unjob_Mentor2coach_spec(&$spec) {
  //$spec['magicword']['api.required'] = 1;
  //$spec['last_report']['api.required'] = 1;

}

/**
 * Unjob.Mentor2coach API
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
function civicrm_api3_unjob_Mentor2coach($params) {
  //$last_report = $params['last_report'];
  $job_return = [];

  // //Validate parameters
  // if (!($last_report > 0)) {
    // throw new API_Exception(/*error_message*/ "Parameter must be ID of last mentor report activity processed", /*error_code*/ 'parameter_error');
    // //Execution stops
  // }

  // //Get invoking job, though conceivably the right paramenter could've been provided by some other invocation
  // //The job parameter is used to inform next run of where to start
  // $job_name = "Copy Coaches on Mentor Contact Reports";
  // $job_in = civicrm_api3('Job', 'get', [
    // 'name' => $job_name,
  // ]);
  
  // if (!(($job_in["id"] ?? 0) > 0)) {
    // throw new API_Exception(/*error_message*/ "Can't find job '$job_name'", /*error_code*/ 'job_name_missing');
    // //Execution stops
  // }

  //Get recent Mentor Contact (i.e. interaction) Reports
  $reports = civicrm_api3('Activity', 'get', [
    'activity_type_id' => "Mentor Contact Report",
    'activity_date_time' => ['>' => date('Y-m-d', strtotime('-7 days'))], //Only go back 7 days
    //'id' => ['>' => 33446],
  ]);

  //Prepare results for job log
  $job_return = ['reports' => $reports['count'],];

  //Process each report
  foreach ($reports['values'] as $report) {
    
    $activity_id = $report['id'];
    //Get Contacts in the Contact Report
    $activity_contacts = [];
    $contacts = civicrm_api3('ActivityContact', 'get', [
      'activity_id' => $activity_id,
    ]);

    $job_return[$activity_id] = ['mentor' => $report['source_contact_id'], 'with' => '', 'mentees' => '', 'coaches' => '', 'emailed' => ''];


    //Build an array of contact id's "With"
    foreach ($contacts['values'] as $contact) {
      
      if ($contact['record_type_id'] == 3) {
        $activity_contacts[] = $contact['contact_id'];
        $job_return[$activity_id]['with'] .= ($contact['contact_id'] . ' ');
      }
    }

    //Get Mentees for the Mentor
    $mentees = civicrm_api3('Relationship', 'get', [
      'relationship_type_id' => "Mentored by",
      'is_active' => 1,
      'contact_id_b' => $report['source_contact_id'],
      'return' => ['contact_id_a'],
    ]);

    //Get Coaches for the Mentees
    foreach ($mentees['values'] as $mentee) {

      $job_return[$activity_id]['mentees'] .= ($mentee['contact_id_a'] . ' ');

      $coaches = civicrm_api3('Relationship', 'get', [
        'contact_id_a' => $mentee['contact_id_a'],
        'relationship_type_id' => "Coached by",
        'is_active' => 1,
      ]);

      foreach ($coaches['values'] as $coach){
        $coach_id = $coach['contact_id_b'];
        $job_return[$activity_id]['coaches'] .= ($coach_id . ' ');

        //If a Coach is not in the Report's Contacts, send an email and add
        if (!in_array($coach_id, $activity_contacts)) {
          $coach_contact = civicrm_api3('Contact', 'getsingle', [
            'id' => $coach_id,
          ]);

          CRM_Case_BAO_Case::sendActivityCopy(NULL, $activity_id, [$coach_contact['email'] => $coach_contact], NULL, NULL);

          civicrm_api3('ActivityContact', 'create', [
            'activity_id' => $activity_id,
            'contact_id' => $coach_id,
            'record_type_id' => 3,
          ]);

          $job_return[$activity_id]['emailed'] .= ($coach_id . ' ');
        }
      }
    }
    
  }

  return civicrm_api3_create_success($job_return, $params, 'Mentor2coach', 'Hhgroups');

}
