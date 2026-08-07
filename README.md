# Device Tracker Database

Device Tracker Database is a premium, web-based inventory management system designed to track physical devices (like Androids and iPhones) allocated to employees. It features a modern Glassmorphism UI, a secure Admin Panel, and real-time client-side filtering.

## 🚀 Features

*   **Premium UI/UX:** Built with a stunning Glassmorphic design, subtle gradients, and modern typography (Plus Jakarta Sans).
*   **Role-Based Access:** 
    *   **Admin Mode:** Full control over master devices, master users, and real-time logs.
    *   **Employee Mode:** Secure login with mandatory password-change enforcement.
    *   **Guest Mode:** View-only access to current device allocations on the dashboard.
*   **Auto-Initializing Database:** Zero-configuration SQLite database (`inventory.sqlite`). Tables and default admin accounts are created automatically on the first run.
*   **Responsive Design:** Fully mobile-compatible tables, headers, and filters using Bootstrap 5 and custom flexbox layouts.
*   **Advanced Filtering & Sorting:** Real-time client-side search, status filters (Issued/Permanent/Returned), device type filters, and column sorting.

## 🛠️ Technology Stack

*   **Frontend:** HTML5, CSS3, Vanilla JavaScript, Bootstrap 5, FontAwesome 6.
*   **Backend:** PHP 8+
*   **Database:** SQLite (via PDO)

## 📦 Installation & Setup

1.  **Prerequisites:** 
    *   A local web server environment like XAMPP, WAMP, or MAMP.
    *   PHP 8.0 or higher.
2.  **Clone the Repository:**
    Place the project folder (`phone_inventory`) into your server's root directory (e.g., `htdocs` for XAMPP).
3.  **Run the Application:**
    Navigate to `http://localhost/phone_inventory/` in your web browser.
4.  **Database Auto-Setup:**
    The system will automatically create `inventory.sqlite` and seed it with the necessary tables (`master_users`, `master_devices`, `devices`) upon first access.

## 🔐 Default Credentials

### Admin Login
*   **Username:** admin
*   **Password:** adminpassword123
*(Note: Admin credentials are securely stored and validated via `admin_credentials.json`)*

### Employee Login (Default)
*   **Password:** 123456
*(Note: Employees are forced to change this default password upon their first login before they can access any authenticated routes).*

## 📖 How to Use

### For Admins
1. Navigate to `/login.php` and log in with admin credentials.
2. Go to the **Admin Panel** (`admin.php`).
3. **Manage Masters:** Add or remove Employee Names (Master Users) and Device Names (Master Devices).
4. **Allocate Devices:** Use the central form to allocate a device to an employee. Select the status (Issued, Permanent, Returned).
5. **View Logs:** Track all check-ins, check-outs, and modifications in the "Action Logs" table. You can sort by Time, Assigned To, and Status.

### For Employees
1. Navigate to the Dashboard.
2. If logging in to change a password, click the User Profile icon -> Login.
3. If it's the first time logging in, you will be redirected to `change_password.php`.
4. You can also click **"Continue as Guest"** to view the current allocations without making changes.

## 🛡️ Security Measures
*   **Bcrypt Hashing:** All passwords are mathematically hashed using `PASSWORD_DEFAULT` (Bcrypt).
*   **SQL Injection Prevention:** All database queries utilize PDO Prepared Statements.
*   **Session Hijacking Prevention:** Secure session handling (`session_start()`) checks roles across restricted pages.
*   **Directory Protection:** Direct access to `db.php` or `header.php` is blocked.

## 📞 Contact & Support
For any inquiries, support, or further details regarding this project, please contact **PixelPanga Tech** at:
📧 **Email:** [pixelpangatech@gmail.com](mailto:pixelpangatech@gmail.com)
