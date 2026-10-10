# Deployment

Production runs on Infomaniak shared hosting. Every push to
`master` runs the tests and, if they pass, deploys (`.github/workflows/deploy.yml`). A deploy can also
be re-run by hand from the Actions tab ("Run workflow").

Production is the source of truth for data ([ADR 0001](adr/0001-production-is-source-of-truth.md)):
deploys ship code and migrations only.

## What a deploy does

1. Tests (PHP 8.5, SQLite in memory).
2. `composer install --no-dev` on the runner (also publishes the Statamic CP assets). No Node build:
   the `/budget` app doesn't use Vite.
3. `artisan down` → `rsync --delete` of the code → `migrate --force` → `optimize` →
   `please stache:refresh` → `artisan up`. The server CLI PHP is `/opt/php8.5/bin/php`.

Never touched by a deploy (server-owned): `.env`, `users/` (the admin login), `storage/`, and
Infomaniak's `.user.ini` / `public/.infomaniak-maintenance.html`.
Anything changed through the CP that is stored as files (blueprints, collection settings) *is*
overwritten — make such changes locally and commit them. Categories are entries in the database,
so editing them in the CP is safe.

If a deploy fails mid-way the site stays in maintenance mode on purpose: fix and re-run, or
`ssh` in and run `/opt/php8.5/bin/php artisan up`.

## GitHub secrets

| Secret | Value |
| --- | --- |
| `INFOMANIAK_SSH_KEY` | Private key `~/.ssh/budget-infomaniak-github-deploy` (on Thomas' laptop) |
| `INFOMANIAK_SSH_HOST` | `xxxx.ftp.infomaniak.com` |
| `INFOMANIAK_SSH_USER` | the FTP/SSH account |
| `INFOMANIAK_KNOWN_HOSTS` | `ssh-keyscan <host>` output |
| `INFOMANIAK_SITE_PATH` | absolute site folder, e.g. `/home/clients/<hash>/sites/<domain>` (the deploy refuses anything else) |

### Deploy key

`~/.ssh/budget-infomaniak-github-deploy` (ed25519, no passphrase) exists only to let GitHub Actions
deploy this app. Its public half is in the server's `~/.ssh/authorized_keys`, with the comment
`github-actions deploy: Meuss/budget -> <domain> (Infomaniak)`. To rotate: generate a
new key, replace the line in `authorized_keys`, and
`gh secret set INFOMANIAK_SSH_KEY < ~/.ssh/<new key>`.

## First-time setup (done once)

1. Infomaniak Manager: add the site (your domain) with its folder pointing at
   `<site path>/public`, PHP 8.5, Let's Encrypt; create a database + user; create an SSH account.
2. Import the local database into the new (empty) database with phpMyAdmin — **before** the first
   deploy, so the first `migrate --force` has nothing to do.
3. Over SFTP: upload `users/<email>.yaml` to `<site path>/users/`, and create `<site path>/.env`
   from `.env.production.example` (new `APP_KEY`: `php artisan key:generate --show`).
4. Add the deploy public key to `~/.ssh/authorized_keys` on the server; set the GitHub secrets.
5. Push to `master`. Then log in at `/cp` and set up two-factor authentication (required for all
   users; `/budget` is unreachable until it's done).

## Monthly reminder email

On the 2nd of every month at 08:00 (`BUDGET_REMINDER_TIMEZONE`, default `Europe/Zurich`), the app
emails `BUDGET_REMINDER_EMAIL` a reminder to import new statements and enter a Relevé, with the
dates of the latest transaction and Relevé. It needs, on the server:

1. **Mail settings** in `.env`: `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`,
   `MAIL_USERNAME`, `MAIL_PASSWORD` and `MAIL_FROM_ADDRESS` (for an Infomaniak mailbox:
   `mail.infomaniak.com`, port `587`, scheme `smtp`, which uses STARTTLS), plus `BUDGET_REMINDER_EMAIL`. Then run
   `/opt/php8.5/bin/php artisan config:cache` so the cached config picks them up.
2. **A trigger.** Infomaniak's "Tâches planifiées" can only call a URL, so it calls
   `https://<domain>/cron/reminder` instead of running the Laravel scheduler:
   - In `.env`, set `BUDGET_REMINDER_TOKEN` to a long random string (`openssl rand -hex 32`).
   - Manager → Tâches planifiées → new task: URL `https://<domain>/cron/reminder`, tick
     "Cette URL est protégée par un mot de passe", any username, the token as password; run it
     monthly, on the 2nd, at 08:00.

   Without the token the URL answers 404. On a host with a real crontab, run
   `php artisan schedule:run` every minute instead (the reminder is scheduled in `routes/console.php`).

Test it by running the task once from the Manager, or with
`curl -u cron:<token> https://<domain>/cron/reminder`.

## Pulling production data locally

`scripts/pull-production.sh` replaces the local database with a production dump (after backing
up the local one to `storage/app/private/db-backups/`). Set `PRODUCTION_SSH` and `PRODUCTION_PATH`
in your local `.env` first.

## Lost password or 2FA device

Password reset by email is disabled, so recovery is over SSH, from the site folder:

- New password: `/opt/php8.5/bin/php please tinker` →
  `Statamic\Facades\User::findByEmail('…')->password('…')->save();`
- Reset 2FA: remove the `two_factor_*` lines from `users/<email>.yaml`; you'll be asked to set it up
  again at next login.

Backups: Infomaniak's automatic hosting backups (files + databases).
