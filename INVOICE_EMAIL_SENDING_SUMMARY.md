# Invoice Email Sending Feature - Summary

## Current Status
The "Send Invoice to Client" feature is implemented but encountering an SMTP authentication error. The code is set to use the `smtp` mailer (operation email) for testing, but the server is still running old code that uses the `financial` mailer.

## What Has Been Implemented

### 1. Send Invoice Button
- **Location**: `app/Filament/Resources/InvoiceResource/Pages/EditInvoice.php`
- **Button**: "Send Invoice to Client" in the header actions
- **Icon**: `paper-airplane`
- **Color**: `warning` (orange)
- **Modal**: Opens with email preview and attachment checkboxes

### 2. Email Preview Component
- **Location**: `resources/views/filament/forms/components/invoice-email-preview.blade.php`
- Shows email subject and body preview in the modal
- Subject format: `"MGA Invoice {invoice_name} for {patient_name} | {client_reference} | {mga_reference}"`
- Body format: Each field on separate line with colon separator

### 3. Attachment Functionality
- **Current**: Only invoice attachment checkbox is implemented
- **Location**: `app/Filament/Resources/InvoiceResource/Pages/EditInvoice.php` (lines 45-48)
- **Mailable**: `app/Mail/SendInvoiceToClient.php`
- **Fixed Issue**: Resolved "Cannot access offset of type string on string" error by ensuring `$attachments` property doesn't conflict with Laravel's internal Mailable `$attachments` property

### 4. Email Body Format
- **Location**: `app/Filament/Resources/InvoiceResource/Pages/EditInvoice.php` (lines 130-155)
- Format:
  ```
  Dear team,

  Find Attached the Invoice {name}:

  Your Reference : {client_reference}
  Patient Name : {patient_name}
  MGA Reference : {mga_reference}
  Issue Date : {date}
  Due Date : {due_date}
  Total : {amount}€
  GOP Total : {gop_total}€

  Attachments
  · Invoice {name}
  ```

### 5. Email Template
- **Location**: `resources/views/emails/financial/send-invoice-to-client.blade.php`
- Includes user signature from `draftsignature` partial
- Uses `nl2br(e($emailBody))` to format the email body

## Current Issue

### SMTP Authentication Error
**Error Message:**
```
Failed to authenticate on SMTP server with username "mga.financial@medguarda.com"
```

**Root Cause:**
- The server is still running old code that uses `$mailer = 'financial'`
- The local code has been updated to `$mailer = 'smtp'` (line 158)
- Changes have NOT been committed or pushed to the server yet

**Current Code State:**
- **Local**: `$mailer = 'smtp'` (line 158 in EditInvoice.php)
- **Server**: Still using old code with `$mailer = 'financial'`

## Files Modified

1. **app/Filament/Resources/InvoiceResource/Pages/EditInvoice.php**
   - Added "Send Invoice to Client" action
   - Implemented attachment checkbox logic
   - Email body construction
   - Currently set to use `smtp` mailer for testing

2. **app/Mail/SendInvoiceToClient.php**
   - Mailable class for sending invoices
   - Handles attachments (invoice, bill, medical report, GOP)
   - Subject includes patient name
   - Fixed property name conflict with Laravel's `$attachments`

3. **resources/views/filament/forms/components/invoice-email-preview.blade.php**
   - Email preview in modal
   - Shows subject and body with proper formatting

4. **resources/views/emails/financial/send-invoice-to-client.blade.php**
   - Email template
   - Includes signature

## Next Steps

### Immediate (To Fix Current Error)
1. **Commit the changes:**
   ```bash
   git add app/Filament/Resources/InvoiceResource/Pages/EditInvoice.php
   git add app/Mail/SendInvoiceToClient.php
   git add resources/views/filament/forms/components/invoice-email-preview.blade.php
   git add resources/views/emails/financial/send-invoice-to-client.blade.php
   git commit -m "Add Send Invoice to Client feature with attachments"
   ```

2. **Push to server:**
   ```bash
   git push origin staging
   ```

3. **On server, clear cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

### Future Enhancements
1. **Add remaining attachment checkboxes:**
   - PDF of the Bill
   - Medical report uploaded PDF
   - GOP in PDF
   - (Currently only "The generated draft invoice" is implemented)

2. **Switch to financial mailer:**
   - When ready, change line 158 from `$mailer = 'smtp'` to:
   ```php
   $mailer = 'financial';
   $user = \App\Models\User::find(Auth::id());
   $financialRoles = ['Financial Manager', 'Financial Supervisor', 'Financial Department'];
   
   if ($user && $user->hasRole($financialRoles) && $user->smtp_username && $user->smtp_password) {
       Config::set('mail.mailers.financial.username', $user->smtp_username);
       Config::set('mail.mailers.financial.password', $user->smtp_password);
   }
   ```
   - This matches the pattern used in `app/Filament/Resources/ClientResource/RelationManagers/InvoiceRelationManager.php` (SendBalanceUpdate action)

## Technical Notes

### Mailer Configuration
- **Testing**: Currently using `smtp` mailer (operation email from `.env` MAIL_USERNAME/MAIL_PASSWORD)
- **Production**: Should use `financial` mailer (from `.env` FINANCIAL_MAIL_* variables)
- **Pattern**: Same as SendBalanceUpdate in InvoiceRelationManager

### Attachment Handling
- Uses `$attachments` array passed to Mailable
- Mailable checks for each attachment type: 'invoice', 'bill', 'medical_report', 'gop'
- Files are attached using `Storage::disk('public')->path()`

### Email Recipient
- Sends to: `$invoice->file->patient->client->email`
- Validates email exists before sending

### Debugging
- Logs added at key points:
  - "SendInvoice action called" - shows form data received
  - "Mailer selection" - shows which mailer is being used
  - "Preparing to send email" - shows attachments array
  - "Sending email" - final confirmation before sending
  - Error logs with full stack traces

## Related Files Reference
- **SendBalanceUpdate** (working example): `app/Filament/Resources/ClientResource/RelationManagers/InvoiceRelationManager.php` (lines 279-306)
- **SendBalance Mailable**: `app/Mail/SendBalance.php`
- **Mail config**: `config/mail.php`

