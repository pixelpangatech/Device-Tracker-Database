# Email Settings UI & Custom SMTP Mailer Implementation

This plan details how we will add an "Email Settings" option to the admin dropdown and build the necessary backend to send emails directly from the application using those settings, without requiring XAMPP's `sendmail.ini` configuration.

## Open Questions

None at this time. The provided screenshot perfectly outlines the required fields: SMTP Host, SMTP Port, SMTP Username (Email), and SMTP Password (App Password).

## Proposed Changes

### Database Schema Update
- We need a place to store these configurations securely.
- **[MODIFY]** `db.php`
  - Add a new table `smtp_settings` to store the Host, Port, Username, and Password.
  - Insert default blank/placeholder values if the table is empty.

### Header UI (Dropdown & Modal)
- **[MODIFY]** `header.php`
  - Add an "Email Settings" link inside the profile dropdown (below "Change Password").
  - Create a Glassmorphism-styled Bootstrap modal matching the layout from your screenshot.
  - The modal will contain the form fields and a "Save Settings" button.

### Backend Mailer Logic
- **[NEW]** `libs/SimpleSMTP.php`
  - Because downloading third-party libraries (like PHPMailer) failed on your system due to network restrictions, I will write a highly optimized, custom `SimpleSMTP` PHP class.
  - This class will use PHP's native `fsockopen` to connect to Gmail's SMTP server (e.g., `smtp.gmail.com` on port `587` with TLS) and authenticate using the credentials saved in the database.
- **[MODIFY]** `db.php`
  - Update the existing `sendWelcomeEmail()` function. Instead of using the native `mail()` function (which relies on local XAMPP config), it will fetch the saved SMTP settings from the database and use the new `SimpleSMTP` class to dispatch the email.

### Settings Handler
- **[MODIFY]** `admin.php` (or a dedicated handler)
  - Add the POST action logic to save/update the SMTP settings in the database when the admin submits the form in the modal.

## Verification Plan

### Automated Tests
- None required.

### Manual Verification
1. Click the profile dropdown and select "Email Settings".
2. Ensure the modal opens and looks exactly like the requested design.
3. Enter your Gmail SMTP settings (smtp.gmail.com, 587, your email, and App Password) and save.
4. Go to "Employees", add a new employee, and verify that the Welcome Email successfully arrives in the inbox using the configured SMTP settings.
