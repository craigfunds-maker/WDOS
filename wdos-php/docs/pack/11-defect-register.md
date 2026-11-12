# 11. Defect and open-items register (as of build v107.0, 12 November 2026)

Every row is something known from the code, the tests or the live test copy. Nothing here is hidden as "minor". Severity: 1 blocks broad rollout, 2 blocks a pilot with real members unless accepted in writing, 3 should be fixed before rollout, 4 polish. Owner and date are blank until assigned.

## Open

| ID | Sev | Item | Evidence | Next action | Owner | Due |
|---|---|---|---|---|---|---|
| R-01 | 1 | No-OTP sign-in and recovery have had no independent security review. Phone-only members rely on a password, a fingerprint, a Chapter Lead reset or an HQ reset. | Workflow W27 declared "part" | Independent review of the design; decide recovery for a member with no email and no fingerprint | | |
| R-02 | 1 | No real member has used WDOS. Every test used invented members on a test copy. | Handoff register: "needs people" rows | Small supervised pilot with entry and exit criteria | | |
| R-03 | 1 | Restore has never been rehearsed on TiDB, only on a local MariaDB copy. | `restore_test.sh` runs locally | Restore a backup into an empty TiDB database and compare counts | | |
| R-04 | 1 | One environment. The "test copy" is also where real emails now go out. No separate production site, no staging on TiDB. | Render: one service | Create production and staging; move real use to production only | | |
| R-05 | 2 | Local tests run on MariaDB and do not catch TiDB-only errors. This caused the 13-minute outage of 12 November. | Incident in document 09 | Run the suites against a TiDB staging database before each release | | |
| R-06 | 2 | No translation has been reviewed by a native speaker: French, Portuguese, Arabic, Swahili screens, and the "Good Mother" phrases on the 17 newly drawn African maps. | Translations written by the developer's AI tool | Native review per language; correct in the dictionaries | | |
| R-07 | 2 | Some text is still English in every language: messages composed on the server (errors, some notifications), the Handoff register and workflow catalogue rows, position titles, report templates and onboarding documents loaded by HQ. | Measured on the test copy in Arabic: 384 of 4,347 visible lines on HQ screens and 145 of 1,117 on leader screens still hold an English sentence | Translate server messages and built sentences; decide who translates HQ-written content | | |
| R-08 | 2 | Hausa, Yoruba and Igbo cover the joining screens only. The five required languages are the others. | Dictionaries `ha`, `yo`, `ig` about 190 entries | `TO DECIDE` whether these three are in scope | | |
| R-09 | 2 | 14 management decisions (D01 to D14) are unrecorded. WDOS runs on guardrails meanwhile, including the WNNN country title (D01) and moving WNNN members at 30 (D08). | System rules, Decision register | Record each with approver and date | | |
| R-10 | 2 | Automation share: 0% verified, 69.2% declared by the developer on estimated weights. The 80% target is not shown to be met. | Digital coverage page | A named person verifies each workflow; replace estimates with real volumes | | |
| R-11 | 2 | Institute is not connected. WDOS links to it and does not read enrolment or progress. | Workflow W25 | Institute side to call WDOS with the integration key | | |
| R-12 | 2 | SMS, WhatsApp and voice delivery are not built. Members without smartphones or data receive nothing between visits. | Workflow W23 | `TO DECIDE` provider and budget | | |
| R-13 | 2 | Email is sent from a Gmail address through Brevo, with no authenticated WODDI domain. Mail may land in spam and the sender is a personal mailbox. | Brevo: one sender | Buy or use a WODDI domain, authenticate it, change `MAIL_FROM` | | |
| R-14 | 2 | Privacy: no lawyer-reviewed notice, no retention periods, no erasure on request, cross-border position undecided. | Document 09 | Legal advice; decisions; then build | | |
| R-15 | 2 | Single points of failure in accounts and administrators. | Document 06 | Name second holders | | |
| R-16 | 3 | World place lists come from an open data set and have oddities (for example the United Kingdom lists 247 areas; some countries have regions with no towns). No worldwide ward or community list exists; the community is typed where none is listed. | `assets/geo/world` | Correct priority countries by hand; accept typing elsewhere | | |
| R-17 | 3 | The African and diaspora map sets use different colour palettes; the 17 new African maps are drawn by program in the style of the Founder's 38. | `assets/goodmother` | Founder to review each new map | | |
| R-18 | 3 | Weekly inspiration messages have English and French texts only. | Rules page, acceptance demonstration | Write Portuguese, Arabic, Swahili texts | | |
| R-19 | 3 | Programme beneficiary records (WHI, WESAP, YES, SNARP) are not built. | Workflow W30 | `TO DECIDE` scope | | |
| R-20 | 3 | No separate Founder, finance or case-handler role: every HQ administrator sees everything, including private reports. | Document 01 | `TO DECIDE` roles; then build | | |
| R-21 | 3 | Guide videos were rebuilt at v106.0 and do not show the newest maps; videos for HQ, leaders and IT are scripts only. | Document 05 | Record after wording is approved | | |
| R-22 | 3 | No accessibility test has been run with a screen reader user or with low-literacy users on basic phones. | No evidence file | Include in the pilot | | |
| R-23 | 3 | No load test at real volumes and no uptime monitoring outside Render's own. | No evidence file | Load test on staging; add monitoring | | |
| R-25 | 2 | AI features run on free lanes. Free tiers may use prompts to improve the provider's products. WDOS sends leader-written text only after redaction, and never private reports or voices, but redaction by pattern can miss a name written in an unusual way. | `lib/ai_fns.php`, test t107 | Put the main lane on a paid plan and list it in `AI_PAID_LANES` before real report text is summarised | | |
| R-26 | 3 | Of the 31 AI features in the AI handoff, 5 are built (digest, weekly brief, library search by meaning, coverage gaps, Listen on every page). Voice-to-report has the speech service but no screen, and needs a paid lane. The other 25 are not built. | AI handoff note of 12 Nov 2026 | Build in the handoff's phase order | | |
| R-24 | 4 | Shared phones: one phone number can sign in one account only. Other women on the same phone need an email or a leader reset. | Registration rule | `TO DECIDE` (see document 08) | | |

## Closed in the last builds

| ID | Item | Fixed in | Evidence |
|---|---|---|---|
| C-01 | Site would not open after a failed migration | v105.2 | Live check; migration runner now non-fatal |
| C-02 | The word "null" printed on Settings, Reports, Structure | v103.0 | Sweep |
| C-03 | Sign-in page and registration messages untranslated | v103.0 | Language walk-through |
| C-04 | A woman with no email could not join | v106.0 | Test suite t106 |
| C-05 | Structure page listed 485 vacant rows after all countries were added | v105.x | Local check |
| C-06 | Leader, HQ and remaining member screen labels untranslated in French, Portuguese, Arabic, Swahili | v107.0 | `tr/extract.py`: 0 member, 0 leader, 0 HQ fixed labels left (unreviewed, see R-06) |
| C-07 | Mauritania had no country map | v107.0 | All 55 African countries now have one |

## Readiness, stated plainly

On this register WDOS is **not ready for broad rollout**. Items R-01 to R-04 are open at severity 1. A small supervised pilot is possible once R-01, R-03 and R-04 have an agreed position and the pilot has written entry and exit criteria. That decision belongs to the Founder/CEO and the IT Lead together.
