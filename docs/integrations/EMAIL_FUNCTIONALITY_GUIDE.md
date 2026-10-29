# Email Functionality Guide

## Overview

The Branch Availability system includes functionality to send appointment request emails to provider branches. This guide explains how it works and how to troubleshoot issues.

## How It Works

### 1. Configure Custom Emails
- Use the **"Configure Custom Emails"** button (previously named "Send CustomEmail")
- This button opens a form where you can add additional email addresses
- These emails will be CC'd on all appointment request emails
- **This button does NOT send emails immediately** - it only configures which additional emails to include

### 2. Send Appointment Requests
- Use the **"Send to All Branches"** button to send emails to all active provider branches
- Use **bulk actions** on the table to send emails to selected branches only
- Both methods will include any configured custom emails as CC recipients

## Email Flow

1. **File Selection**: Select an MGA file from the dropdown
2. **Custom Email Configuration**: Add additional email addresses that should receive copies
3. **Email Sending**: Use one of the send buttons to actually send the emails
4. **Email Delivery**: Emails are sent to:
   - Primary recipient: Branch email address
   - CC: Operation contact email (if available)
   - CC: All configured custom emails

## Troubleshooting

### Emails Not Being Sent

#### Check 1: File Selection
- Ensure you have selected an MGA file from the dropdown
- The file must have a patient, client, and service type

#### Check 2: Branch Email Contacts
- Verify that provider branches have email addresses configured
- Check both the branch's direct email and operation contact email
- Use the "Has Email Contact" filter to see only branches with emails

#### Check 3: Mail Configuration
- Check your `.env` file for mail settings:
  ```
  MAIL_MAILER=smtp
  MAIL_HOST=your-smtp-host
  MAIL_PORT=587
  MAIL_USERNAME=your-username
  MAIL_PASSWORD=your-password
  MAIL_ENCRYPTION=tls
  ```

#### Check 4: Logs
- Check Laravel logs in `storage/logs/laravel.log`
- Look for email-related errors and warnings
- The system now logs detailed information about email sending attempts

### Testing Email Functionality

Run the test script to verify email functionality:

```bash
php test_email_functionality.php
```

This script will:
- Check if files and branches exist
- Verify mail configuration
- Test mailable creation
- Optionally send a test email

### Common Issues

#### Issue: "No Emails Sent" Notification
**Cause**: No branches have email contacts configured
**Solution**: 
- Check branch email settings
- Ensure operation contacts have email addresses
- Use the "Has Email Contact" filter

#### Issue: "Some Emails Failed" Notification
**Cause**: Mail server issues or invalid email addresses
**Solution**:
- Check mail server configuration
- Verify email addresses are valid
- Check logs for specific error messages

#### Issue: Custom Emails Not Receiving Copies
**Cause**: Custom emails not properly configured
**Solution**:
- Use "Configure Custom Emails" button to add email addresses
- Ensure email addresses are valid
- Check that emails are being added to the `customEmails` array

## UI Improvements Made

1. **Button Renaming**: "Send CustomEmail" → "Configure Custom Emails"
2. **Status Indicators**: Button shows count of configured emails
3. **Color Coding**: Button turns green when emails are configured
4. **Better Descriptions**: Clear explanations of what each button does
5. **Status Display**: Shows configured custom emails in file details
6. **Enhanced Logging**: Detailed logging for troubleshooting

## Email Template

The system uses the `AppointmentRequestMailable` class which:
- Sends to branch email addresses
- CCs operation contact emails
- CCs all configured custom emails
- Includes file details, patient information, and service details
- Uses the `emails.request-appointment` view template

## Next Steps

If you're still experiencing issues:

1. Run the test script to identify the problem
2. Check Laravel logs for detailed error messages
3. Verify mail server configuration
4. Ensure branches have valid email addresses
5. Test with a simple email address first

## Support

For additional help, check:
- Laravel logs in `storage/logs/`
- Mail configuration in `config/mail.php`
- Database records for provider branches and their contacts
