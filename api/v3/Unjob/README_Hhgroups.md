Scan for membership (subscriptions) changes in Parents groups involving a Head of Houshold. Propagate additions to all other Heads of the Household (unless previously removed by Admin). Propagate removals only if the most recent was by Webform and the other Heads additions were by API or Webform.

Uses API v3 but the key tables are civicrm_subscription_history and civicrm_group_contact.

Reads from scheduled job parameters:
- last processed subscription history ID
- last processed head of household relationship ID
At the end rewrites the job's parameters with the new last processed so the next run picks up from there

API result is an array of Households scanned. For each household ID there's an array (empty if no Heads of Household) with:
heads => number of heads of household
groups => number of groups with "parents" in the title that at least one head of household is/was in
added => number of new head -> group memberships
re-added => number of head -> group memberships that were re-established
removed => number of removed head -> group memberships
