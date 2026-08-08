# Phone Inventory Project Analysis

Based on the files in your workspace, here is an analysis of your project:

## Overview
The project is a web-based **Phone Inventory Management System** built using Python and the **Flask** framework. It helps in tracking mobile devices issued to users within an office environment. 

## Tech Stack
*   **Backend:** Python with Flask
*   **Database:** SQLite (`inventory.db` and `device_tracker.db`)
*   **Frontend:** HTML templates (rendered via Flask's `render_template` using Jinja2)

## Directory Structure
*   `app.py`: The main application file containing all the backend logic, routing, and database interaction.
*   `inventory.db`: The SQLite database storing devices, master lists, and user information.
*   `device_tracker.db`: Another SQLite database (possibly an older version or for a specific feature).
*   `templates/`: Contains HTML files for the user interface.
    *   `index.html`: The main public-facing dashboard.
    *   `admin.html`: The admin dashboard for managing inventory.
    *   `admin_login.html`: Login page for admin access.
*   `backups/`: A directory where daily history CSV files are automatically generated.

## Key Features
1.  **Device Tracking:** Tracks which device is assigned to which user, along with SIM card details, assigned date, and IP address.
2.  **Permanent Devices:** Supports marking devices as "permanent" for specific users, which are automatically logged daily (`auto_log_permanent_devices` function).
3.  **Daily History Logging:** Maintains a daily backup/history of all actions (like issuing a device) in CSV format within the `backups` folder (`log_daily_history` function).
4.  **Admin Panel:** Includes a secure administrative section (protected by session and password) to manage master lists of devices and users.
5.  **IP Tracking:** Records the client IP address (`get_client_ip` function) when an action is performed for auditing purposes.

## Security
*   The admin panel is protected by a username (`admin`) and a hardcoded password. 
*   Session security is implemented using a secret key (`super_secret_admin_session_key_123`).
    > [!WARNING]
    > Hardcoding credentials and secret keys directly in the `app.py` file is a security risk. In a production environment, these should be moved to environment variables.

Let me know if you would like me to analyze a specific part of the code in more detail, or if you want to make any modifications to the project!
