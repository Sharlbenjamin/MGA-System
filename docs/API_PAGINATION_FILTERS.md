# API Pagination, Filters & Actions

All listing endpoints return Laravel paginator JSON: `data`, `meta`, `links`.  
Query params: `page`, `per_page` (default 20, max 100), plus resource-specific filters.  
All routes under `/api/*` (except login/patients) require `Authorization: Bearer <token>`.

---

## Query parameters (all index endpoints)

| Param      | Type   | Default   | Description |
|-----------|--------|-----------|-------------|
| `page`    | int    | 1         | Page number |
| `per_page`| int    | 20 (max 100) | Items per page |
| `search`  | string | -         | Search on main name/reference fields |
| `status`  | string | -         | Exact match on status |
| `sort`    | string | see below | Sort column |
| `direction`| string| desc      | `asc` or `desc` |
| `ids`     | array  | -         | Filter by IDs (e.g. `ids[]=1&ids[]=2`) |

Resource-specific filters are listed per endpoint below.

---

## Supported filters per endpoint

### GET /api/files
| Filter | Type | Description |
|--------|------|-------------|
| search | string | mga_reference, client_reference, patient name, client company_name |
| status | string | New, Handling, Available, Confirmed, Assisted, Hold, Waiting MR, Refund, Cancelled, Void |
| ids | array | file ids |
| country_id | int | exists:countries |
| city_id | int | exists:cities |
| service_type_id | int | exists:service_types |
| provider_id | int | via provider_branch |
| client_id | int | via patient.client_id |
| sort | id, mga_reference, created_at, updated_at, service_date |
| direction | asc, desc |

### GET /api/leads
| Filter | Type | Description |
|--------|------|-------------|
| search | string | first_name, email, client company_name |
| status | string | exact |
| ids | array | lead ids |
| client_id | int | exists:clients |
| sort | id, first_name, created_at, updated_at, last_contact_date |
| direction | asc, desc |

### GET /api/provider-leads
| Filter | Type | Description |
|--------|------|-------------|
| search | string | name, email, provider name |
| status | string | exact |
| ids | array | provider_lead ids |
| city_id | int | exists:cities |
| provider_id | int | exists:providers |
| sort | id, name, created_at, updated_at, last_contact_date |
| direction | asc, desc |

### GET /api/providers
| Filter | Type | Description |
|--------|------|-------------|
| search | string | name, email |
| status | string | Active, Hold, Potential, Black list |
| ids | array | provider ids |
| country_id | int | exists:countries |
| sort | id, name, created_at, updated_at |
| direction | asc, desc |

---

## Example response (paginated list)

```json
{
  "data": [
    {
      "id": 1,
      "name": "Example",
      "status": "Active",
      ...
    }
  ],
  "links": {
    "first": "https://system.medguarda.com/api/files?page=1",
    "last": "https://system.medguarda.com/api/files?page=5",
    "prev": null,
    "next": "https://system.medguarda.com/api/files?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "path": "https://system.medguarda.com/api/files",
    "per_page": 20,
    "to": 20,
    "total": 100
  }
}
```

---

## Curl examples (replace BASE and TOKEN)

```bash
BASE="https://system.medguarda.com"
TOKEN="your_bearer_token"
```

### Pagination

```bash
# Page 2, 10 per page
curl -s -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  "$BASE/api/files?page=2&per_page=10"

# Provider leads with filters
curl -s -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  "$BASE/api/provider-leads?status=Active&city_id=1&per_page=5&sort=name&direction=asc"

# Leads search
curl -s -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  "$BASE/api/leads?search=acme&per_page=20"

# Files by status and client
curl -s -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  "$BASE/api/files?status=Handling&client_id=2&page=1"
```

### CRUD

```bash
# Create provider lead
curl -s -X POST "$BASE/api/provider-leads" \
  -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"name":"New Lead","type":"Clinic","status":"New","communication_method":"Email"}'

# Update lead (PATCH)
curl -s -X PATCH "$BASE/api/leads/1" \
  -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"status":"Interested"}'

# Delete provider lead
curl -s -X DELETE "$BASE/api/provider-leads/1" -H "Authorization: Bearer $TOKEN"
```

### File actions

```bash
# Assign file to user
curl -s -X POST "$BASE/api/files/1/assign" \
  -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"user_id":2}'

# Request appointment for file
curl -s -X POST "$BASE/api/files/1/request-appointment" \
  -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"provider_branch_id":1,"service_date":"2025-03-01","service_time":"10:00"}'
```

### Provider lead convert

```bash
curl -s -X POST "$BASE/api/provider-leads/1/convert" \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

---

## Routes summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/files | List files (paginated, filters) |
| POST | /api/files | Create file |
| GET | /api/files/{id} | Show file (full view) |
| PATCH | /api/files/{id} | Update file |
| DELETE | /api/files/{id} | Delete file |
| POST | /api/files/{id}/assign | Assign case to user |
| POST | /api/files/{id}/request-appointment | Request appointment |
| GET | /api/leads | List leads (paginated) |
| POST | /api/leads | Create lead |
| GET | /api/leads/{id} | Show lead |
| PATCH | /api/leads/{id} | Update lead |
| DELETE | /api/leads/{id} | Delete lead |
| GET | /api/provider-leads | List provider leads (paginated) |
| POST | /api/provider-leads | Create provider lead |
| GET | /api/provider-leads/{id} | Show provider lead |
| PATCH | /api/provider-leads/{id} | Update provider lead |
| DELETE | /api/provider-leads/{id} | Delete provider lead |
| POST | /api/provider-leads/{id}/convert | Convert action |
| GET | /api/providers | List providers (paginated) |
| POST | /api/providers | Create provider |
| GET | /api/providers/{id} | Show provider |
| PATCH | /api/providers/{id} | Update provider |
| DELETE | /api/providers/{id} | Delete provider |

All of the above require `auth:sanctum` and respect existing policies (viewAny, view, create, update, delete).
