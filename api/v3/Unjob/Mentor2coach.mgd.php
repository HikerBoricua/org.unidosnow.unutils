<?php
// This file declares a managed database record of type "Job".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
return [
  [
    'name' => 'Cron:Unjob.Mentor2coach',
    'entity' => 'Job',
    'params' => [
      'version' => 3,
      'name' => 'Copy Coaches on Mentor Contact Reports',
      'description' => 'Call Unjob.Mentor2coach API',
      'run_frequency' => 'Hourly',
      'api_entity' => 'Unjob',
      'api_action' => 'Mentor2coach',
      'parameters' => '',
    ],
  ],
];
