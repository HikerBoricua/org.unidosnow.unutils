Read from scheduled job parameters last processed subscription history ID and at the end set the parameters with the new last processed

Then CRM_CORE_DAO::executeQuery to fetch rows from civicrm_subscription_history with id > last processed. Keep only those added and removed by webform and only the most recent status per cid and groupid

With all the unique status/cid/groupid use API Relationship to keep only cids w. Head of household relationships

For each Relationship see if the Household has another Head

For each other Head use API GroupContact to make sure they are (re-)added or removed from groups to match their co-heads. Careful to only override/apply things done by Webform, if Admin/User does anything to one they need to do to the other head as well if that was the intent


