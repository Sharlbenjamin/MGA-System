# SMTP Authentication Issue - Financial Mailer

## Problem Summary

The application is failing to send emails through the "financial" mailer configuration. The SMTP authentication is being rejected by Google's Gmail SMTP server.

## Error Details

```
Error: Failed to authenticate on SMTP server with username "mga.financial@medguarda.com" 
using the following authenticators: "LOGIN", "PLAIN", "XOAUTH2". 

Authenticator "LOGIN" returned "Expected response code "235" but got code "535", 
with message "535-5.7.8 Username and Password not accepted. For more information, 
go to 535 5.7.8 https://support.google.com/mail/?p=BadCredentials..."
```

## Configuration Details

**Email Account:** `mga.financial@medguarda.com`

**SMTP Configuration (from .env):**
- Host: `smtp.gmail.com`
- Port: `587`
- Encryption: `tls`
- Username: `mga.financial@medguarda.com`
- Password: `tutsbthrihixfsod` (appears to be an app-specific password)

**Configuration Location:**
- Mail config: `config/mail.php` (lines 38-51)
- Environment variables: `FINANCIAL_MAIL_*` prefixed variables

## Where It's Used

The financial mailer is used in several places:
1. **Invoice sending** - `app/Filament/Resources/InvoiceResource/Pages/EditInvoice.php` (line 158)
2. **Balance updates** - `app/Filament/Resources/ClientResource/RelationManagers/InvoiceRelationManager.php` (line 285)
3. **Email classes** - `app/Mail/SendInvoiceToClient.php`, `app/Mail/SendBalance.php`

## Likely Causes

1. **App-specific password expired/revoked** - Gmail app-specific passwords can expire or be revoked
2. **2FA requirement** - If 2FA is enabled, regular passwords won't work; needs app-specific password
3. **Account security restrictions** - Google may have locked the account due to suspicious activity
4. **Password changed** - The app-specific password may have been changed in Google account settings
5. **"Less secure app access"** - Though deprecated, some legacy accounts may have restrictions

## Recommended Solutions

1. **Verify/Regenerate App-Specific Password:**
   - Go to Google Account → Security → 2-Step Verification → App passwords
   - Generate a new app-specific password for "Mail"
   - Update the `FINANCIAL_MAIL_PASSWORD` in `.env` file

2. **Check Account Status:**
   - Verify the account `mga.financial@medguarda.com` is active and not locked
   - Check for any security alerts in Google Account

3. **Verify 2FA Status:**
   - Ensure 2FA is enabled (required for app-specific passwords)
   - If 2FA is not enabled, either enable it or use OAuth2 instead

4. **Alternative: Use OAuth2:**
   - Consider implementing OAuth2 authentication instead of app-specific passwords
   - More secure and doesn't require password rotation

5. **Test Connection:**
   - After updating credentials, test the SMTP connection
   - Check application logs for detailed error messages

## Files to Check/Modify

- `.env` - Update `FINANCIAL_MAIL_PASSWORD`
- `config/mail.php` - Verify financial mailer configuration
- Application logs - Check for detailed SMTP error messages

## Next Steps

1. Verify the Google account status and security settings
2. Generate a new app-specific password if needed
3. Update the `.env` file with the new password
4. Test email sending functionality
5. Consider implementing OAuth2 for better security

