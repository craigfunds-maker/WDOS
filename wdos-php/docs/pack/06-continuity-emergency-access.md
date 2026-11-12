# 06. Administrator continuity and emergency access

Purpose: WDOS must keep running if one person is away, leaves, or loses access. This is a proposed playbook. Names and decisions are blank until management fills them.

## Who holds what today

| Key to the system | Held by | Second holder |
|---|---|---|
| WDOS super administrator login | Azeez Olayinka, IT Lead | `TO DECIDE` ____________ |
| Render account (hosting, environment secrets) | ____________ | ____________ |
| TiDB Cloud account (database) | ____________ | ____________ |
| Brevo account (email) | ____________ | ____________ |
| Code repository | ____________ | ____________ |
| Domain name, when one is bought | ____________ | ____________ |

**Risk today:** if every row has one holder, one lost phone or one departure can lock WODDI out of its own system. Recommended rule: two named people for every row, each with their own login, never a shared password.

## Rules proposed for approval

1. At least two super administrators at all times, each a named person.
2. Every hosting, database and email account is owned by a WODDI address, with two-step sign-in on, and recovery codes sealed and kept by ____________.
3. A leaver's logins are switched off the same day: Staff and logins, Switch off; then remove the person from Render, TiDB, Brevo and the repository.
4. Secrets are changed when a holder leaves: database password, `BREVO_API_KEY`, `INTEGRATION_KEY`, push keys, setup key.
5. HQ access is reviewed every three months against the staff list.

## Emergency access ("break glass")

When to use: both super administrators are unreachable and something must be done now (the site is down, or an account must be shut).

1. The person authorising is ____________ (proposed: the Founder/CEO), in writing.
2. The sealed recovery codes are opened in front of a second person.
3. Every action taken is written down with the time. WDOS records HQ actions in its audit log by itself; actions in Render and TiDB must be noted by hand.
4. Within 48 hours: all secrets used are changed, new recovery codes are sealed, and a short note of what happened is filed.

WDOS has no hidden back door. If every super administrator login is lost, access is restored through the database by someone holding the TiDB account. That is why the second holder matters.

## Release process

One release at a time; tests must pass; check `/api/config` and sign in after every deploy; read the Handoff register; keep the previous deploy ready to restore. Details in document 04.

`TO DECIDE`: who may approve a release to a future production site, and whether releases are barred during campaigns or events.

## What is not in place yet

- A second super administrator (unless one has been added since this was written).
- A production environment separate from the test copy.
- A restore rehearsed on TiDB.
- A WODDI domain and a WODDI sender address for email (email currently goes out from a Gmail address).
