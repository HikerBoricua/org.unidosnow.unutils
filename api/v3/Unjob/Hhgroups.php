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
  $last_subscription += 10;

  $job_in = civicrm_api3('Job', 'get', [
    'name' => "Heads of Household Group Sync",
  ]);
  $job_out = civicrm_api3('Job', 'create', [
    'id' => $job_in["id"],
    'parameters' => "last_subscription=$last_subscription",
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
