Enables a workflow where attendees can record their attendance to an event by filling out a webform. By switching the event from a "waiting" type to a "recording" type, this scheduled job makes sure attendance is recorded via webform only around the time the event is scheduled. Relies on the fact that a webform's participant registration functionality will only offer events of a designated type that haven't ended.

In addition, it changes the participant role from Registered to Attended. This is necessary because the webform will only start a participant record as Registered (or Pending if payments involved, but that's out of scope). In case some registrations were managed by staff, it only looks at participant records which Registered around the time of the meeting.

The expectation is that, once recording, the job will run several times during the lifespan of the event. From around (event start time minus a cron cycle), at each cron beat, until (event end time plus a cron cycle). Hence the minutes in a cron cycle are another parameter. And the scheduled job must be set to run on every cron beat ("Always"). These runs allow the job to count webform submissions and total attendance. The values are populated into Event custom fields.

Though not necessary, indexes were added to db table civicrm\_event for start\_date and end\_date.
