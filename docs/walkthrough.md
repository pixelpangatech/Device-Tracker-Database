# Email Settings UI & Custom SMTP Mailer

I have successfully implemented the dynamic Email Settings configuration panel within the system!

## What was changed:

1. **Email Settings UI (Modal)**
   - Added an "Email Settings" option in the admin profile dropdown (accessible from any page via the header).
   - Clicking this opens a beautifully styled, glassmorphism-themed modal matching the exact design of your screenshot.
   - You can enter your **SMTP Host**, **SMTP Port**, **SMTP Username**, and **SMTP Password (App Password)**.

2. **Custom SMTP Mailer**
   - Since downloading external libraries like PHPMailer failed on your machine previously, I wrote a custom, lightweight `SimpleSMTP` class.
   - This class natively communicates with the SMTP server using TLS over PHP sockets, bypassing the need for XAMPP `sendmail` configuration!
   
3. **Database & Execution**
   - Created an `smtp_settings` table to save your settings securely.
   - The Welcome Email functionality (when adding a new employee) will now fetch your saved credentials from the database and send the email directly using the new custom SMTP script.

> [!TIP]
> **Setup Instructions**
> 1. Click on the profile dropdown in the top right and select **Email Settings**.
> 2. Enter your details:
>    - **SMTP Host**: `smtp.gmail.com`
>    - **SMTP Port**: `587`
>    - **SMTP Username**: `your.email@gmail.com`
>    - **SMTP Password**: Your 16-digit Google App Password
> 3. Click **Save Settings**.
> 
> You're now ready to add employees and send automated emails without modifying any XAMPP configuration files!
