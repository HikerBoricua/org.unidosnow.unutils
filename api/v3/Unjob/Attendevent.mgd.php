<?php
// This file declares a managed database record of type "Job".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
return [
  [
    'name' => 'Cron:Unjob.Attendevent',
    'entity' => 'Job',
    'params' => [
      'version' => 3,
      'name' => 'Event Attendance',
      'description' => 'Switches events of the "waiting" type to the "recording" type one cron cycle prior to their scheduled start time so they\'ll be listed in webforms for participants to register their attendance.',
      'run_frequency' => 'Always',
      'api_entity' => 'Unjob',
      'api_action' => 'Attendevent',
      'parameters' => "cron_minutes=15\nwaiting_type=7\nrecording_type=8",
    ],
  ],
];
