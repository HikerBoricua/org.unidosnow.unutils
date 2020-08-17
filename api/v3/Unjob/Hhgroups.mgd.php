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
      'description' => 'When webforms alter group memberships (subscriptions) for one Head of Houshold, propagate to any others. Calls Unjob.hhgroups API',
      'run_frequency' => 'Daily',
      'api_entity' => 'Unjob',
      'api_action' => 'Hhgroups',
      'parameters' => '',
    ],
  ],
];
