# WDOS v101.0: response to the Production Handoff of 11 November 2026

Prepared by Azeez Olayinka (IT Lead) with Claude. This follows the response format set in `CLAUDE.md`.
It is a statement of where the build stands. It is not a claim that WDOS is production-ready.

## 1. Current state, from the inspected code

- Stack: plain PHP 8.3 with PDO, TiDB (MySQL protocol), vanilla JavaScript PWA with a service worker. One Docker service on Render. A modular monolith, as section 12 allows. No rewrite was done.
- Repository: `craigfunds-maker/WDOS`, folder `wdos-php/`. 17 numbered migrations that run by themselves.
- Test deployment: https://wdos.onrender.com. This is a test deployment with real registrations in it, not an approved production system.
- Automated checks: seven API suites and two placement tests, run on a local test copy with invented members.

## 2. Requirement mapping

`REQUIREMENTS_TRACEABILITY.csv` (Q01 to Q48, A01 to A12) and `ACCEPTANCE_TESTS.csv` (T01 to T36) are filled in, row by row, with status, evidence and what is still open. The same register is inside WDOS for HQ under Administration, Handoff register.

| | Implemented | Partial | Missing | Not verified | For management |
|---|---|---|---|---|---|
| Q01 to Q48 | 11 | 33 | 1 | 1 | 2 |
| A01 to A12 | 7 | 5 | 0 | 0 | 0 |

| | Passed | Passed in part | Not run | Needs people |
|---|---|---|---|---|
| T01 to T36 | 17 | 13 | 4 | 2 |

"Passed" means an automated check passes on the test copy. No test has been run by an independent reviewer.

## 3. What v101.0 added, and the choices made

1. Decision register D01 to D14 inside WDOS, with approver, date and version. All 14 are Pending. No policy value was chosen in code on management's behalf.
2. Confidential lane for complaints, safeguarding, security, privacy and eligibility disputes. Only HQ handlers see a case. The person named never sees it, including an HQ handler. Notices carry the reference only. Nothing is sanctioned automatically.
3. Membership lifecycle for the member herself: pause, return, close, history, and download of everything WDOS holds about her. A removal request becomes a privacy case; nothing is deleted automatically.
4. Registration drafts moved off the phone. The phone keeps a random token; the details wait on the server for two days. The PIN is never kept. A repeated send returns the same account.
5. Sign-out now clears the phone.
6. Language is a dropdown and the first choice on the screen: Arabic, English, French, Portuguese, Swahili.
7. Where the member heard of WODDI, invitation links and campaign links, with an HQ report.
8. WNNN member reaching 30: told once, listed for HQ, moved only by HQ. Person ID and history stay. No WGMN role is given.
9. Appointment states: candidate, appointed, acting, on leave, suspended, resigned, replaced. Access follows the state at once.
10. Meeting replies, and an annual calendar with automatic reminders.
11. The 80 percent instrument exactly as section 15 defines it, with CSV export.
12. Live integrity checks for chapter counts, double placements, cross-network placements and capacity.

## 4. Files changed

- `migrations/017_production_handoff.sql` (adds only; nothing is altered or dropped)
- `lib/handoff_fns.php`, `lib/handoff.php`, `lib/handoff_register.json`
- `lib/core.php`, `lib/notify.php`, `lib/webauthn.php`, `lib/amend.php`, `lib/amend_fns.php`, `api/index.php`
- `js/pages/help.js`, `cases.js`, `calendar.js`, `coverage.js`, `handoff.js` (new); `welcome.js`, `rules.js`, `meetings.js`, `members.js`, `reports.js`, `js/app.js`, `js/ui.js`, `sw.js`
- `assets/i18n/fr.json`, `pt.json`, `ar.json`, `sw.json` (73 new member-facing sentences each), `assets/css/app.css`

## 5. Testing actually run

| Suite | Checks | Failures |
|---|---|---|
| t93 and t96 | 110 | 0 |
| t97 | 67 | 0 |
| t98 | 54 | 0 |
| t99 | 50 | 0 |
| t100 | 34 | 0 |
| t101 (this release) | 72 | 0 |

- T11: 80 arrivals through 8 processes at once at a chapter of 50. Result: 50 seated, 31 pending, nobody in two chapters.
- T12: 1,000 Maitama WGMN members through 8 processes at once. Result: 20 chapters, each exactly 50.
- Browser checks at phone and desktop width: language order, draft not on the phone, Arabic right to left, sign-out, all new pages. No console errors.

Limits, said plainly: T11 and T12 ran on MariaDB, not on TiDB, which locks differently. Three older checks expected 9 decisions and were updated to 14.

## 6. Open decisions and security risks

- D01 to D14 are all Pending.
- **D04 runs differently from the guardrail on the test deployment.** Automatic chapter creation is ON there (set by migration 013 before this handoff arrived). The guardrail is off. It was not switched off silently, because that would leave every new member in a new area pending. It is flagged in red in the decision register and is one setting under System rules.
- No threat model document, no penetration test, no WCAG audit, no restore test.
- A member with no email, no fingerprint and a lost phone can recover only through HQ (D05).
- HQ accounts are not forced to use a stronger sign-in.
- One-chapter-per-membership is enforced in code and checked, not by a database constraint.

## 7. Deployment, migration and rollback

- Migration 017 only adds tables and columns. To roll back, deploy the previous commit; the new tables and columns can stay unused or be dropped.
- No existing row is changed by the migration.
- There is one environment. Section 12 asks for separate dev, staging and production. That is not in place.

## 8. What remains

Not built: SMS, WhatsApp and voice; Institute sync; backups, restore drills and monitoring; quiet hours; QR codes; engagement scores; Founder strategic view and PDF packs; consent versions and retention schedule; role switch for people with two appointments; dead-letter and replay tools; recorded audio and video.

Needs people, not code: decisions D01 to D14; native review of four languages; a rural usability session (T03); weights and independent verification for the 80 percent figure; privacy and legal review; a pilot.
