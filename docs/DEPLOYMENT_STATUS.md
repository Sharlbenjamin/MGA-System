# Deployment status checklist

States: COMPLETE, ACTION REQUIRED, BLOCKED, NOT STARTED, NOT APPLICABLE.

A row is COMPLETE only when it was implemented and checked.

| Item | State | Evidence / blocker |
|---|---|---|
| Preserve existing Laravel + Filament app | COMPLETE | No rewrite; existing hosts still serve `/api/login` as JSON |
| Identify stack (Laravel 11, PHP 8.3, MySQL, Expo 58) | COMPLETE | `composer.json`, `mobile/package.json`, live hosts |
| Existing GitHub repository | COMPLETE | `origin` is `git@github.com:Sharlbenjamin/MGA-System.git` |
| Branching: `staging` + `main` | COMPLETE | Already in use; documented |
| `.gitignore` excludes env and signing files | COMPLETE | Root and `mobile/.gitignore` updated |
| Remove tracked mailbox password from `.env.example` | COMPLETE | Value blanked; rotate the real mailbox if that password was ever used |
| Remove plaintext secrets from `UsersSeeder` | COMPLETE | Seeder now reads `SEED_ADMIN_*` env values |
| Local / staging / production env templates | COMPLETE | `.env.example`, `.env.staging.example`, `.env.production.example` |
| Reuse existing Cloudways server | COMPLETE | Recommendation + live API proof; no new server created |
| Confirm Cloudways application names, PHP 8.3, `public/` root | ACTION REQUIRED | Dashboard access is not available from this workspace |
| Create a third Cloudways application | NOT APPLICABLE | Staging and production applications already exist |
| Separate staging and production databases | ACTION REQUIRED | Must be confirmed in Cloudways; do not share one database |
| Server `.env` values (keys, DB, mail) | ACTION REQUIRED | Stay on the server; never paste into chat |
| HTTPS on current hosts | COMPLETE | Both hosts answered API calls over HTTPS |
| Custom domain / DNS change | NOT APPLICABLE | `system.medguarda.com` and `mgastaging.medguarda.com` already work |
| API JSON login on production | COMPLETE | `POST /api/login` returned JSON 422 for invalid credentials |
| API JSON login on staging | COMPLETE | Same 422 JSON response |
| `/api/health` endpoint | COMPLETE locally | Feature test added; **not on live hosts until deployed** |
| Login rate limit (`10` / minute) | COMPLETE locally | Not on live hosts until deployed |
| Sanctum token expiration via env | COMPLETE locally | Default remains unlimited until `SANCTUM_TOKEN_EXPIRATION` is set on the server |
| Replace Sanctum | NOT APPLICABLE | Existing token auth is the correct system for this mobile app |
| Authenticated API smoke test (code) | COMPLETE | `tests/Feature/ApiInfrastructureSmokeTest.php` |
| Authenticated API smoke test against staging with a real user | ACTION REQUIRED | Needs a staging login; do not send the password in chat |
| GitHub Actions CI | COMPLETE locally | `.github/workflows/ci.yml`; not verified on GitHub until pushed |
| Cloudways Git auto-deploy | ACTION REQUIRED | Confirm each application tracks the correct branch |
| Repeatable server release script | COMPLETE | `scripts/cloudways-release.sh` |
| Cron `schedule:run` | ACTION REQUIRED | Required for communications polling |
| Queue worker | ACTION REQUIRED | Required for appointment and Drive jobs |
| PHP `imap` extension | ACTION REQUIRED | Required for Gmail sync |
| Cloudways backups enabled | ACTION REQUIRED | Confirm in Backup And Restore |
| Mobile env-specific API URLs | COMPLETE locally | `app.config.js`, `eas.json`, `src/api.ts` |
| Mobile connected to staging on a physical device | ACTION REQUIRED | Install a build or Expo Go, set server if needed, sign in |
| EAS project / remote push | ACTION REQUIRED | `extra.eas.projectId` is empty |
| App Store / Play submission | NOT STARTED | Signing, privacy, and store listings are not done |
| Camera, Health, IAP, Sign in with Apple/Google | NOT APPLICABLE | Not part of this app |
| GitHub CLI authentication on this machine | ACTION REQUIRED | `gh auth status` reports an invalid token |
| Documentation | COMPLETE | This file + `docs/INFRASTRUCTURE_AND_DEPLOYMENT.md` |
