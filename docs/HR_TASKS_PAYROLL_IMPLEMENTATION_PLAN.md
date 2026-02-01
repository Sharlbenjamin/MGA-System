# MGA System — HR / Tasks / Payroll Implementation Plan (Repo-Grounded)

**Generated:** Implementation plan for Case Assignment, Tasks + Notifications, Provider Onboarding, HR, Attendance/Shifts, Payroll, and Reporting.

---

## 1. Executive Summary

**What exists**

- **File/Case status**: `files.status` is a string, default `"New"` (`database/migrations/2025_02_27_165947_create_files_table.php` line 21). No enum; statuses used in UI/widgets: New, Handling, Available, Confirmed, Requesting GOP, Assisted, Hold, Waiting MR, Refund, Void, Cancelled (e.g. `app/Filament/Resources/PatientResource/RelationManagers/FileRelationManager.php` line 52; `app/Filament/Widgets/FileStatsOverview.php` lines 100, 106; `app/Filament/Widgets/CasesPerMonthStatus.php` line 47).
- **Tasks**: `Task` model with `user_id`, `file_id`, `taskable_type`/`taskable_id`, `department`, `is_done`, `done_by` (`app/Models/Task.php` lines 14–17; `database/migrations/2025_03_19_201653_create_tasks_table.php`). `TaskResource` exists but is hidden (`app/Filament/Resources/TaskResource.php` lines 21–24). File tasks via `TaskRelationManager` on FileResource (`app/Filament/Resources/FileResource/RelationManagers/TaskRelationManager.php`). Taskable: Lead, ProviderLead, ProviderBranch, Patient, Client, Provider (`app/Models/*.php` morphMany Task). **File has no `reference` attribute** — only `mga_reference` and `name`; TaskResource form uses `relationship('file', 'reference')` (`app/Filament/Resources/TaskResource.php` line 40) — **bug**.
- **Notifications**: Filament database notifications enabled (`app/Providers/Filament/AdminPanelProvider.php` line 96: `->databaseNotifications()`). `notifications` table exists (`database/migrations/2025_03_06_160609_create_notifications_table.php`). `NotificationResource` for admin list/edit (`app/Filament/Resources/NotificationResource.php`). Docs reference `NotificationService` and `sendToDatabase`; **`app/Services/NotificationService.php` NOT FOUND**.
- **Users/roles**: `User` with Spatie `HasRoles`, `team_id`; fillable: name, email, password, team_id, smtp_*, signature_image (`app/Models/User.php` lines 29–36). `UserSignature`: name, job_title, department, work_phone (`app/Models/UserSignature.php`; `database/migrations/2025_02_24_170244_create_user_signatures_table.php`). No Employee table; no DOB, national_id, salary, start_date, contract, social_insurance, photo_id, bank_account.
- **Policies**: `FilePolicy`, `ProviderPolicy`, `UserPolicy`, etc. (`app/Policies/`). **TaskPolicy NOT FOUND**. Permission names: `view/create/edit/delete {Resource}` (`database/seeders/PermissionSeeder.php`).
- **Provider creation**: `ProviderResource` with `CreateProvider` page; no `afterCreate`, no observer, no event (`app/Filament/Resources/ProviderResource/Pages/CreateProvider.php`). **Provider onboarding task array NOT FOUND**.
- **Observers/Events/Listeners**: **`app/Observers`**, **`app/Events`**, **`app/Listeners` NOT FOUND**. `app/Providers/EventServiceProvider.php` NOT FOUND (Laravel 11 style).
- **Enums**: **`app/Enums` NOT FOUND**.
- **Scheduled jobs**: Only `clean:temp-zips` in `routes/console.php`. No monthly bonus/payroll job.

**What is missing**

Case assignment (traceable history), “My Tasks” page + task notifications with “Open Task” deep link, provider onboarding task templates + auto-create on Provider created, full Employee model + hierarchy + permissions, attendance/shifts/planning (Shift → employees visibility), payroll (salary + bonus with your rules), bonus settings entity + UI, reporting (monthly KPIs), TaskPolicy, and real-time/polling if desired.

---

## 2. Gap Analysis (Grouped)

### 2.1 Case Assignment

| Item | Status | Location / Note |
|------|--------|-----------------|
| File assigned to user/employee | **NOT FOUND** | `files` table has no `assigned_to` / `assigned_by`; `database/migrations/2025_02_27_165947_create_files_table.php` |
| Assignment history (who, when) | **NOT FOUND** | No `file_assignments` or equivalent table |
| CaseAssignmentService | **NOT FOUND** | No `app/Services/CaseAssignmentService.php` |
| UI to assign case to employee | **NOT FOUND** | FileResource/ViewFile has no assignment widget/action |

**Required**: `file_assignments` table (file_id, user_id, assigned_by_id, assigned_at, unassigned_at, is_primary?), `CaseAssignmentService`, migration, Filament action/RelationManager for “Assign case” and history.

---

### 2.2 Tasks + Persistent Notifications

| Item | Status | Location / Note |
|------|--------|-----------------|
| Task model + table | **EXISTS** | `app/Models/Task.php`, `database/migrations/2025_03_19_201653_create_tasks_table.php` |
| Task status (workflow) | **MISSING** | Only `is_done` boolean; no status enum (Pending, In Progress, Completed, etc.) |
| Task priority | **MISSING** | No priority column |
| Task “Linked To” (taskable) | **EXISTS** | `taskable_type` / `taskable_id`; TaskResource form uses wrong label and options (e.g. Branch vs ProviderBranch) |
| Optional FKs for reporting | **MISSING** | No nullable gop_id, bill_id, invoice_id, patient_id, etc. on tasks (file_id exists) |
| My Tasks page (filter assigned_to = me) | **MISSING** | TaskResource hidden; no dedicated “My Tasks” page with default filter |
| Persistent notification + “Open Task” | **PARTIAL** | Panel has `databaseNotifications()`; no task-specific notification on assign/update with action URL to task |
| NotificationService | **NOT FOUND** | Referenced in NOTIFICATION_SERVICE_GUIDE.md; `app/Services/NotificationService.php` missing |
| TaskPolicy | **NOT FOUND** | No `app/Policies/TaskPolicy.php` |

**Required**: Add task `status` (and optionally keep `is_done` for backward compat or migrate), `priority`, optional FKs; fix TaskResource form (`file` relationship: use `mga_reference` or `name`); “My Tasks” page with default filter `user_id = auth()->id()`; on task assign/update send Filament database notification with “Open Task” action to task view; optionally (re)introduce NotificationService for task notifications; add TaskPolicy.

---

### 2.3 Provider Onboarding Task Array

| Item | Status | Location / Note |
|------|--------|-----------------|
| Provider created hook | **NOT FOUND** | No observer/event/listener; `CreateProvider` does not call any service (`app/Filament/Resources/ProviderResource/Pages/CreateProvider.php`) |
| Task templates for Provider | **NOT FOUND** | No `task_templates` table or model |
| ProviderOnboardingTasksService | **NOT FOUND** | No service to create tasks from templates |

**Required**: `task_templates` table (e.g. title, description, department, trigger_entity_type = 'Provider', order, is_active); ProviderObserver or `ProviderCreated` event + listener calling `ProviderOnboardingTasksService::createOnboardingTasks(Provider $provider)`; register observer in `AppServiceProvider` or `bootstrap/app.php` if using discovery.

---

### 2.4 HR (Employees / Hierarchy)

| Item | Status | Location / Note |
|------|--------|-----------------|
| Employees table (full spec) | **MISSING** | Users + UserSignature only; no DOB, national_id, basic_salary, start_date, signed_contract path, social_insurance_number, photo_id path, department, title, bank_account |
| Job titles + hierarchy | **MISSING** | UserSignature has string `job_title`; no JobTitle model, no level/multiplier |
| Manager / hierarchy | **MISSING** | No manager_id on users or employees |
| Permissions (managers assign to lower titles) | **MISSING** | No policy or gate “can assign task to this user based on title level” |
| EmployeeResource | **MISSING** | UserResource exists; no dedicated Employee resource with full fields |

**Required**: Either extend `users` + add `employees` detail table, or single `employees` table with user_id nullable; add `job_titles` (name, code, level, department, bonus_multiplier); add `manager_id` and hierarchy checks in TaskPolicy/EmployeePolicy.

---

### 2.5 Attendance / Shifts / Planning

| Item | Status | Location / Note |
|------|--------|-----------------|
| Shifts table | **NOT FOUND** | No shifts migration or model |
| ShiftSchedule (planning per day) | **NOT FOUND** | No shift_schedules table |
| Attendance (actual check-in/out) | **NOT FOUND** | No attendances table |
| Shift → employees visibility | **NOT FOUND** | No resource/page showing “which shift has which employees” |
| Employee → shifts visibility | **NOT FOUND** | No “my schedule” relation manager |

**Required**: `shifts` table; `shift_schedules` (e.g. date, shift_id, user_id, location_type); `attendances` (user_id, date, check_in, check_out, status); ShiftResource, ShiftScheduleResource or calendar page, AttendanceResource; RelationManagers: “Upcoming schedules” on Shift (employees per day), “My schedule” on Employee/User (shifts per day).

---

### 2.6 Payroll (Salary + Bonus + Settings)

| Item | Status | Location / Note |
|------|--------|-----------------|
| Salary per month (locked) | **NOT FOUND** | No salaries table |
| Bonus per month (with rules) | **NOT FOUND** | No bonuses table |
| BonusSettings / PayrollSettings | **NOT FOUND** | No settings entity for rate, thresholds, cap, multipliers |
| BonusCalculationService | **NOT FOUND** | No service implementing your formula |
| Active case definition in code | **EXISTS (widget logic)** | FileStatsOverview uses `status = 'Assisted'`; CasesPerMonthStatus uses `status = 'Assisted'`. **Waiting MR** not grouped as “active” in those widgets; your rule is status IN ['Assisted','Waiting MR'] — must be enforced in bonus logic |

**Required**: `salaries` table (employee_id, year, month, base_salary, adjustments, deductions, net_salary, is_locked, locked_at, locked_by_id); `bonuses` table (employee_id, year, month, active_cases_count, rate, multiplier, thresholds_applied, cap_applied, bonus_before_cap, bonus_after_cap, calculated_at, calculated_by_id, locked_at, locked_by_id); `payroll_settings` or `bonus_settings` (rate_per_active_case, department_min_active_cases, employee_min_active_cases, max_bonus_cap_percent, etc.); BonusCalculationService implementing: active = status IN ['Assisted','Waiting MR'], baseBonus = active_cases_assigned * rate, multiplier by job title, cap by % base salary; store audit fields on Bonus.

---

### 2.7 Reporting (Monthly KPIs)

| Item | Status | Location / Note |
|------|--------|-----------------|
| Payroll report (salary + bonus per employee) | **NOT FOUND** | No report page |
| Attendance summary (hours/days) | **NOT FOUND** | No attendance model yet |
| Productivity (active cases handled) | **NOT FOUND** | No report; depends on file_assignments + status |
| Productivity (tasks completed) | **NOT FOUND** | No report aggregating completed tasks per user/month |

**Required**: Report pages/widgets: Payroll (salary + bonus by employee/month), Attendance summary (hours/days clocked), Productivity (active cases per employee from assignments + status IN ['Assisted','Waiting MR']), Tasks completed per employee/month.

---

### 2.8 Permissions / Policies

| Item | Status | Location / Note |
|------|--------|-----------------|
| TaskPolicy | **NOT FOUND** | Not in `app/Policies/` |
| EmployeePolicy | **NOT FOUND** | No Employee model yet |
| “Managers can assign to lower titles” | **NOT FOUND** | No hierarchy check in code |
| Permission seeder for Task/Employee/Bonus | **MISSING** | PermissionSeeder has no Task, Employee, Bonus, etc. (`database/seeders/PermissionSeeder.php`) |

**Required**: TaskPolicy (view/create/update/delete + assign only to subordinates if hierarchy enforced), EmployeePolicy, add permissions and seed them.

---

### 2.9 Real-time Updates

| Item | Status | Location / Note |
|------|--------|-----------------|
| Task list polling / live updates | **NOT FOUND** | TaskResource table has no `->poll()` |
| Broadcasting for tasks | **NOT FOUND** | No Laravel Broadcasting usage for tasks |

**Optional**: Add `->poll('30s')` on My Tasks table; or implement broadcast for task updates.

---

## 3. Missing Items — Full Specs

### 3.1 Case Assignment

#### FileAssignment (Model + Migration)
- **What**: Model + migration for traceable case assignment.
- **Why**: Track who is assigned to each file and who assigned them, for bonus and audits.
- **Where**: `app/Models/FileAssignment.php`, migration `create_file_assignments_table`.
- **Schema**:
  - `id`, `file_id` (FK files), `user_id` (FK users), `assigned_by_id` (FK users), `assigned_at` (timestamp), `unassigned_at` (nullable), `is_primary` (boolean, default true).
  - Indexes: `(file_id, is_primary)`, `(user_id, assigned_at)`, `(file_id, user_id, assigned_at)`.
- **Relationships**: FileAssignment belongsTo File, User (assignee), User (assignedBy). File hasMany FileAssignment; User hasMany FileAssignment.
- **UI**: FileResource ViewFile or RelationManager “Assignments” with table (assignee, assigned_by, assigned_at, unassigned_at); action “Assign to employee” (opens modal with user select and optional “Unassign previous”).
- **Checklist**: [ ] Migration, [ ] Model + relations, [ ] FileAssignmentResource or RelationManager, [ ] “Assign” action that creates FileAssignment and optionally sets previous row unassigned_at.

#### CaseAssignmentService
- **What**: Service for assigning/unassigning and querying history.
- **Why**: Single place for rules (e.g. one primary assignee per file, history).
- **Where**: `app/Services/CaseAssignmentService.php`.
- **Methods**: `assign(File $file, User $user, User $assignedBy)`, `unassign(FileAssignment $assignment)`, `getAssignmentsForFile(File $file)`, `getActiveAssignmentsForUser(User $user, $month, $year)` (for bonus).
- **Checklist**: [ ] Create class, [ ] Implement assign/unassign and history, [ ] Use in Filament action.

---

### 3.2 Tasks + Notifications

#### Task status & priority (Migration + Model)
- **What**: Add `status` (string or enum) and `priority` (string or enum) to tasks.
- **Why**: Workflow and ordering; “Open Task” in notifications.
- **Where**: New migration `add_status_and_priority_to_tasks_table`; `app/Models/Task.php`.
- **Schema**: `status` default 'pending'; `priority` default 'normal'. Optional: `completed_at`, `assigned_by_id`, `assigned_at`.
- **Enums**: e.g. TaskStatus: Pending, In Progress, On Hold, Completed, Cancelled; TaskPriority: Low, Normal, High, Urgent.
- **Checklist**: [ ] Migration, [ ] Enums (or validated strings), [ ] Model fillable/casts, [ ] TaskResource form/table.

#### Optional FKs on tasks
- **What**: Nullable FK columns for reporting/filtering: e.g. `gop_id`, `bill_id`, `invoice_id`, `patient_id`, `client_id`, `provider_id`, `provider_branch_id`, `medical_report_id`, `country_id`, `city_id`.
- **Why**: Reporting and filters without always joining through taskable.
- **Where**: New migration `add_optional_entity_fks_to_tasks_table`; Task model.
- **Checklist**: [ ] Migration, [ ] Model relations, [ ] TaskResource “Linked To” + optional filters.

#### My Tasks page
- **What**: Filament page or resource list with default filter “Assigned to me”.
- **Why**: UX requirement: employees see their tasks in one place.
- **Where**: `app/Filament/Pages/MyTasks.php` (custom page with table) or TaskResource with default filter and visible in nav.
- **UI**: Table: title, linked to (taskable + file), due_date, status, priority; filter “Mine” (user_id = auth()->id()); action “Open” → task edit/view URL.
- **Checklist**: [ ] Page or unhide TaskResource + default filter, [ ] Add to nav, [ ] Link from dashboard if needed.

#### Task notification with “Open Task”
- **What**: When a task is assigned or updated, send Filament database notification with action linking to task.
- **Why**: Persistent visibility and deep link.
- **Where**: Task model observer or Filament TaskResource/RelationManager `afterCreate`/`afterSave`; or NotificationService.
- **Implementation**: `Notification::make()->title('...')->body('...')->actions([Action::make('open')->label('Open Task')->url(route('filament.admin.resources.tasks.edit', ['record' => $task]))])->sendToDatabase($task->user)`.
- **Checklist**: [ ] Create notification call on assign/update, [ ] Use `sendToDatabase($user)` and ensure panel uses databaseNotifications(), [ ] Add TaskPolicy so assignee can view.

#### TaskPolicy
- **What**: Policy for Task.
- **Where**: `app/Policies/TaskPolicy.php`.
- **Rules**: viewAny/view/create/update/delete; optionally “can assign to” only if assignee’s job title level <= current user’s (when hierarchy exists).
- **Checklist**: [ ] Create TaskPolicy, [ ] Register in AuthServiceProvider if not auto-discovered, [ ] Add permissions (view Task, etc.) and seed.

#### TaskResource form fix
- **What**: Fix file relationship display.
- **Where**: `app/Filament/Resources/TaskResource.php` line 40.
- **Change**: Use `relationship('file', 'mga_reference')` (or a computed attribute like `name`) instead of `'reference'`.
- **Checklist**: [ ] Fix Select/relationship, [ ] Add taskable_type options: File, Provider, ProviderBranch, Patient, Client, Lead, ProviderLead, MedicalReport, Gop, etc., and show “Linked To” as clickable link in table.

---

### 3.3 Provider Onboarding

#### TaskTemplate (Model + Migration)
- **What**: Template for auto-created tasks.
- **Why**: Define checklist when e.g. Provider is created.
- **Where**: `app/Models/TaskTemplate.php`, migration `create_task_templates_table`.
- **Schema**: `id`, `title`, `description` (nullable), `department`, `trigger_entity_type` (e.g. 'Provider'), `sort_order`, `is_active`, `assign_to_role_or_title` (nullable), timestamps.
- **Relationships**: None required for template itself; service will create Task from template.
- **Checklist**: [ ] Migration, [ ] Model, [ ] TaskTemplateResource (admin), [ ] Seeder with default Provider onboarding templates.

#### ProviderOnboardingTasksService
- **What**: Creates tasks from templates when Provider is created.
- **Where**: `app/Services/ProviderOnboardingTasksService.php`.
- **Method**: `createOnboardingTasks(Provider $provider): void` — get templates where trigger_entity_type = 'Provider' and is_active, create Task for each (taskable = $provider, assign to configured role/title or leave unassigned).
- **Checklist**: [ ] Create service, [ ] Call from Provider observer or ProviderCreated listener.

#### Provider created observer/event
- **What**: On Provider created, run onboarding task creation.
- **Where**: `app/Observers/ProviderObserver.php` (created) or `app/Events/ProviderCreated.php` + `app/Listeners/CreateProviderOnboardingTasks.php`.
- **Checklist**: [ ] Create Observer or Event+Listener, [ ] Register in AppServiceProvider boot (Observer::observe(ProviderObserver::class)) or subscribe listener, [ ] Call ProviderOnboardingTasksService.

---

### 3.4 HR (Employees, Hierarchy)

#### Employee data (Migration + Model)
- **What**: Full employee fields as specified.
- **Why**: HR and payroll need name, DOB, national_id, phone, basic_salary, start_date, contract, social_insurance, photo_id, department, title, bank_account.
- **Where**: Either (a) `employees` table with `user_id` (FK users) and all HR fields, or (b) add columns to `users` + optional `employee_profiles` for extras. Recommended: `employees` table (user_id nullable if you ever have employees without login).
- **Schema (employees table)**:
  - `id`, `user_id` (FK users nullable), `name`, `date_of_birth` (date nullable), `national_id` (string nullable), `phone` (string nullable), `basic_salary` (decimal), `start_date` (date), `signed_contract_path` (string nullable), `signed_contract` (boolean default false), `social_insurance_number` (string nullable), `photo_id_path` (string nullable), `department` (string/enum), `job_title_id` (FK job_titles), `bank_account_id` (FK bank_accounts nullable or bank details as JSON), `manager_id` (FK employees nullable), `status` (enum: active, inactive), timestamps.
- **Relationships**: Employee belongsTo User, JobTitle, Employee (manager); hasMany Employee (subordinates), FileAssignment, Attendance, etc.
- **UI**: EmployeeResource with form (all fields), file upload for contract and photo_id; table with key columns.
- **Checklist**: [ ] Migration, [ ] Model, [ ] EmployeeResource, [ ] RelationManagers (e.g. assignments, attendance).

#### JobTitle (Model + Migration)
- **What**: Job titles with level and bonus multiplier.
- **Where**: `app/Models/JobTitle.php`, migration `create_job_titles_table`.
- **Schema**: `id`, `name`, `code` (unique), `level` (int, higher = more senior), `department` (string/enum), `bonus_multiplier` (decimal, e.g. 1.0, 1.5, 2.0), timestamps.
- **Relationships**: JobTitle hasMany Employee.
- **Checklist**: [ ] Migration, [ ] Model, [ ] JobTitleResource, [ ] Seeder: Operation Team Member (1.0), Team Leader / Country Manager (1.5), Operation Manager (2.0).

#### EmployeePolicy
- **What**: Who can view/edit employees; managers see subordinates.
- **Where**: `app/Policies/EmployeePolicy.php`.
- **Checklist**: [ ] Create, [ ] Register, [ ] Add permissions.

---

### 3.5 Attendance / Shifts / Planning

#### Shift (Model + Migration)
- **What**: Shift definition (name, start/end time).
- **Where**: `app/Models/Shift.php`, migration `create_shifts_table`.
- **Schema**: `id`, `name`, `start_time`, `end_time`, `break_minutes` (optional), timestamps.
- **Checklist**: [ ] Migration, [ ] Model, [ ] ShiftResource.

#### ShiftSchedule (Model + Migration)
- **What**: Planning: who works which shift on which day.
- **Where**: `app/Models/ShiftSchedule.php`, migration `create_shift_schedules_table`.
- **Schema**: `id`, `user_id` (FK users), `shift_id` (FK shifts), `scheduled_date` (date), `location_type` (enum: on_site, remote, hybrid), optional `notes`, timestamps. Unique (user_id, scheduled_date) or allow multiple per day if needed.
- **Relationships**: ShiftSchedule belongsTo User, Shift. Shift hasMany ShiftSchedule; User hasMany ShiftSchedule.
- **Checklist**: [ ] Migration, [ ] Model, [ ] ShiftScheduleResource or calendar page.

#### Attendance (Model + Migration)
- **What**: Actual check-in/out.
- **Where**: `app/Models/Attendance.php`, migration `create_attendances_table`.
- **Schema**: `id`, `user_id`, `date`, `check_in_at`, `check_out_at`, `status` (present, absent, leave, etc.), `notes` nullable, timestamps.
- **Relationships**: Attendance belongsTo User.
- **Checklist**: [ ] Migration, [ ] Model, [ ] AttendanceResource, [ ] Widget or action for “Check in” / “Check out”.

#### Shift → employees visibility
- **Where**: ShiftResource RelationManager “Schedules” or dedicated “ShiftSchedule” resource with filter by shift + date; table: date, employee, shift.
- **Checklist**: [ ] RelationManager on Shift: “Upcoming schedules” (employees per day), [ ] Or calendar page: week/month view, shift → list of employees.

#### Employee → shifts visibility
- **Where**: EmployeeResource or UserResource RelationManager “My schedule” (ShiftSchedule for this user).
- **Checklist**: [ ] RelationManager “Schedule” on Employee/User showing ShiftSchedule records (shifts per day).

---

### 3.6 Payroll (Salary, Bonus, Settings)

#### Salary (Model + Migration)
- **What**: Monthly salary record (lockable).
- **Where**: `app/Models/Salary.php`, migration `create_salaries_table`.
- **Schema**: `id`, `employee_id` (FK employees), `year`, `month`, `base_salary`, `adjustments`, `deductions`, `net_salary`, `is_locked`, `locked_at`, `locked_by_id`, timestamps. Unique (employee_id, year, month).
- **Checklist**: [ ] Migration, [ ] Model, [ ] SalaryResource with lock action.

#### Bonus (Model + Migration)
- **What**: Monthly bonus with full audit fields.
- **Where**: `app/Models/Bonus.php`, migration `create_bonuses_table`.
- **Schema**: `id`, `employee_id`, `year`, `month`, `active_cases_count`, `rate_per_active_case`, `multiplier`, `department_min_active_cases`, `employee_min_active_cases`, `max_bonus_cap_percent`, `bonus_before_cap`, `bonus_after_cap`, `calculated_at`, `calculated_by_id`, `is_locked`, `locked_at`, `locked_by_id`, timestamps.
- **Checklist**: [ ] Migration, [ ] Model, [ ] BonusResource.

#### PayrollSettings / BonusSettings (Model + Migration)
- **What**: Configurable bonus and qualification settings.
- **Where**: `app/Models/PayrollSetting.php` or single-row `bonus_settings` table; migration `create_bonus_settings_table` or `create_payroll_settings_table`.
- **Schema**: Single row or key-value. Columns: `rate_per_active_case` (default 10), `department_min_active_cases` (80), `employee_min_active_cases` (20), `max_bonus_cap_percent` (0.30). Multipliers can be on JobTitle (bonus_multiplier) or here as JSON.
- **Checklist**: [ ] Migration, [ ] Model (or settings helper), [ ] Filament settings page (see §4).

#### BonusCalculationService
- **What**: Implements bonus formula and writes Bonus record with audit fields.
- **Where**: `app/Services/BonusCalculationService.php`.
- **Logic**: For each employee (with job_title) in Operations: (1) Count active cases assigned in month (FileAssignment + file status IN ['Assisted','Waiting MR']). (2) Get department total active cases (all files with status IN ['Assisted','Waiting MR'] in that month — or per department if you track department on file). (3) Apply qualification: if department_total < department_min_active_cases or employee_active_cases < employee_min_active_cases → bonus = 0. (4) baseBonus = employee_active_cases * rate_per_active_case; multiplier = job_title.bonus_multiplier; bonus_before_cap = baseBonus * multiplier. (5) cap = employee base_salary * max_bonus_cap_percent; bonus_after_cap = min(bonus_before_cap, cap). (6) Save Bonus with all audit fields.
- **Checklist**: [ ] Create service, [ ] Implement steps above, [ ] Use PayrollSettings and JobTitle.bonus_multiplier, [ ] Optional: scheduled job that runs monthly.

#### AttendanceCalculationService
- **What**: Aggregates attendance (hours/days) for a user/period.
- **Where**: `app/Services/AttendanceCalculationService.php`.
- **Checklist**: [ ] Create, [ ] Methods: getHoursForUser($userId, $month, $year), getDaysForUser(...).

---

### 3.7 Reporting

#### Payroll report page
- **Where**: `app/Filament/Pages/Reports/PayrollReport.php`.
- **Content**: Table/export: employee, month, year, salary paid, bonus paid, total. Filters: month, year, employee.
- **Checklist**: [ ] Page, [ ] Query Salary + Bonus, [ ] Optional export.

#### Attendance summary report
- **Where**: `app/Filament/Pages/Reports/AttendanceSummaryReport.php`.
- **Content**: By employee and period: days present, total hours (from Attendance).
- **Checklist**: [ ] Page, [ ] Use AttendanceCalculationService.

#### Productivity report
- **Where**: `app/Filament/Pages/Reports/ProductivityReport.php`.
- **Content**: Active cases handled (from FileAssignment + status IN ['Assisted','Waiting MR']) and tasks completed per employee per month.
- **Checklist**: [ ] Page, [ ] Queries for both metrics.

---

## 4. Bonus Settings + UI Help Text (Must Include)

**Settings entity**: e.g. `PayrollSetting` or single-row table `bonus_settings`.

For **each** setting field provide: **label**, **helperText** (written recommendation to remember what to put), **validation rules**, **example value**, **notes/warnings**.

| Field | Label | helperText (recommendation to remember what to put) | Validation rules | Example value | Notes / warnings |
|-------|--------|-----------------------------------------------------|------------------|---------------|-------------------|
| rate_per_active_case | Rate per active case | Amount paid per qualified active case (Assisted or Waiting MR). Used in bonus calculation. | numeric, min:0 | 10 | Currency from app settings. |
| department_min_active_cases | Department minimum active cases | Department must reach this many active cases in the month for any bonus to be paid. | integer, min:0 | 80 | Qualification gate. |
| employee_min_active_cases | Employee minimum active cases | Each employee must have at least this many assigned active cases to qualify for bonus. | integer, min:0 | 20 | Qualification gate. |
| max_bonus_cap_percent | Max bonus cap (% of base salary) | Bonus is capped at this fraction of the employee’s base salary (e.g. 0.30 = 30%). | numeric, min:0, max:1 | 0.30 | Stored as decimal; display as %. |

**Title multipliers**: Store on `job_titles.bonus_multiplier` (1.0, 1.5, 2.0) as in your rules. No separate settings table needed unless you want overrides.

**Filament**: One settings resource or custom Filament page with form that loads/saves single record; for each field use `->helperText('...')` (recommendation to remember what to put), `->rules([...])`, and place “Example: …” in description if needed.

---

## 5. Bonus Calculation Implementation

**BonusCalculationService** (pseudocode):

- Get settings: rate_per_active_case, department_min_active_cases, employee_min_active_cases, max_bonus_cap_percent.
- For the given month/year:
  - Department total active cases: count files with status IN ['Assisted','Waiting MR'] and (if needed) updated_at/created_at in month; or count from FileAssignment where file status IN ['Assisted','Waiting MR'] and assignment overlaps month.
  - If department_total < department_min_active_cases → skip bonus for all (or set 0).
  - For each employee in Operations with job_title:
    - active_cases_count = count of file assignments for this employee in month where file.status IN ['Assisted','Waiting MR'] (use FileAssignment.assigned_at/unassigned_at and file.status at snapshot or current).
    - If active_cases_count < employee_min_active_cases → bonus_after_cap = 0.
    - baseBonus = active_cases_count * rate_per_active_case.
    - multiplier = job_title.bonus_multiplier (1.0 / 1.5 / 2.0).
    - bonus_before_cap = baseBonus * multiplier.
    - cap = employee.basic_salary * max_bonus_cap_percent.
    - bonus_after_cap = min(bonus_before_cap, cap).
    - Save Bonus with: active_cases_count, rate, multiplier, thresholds (department_min, employee_min), cap %, bonus_before_cap, bonus_after_cap, calculated_at, calculated_by_id; optionally is_locked, locked_at, locked_by_id.

**Counting “assigned in month”**: Use `file_assignments` with assigned_at/unassigned_at; count a file for the employee if there exists an assignment row for that user and file with assigned_at <= end of month and (unassigned_at IS NULL or unassigned_at >= start of month). Then filter by file.status IN ['Assisted','Waiting MR'] (either current status or status at end of month if you store history).

---

## 6. Implementation Roadmap (Phases + Dependencies)

**Phase A — Foundation (employees, org, permissions)**  
1. JobTitle migration + model + resource + seeder (multipliers 1.0, 1.5, 2.0).  
2. Employees migration + model (all required fields including job_title_id, manager_id).  
3. EmployeeResource + EmployeePolicy; add permissions and seed.  
4. Optional: extend User with employee_id or keep Employee.user_id as single link.

**Phase B — Case assignment**  
1. FileAssignment migration + model.  
2. CaseAssignmentService.  
3. FileResource: Assignments RelationManager + “Assign to employee” action.

**Phase C — Tasks + notifications**  
1. Migration: add task status, priority, optional FKs (gop_id, bill_id, …).  
2. Task model + TaskResource updates; fix file relationship; add “Linked To” and optional filters.  
3. TaskPolicy + permissions.  
4. My Tasks page (default filter assigned_to = me).  
5. On task assign/update: send database notification with “Open Task” action.  
6. Optional: NotificationService for task notifications.

**Phase D — Provider onboarding**  
1. TaskTemplate migration + model + TaskTemplateResource + seeder.  
2. ProviderOnboardingTasksService.  
3. ProviderObserver (or ProviderCreated event + listener) → create onboarding tasks.

**Phase E — Attendance & shifts**  
1. Shifts migration + model + ShiftResource.  
2. ShiftSchedule migration + model; ShiftScheduleResource or calendar page.  
3. Attendances migration + model + AttendanceResource.  
4. Shift “Upcoming schedules” RelationManager (employees per day).  
5. Employee “Schedule” RelationManager (shifts per day).

**Phase F — Payroll (salary + bonus + settings)**  
1. BonusSettings/PayrollSettings migration + model + Filament settings page (with labels, helperText, validation, example, notes as above).  
2. Salaries migration + model + SalaryResource (lock/unlock).  
3. Bonuses migration + model + BonusResource.  
4. BonusCalculationService (using settings + JobTitle multipliers + FileAssignment + status IN ['Assisted','Waiting MR']).  
5. AttendanceCalculationService.  
6. Scheduled job: monthly bonus calculation (and optionally lock previous month).

**Phase G — Reporting**  
1. Payroll report page (salary + bonus per employee/month).  
2. Attendance summary report.  
3. Productivity report (active cases + tasks completed).

**Phase H — Optional**  
- Real-time: Task list polling or broadcasting.  
- File created: optional default operational tasks (similar to Provider onboarding).

---

## 7. Concrete Build Plan

### 7.1 Migrations (order)

1. `create_job_titles_table`  
2. `create_employees_table` (user_id, job_title_id, manager_id, all HR fields)  
3. `create_file_assignments_table`  
4. `add_status_and_priority_to_tasks_table` (+ optional assigned_by_id, assigned_at, completed_at)  
5. `add_optional_entity_fks_to_tasks_table` (gop_id, bill_id, invoice_id, patient_id, client_id, provider_id, provider_branch_id, medical_report_id, country_id, city_id)  
6. `create_task_templates_table`  
7. `create_shifts_table`  
8. `create_shift_schedules_table`  
9. `create_attendances_table`  
10. `create_bonus_settings_table` (or payroll_settings)  
11. `create_salaries_table`  
12. `create_bonuses_table`  

(Order respects FKs: job_titles before employees; employees before file_assignments, salaries, bonuses; users already exist.)

### 7.2 Models + relationships

- **JobTitle**: hasMany Employee.  
- **Employee**: belongsTo User, JobTitle, Employee (manager); hasMany FileAssignment, Attendance, ShiftSchedule, Salary, Bonus; hasMany Employee (subordinates).  
- **FileAssignment**: belongsTo File, User (assignee), User (assignedBy).  
- **Task**: existing + belongsTo (optional) Gop, Bill, Invoice, Patient, Client, Provider, ProviderBranch, MedicalReport, Country, City; keep taskable().  
- **TaskTemplate**: no relations.  
- **Shift**: hasMany ShiftSchedule.  
- **ShiftSchedule**: belongsTo User, Shift.  
- **Attendance**: belongsTo User.  
- **Salary**: belongsTo Employee, User (lockedBy).  
- **Bonus**: belongsTo Employee, User (calculatedBy, lockedBy).  
- **PayrollSetting / BonusSetting**: single row or key-value.

### 7.3 Filament Resources / RelationManagers / Pages / Widgets

- **Resources**: JobTitleResource, EmployeeResource, TaskTemplateResource, ShiftResource, ShiftScheduleResource (or embedded in calendar), AttendanceResource, SalaryResource, BonusResource, FileAssignment (or RelationManager only), PayrollSettings (custom page).  
- **RelationManagers**: File → Assignments (FileAssignment); Shift → Schedules (ShiftSchedule, employees per day); Employee → Schedule (ShiftSchedule); Employee → Assignments (FileAssignment); Employee → Salaries, Bonuses.  
- **Pages**: MyTasks (or unhide TaskResource + default filter); ShiftCalendar (week/month); Reports: PayrollReport, AttendanceSummaryReport, ProductivityReport; BonusSettings page.  
- **Widgets**: Optional dashboard widgets (e.g. my tasks count, my schedule today).

### 7.4 Policies + permissions

- TaskPolicy (view/create/edit/delete Task; optionally assign only to lower title).  
- EmployeePolicy (view/edit by manager/role).  
- Add to PermissionSeeder: Task, Employee, JobTitle, FileAssignment, Shift, ShiftSchedule, Attendance, Salary, Bonus, TaskTemplate.  
- Register policies in AuthServiceProvider if not auto-discovered.

### 7.5 Services

- **CaseAssignmentService**: assign, unassign, history, getActiveAssignmentsForUser(month, year).  
- **BonusCalculationService**: compute bonus for month/year using settings + FileAssignment + status IN ['Assisted','Waiting MR'] + JobTitle multiplier + cap; save Bonus with audit fields.  
- **ProviderOnboardingTasksService**: createOnboardingTasks(Provider $provider).  
- **AttendanceCalculationService**: hours/days per user/period.

### 7.6 Events / Observers

- **Provider created**: ProviderObserver::created(Provider $provider) or ProviderCreated event → CreateProviderOnboardingTasks listener → ProviderOnboardingTasksService::createOnboardingTasks($provider).  
- **File created** (optional): FileObserver::created → create default operational tasks from templates if you add trigger_entity_type = 'File'.  
- **Task assigned/updated**: Observer or Filament hook → send database notification with “Open Task” URL.

### 7.7 Scheduled jobs

- **Monthly bonus calculation**: e.g. `Schedule::job(new CalculateMonthlyBonusJob())->monthlyOn(1, '02:00')`; job loads settings, runs BonusCalculationService for previous month, optionally locks bonuses/salaries.  
- **CalculateMonthlyBonusJob**: `app/Jobs/CalculateMonthlyBonusJob.php`; dispatch or call BonusCalculationService for (year, month).

### 7.8 Monthly reports

- **Payroll report**: Page that lists Salary + Bonus by employee and month (and optionally exports).  
- **Attendance summary**: Page that uses AttendanceCalculationService and shows hours/days per employee/period.  
- **Productivity report**: Page that shows active cases handled (from FileAssignment + status) and tasks completed per employee per month.

---

*End of implementation plan. All claims are grounded in the repo paths and line numbers cited in §1–2.*
