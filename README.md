# TechVault (v5.0)

TechVault is a modern, responsive, and secure device inventory management system designed to track internal assets (Phones, Laptops, SIM cards, etc.) across testing floors or within an organization. 

Featuring a stunning **Glassmorphism** user interface, TechVault makes assigning, tracking, and returning devices a seamless experience.

## ✨ Key Features
- **Live Dashboard:** Real-time metrics on available vs. allocated devices.
- **Master Lists:** Manage inventory categories, devices, SIM cards, and registered employees.
- **One-Click Allocations:** Easily assign or return devices with automated timestamp tracking.
- **Automated Welcome Emails:** Sends auto-generated HTML emails with login credentials to new employees via custom SMTP setup.
- **Smart Database Initialization:** Plug-and-play database setup. Automatically creates tables for blank databases on shared hosting (cPanel) or local environments (XAMPP).
- **Glassmorphism UI:** Premium aesthetic with blurred backgrounds, dark themes, and flat CSS icons.

## 🚀 Quick Setup (Installation)
1. **Clone the repository:** 
   ```bash
   git clone https://github.com/YOUR_USERNAME/TechVault.git
   ```
2. **Move to Server Directory:** Place the folder inside your web server directory (e.g., `C:/xampp/htdocs/TechVault/`).
3. **Configure Database:**
   - Open `db.php`.
   - Update `$host`, `$username`, `$password`, and `$db_name` with your MySQL credentials.
   - *Note: If connecting to a live server with restricted permissions, create a blank database first. TechVault will auto-create the necessary tables.*
4. **Launch:** Open `http://localhost/TechVault` in your browser.

## 📧 Email Settings
No `sendmail` configuration is required! 
1. Log in to the system.
2. Click your profile dropdown in the top-right corner.
3. Select **Email Settings**.
4. Enter your SMTP details (e.g., `smtp.gmail.com`, Port `587`, your email, and App Password).
5. TechVault's custom `SimpleSMTP` engine will handle the rest.

## 🔐 Default Admin Account
Upon the first initialization, the system will create a default administrator account:
- **Username:** `admin`
- **Password:** `123456`
*(Please change this immediately after logging in)*
