<?php
// This file declares a managed database record of type "Job".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
return [
  [
    'name' => 'Cron:Unjob.Hhgroups',
    'entity' => 'Job',
    'params' => [
      'version' => 3,
      'name' => 'Heads of Household Group Sync',
      'description' => 'Scan for membership (subscriptions) changes in Parents groups involving a Head of Houshold.\nPropagate additions to all other Heads of the Household (unless previously removed by Admin).\nPropagate removals only if the most recent was by Webform and the other Heads additions were by API or Webform.\nCalls Unjob.hhgroups API',
      'run_frequency' => 'Hourly',
      'api_entity' => 'Unjob',
      'api_action' => 'Hhgroups',
      'parameters' => 'last_subscription=1\nlast_relation=1',
    ],
  ],
];
