# Production is the source of truth for budget data

The production database (budget.example.com, Infomaniak) was seeded once from the local database by a manual phpMyAdmin import. From then on, production owns the data: CSV imports and classifications happen on the live site, deploys ship code and migrations only, and data only ever flows production → local (via `scripts/pull-production.sh`), never local → production. Overwriting production on each deploy was rejected because it would silently discard anything imported or classified online.

## Consequences

- The local database is a disposable copy; refresh it with the pull script instead of editing it and expecting changes to go live.
- The admin login lives in `users/*.yaml` (gitignored), not the database, so it was uploaded to the server separately and deploys never touch `users/`.
- Backups rely on Infomaniak's automatic hosting backups.
