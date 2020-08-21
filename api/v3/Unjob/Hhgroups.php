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
  $spec['last_relation']['api.required'] = 1;
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
  $last_relation = $params['last_relation'];

  //Validate parameters
  if (!($last_subscription > 0) || !($last_relation > 0)) {
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
  $rel_hhs = relationship_hhs($last_relation);  //Argument by reference, modified

  //Merge keys (household IDs) from both lists
  $hh_list = $subs_hhs + $rel_hhs;

  //Process each household
  foreach ($hh_list as $hh_id => $unused) {
    
    //Get all Heads of Household
    $hhh_rels = civicrm_api3('Relationship', 'get', [
      'contact_id_b' => $hh_id,
      'relationship_type_id' => 7,
      'is_active' => 1,
    ]);

    ob_start();
    print("HHH Relations for Household $hh_id:");
    print_r($hhh_rels);
    Civi::log()->debug(ob_get_clean());

    //Process Heads and Groups in each Household
    if ($hhh_rels['count'] < 2) continue; //Sync only applies if multiple heads
    $heads_groups = []; //Groups for each Head of the household
    $hh_groups = []; //Unique Parents groups for the household
    foreach ($hhh_rels['values'] as $hhh_rel) {
      $head = $hhh_rel['contact_id_a'];
      
      //Get groups for each head. Assumes a group will only show up once
      $heads_groups[$head] = civicrm_api3('GroupContact', 'get', [
        'sequential' => 1, //The values[] keys aren't useful
        'status' => "", //Needed to get both "Added" and "Removed"status
        'contact_id' => $head,
      ])['values'];

      ob_start();
      print("Groups for Household $hh_id Head $head before:");
      print_r($heads_groups[$head]);
      Civi::log()->debug(ob_get_clean());

      //Prepare groups for syncing
      if (count($heads_groups[$head]) == 0) continue; //No groups for this Head
      $group_keys = [];
      foreach ($heads_groups[$head] as &$group) { //Modify elements by reference
        $group_keys[] = $group['group_id'];
        
        //Consolidate Adds and Removes
        if (isset($group['in_method'])) {
          $group['status'] = "Added";
          $group['method'] = $group['in_method'];
          $group['timestamp'] = strtotime($group['in_date']);
        } else {
          $group['status'] = "Removed";
          $group['method'] = $group['out_method'];
          $group['timestamp'] = strtotime($group['out_date']);
        }

        //For the household, track only the most recent activity on Parents groups
        if (!strpos(strtoupper($group['title']), 'PARENTS')) continue;
        if (!isset($hh_groups[$group['group_id']])) $hh_groups[$group['group_id']] = $group;
        else if ($group['timestamp'] > $hh_groups[$group['group_id']]['timestamp']) $hh_groups[$group['group_id']] = $group;
      }

      //Set the keys to the group id
      $heads_groups[$head] = array_combine($group_keys, $heads_groups[$head]);

      ob_start();
      print("Groups for Household $hh_id Head $head after:");
      print_r($heads_groups[$head]);
      Civi::log()->debug(ob_get_clean());

    }

    ob_start();
    print("Actionable groups for Household $hh_id:");
    print_r($hh_groups);
    Civi::log()->debug(ob_get_clean());

    //hh_groups is now the actionable Parents groups across all heads of the household
    foreach ($hh_groups as $group_id => $hh_group) {
      foreach ($heads_groups as $head => $head_groups) {
          
        //The head does not have the HH group
        if (!isset($head_groups[$group_id])) {
          //If the HH group has been removed, do nothing
          if ($hh_group['status'] == 'Removed') continue;
          //Otherwise add the head to the group
          else Civi::log()->debug("Adding $head to $hh_group[title]");

        //The head has the HH group
        } else {
          $head_group = $head_groups[$group_id];

          //The status is the same, do nothing
          if ($head_group['status'] == $hh_group['status']) continue;
          //If the head's group was removed programatically, re-add it
          else if ($hh_group['status'] == 'Added' && 
              $head_group['status'] == 'Removed' &&
              array_search($head_group['method'], ['Webform', 'API'])
          ) Civi::log()->debug("Re-adding $head to $hh_group[title]");
          //If the househld group was removed by webform and the head's had been added programatically, remove it
          else if ($hh_group['status'] == 'Removed' && 
              $hh_group['method'] == 'Webform' && 
              $head_group['status'] == 'Added' &&
              array_search($head_group['method'], ['Webform', 'API'])
          ) Civi::log()->debug("Removing $head from $hh_group[title]");
        }
      }
    }
  }


  //Update job parameters so next run picks up where this one left off

  $job_out = civicrm_api3('Job', 'create', [
    'id' => $job_in["id"],
    'parameters' => "last_subscription=$last_subscription\nlast_relation=$last_relation",
  ]);

  $job_return = ['subs_hhs' => count($subs_hhs), 'rel_hhs' => count($rel_hhs)];
  return civicrm_api3_create_success($job_return, $params, 'Unjob', 'Hhgroups');
  
}

//Return an array of unique household IDs where a Head has had group activity
//(subscription change) since last run
//The &parameter comes in as the last ID processed in the previous run, leaves as last ID on this run
function subscription_hhs(&$last_subscription) {

  //SQL to extract the most recent action on any contact+group
  $subsSQL = <<<SQL
select *
from civicrm_subscription_history
where id in
 (select max(id)
  from civicrm_subscription_history
  where id > $last_subscription
  group by contact_id, group_id
  order by max(id))
limit 10
SQL;

  $hh_list = [];
  $subsDAO = CRM_Core_DAO::executeQuery($subsSQL, []);
  while ($subsDAO->fetch()) {
    $last_subscription = $subsDAO->id; //Records must be sorted ascending by ID for this to work

    //Get all Head of hh relationships for this contact
    $hhh_rels = civicrm_api3('Relationship', 'get', [
      'contact_id_a' => $subsDAO->contact_id,
      'relationship_type_id' => 7,
      'is_active' => 1,
    ]);

    //Build the list of household IDs, values[] is empty if this contact not a head of hh
    //Use keys in $hh_list so any hh shows up only once
    foreach ($hhh_rels['values'] as $value) {
      $hh_list[$value['contact_id_b']] = 0;
      //Civi::log()->debug("subs head id: $subsDAO->contact_id and hh id: $value[contact_id_b]");
    }

  }

  return $hh_list;

}

//Return an array of unique household IDs where a Head relationship has been added since last run
//This will miss a relationship that's edited to Head of Household
function relationship_hhs(&$last_relation) {

  $hhh_rels = civicrm_api3('Relationship', 'get', [
    'id' => ['>' => $last_relation],
    'relationship_type_id' => 7,
    'is_active' => 1,
    'options' => ['limit' => 10, 'sort' => 'id ASC'],
  ]);
  
  //Build the list of household IDs, values[] is empty if a contact not a head of hh
  //Use keys in $hh_list so any hh shows up only once
  $hh_list = [];
  foreach ($hhh_rels['values'] as $key => $value) {
    $hh_list[$value['contact_id_b']] = 0;
    $last_relation = $key;
    //Civi::log()->debug("rel head id: $value[contact_id_a] and hh id: $value[contact_id_b]");
  }

  return $hh_list;  

}