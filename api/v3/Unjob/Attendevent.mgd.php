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
      'name' => 'Manage attendance to events w/o pre-registration',
      'description' => 'Enable/activate events of the indicated type one cron cycle prior to their scheduled start time so they\'ll be listed in forms for participants to register their attendance, thus permitting registration via those forms only during the duration of the event. Registrations that take place during the active window will switch from "Registered" (webform default) to "Attended" during or shortly after the end of the meeting. This is not particularly compatible with the normal pre-registration workflow for Civi Participants records, hence the focus on a special activity type.',
      'run_frequency' => 'Always',
      'api_entity' => 'Unjob',
      'api_action' => 'Attendevent',
      'parameters' => 'cron_minutes=15\nevent_type_id=7',
    ],
  ],
];
