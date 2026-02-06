# LIVE API Verification – system.medguarda.com

**Constraints respected:** No DB schema, migrations, models, factories, seeders, Filament, or new packages changed. Only API exposure and the provider-leads 500 fix.

---

## 1) Endpoint status table

### A) FILE APIs

| Endpoint | Status | Notes |
|----------|--------|-------|
| GET /api/files | ✅ Implemented | Paginated + filters |
| POST /api/files | ✅ Implemented | Create (already supported) |
| GET /api/files/{id} | ✅ Implemented | Show with full relations (file, patient, client, country, city, serviceType, providerBranch, currentAssignment, bills, medicalReports, gops, tasks, comments, assignments, appointments, invoices, bankAccounts, activityLogs) |
| PATCH /api/files/{id} | ✅ Implemented | Update |
| DELETE /api/files/{id} | ✅ Implemented | If policy allows |
| GET /api/files/{id}/bills | ✅ Implemented | Read only |
| GET /api/files/{id}/medical-reports | ✅ Implemented | Read only |
| GET /api/files/{id}/gops | ✅ Implemented | Read only |
| GET /api/files/{id}/tasks | ✅ Implemented | Read only |
| GET /api/files/{id}/comments | ✅ Implemented | Read only |
| GET /api/files/{id}/assignments | ✅ Implemented | Read only |
| GET /api/files/{id}/appointments | ✅ Implemented | Read only |
| GET /api/files/{id}/invoices | ✅ Implemented | Read only |
| GET /api/files/{id}/activity-logs | ✅ Implemented | Read only |
| GET /api/files/{id}/bank-accounts | ✅ Implemented | Read only |
| POST /api/files/{id}/assign | ✅ Implemented | Body: { user_id } (web supported) |
| POST /api/files/{id}/request-appointment | ✅ Implemented | Body: { provider_branch_id, service_date, service_time } (web supported) |

### B) PROVIDER APIs

| Endpoint | Status | Notes |
|----------|--------|-------|
| GET /api/providers | ✅ Implemented | Paginated + filters |
| POST /api/providers | ✅ Implemented | Create (already supported) |
| GET /api/providers/{id} | ✅ Implemented | Show with relations |
| PATCH /api/providers/{id} | ✅ Implemented | Update |
| DELETE /api/providers/{id} | ✅ Implemented | If policy allows |
| GET /api/providers/{id}/provider-leads | ✅ Implemented | Read only |
| GET /api/providers/{id}/branches | ✅ Implemented | Read only |
| GET /api/providers/{id}/branch-services | ✅ Implemented | Read only (branches + services with pivot costs) |
| GET /api/providers/{id}/service-costs | ✅ Implemented | Read only (same response as branch-services) |
| GET /api/providers/{id}/bills | ✅ Implemented | Read only |
| GET /api/providers/{id}/files | ✅ Implemented | Read only |
| GET /api/providers/{id}/bank-accounts | ✅ Implemented | Read only |

### C) PROVIDER LEADS

| Endpoint | Status | Notes |
|----------|--------|-------|
| GET /api/provider-leads | ✅ Implemented | **500 FIX APPLIED** – no serviceTypes eager load (pivot table absent on LIVE) |
| POST /api/provider-leads | ✅ Implemented | Create |
| GET /api/provider-leads/{id} | ✅ Implemented | Show |
| PATCH /api/provider-leads/{id} | ✅ Implemented | Update |
| DELETE /api/provider-leads/{id} | ✅ Implemented | If policy allows |
| POST /api/provider-leads/{id}/convert | ✅ Implemented | Action (stub) |

### D) CLIENT APIs

| Endpoint | Status | Notes |
|----------|--------|-------|
| GET /api/clients | ✅ Implemented | Paginated index |
| GET /api/clients/{id} | ✅ Implemented | Show |
| GET /api/clients/{id}/files | ✅ Implemented | Read only |
| GET /api/clients/{id}/invoices | ✅ Implemented | Read only |
| GET /api/clients/{id}/transactions | ✅ Implemented | Read only |
| GET /api/clients/{id}/leads | ✅ Implemented | Read only |
| GET /api/leads | ✅ Implemented | Client leads, paginated + filters |
| POST /api/leads | ✅ Implemented | Create |
| GET /api/leads/{id} | ✅ Implemented | Show |
| PATCH /api/leads/{id} | ✅ Implemented | Update |
| DELETE /api/leads/{id} | ✅ Implemented | If policy allows |

### E) AUTH (unchanged)

| Endpoint | Status |
|----------|--------|
| POST /api/login | ✅ Exists |
| GET /api/user | ✅ Exists (auth:sanctum) |
| POST /api/logout | ✅ Exists (auth:sanctum) |

---

## 2) Example curl (one per area)

**Auth**
```bash
curl -i -X POST https://system.medguarda.com/api/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"u@e.com","password":"p","device_name":"ios"}'

curl -i https://system.medguarda.com/api/user \
  -H "Authorization: Bearer TOKEN"
```

**Provider leads (500 fix verification)**
```bash
curl -i https://system.medguarda.com/api/provider-leads \
  -H "Accept: application/json" \
  -H "Authorization: Bearer TOKEN"
```

**Files**
```bash
curl -i "https://system.medguarda.com/api/files?page=1&per_page=5" \
  -H "Authorization: Bearer TOKEN"

curl -i https://system.medguarda.com/api/files/1 \
  -H "Authorization: Bearer TOKEN"
```

**Providers**
```bash
curl -i https://system.medguarda.com/api/providers \
  -H "Authorization: Bearer TOKEN"

curl -i https://system.medguarda.com/api/providers/1/service-costs \
  -H "Authorization: Bearer TOKEN"
```

**Clients**
```bash
curl -i https://system.medguarda.com/api/clients \
  -H "Authorization: Bearer TOKEN"

curl -i https://system.medguarda.com/api/leads \
  -H "Authorization: Bearer TOKEN"
```

---

## 3) Confirmation

- **GET /api/provider-leads**  
  **Fix:** Controller does not eager-load `serviceTypes` (pivot `provider_lead_service_type` does not exist on LIVE). Only `provider` and `city` are loaded.  
  **Expected:** `HTTP/1.1 200 OK` and JSON with `data`, `links`, `meta` (paginator).  
  **If you still see 500:** Check `storage/logs/laravel.log` for the exact exception (e.g. missing table or policy). Auth failures return 403 JSON (handled in `bootstrap/app.php`).

- **No /api/* endpoint returns HTML or redirects**  
  All `/api/*` exceptions are rendered as JSON in `bootstrap/app.php` (ValidationException, AuthorizationException, HttpException, and generic Throwable). WAF/app-password in front of the app can still return HTML/307; that is not fixed in app code (see `docs/API_WAF_BYPASS.md` for Cloudways whitelist steps).

---

## 4) Endpoints NOT exposed (require schema or out of scope)

- **None** of the required endpoints above need schema changes; all are implemented or already existed.  
- The following are **not** part of the required surface and were not added:  
  - POST /api/files/{id}/comments, /tasks, /bills, /medical-reports, /gops (create)  
  - POST/PATCH /api/providers/{id}/branches, branch-services, bank-accounts  
  - POST/PATCH/DELETE /api/clients  
  - POST/PATCH client invoices, transactions  

No endpoint was omitted **because** of schema limits; the required list is covered.
