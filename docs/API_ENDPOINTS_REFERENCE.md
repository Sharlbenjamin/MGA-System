# API Endpoints Reference (LIVE – system.medguarda.com)

**Auth:** All routes below (except login) require `Authorization: Bearer <token>`.  
**Base URL:** `https://system.medguarda.com`  
**All `/api/*` responses are JSON only** (exceptions and auth failures return JSON; see `docs/API_WAF_BYPASS.md` if you get HTML/redirects from WAF).

---

## 1) Endpoint status table

### A) FILE APIs

| Endpoint | Status | Notes |
|----------|--------|-------|
| POST /api/login | ✅ Exists | email, password, device_name → { token, user } |
| GET /api/user | ✅ Exists | auth:sanctum |
| POST /api/logout | ✅ Exists | auth:sanctum |
| GET /api/files | ✅ Implemented | Paginated + filters (search, status, ids, country_id, city_id, service_type_id, provider_id, client_id, sort, direction, page, per_page) |
| POST /api/files | ✅ Implemented | Create file |
| GET /api/files/{id} | ✅ Implemented | Show with full relations (file, patient, client, country, city, serviceType, providerBranch, currentAssignment, bills, medicalReports, gops, tasks, comments, assignments, appointments, invoices, bankAccounts, activityLogs) |
| PATCH/PUT /api/files/{id} | ✅ Implemented | Update file |
| DELETE /api/files/{id} | ✅ Implemented | Destroy (policy) |
| GET /api/files/{id}/bills | ✅ Implemented | Relation manager read |
| GET /api/files/{id}/medical-reports | ✅ Implemented | Relation manager read |
| GET /api/files/{id}/gops | ✅ Implemented | Relation manager read |
| GET /api/files/{id}/tasks | ✅ Implemented | Relation manager read |
| GET /api/files/{id}/comments | ✅ Implemented | Relation manager read |
| GET /api/files/{id}/assignments | ✅ Implemented | Assigned users |
| GET /api/files/{id}/appointments | ✅ Implemented | Optional read |
| GET /api/files/{id}/invoices | ✅ Implemented | Optional read |
| GET /api/files/{id}/activity-logs | ✅ Implemented | Optional read |
| GET /api/files/{id}/bank-accounts | ✅ Implemented | Optional read |
| POST /api/files/{id}/assign | ✅ Implemented | body: { user_id } |
| POST /api/files/{id}/request-appointment | ✅ Implemented | body: { provider_branch_id, service_date, service_time } |
| POST /api/files/{id}/comments | ❌ Missing | Not implemented for API; web creates via Filament. Document only. |
| POST /api/files/{id}/tasks | ❌ Missing | Not implemented for API; web creates via Filament. Document only. |
| POST /api/files/{id}/bills, /medical-reports, /gops | ❌ Missing | Not implemented for API; web creates via relation managers. Document only. |

### B) PROVIDER APIs

| Endpoint | Status | Notes |
|----------|--------|-------|
| GET /api/providers | ✅ Implemented | Paginated + filters (search, status, ids, country_id, sort, direction, page, per_page) |
| POST /api/providers | ✅ Implemented | Create provider |
| GET /api/providers/{id} | ✅ Implemented | Show with country, branches, bankAccounts |
| PATCH/PUT /api/providers/{id} | ✅ Implemented | Update provider |
| DELETE /api/providers/{id} | ✅ Implemented | Destroy (policy) |
| GET /api/providers/{id}/provider-leads | ✅ Implemented | Relation manager read |
| GET /api/providers/{id}/branches | ✅ Implemented | Relation manager read |
| GET /api/providers/{id}/branch-services | ✅ Implemented | Branches with services (pivot min_cost, max_cost); serves as “service-costs” data |
| GET /api/providers/{id}/service-costs | ✅ Via branch-services | Use GET .../branch-services for costs per branch/service. No separate table. |
| GET /api/providers/{id}/bills | ✅ Implemented | Relation manager read |
| GET /api/providers/{id}/files | ✅ Implemented | Relation manager read |
| GET /api/providers/{id}/bank-accounts | ✅ Implemented | Relation manager read |
| POST/PATCH branches, branch-services, service-costs, bank accounts | ❌ Missing | Not implemented for API; web manages via Filament. Document only. |

### C) PROVIDER LEADS

| Endpoint | Status | Notes |
|----------|--------|-------|
| GET /api/provider-leads | ✅ Implemented | Paginated + filters (search, status, ids, city_id, provider_id, sort, direction, page, per_page). **HTTP 500 fix:** no eager load of `serviceTypes` (pivot table may not exist on LIVE). |
| POST /api/provider-leads | ✅ Implemented | Create provider lead |
| GET /api/provider-leads/{id} | ✅ Implemented | Show with provider, city |
| PATCH/PUT /api/provider-leads/{id} | ✅ Implemented | Update provider lead |
| DELETE /api/provider-leads/{id} | ✅ Implemented | Destroy (policy) |
| POST /api/provider-leads/{id}/convert | ✅ Implemented | Action (stub: acknowledges; full conversion would create Provider) |

### D) CLIENT APIs

| Endpoint | Status | Notes |
|----------|--------|-------|
| GET /api/clients | ✅ Implemented | Paginated index + filters (search, status, ids, sort, direction, page, per_page) |
| GET /api/clients/{id} | ✅ Implemented | Show client (no POST/PATCH/DELETE per spec “if exists”) |
| GET /api/clients/{id}/files | ✅ Implemented | Relation manager read |
| GET /api/clients/{id}/invoices | ✅ Implemented | Relation manager read |
| GET /api/clients/{id}/transactions | ✅ Implemented | Relation manager read (Transaction where related_type=Client) |
| GET /api/clients/{id}/leads | ✅ Implemented | Relation manager read |
| GET /api/leads | ✅ Implemented | Client leads: paginated + filters (search, status, ids, client_id, sort, direction, page, per_page) |
| POST /api/leads | ✅ Implemented | Create lead |
| GET /api/leads/{id} | ✅ Implemented | Show lead |
| PATCH/PUT /api/leads/{id} | ✅ Implemented | Update lead |
| DELETE /api/leads/{id} | ✅ Implemented | Destroy (policy) |
| POST/PATCH invoices, transactions | ❌ Missing | Not implemented for API; web manages via Filament. Document only. |

---

## 2) HTTP 500 fix (GET /api/provider-leads)

**Cause:** Eager loading `serviceTypes` on `ProviderLead` uses pivot table `provider_lead_service_type`, which does not exist on LIVE (no migration). That triggered a SQL exception and 500.

**Typical exception in `storage/logs/laravel.log` (before fix):**
```
SQLSTATE[42S02]: Base table or view not found: 1146 Table 'database.provider_lead_service_type' doesn't exist
```
Stack trace points at the query that joins `provider_lead_service_type` when loading `serviceTypes`.

**Fix applied:**  
- `ProviderLeadApiController::index()` and `show()` already do **not** eager-load `serviceTypes`; they load only `provider` and `city`.  
- `bootstrap/app.php`: **AuthorizationException** is now rendered as **403 JSON** for `/api/*`, so policy failures do not result in 500.

**Confirm with curl (use a valid Bearer token):**

```bash
curl -i https://system.medguarda.com/api/provider-leads \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Expected:** `HTTP/1.1 200 OK` and JSON body with Laravel paginator shape (`data`, `links`, `meta`).  
If token is missing/invalid: `401` or `403` JSON.  
If WAF blocks: HTML or 307 → follow `docs/API_WAF_BYPASS.md` (whitelist `/api/*` in Cloudways/WAF; no app code change).

---

## 3) Example curl calls (replace YOUR_TOKEN and IDs)

```bash
BASE="https://system.medguarda.com"
T="YOUR_TOKEN"
```

**Auth**

```bash
curl -i -X POST "$BASE/api/login" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"secret","device_name":"ios"}'

curl -i "$BASE/api/user" -H "Accept: application/json" -H "Authorization: Bearer $T"
curl -i -X POST "$BASE/api/logout" -H "Authorization: Bearer $T"
```

**Files**

```bash
curl -i "$BASE/api/files?page=1&per_page=5" -H "Accept: application/json" -H "Authorization: Bearer $T"
curl -i "$BASE/api/files/1" -H "Accept: application/json" -H "Authorization: Bearer $T"
curl -i -X POST "$BASE/api/files" -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer $T" \
  -d '{"patient_id":1,"mga_reference":"MG999XX","service_type_id":1,"status":"New"}'
curl -i -X PATCH "$BASE/api/files/1" -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer $T" \
  -d '{"status":"Handling"}'
curl -i -X POST "$BASE/api/files/1/assign" -H "Content-Type: application/json" -H "Authorization: Bearer $T" -d '{"user_id":2}'
curl -i -X POST "$BASE/api/files/1/request-appointment" -H "Content-Type: application/json" -H "Authorization: Bearer $T" \
  -d '{"provider_branch_id":1,"service_date":"2025-03-01","service_time":"10:00"}'
curl -i "$BASE/api/files/1/bills" -H "Authorization: Bearer $T"
curl -i "$BASE/api/files/1/comments" -H "Authorization: Bearer $T"
```

**Providers**

```bash
curl -i "$BASE/api/providers?per_page=10" -H "Accept: application/json" -H "Authorization: Bearer $T"
curl -i "$BASE/api/providers/1" -H "Authorization: Bearer $T"
curl -i "$BASE/api/providers/1/provider-leads" -H "Authorization: Bearer $T"
curl -i "$BASE/api/providers/1/branches" -H "Authorization: Bearer $T"
curl -i "$BASE/api/providers/1/branch-services" -H "Authorization: Bearer $T"
curl -i "$BASE/api/providers/1/bills" -H "Authorization: Bearer $T"
curl -i "$BASE/api/providers/1/files" -H "Authorization: Bearer $T"
curl -i "$BASE/api/providers/1/bank-accounts" -H "Authorization: Bearer $T"
```

**Provider leads**

```bash
curl -i "$BASE/api/provider-leads?page=1&per_page=20" -H "Accept: application/json" -H "Authorization: Bearer $T"
curl -i "$BASE/api/provider-leads/1" -H "Authorization: Bearer $T"
curl -i -X POST "$BASE/api/provider-leads/1/convert" -H "Authorization: Bearer $T"
```

**Clients & leads**

```bash
curl -i "$BASE/api/clients?per_page=10" -H "Accept: application/json" -H "Authorization: Bearer $T"
curl -i "$BASE/api/clients/1" -H "Authorization: Bearer $T"
curl -i "$BASE/api/clients/1/files" -H "Authorization: Bearer $T"
curl -i "$BASE/api/clients/1/invoices" -H "Authorization: Bearer $T"
curl -i "$BASE/api/clients/1/transactions" -H "Authorization: Bearer $T"
curl -i "$BASE/api/clients/1/leads" -H "Authorization: Bearer $T"
curl -i "$BASE/api/leads?client_id=1" -H "Authorization: Bearer $T"
curl -i "$BASE/api/leads/1" -H "Authorization: Bearer $T"
```

---

## 4) Endpoints not implemented (no schema change)

- **POST /api/files/{id}/comments** – Comment creation exists on web (Filament); not exposed on API. Add later if needed.
- **POST /api/files/{id}/tasks** – Task creation exists on web; not exposed on API. Add later if needed.
- **POST /api/files/{id}/bills, /medical-reports, /gops** – Create via web only; not exposed on API.
- **POST/PATCH provider branches, branch-services, service-costs, bank accounts** – Managed in Filament; not exposed on API.
- **POST/PATCH client invoices, transactions** – Managed in Filament; not exposed on API.
- **POST/PATCH/DELETE /api/clients** – Spec said “GET /api/clients (if exists)” and “use existing client representation”; only GET index/show and relation reads implemented. Create/update/delete clients via web.

No schema, migrations, models, or Filament resources were changed; only API routes and controllers were added or confirmed.
