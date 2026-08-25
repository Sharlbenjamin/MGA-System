# Phase 1 — WhatsApp + Communication Engine

## Deployment

1. Pull the branch and install dependencies (no new Composer packages).
2. Run migrations (includes communication permissions):
   ```bash
   php artisan migrate
   ```
   If you deployed before this migration existed, run instead:
   ```bash
   php artisan db:seed --class=PermissionSeeder
   php artisan db:seed --class=RoleSeeder
   php artisan permission:cache-reset
   ```
3. Seed communication templates (production-safe — uses `updateOrCreate`):
   ```bash
   php artisan db:seed --class=CommunicationTemplateSeeder
   ```
4. Assign permissions to roles (admin receives them automatically from the migration):
   - `contact providers` — users who may open WhatsApp from MGA
   - `view/edit CommunicationTemplate` — admins who manage templates
5. Optional `.env`:
   ```env
   WHATSAPP_ENABLED=true
   WHATSAPP_DEFAULT_COUNTRY_CODE=353
   COMMUNICATIONS_EMAIL_ENABLED=false
   ```
6. Clear caches:
   ```bash
   php artisan optimize:clear
   php artisan filament:optimize
   ```

No Slack, Gmail OAuth, or Twilio credentials are required for Phase 1.

## Manual testing checklist

- [ ] **Request Appointment** → select branch(es) → **Message Doctor** → preview → Open WhatsApp → correct number + prefilled message (Mac Safari/Chrome)
- [ ] Same flow on **iPhone Safari** (mobile)
- [ ] **Provider → Files** → bulk select cases → **Request Missing Documents** → one consolidated message
- [ ] **Provider Branch → Files** → same actions
- [ ] **Provider / Branch → Bills** → **Request Missing Bills** and **Send Outstanding Bills Update**
- [ ] **Provider / Branch → Transactions** → **Send Transaction Details**
- [ ] Multiple phone numbers → recipient selector appears
- [ ] No phone → clear error, no WhatsApp open
- [ ] Communication log row created with status `prepared` then `opened` (not `sent` until user clicks **Mark as sent**)
- [ ] **CRM → Communication Templates** — edit template text, confirm preview updates
- [ ] User without `contact providers` / `edit Provider` cannot see actions

## Files created

- `config/communications.php`
- `app/Enums/CommunicationChannel.php`, `CommunicationStatus.php`, `CommunicationContextType.php`
- `app/Support/Communications/*`
- `app/Services/Communications/*`
- `app/Models/CommunicationTemplate.php`, `CommunicationLog.php`
- `app/Policies/CommunicationTemplatePolicy.php`
- `app/Filament/Resources/CommunicationTemplateResource.php` (+ pages)
- `app/Filament/Support/ContactProviderCommunications.php`
- `app/Filament/Resources/ProviderBranchResource/RelationManagers/FileRelationManager.php`
- `app/Filament/Resources/ProviderBranchResource/RelationManagers/TransactionRelationManager.php`
- `database/migrations/2026_08_10_000001_create_communication_templates_and_logs_tables.php`
- `database/seeders/CommunicationTemplateSeeder.php`
- `tests/Unit/PhoneNumberNormalizerTest.php`
- `tests/Unit/CommunicationTemplateRendererTest.php`
- `tests/Unit/CommunicationRecipientResolverTest.php`
- `tests/Unit/WhatsAppDeepLinkTransportTest.php`
- `docs/phase-1-communications.md`

## Files modified

- `app/Filament/Resources/FileResource/Pages/RequestAppointment.php`
- `app/Filament/Resources/ProviderResource/RelationManagers/FileRelationManager.php`
- `app/Filament/Resources/ProviderResource/RelationManagers/BillRelationManager.php`
- `app/Filament/Resources/ProviderResource/RelationManagers/TransactionRelationManager.php`
- `app/Filament/Resources/ProviderBranchResource.php`
- `app/Filament/Resources/ProviderBranchResource/RelationManagers/BillRelationManager.php`
- `app/Models/ProviderBranch.php`
- `app/Providers/AuthServiceProvider.php`
- `database/seeders/PermissionSeeder.php`
- `database/seeders/DatabaseSeeder.php`
- `.env.example`

## Scope lock

Phase 1 WhatsApp is the active communications build.

- Gmail API / OAuth / Pub/Sub (Phase 2) — **stopped / not started**
- Slack (Phase 3) — **not implemented**

Do not prepare Google Gmail credentials for this branch unless Phase 2 is explicitly restarted.
