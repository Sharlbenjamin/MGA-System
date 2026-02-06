# File View Performance Optimizations

## Summary

Optimizations were applied to all File RelationManagers to fix slow TTFB (~13s) caused by N+1 queries, per-row relation access, and heavy form selects. **No functionality or UI was changed**; only queries and loading were optimized.

---

## 1. What Changed Per RelationManager

### AppointmentsRelationManager
- **Why it was slow:** Columns use `providerBranch.branch_name`, and the Distance column uses `$record->file` and `$record->file->address`, causing N+1. Create modal loaded all fileBranches() on open.
- **Changes:** Eager load `['providerBranch', 'file']`. Explicit `select(['id', 'file_id', 'provider_branch_id', 'service_date', 'service_time', 'status'])`. Create modal provider branch Select: `searchable()->preload(false)` with `getSearchResultsUsing` (same filter as fileBranches: service_type, city, province, status=Active) and `getOptionLabelUsing` so options load on search only. Form Select `relationship('providerBranch', 'branch_name')->preload(false)`. Pagination 10.

### CommentsRelationManager
- **Why it was slow:** Column `user.name` triggered one query per row.
- **Changes:** Eager load `['user']`. Explicit `select(['id', 'file_id', 'user_id', 'content', 'created_at'])`. Pagination 10.

### TaskRelationManager
- **Why it was slow:** Columns `user.name` and `doneBy.name` caused N+1; form Select loaded all users.
- **Changes:** Eager load `['user', 'doneBy']`. Explicit `select(['id', 'file_id', 'user_id', 'title', 'description', 'due_date', 'is_done', 'done_by', 'department'])`. User Select `searchable()->preload(false)`. Pagination 10.

### BillRelationManager
- **Why it was slow:** Column `file.patient.client.company_name` and actions using `$record->file->patient` caused deep N+1.
- **Changes:** `modifyQueryUsing` to eager load `['file.patient.client']`. `defaultPaginationPageOption(10)`.

### InvoiceRelationManager
- **Why it was slow:** Actions (e.g. generate) use `$record->file`; no relation columns in table but same record used in modals.
- **Changes:** `modifyQueryUsing` to eager load `['file']`. `defaultPaginationPageOption(10)`. Kept existing `defaultSort('created_at', 'desc')`.

### GopRelationManager
- **Why it was slow:** Column `file.patient.client.company_name` and actions using `$record->file->patient` caused N+1.
- **Changes:** `modifyQueryUsing` to eager load `['file.patient.client']`. `defaultPaginationPageOption(10)`.

### MedicalReportRelationManager
- **Why it was slow:** Column `file.patient.client.company_name` and export action using `$record->file->providerBranch?->provider?->name` and `$record->file->patient` caused N+1.
- **Changes:** `modifyQueryUsing` to eager load `['file.patient.client', 'file.providerBranch.provider']`. `defaultPaginationPageOption(10)`.

### PrescriptionRelationManager
- **Why it was slow:** Export action uses `$record->file->patient` and `$record->file->mga_reference`.
- **Changes:** `modifyQueryUsing` to eager load `['file.patient']`. `defaultPaginationPageOption(10)`.

### PatientRelationManager
- **Why it was slow:** Column `client.company_name` triggered an extra query for the single patient row.
- **Changes:** `modifyQueryUsing` to eager load `['client']`. No pagination change (single row).

### AssignmentsRelationManager
- **Why it was slow:** Columns `user.name` and `assignedBy.name` caused N+1; Assign form used `User::pluck()` loading all users on mount.
- **Changes:** Eager load `['user', 'assignedBy']`. Explicit `select(['id', 'file_id', 'user_id', 'assigned_by_id', 'assigned_at', 'unassigned_at', 'is_primary'])`. User Select `searchable()->preload(false)` + `getSearchResultsUsing` / `getOptionLabelUsing`. Pagination 10.

### BankAccountRelationManager
- **Why it was slow:** Country filter uses `relationship('country', 'name')`; form Select preloaded all countries.
- **Changes:** `modifyQueryUsing` to eager load `['country']`. Country Select: `preload(false)` added. `defaultPaginationPageOption(10)`.

### ActivityLogRelationManager
- **Why it was slow:** Column `user.name` caused N+1.
- **Changes:** Eager load `['user']`. Explicit `select(['id', 'user_id', 'action', 'changes', 'created_at', 'subject_type', 'subject_id', 'subject_reference'])`. Already had `paginated([10, 25, 50])` (default 10).

---

## 2. Performance Logging (PERF_LOG)

- **Config:** `config('app.perf_log')` driven by env `PERF_LOG` (default `false`). Set `PERF_LOG=true` in `.env` to enable.
- **AppServiceProvider:** Registers `DB::listen` when `perf_log` is true. On the File view route it increments a request-level query count and logs any query with execution time > 200ms (route name must contain `filament`, `files`, and `view`).
- **FileViewPerfLoggingMiddleware:** Sets request start time and query count at start for the File view route; in `terminate()` logs request duration (ms) and total query count for that route. Only runs when `PERF_LOG` is true; no log spam in production when disabled.

---

## 3. Verification Checklist

After deployment or local testing:

- [ ] **TTFB:** Compare main document TTFB before/after in Browser Network (expect a clear drop from ~13s).
- [ ] **Query count:** With `PERF_LOG=true`, open a File show page and check `storage/logs/laravel.log` for the "File view request" line; note `query_count` and `duration_ms`. Compare with previous run or enable Laravel Debugbar to confirm fewer queries.
- [ ] **No repeated queries:** Ensure no duplicate N+1 patterns (e.g. same relation loaded per row); Filament tables should issue one main query plus one query per eager-loaded relation (or batched), not per row.
- [ ] **RelationManagers unchanged:** Each tab (Appointments, Comments, Tasks, Bills, Invoices, GOP, Medical Reports, Prescriptions, Patient, Assignments, Bank Accounts, Activity log) shows the same columns, filters, and data.
- [ ] **Actions:** Create, Edit, Delete, View, Upload, Generate, etc. work as before for each RelationManager.
- [ ] **Pagination:** Tables default to 10 rows per page where we set it; changing to 25/50/etc. still works.
- [ ] **Assignments “Assign to employee”:** Modal opens; searching users returns results; selecting and submitting assigns correctly.
- [ ] **Task create/edit:** User dropdown is searchable and loads options on search; form still validates and saves.
- [ ] **Appointments “New Appointment”:** Provider branch dropdown loads options on search (same filtered list as before); create works.

---

## 4. Optional Index Improvements (not required for functionality)

These indexes are **not required** for the optimizations above to work. Add only if you still see slow queries (e.g. PERF_LOG or DB slow-query log) and table sizes justify them.

- **files:** `(patient_id)`, `(status)`, or `(provider_branch_id, service_date)` for list/view filters and joins.
- **appointments:** `(file_id, service_date)` or `(provider_branch_id, file_id)` for listing per file or per branch.
- **bills / invoices / gops:** `(file_id)` if not already present as FK and EXPLAIN shows full table scans.
- **activity_logs:** `(subject_type, subject_id)` for the morph relation used by the Activity log RelationManager.
- **file_assignments:** `(file_id, unassigned_at)` if filtering active assignments is slow.

Run migrations only after measuring; avoid adding indexes that duplicate existing ones.

---

## 5. Files Touched

- `app/Filament/Resources/FileResource/RelationManagers/AppointmentsRelationManager.php`
- `app/Filament/Resources/FileResource/RelationManagers/CommentsRelationManager.php`
- `app/Filament/Resources/FileResource/RelationManagers/TaskRelationManager.php`
- `app/Filament/Resources/FileResource/RelationManagers/BillRelationManager.php`
- `app/Filament/Resources/FileResource/RelationManagers/InvoiceRelationManager.php`
- `app/Filament/Resources/FileResource/RelationManagers/GopRelationManager.php`
- `app/Filament/Resources/FileResource/RelationManagers/MedicalReportRelationManager.php`
- `app/Filament/Resources/FileResource/RelationManagers/PrescriptionRelationManager.php`
- `app/Filament/Resources/FileResource/RelationManagers/PatientRelationManager.php`
- `app/Filament/Resources/FileResource/RelationManagers/AssignmentsRelationManager.php`
- `app/Filament/Resources/FileResource/RelationManagers/BankAccountRelationManager.php`
- `app/Filament/RelationManagers/ActivityLogRelationManager.php`
- `config/app.php` (perf_log config)
- `app/Providers/AppServiceProvider.php` (PERF_LOG + DB::listen)
- `app/Http/Middleware/FileViewPerfLoggingMiddleware.php` (new)
- `bootstrap/app.php` (middleware registration)
