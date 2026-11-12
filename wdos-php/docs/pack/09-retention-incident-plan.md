# 09. Data retention and incident response plan

Proposed for approval. Retention periods are management and legal decisions; the periods below are suggestions to react to, not rules WDOS enforces today.

## What WDOS holds

Names, date of birth, phone, email where given, place, network, language, optional photo, chapter and seat history, journey answers, tasks, reports, meetings and attendance, messages, private reports, device sign-in keys, notification addresses, and an audit log. Passwords are held only as one-way hashes. WDOS holds no payment data and no government ID numbers.

## How data is kept today (as built)

- Nothing about a person is deleted automatically. History is closed with a date.
- A member can pause or close membership in Settings. Closing changes the status; it does not erase the record.
- Sign-in attempt records are removed after one day. Sessions end on sign-out or reset.
- Backups are made only when a super administrator takes one; WDOS keeps no copy.

## Retention: proposals `TO DECIDE`

| Data | Proposed | Why |
|---|---|---|
| Active member record | While a member | Needed to serve the member |
| Closed membership | Keep ____ years, then remove name, phone, email, date of birth and photo, keeping counts only | Members may return; reports need totals |
| Did not finish the seven days | Remove personal details after ____ months | No lasting relationship |
| Leadership seats and agreements | ____ years after the seat ends | Accountability |
| Private reports and safeguarding cases | ____ years after closing, restricted | Legal and duty of care; take advice |
| Messages with HQ | ____ years | Service history |
| Audit log | ____ years, never edited | Governance |
| Backup files | Keep the last ____; destroy older ones | Each is a full copy of personal data |

Also to decide: how a member asks for a copy of her data or for erasure, who answers and within how many days, and which country's law is followed where they differ. Erasure on request is **not built**; today it would be done by IT by hand, with the action noted.

## Incident response

An incident is: personal data seen, changed or lost by someone who should not; a lost backup file or device with access; a leaked key; an account taken over; or the site down for more than ____.

| Step | Who | What |
|---|---|---|
| 1. Report | Anyone | Tell IT and ____________ at once. Do not investigate alone or delete anything. |
| 2. Contain (first hour) | IT | Switch off the affected login (Staff and logins). Reset the member's password, which signs her out everywhere. Change any exposed key in Render. If the code is at fault, restore the last good deploy. |
| 3. Record | IT | Time found, what was seen, which records, how many people. The WDOS audit log shows HQ actions. Render logs show requests. |
| 4. Assess (first day) | IT with management | What data, how many people, which countries, likely harm. |
| 5. Tell people | Management | `TO DECIDE` with legal advice: when members and regulators must be told, and within what time. Many laws set short limits, for example 72 hours. |
| 6. Repair | IT | Fix the cause. Restore from backup if data was lost. Check integrity counts. |
| 7. Review (within two weeks) | All | What happened, what changes. Add it to the defect register. No blame of individuals. |

Incident lead: ____________. Deputy: ____________. Legal contact: ____________.

## Recorded incidents

| Date | What | Effect | Cause | Change made |
|---|---|---|---|---|
| 12 Nov 2026 | Test copy would not open for about 13 minutes | Nobody could open WDOS. No data lost or exposed. | A database migration that passed locally failed on TiDB | Migration rewritten; a failed migration no longer stops the site and is shown in the Handoff register; check after every deploy |

## Not in place

Automatic scheduled backups from WDOS, a restore rehearsed on TiDB, erasure on request, automatic retention, and a privacy notice reviewed by a lawyer.
