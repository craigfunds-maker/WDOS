# 04. Quick guide for IT

## What WDOS is

A PHP 8.3 application with a MySQL-compatible database (TiDB Cloud), packaged with Docker, on Render. Code: the `wdos-php` folder of the WDOS repository. Full steps are in `README-DEPLOY.md`.

| Part | Where |
|---|---|
| Test copy | https://wdos.onrender.com (Render web service) |
| Database | TiDB Cloud. The password lives only in Render's environment settings. |
| Email | Brevo, through `BREVO_API_KEY` and `MAIL_FROM` in Render |
| Health | Sign in as HQ, Handoff register (integrity checks, migration error if any) and Dashboard, Operations |

There is one environment today. **A separate production environment does not exist yet.** See register item R-04.

## Releasing an update

1. Change the code. Raise the build number in `lib/core.php` and `sw.js` together.
2. Run the test suites on a local copy. All must print `FAILURES 0`.
3. Commit and push to `main`. Deploy on Render.
4. After the deploy, open `/api/config`. It must answer. Then open the site and sign in.
5. Sign in as HQ and read Handoff register. "Migration error" must be empty.

Database changes are files in `migrations/`, applied in order, once, when the site is first opened after a deploy. A failed migration no longer stops the site: the error is kept and shown in the Handoff register.

**Lesson from 12 November 2026.** A migration that passed locally failed on TiDB (it compared two columns of a derived table, error 1267) and the site was down for about 13 minutes. Local tests run on MariaDB and do not catch TiDB-only errors. Until a TiDB staging copy exists, write each migration as simple single statements and check `/api/config` straight after every deploy.

## Rolling back

Render keeps earlier deploys: choose the last good one and redeploy it. Migrations only add tables and columns, so the older code runs on the newer database. If data was damaged, restore from a backup (below).

## Backup and restore

- **Take one:** a super administrator opens Dashboard, Operations, Take a backup now, and types their own password. One compressed file downloads. It is not kept on the server.
- **Restore:** `php lib/restore_cli.php <file>` on the command line, into an empty database only. It compares row counts at the end.
- **Honest state:** restore has been rehearsed on a local MariaDB copy, not yet on TiDB. See R-03. TiDB Cloud's own backups are the second line; check what the current plan includes.

## Secrets

`DB_*`, `BREVO_API_KEY`, `MAIL_FROM`, `INTEGRATION_KEY`, the push keys and the setup key live in Render only. Never in the repository, a chat, an email or a ticket. Change a key if it was ever pasted anywhere.

## Monthly checks

| Check | Where |
|---|---|
| Integrity checks all "none" | Handoff register |
| Daily automatic work ran each day | Handoff register, Daily automatic work |
| Email and phone notification failures | Dashboard, Operations |
| A backup was taken and stored off the server | Dashboard, Operations, Last backup |
| Staff logins still needed | Staff and logins |
