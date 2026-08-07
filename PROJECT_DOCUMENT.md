# Project Detailed Documentation
**Project:** Device Tracker Database

This document outlines the folder structure, core architecture, and provides a detailed breakdown of each file and the specific services it handles within the application.

---

## 📁 Project Structure

The project currently uses a flat directory structure for simplicity and ease of deployment on lightweight servers (like XAMPP). All core logic, UI, and database files reside in the root folder.

```text
phone_inventory/
│
├── admin.php                 # Admin Panel & Management Interface
├── change_password.php       # Password Reset Service
├── db.php                    # Database Connection & Initialization
├── export_excel.php          # Data Export Service
├── footer.php                # Global Footer & Scripts
├── header.php                # Global Header & Navigation
├── index.php                 # Main Dashboard & View Port
├── login.php                 # Authentication Gateway
├── logout.php                # Session Termination Service
├── style.css                 # Global UI/UX Stylesheet
│
├── README.md                 # General Project Overview
├── UIUX_DESIGN.md            # UI/UX Design Principles
└── PROJECT_DOCUMENT.md       # (This File) Detailed Architecture
```

*(Note: During runtime, the system will auto-generate `inventory.sqlite` to store database records and `admin_credentials.json` to store admin secrets).*

---

## 📄 File Details & Services

### 1. `db.php` (Database Service)
*   **Purpose:** Acts as the central nervous system for data persistence. 
*   **Services Provided:**
    *   Establishes a PDO (PHP Data Objects) connection to an SQLite database.
    *   **Auto-Initialization:** Checks if `inventory.sqlite` exists. If not, it automatically executes a schema generation script to create the `devices`, `master_devices`, and `master_users` tables.
    *   Automatically hashes and inserts default employee passwords if missing.

### 2. `index.php` (Dashboard & Allocation Service)
*   **Purpose:** The main landing page and public/private dashboard.
*   **Services Provided:**
    *   **Guest View:** Displays a read-only table of all current "Today's Allocations".
    *   **Admin View:** Provides a form to allocate a device to an employee (capturing Device, Employee, Status, SIM details, and Time).
    *   **Real-time Filtering:** Contains JavaScript logic to filter the table by Status (Issued, Permanent, Returned), OS Type (Android/iPhone), and text search.

### 3. `admin.php` (Management Service)
*   **Purpose:** The restricted control center for the application.
*   **Services Provided:**
    *   **Master Data Management:** Interfaces to add or remove items from the `master_devices` (e.g., adding a new phone model) and `master_users` (e.g., onboarding a new employee) tables.
    *   **Action Logs:** Displays a historical table of all allocations and state changes.
    *   **Client-Side Sorting:** Contains JavaScript to sort log columns dynamically (e.g., sorting by Time, Assigned To, or Status).

### 4. `login.php` (Authentication Service)
*   **Purpose:** Validates user identities before granting access.
*   **Services Provided:**
    *   Validates Admin credentials against `admin_credentials.json`.
    *   Validates Employee credentials against the `master_users` table in SQLite using `password_verify()` (Bcrypt).
    *   Initializes secure PHP `$_SESSION` variables (`role`, `user_name`, `must_change_password`).

### 5. `change_password.php` (Security & Onboarding Service)
*   **Purpose:** Ensures account security by managing password updates.
*   **Services Provided:**
    *   Forces users with the `must_change_password` flag (default login state) to update their password before proceeding.
    *   Updates the Bcrypt hash in the database.
    *   Provides a "Continue as Guest" fallback which routes to `logout.php`.

### 6. `header.php` & `footer.php` (Layout Services)
*   **Purpose:** DRY (Don't Repeat Yourself) structural components.
*   **Services Provided:**
    *   `header.php`: Imports Bootstrap, FontAwesome, Google Fonts, and `style.css`. It renders the responsive Glassmorphism Navbar and dynamic dropdowns based on session state. It also blocks direct access to itself.
    *   `footer.php`: Closes HTML tags and imports Bootstrap JavaScript bundles.

### 7. `logout.php` (Session Service)
*   **Purpose:** Safely terminates user access.
*   **Services Provided:**
    *   Calls `session_destroy()` and clears all session cookies/arrays.
    *   Redirects the user cleanly back to `index.php` (Guest Mode).

### 8. `export_excel.php` (Export Service)
*   **Purpose:** Data extraction.
*   **Services Provided:**
    *   Queries the `devices` table and streams the output to the browser as an `.xls` (Excel) file download by manipulating HTTP headers (`Content-Type: application/vnd.ms-excel`).

### 9. `style.css` (Presentation Service)
*   **Purpose:** Controls the entire visual identity of the app.
*   **Services Provided:**
    *   Defines CSS Variables for theme colors and glassmorphic transparencies.
    *   Handles Mobile Responsiveness (`@media` queries for `<main>`, headers, and flex-wrapping tables).
    *   Implements micro-animations (hover lifts, fade-ins).

---

## 🔄 Data Flow Summary

1.  **Unauthenticated (Guest):** Hits `index.php` -> Views `devices` table (Read-only).
2.  **Authentication:** Hits `login.php` -> Validates via `db.php` -> Creates `$_SESSION`.
3.  **Onboarding (If Employee & default password):** `login.php` -> Redirects to `change_password.php` -> Updates `master_users` -> Grants access.
4.  **Admin Operations:** `admin.php` -> Performs CRUD operations via PDO -> Modifies `master_devices`, `master_users`, or `devices`.
