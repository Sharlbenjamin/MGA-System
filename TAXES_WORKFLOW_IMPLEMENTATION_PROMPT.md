# MedGuard Taxes Workflow — Implementation Prompt

Use this document when extending the Taxes page, Excel export, zip export, and accountant reconciliation workflow. Related docs: [TAXES_EXPORT_ZIP Update Promt.md](./TAXES_EXPORT_ZIP%20Update%20Promt.md), [BACKFILL_DRIVE_DOCUMENTS_USAGE.md](./BACKFILL_DRIVE_DOCUMENTS_USAGE.md).

---

## ACCOUNTING COMPLIANCE & ATTACHMENT VALIDATION (HIGH PRIORITY)

The objective of the tax workflow is not only to export transactions but also to ensure that all accounting records are properly supported by documentation.

Before creating any new attachment validation workflow, inspect the existing Finance, Taxes, Transaction, Invoice, Bill, and File modules to determine whether attachment tracking already exists.

If attachment functionality already exists:

* Reuse and extend it.
* Do not create duplicate attachment systems.
* Do not create duplicate upload fields.
* Do not create duplicate storage structures.

If attachment functionality does not exist:

* Propose the safest implementation before writing code.

### ATTACHMENT VALIDATION RULES

The system should be able to identify transactions that are missing required supporting documents.

**Examples:**

**Incoming Transaction:**

* Bank transfer received
* Payment link payment received
* Cash deposit

**Recommended supporting documents:**

* Bank receipt
* Transfer confirmation
* Linked invoice(s)

**Outgoing Transaction:**

* Provider payment
* Refund
* Office expense
* Supplier payment

**Recommended supporting documents:**

* Bank receipt
* Transfer confirmation
* Linked bill(s)
* Supplier invoice, when applicable

### TAX REVIEW DASHBOARD

Within the existing Taxes page, add validation logic that identifies:

* Transactions missing attachments
* Transactions missing linked invoices
* Transactions missing linked bills
* Transactions partially reconciled
* Transactions fully reconciled

Each transaction should have a reconciliation status:

* Complete
* Missing Attachment
* Missing Invoice/Bill
* Partially Reconciled
* Needs Review

### PRE-EXPORT VALIDATION

Before generating the Excel report, the system should calculate:

* Total transactions
* Transactions with all required documents
* Transactions missing attachments
* Transactions missing invoice/bill links
* Transactions requiring review

The user should be able to immediately identify which records require attention before sending the tax report to an accountant.

### SAFETY REQUIREMENT

Before implementing any validation workflow:

1. Inspect the current Taxes page.
2. Inspect the Transaction model.
3. Inspect the Invoice model.
4. Inspect the Bill model.
5. Inspect any existing attachment/media/file upload functionality.

Then document:

* What already exists.
* What can be reused.
* What must be extended.
* What must NOT be duplicated.

Do not create new workflows until the existing implementation has been fully analyzed.

This will make Cursor first audit your existing Taxes workflow and attachment system, then extend it, while also creating an accountant-style reconciliation process to ensure every MedGuard bank transaction is backed by the correct invoice, bill, and receipt before export.

---

## EXISTING IMPLEMENTATION AUDIT (completed — do not duplicate)

> Generated from codebase inspection. Update this section if models or resources change materially.

### What already exists

| Area | Location | Notes |
|------|----------|--------|
| **Taxes page** | `app/Filament/Resources/TaxesResource.php`, `Pages/ListTaxes.php` | Custom list (no Eloquent model). Period selector widgets, invoice/bill tables, **Export Data** (Excel), **Send Tax Email**, **Export Zip**. No reconciliation status or pre-export validation counts yet. |
| **Tax export** | `app/Http/Controllers/TaxesExportController.php`, `app/Exports/TaxesModeExport.php` | Builds Excel payload by year/quarter; zip bundles invoice/bill/expense PDFs from Drive or local paths. |
| **Tax summary** | `app/Filament/Widgets/TaxSummaryWidget.php` | Invoice/bill/expense totals for period; admin-only. |
| **Transaction attachments** | `Transaction.attachment_path` | Single field: uploaded file (`transactions/…`), Google Drive URL, or other HTTP link. Helpers: `hasAttachment()`, `isGoogleDriveAttachment()`, `isUploadedFile()`. |
| **Transaction ↔ invoices/bills** | `invoices()`, `bills()` many-to-many with `amount_paid` pivot; `related()` morph | `calculateBankCharges()` compares transaction amount vs pivot totals (partial allocation signal). |
| **Invoice documents** | `invoice_google_link`, `invoice_document_path` | Drive link + optional local signed document path. |
| **Bill documents** | `bill_google_link` | Google Drive link for bill PDFs. |
| **Missing-attachment workflow** | `TransactionsWithoutDocumentsResource` | Dedicated Filament resource listing transactions with empty `attachment_path`; navigation badge count; upload/link UI. **Reuse this logic on Taxes page — do not build a parallel “missing docs” system.** |
| **Transaction CRUD upload** | `TransactionResource` | `FileUpload` + hidden `attachment_path`; same storage conventions as above. |
| **File module** | `File` model + relation managers | Cases/files link to invoices/bills; used for tax period file scoping in exports. |

### What can be reused

* **`attachment_path`** on `Transaction` — sole attachment store for bank receipts / transfer confirmations; extend with validation rules only.
* **`TransactionsWithoutDocumentsResource` query** — `whereNull('attachment_path')->orWhere('attachment_path', '')` — extract to a shared scope or service for Taxes dashboard counts.
* **Invoice/bill link checks** — `invoices()` / `bills()` pivot counts and `amount_paid` vs `amount` for **Partially Reconciled** vs **Complete**.
* **Document presence on linked records** — `invoice_google_link` / `invoice_document_path`, `bill_google_link` (and expense `attachment_path` for type `Expense`).
* **`TaxesExportController::buildExportPayload()`** — hook **pre-export validation** stats before Excel generation / email attach.
* **`TaxSummaryWidget` / `TaxPeriodSelector`** — extend with compliance stats cards, not duplicate period UI.

### What must be extended

* **`ListTaxes`** — transaction-level table or tab with reconciliation status column and filters.
* **Reconciliation status** — computed attribute or service method on `Transaction` (enum-like values: Complete, Missing Attachment, Missing Invoice/Bill, Partially Reconciled, Needs Review); rules must respect Inflow vs Outflow and transaction type.
* **Pre-export modal** — show validation summary when user clicks Export Data / Send Tax Email; block or warn based on product decision.
* **Excel export rows** (optional) — include reconciliation status column for accountant review.

### What must NOT be duplicated

* New `attachments` table or polymorphic media library **unless** audit proves `attachment_path` cannot meet requirements.
* Second upload field on Transactions (keep `FileUpload` → `attachment_path` in `TransactionResource`).
* New Filament resource mirroring `TransactionsWithoutDocumentsResource`.
* Separate Google Drive storage for tax receipts (use existing paths and Drive helpers).
* Duplicate zip/Excel export controllers.

### Suggested implementation order

1. Add `TransactionReconciliationService` (or model methods) centralizing status + period-scoped queries.
2. Extend Taxes page UI (stats + filtered transaction list) using existing period selection.
3. Add pre-export validation to export/email actions.
4. Optionally add reconciliation column to `TaxesModeExport` rows.
