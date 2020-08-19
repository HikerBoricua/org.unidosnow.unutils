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
    //Manual is clear that execution stops on thrown error inside a try{}, assuming that's also true without try{}
  }

  //Get invoking job, though conceivably the right paramenters could've been provided by some other invocation
  //The job parameters are used to keep track of subscriptions and households already processed
  $job_name = "Heads of HH Parents Group Sync";
  $job_in = civicrm_api3('Job', 'get', [
    'name' => $job_name,
  ]);
  if (!(($job_in["id"] ?? 0) > 0)) {
    throw new API_Exception(/*error_message*/ "Can't find job '$job_name'", /*error_code*/ 'job_name_missing');
    //Manual is clear that execution stops on thrown error inside a try{}, assuming that's also true without try{}
  }

  //Get hh's where a Head has had group activity (subscription changes) since last run

  //SQL to extract the most recent action on any contact+group
  //$last_subscription tracks records already processed so they aren't re-processed
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

  $subsDAO = CRM_Core_DAO::executeQuery($subsSQL, []);
  while ($subsDAO->fetch()) {
    $last_subscription = $subsDAO->id; //Records MUST be sorted ascending by ID for this to work

    ob_start();
    print_r($subsDAO);
    Civi::log()->debug(ob_get_clean());

  }

  //Get hh's where a Head relationship has been added since last run
  //This will miss a relationship that's edited to Head of Household

  //Combine the two hh lists

  //Get all Heads of Households and the groups they're in

  //Consolidate all Households to the most recent status per group

  //Ignore hh+group where method wasn't Webform or group isn't PARENTS

  //If hh added to group & Head is absent or removed by Web/API, add Head

  //If hh removed from group & Head is added by Web/API, remove Head

  //Update job parameters so next run picks up where this one left off
  $last_household += 10;
  $job_out = civicrm_api3('Job', 'create', [
    'id' => $job_in["id"],
    'parameters' => "last_subscription=$last_subscription\nlast_household=$last_household",
  ]);

  if ($job_out["id"] == $job_in["id"]) return civicrm_api3_create_success([], $params, 'Unjob', 'Hhgroups');
  else throw new API_Exception(/*error_message*/ 'Job ID mismatch', /*error_code*/ 'job_mismatch');

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
