Enables a workflow where attendees can record their attendance to an event by filling out a webform. By controlling when the event becomes enabled/active, this scheduled job makes sure attendance is recorded via webform only around the time the event is scheduled. Relies on the fact that a webform's participant registration functionality will only offer an event if it is active and hasn't ended.

In addition, it changes the participant role from Registered to Attended. This is necessary because the webform will only start a participant record as Registered (or Pending if payments involved, but that's out of scope). In case some registrations were managed by staff, it only looks at participant records which Registered around the time of the meeting.

Only meetings of the type indicated by the event_type parameter are monitored. The expectation is that the job will run several times during the lifespan of the event, from around (event start time minus a cron cycle) at each cron beat until (event end time plus a cron cycle). Hence the minutes in a cron cycle are the other parameter. And the scheduled job must be set to run on every cron beat ("Always").

Though not necessary, indexes were added to civicrm\_event for start\_date and end\_date.

Functionality may be included to add up attendance, since it is seeing all the data already. This requires manipulating custom fields.