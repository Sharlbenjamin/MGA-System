# TRX Out date repair — production task list

Use this checklist on production after deploying the repair command.

## What this fixes

- **201** of **432** imported TRX Out rows have wrong dates (day/month swap from original Excel Value date serials).
- [`TRX Out Revision.xlsx`](TRX Out Revision.xlsx) is the source of truth (Date column).
- Procedure: **audit + targeted date update** — not delete + re-import.

---

## Step 0 — Deploy code (local → server)

On your Mac (project root):

```bash
git add app/Services/TrxOutImportDateRepairService.php \
        app/Services/TrxOutStatementNormalizer.php \
        app/Console/Commands/RepairTrxOutImportDates.php \
        tests/Unit/TrxOutImportDateRepairServiceTest.php
git commit -m "Add TRX Out date repair command and revision file parser support."
git push
```

On production SSH:

```bash
cd ~/applications/fdcpgwbqxd/public_html
git pull
php artisan optimize:clear
```

No migration required.

---

## Step 1 — Upload revision file to server

On your **Mac** (not inside SSH):

```bash
scp "/Users/sharlbenjamen/Desktop/All Apps/mga-system/TRX Out Revision.xlsx" \
  master_mgexgxgevm@46.101.27.90:/home/master/TRX-Out-Revision.xlsx
```

---

## Step 2 — Confirm import batch ID

On production SSH:

```bash
cd ~/applications/fdcpgwbqxd/public_html
php artisan tinker --execute="
\$b = \App\Models\TransactionImportBatch::query()
    ->where('filename', 'like', '%TRX Out%')
    ->orderByDesc('id')
    ->first();
echo \$b ? \"Batch #{$b->id} — {$b->filename} — imported {$b->imported_count}\n\" : \"No batch found\n\";
"
```

Note the batch ID (usually `1` if first import). Bank account ID is typically `1`.

---

## Step 3 — Dry-run audit (no DB changes)

```bash
php artisan transactions:repair-trx-out-dates \
  /home/master/TRX-Out-Revision.xlsx \
  1 \
  --batch-filename="TRX Out.xlsx" \
  --dry-run
```

**Expected output:**

| Metric | Expected |
|--------|----------|
| Transactions in batch | 432 |
| Revision rows | 432 |
| Already correct | 231 |
| To fix | 201 |
| Anomalies | **0** |
| Can apply | **yes** |

If anomalies > 0 or counts differ, **stop** — do not apply.

Optional: pin batch explicitly:

```bash
php artisan transactions:repair-trx-out-dates \
  /home/master/TRX-Out-Revision.xlsx \
  1 \
  --batch-id=BATCH_ID_HERE \
  --dry-run
```

---

## Step 4 — Apply date fixes

Only after Step 3 passes with 0 anomalies:

```bash
php artisan transactions:repair-trx-out-dates \
  /home/master/TRX-Out-Revision.xlsx \
  1 \
  --batch-filename="TRX Out.xlsx" \
  --apply
```

Confirm when prompted. Command will:

1. Re-run pre-flight audit
2. Update `date` on 201 transactions only
3. Run post-audit automatically

**Post-audit must show:**

| Check | Expected |
|-------|----------|
| Remaining mismatches | 0 |
| Anomalies | 0 |
| 100% aligned | **yes** |

---

## Step 5 — Clear cache and verify in UI

```bash
php artisan optimize:clear
```

In the admin UI (bank transactions for account #1):

- [ ] Date column sort (newest / oldest) looks correct
- [ ] Filter by date range (e.g. May 2025) shows expected rows
- [ ] Spot-check row 2 (patient refund): date should be **12 May 2025**, not 5 Dec 2025
- [ ] Spot-check a July row: e.g. **1 July 2025**, not 7 January

---

## 100% effect guarantee

This procedure is complete when **all three** are true:

1. **Pre-audit:** 432/432 rows paired, 0 anomalies, 201 to fix
2. **Apply:** post-audit reports 0 remaining mismatches
3. **UI:** date filter and sort behave correctly on the bank transactions page

---

## Rollback (if needed)

If something went wrong and you noted transaction IDs from the dry-run sample:

```sql
-- Only if you captured old_date values from audit output
UPDATE transactions SET date = 'OLD_DATE' WHERE id = TRANSACTION_ID;
```

Safer: restore from DB backup if available.

---

## Files involved

| File | Role |
|------|------|
| `app/Console/Commands/RepairTrxOutImportDates.php` | Artisan command |
| `app/Services/TrxOutImportDateRepairService.php` | Audit + apply logic |
| `app/Services/TrxOutStatementNormalizer.php` | Parses Revision Date column |
| `TRX Out Revision.xlsx` | Source of truth for correct dates |
