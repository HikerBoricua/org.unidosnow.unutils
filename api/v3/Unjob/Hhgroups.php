<?php
use CRM_Unutils_ExtensionUtil as E;

/**
 * Unjob.Hhgroups API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_unjob_Hhgroups_spec(&$spec) {
  // $spec['magicword']['api.required'] = 1;
  $spec['last_subscription']['api.required'] = 1;
  $spec['last_household']['api.required'] = 1;
}

/**
 * Unjob.Hhgroups API
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
function civicrm_api3_unjob_Hhgroups($params) {
  $last_subscription = $params['last_subscription'];
  $last_household = $params['last_household'];

  //Validate parameters
  if (!($last_subscription > 0) || !($last_household > 0)) {
    throw new API_Exception(/*error_message*/ "Parameters must be subscription and household ID's", /*error_code*/ 'parameter_error');
    //Execution stops
  }

  //Get invoking job, though conceivably the right paramenters could've been provided by some other invocation
  //The job parameters are used to inform next run of subscriptions and households already processed
  $job_name = "Heads of HH Parents Group Sync";
  $job_in = civicrm_api3('Job', 'get', [
    'name' => $job_name,
  ]);
  if (!(($job_in["id"] ?? 0) > 0)) {
    throw new API_Exception(/*error_message*/ "Can't find job '$job_name'", /*error_code*/ 'job_name_missing');
    //Execution stops
  }

  //Find households with subscription (group) activity or new Head of hh since last run
  $subs_hhs = subscription_hhs($last_subscription); //Argument by reference, modified
  $rel_hhs = relationship_hhs($last_household);  //Argument by reference, modified

  //Merge keys (household IDs) from both lists
  $hh_list = $subs_hhs + $rel_hhs;

  //Get all Heads of Households and the groups they're in

  //Consolidate all Households to the most recent status per group

  //Ignore hh+group where method wasn't Webform or group isn't PARENTS

  //If hh added to group & Head is absent or removed by Web/API, add Head

  //If hh removed from group & Head is added by Web/API, remove Head

  //Update job parameters so next run picks up where this one left off

  $job_out = civicrm_api3('Job', 'create', [
    'id' => $job_in["id"],
    'parameters' => "last_subscription=$last_subscription\nlast_household=$last_household",
  ]);

  $job_return = ['subs_hhs' => count($subs_hhs), 'rel_hhs' => count($rel_hhs)];
  return civicrm_api3_create_success($job_return, $params, 'Unjob', 'Hhgroups');
  
  // if (array_key_exists('magicword', $params) && $params['magicword'] == 'sesame') {
  //   $returnValues = array(
  //     // OK, return several data rows
  //     12 => ['id' => 12, 'name' => 'Twelve'],
  //     34 => ['id' => 34, 'name' => 'Thirty four'],
  //     56 => ['id' => 56, 'name' => 'Fifty six'],
  //   );
  //   // ALTERNATIVE: $returnValues = []; // OK, success
  //   // ALTERNATIVE: $returnValues = ["Some value"]; // OK, return a single value

  //   // Spec: civicrm_api3_create_success($values = 1, $params = [], $entity = NULL, $action = NULL)
  //   return civicrm_api3_create_success($returnValues, $params, 'Job', 'HhGroups');
  // }
  // else {
  //   throw new API_Exception(/*error_message*/ 'Everyone knows that the magicword is "sesame"', /*error_code*/ 'magicword_incorrect');
  // }

}

//Return an array of unique household IDs where a Head has had group activity
//(subscription change) since last run
//The &parameter comes in as the last ID processed in the previous run, leaves as last ID on this run
function subscription_hhs(&$last_subscription) {

  //SQL to extract the most recent Webform action on any contact+group
  $subsSQL = <<<SQL
select *
from civicrm_subscription_history
where id in
 (select max(id)
  from civicrm_subscription_history
  where method = "Webform" and id > $last_subscription
  group by contact_id, group_id
  order by max(id))
limit 10
SQL;

  $hh_list = [];
  $subsDAO = CRM_Core_DAO::executeQuery($subsSQL, []);
  while ($subsDAO->fetch()) {
    $last_subscription = $subsDAO->id; //Records must be sorted ascending by ID for this to work

    //Get all Head of hh relationships for this contact
    $hhh_rel = civicrm_api3('Relationship', 'get', [
      'contact_id_a' => $subsDAO->contact_id,
      'relationship_type_id' => 7,
      'is_active' => 1,
    ]);

    //Build the list of household IDs, values[] is empty if this contact not a head of hh
    //Use keys in $hh_list so any hh shows up only once
    foreach ($hhh_rel['values'] as $value) {
      $hh_list[$value['contact_id_b']] = 0;
      Civi::log()->debug("subs head id: $subsDAO->contact_id and hh id: $value[contact_id_b]");
    }

  }

  return $hh_list;

}

//Return an array of unique household IDs where a Head relationship has been added since last run
//This will miss a relationship that's edited to Head of Household
function relationship_hhs(&$last_household) {

  $hhh_rel = civicrm_api3('Relationship', 'get', [
    'id' => ['>' => $last_household],
    'relationship_type_id' => 7,
    'is_active' => 1,
    'options' => ['limit' => 10, 'sort' => 'id ASC'],
  ]);
  
  //Build the list of household IDs, values[] is empty if a contact not a head of hh
  //Use keys in $hh_list so any hh shows up only once
  $hh_list = [];
  foreach ($hhh_rel['values'] as $key => $value) {
    $hh_list[$value['contact_id_b']] = 0;
    $last_household = $key;
    Civi::log()->debug("rel head id: $value[contact_id_a] and hh id: $value[contact_id_b]");
  }

  return $hh_list;  

}