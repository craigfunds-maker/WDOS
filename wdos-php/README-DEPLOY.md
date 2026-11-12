# WDOS on PHP: deployment guide (build v91.0)

Everything here is done in a browser. No terminal is needed.

## What this package is

WDOS rebuilt as an installable PWA on plain PHP 8.1+ with a MySQL-compatible
database. It runs on Render with TiDB Cloud for testing, and on QServers shared
hosting (cPanel) later, with no code change. Only the settings differ.

Phase 1 contains: the network sign-up home page (WGMN and WNNN logos), sign-in
with password, Google and fingerprint, in-app notifications, push
notifications with automations, the HQ dashboard, the volunteer leadership
dashboard, Members, Announcements and Settings.

## A. Test deployment on Render + TiDB Cloud

1. **Database.** In TiDB Cloud, open your cluster and create a database named
   `wdos` (SQL Editor: `CREATE DATABASE wdos;`). Keep the host, port (4000),
   user and password from the Connect panel.
2. **Code.** On github.com create a private repository, for example
   `Buja-OS/wdos-php`. Unzip this package on your computer, open the
   repository, choose Add file > Upload files, and drag in everything inside
   the `wdos-php` folder. The website accepts 100 files at a time, so upload
   `assets/flags` separately from the rest. Commit.
3. **Service.** Tell Claude "deployed to github" with the repository address.
   Claude creates the Render web service (Docker, free plan) and triggers each
   deploy, the same routine as Buja.
4. **Settings.** In Render > the service > Environment, add:

   | Name | Value |
   |---|---|
   | `APP_URL` | the Render address, e.g. `https://wdos.onrender.com` (no slash at the end) |
   | `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | from TiDB (`DB_NAME` = `wdos`) |
   | `DB_SSL` | `1` |
   | `ADMIN_KEY` | a long random phrase you invent; keep it private |

   Paste the database password yourself. Never send it in chat.
5. **Setup page.** Open `APP_URL/api/setup`. Enter the admin key, then press, in
   order: Check the server, Create or update tables, Create HQ administrator,
   Generate push keys.
6. **Push keys.** Copy the two values shown into Render > Environment as
   `VAPID_PUBLIC` and `VAPID_PRIVATE`. Save (Render restarts). Generate these
   once only.
7. **Timer for automations.** At cron-job.org create a job that opens
   `APP_URL/api/cron?key=YOUR_ADMIN_KEY` every 5 minutes. This sends queued
   push notifications, birthday greetings, the photo reminder and the HQ
   morning summary, and it keeps the free Render service awake.

## B. Google sign-in (optional, can be added any time)

In Google Cloud Console > Credentials, open the OAuth client already used for
WDOS (or create a Web application client) and add this Authorised redirect URI:
`APP_URL/api/auth/google/callback`. Then add `GOOGLE_CLIENT_ID` and
`GOOGLE_CLIENT_SECRET` to the environment. Until then the Google button is
shown greyed out.

## C. Email for password-reset links (optional)

Add `BREVO_API_KEY` and `MAIL_FROM` (a sender verified in Brevo). Without them
members ask HQ, and HQ resets the password from Members.

## D. Moving to QServers shared hosting later

1. In cPanel create a MySQL database and user, and note the names.
2. Upload the same files into `public_html` with File Manager (upload the zip,
   then Extract). Make sure `.htaccess` is there: switch on "Show hidden files"
   in File Manager settings.
3. Copy `config.sample.php` to `config.local.php` and fill it in (`DB_SSL` = 0).
4. Open `https://your-domain/api/setup` and press the same buttons. Reuse the
   same `VAPID` keys only if the domain stays the same; on a new domain members
   switch notifications and fingerprint on again, because browsers tie both to
   the web address.
5. In cPanel > Cron Jobs add, every 5 minutes:
   `curl -s "https://your-domain/api/cron?key=YOUR_ADMIN_KEY" > /dev/null`
6. Moving the data: export the TiDB database (or let Claude prepare the SQL
   file) and import it with phpMyAdmin.

## Releasing an update

Upload the changed files, open `/api/setup` and press Create or update tables.
Each release changes `BUILD` in `sw.js`, and open apps reload themselves once.

## Bringing members across from the current WDOS (v90.1)

Open `/api/setup`, section 5. It gives the export query to run in the current
WDOS (Supabase SQL Editor, row limit set to No limit, Export > Download CSV),
then checks the file without saving, then imports. Members keep their current
passwords. Existing emails are never overwritten, so it is safe to run again
later to pick up people who joined in between. HQ access is never granted by
the import. The same query is saved in `migrations/EXPORT-FROM-CURRENT-WDOS.pgsql.txt`.

## Inspiration messages (v91.0)

Weekly inspiration goes to every WGMN and WNNN member on Mondays at 7:00 Lagos
time, the monthly leadership inspiration goes to leaders on the first
Wednesday, and the birthday card shows on the day. All three need the 5-minute
timer from section A step 7, because the timer is what sends them. Pictures
live in `assets/inspiration`. After uploading this build, open `/api/setup` and
press Create or update tables once.

## Reporting and accountability (v92.0)

Leaders and HQ get a Reporting menu: dashboard, my reports, operational
records, reports I supervise, compliance, risks and escalations, archive. The
five approved report forms are part of the build (migration 005), taken
unchanged from the current WDOS. Setup page section 7 can reload them if the
forms are revised there.
Automatic reminders are off until HQ switches them on under Compliance >
Configure. Evidence is added as links; file upload is not built yet.

## Not in this phase

The current Supabase WDOS stays the live system. Its other modules (meetings and the multilingual room, activation journeys, referral links,
onboarding reader, HQ role homepages, message library, tasks, documents) are
not moved yet. Profile photographs are not copied by the import. Interface languages other
than English are also still to be ported.

## Backup and restore (from v104.0)

**Taking a backup (browser only).** Sign in as a super administrator. On the HQ dashboard, in the Operations card, type your own password under Backup and press "Take a backup now". One file named `wdos-backup-YYYYMMDD-HHMMSS.jsonl.gz` downloads to your computer. WDOS does not keep a copy. The file holds members' details and password hashes: store it somewhere private and never send it by email or chat.

**What is in it.** Every table except sign-in sessions and throttling counters. The first line lists each table and its number of rows.

**Restoring (needs a technical person and a command line).** Restore only into a new, empty database, never over the live one:
1. Create the new empty database and point WDOS at it (DB_HOST, DB_NAME and the other DB settings).
2. Run the migrations (the setup page, action "migrate").
3. Run `php lib/restore_cli.php path/to/wdos-backup-....jsonl.gz`.
4. Read the last line. "RESTORE CLEAN" means every table has the same number of rows as the backup. Anything else lists the tables that differ.

The tool refuses to run on a database that already holds people, and it cannot be opened from a browser.

**What has been proven.** On the test database a backup was restored into a new database, every one of 72 tables matched, sign-in worked on the restored copy and the integrity checks were all zero. It has not yet been rehearsed against the live TiDB database.


## Document pack (from v107.0)

`docs/pack/` holds eleven working documents written from the code as built: roles and access matrix, quick guides for HQ, leaders and IT, video scripts, administrator continuity and emergency access, support routing, migration plan, retention and incident plan, acceptance criteria for workflows W01 to W30, and the defect and open-items register. They are drafts for joint review, not approved policy. Blanks marked `TO DECIDE` are management decisions.

## AI features (from v108.0)

One AI service (`lib/ai_fns.php`, class `Ai`) is the only code that talks to an AI provider. Lanes are tried in this order: Cloudflare Workers AI, Hugging Face, Groq, Gemini. A lane is used only when its key is set in Render, under Environment:

| Lane | Render variables |
|---|---|
| Cloudflare Workers AI (main lane) | `CF_ACCOUNT_ID`, `CF_API_TOKEN` (token with Workers AI Read and Edit) |
| Hugging Face | `HF_TOKEN` |
| Groq | `GROQ_API_KEY` |
| Gemini | `GEMINI_API_KEY` |

Optional: `AI_DAILY_CALLS` (default 300), `AI_LANES` (order), `AI_PAID_LANES` (names of lanes on a paid plan, for example `cloudflare`). With no key every AI panel shows its counted figures and a plain note; nothing breaks.

Rules in System rules: `ai_features` (on/off) and `ai_free_lane_text` (redacted/off). Leader-written text goes to a free lane only after names, emails, phone numbers, links and membership numbers are removed. Private reports and voice recordings never go to a free lane. Every call is logged in `ai_calls` and shown on HQ, AI assistant.

Built: AI service, report digest, weekly brief (also written by itself on Mondays), library search by meaning with word search underneath, coverage gap finder, Listen on every page (the phone's own voice). Not built yet: the other 26 items of the AI handoff list.
