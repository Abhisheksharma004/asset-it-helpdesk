# Asset Management & IT Service Desk Portal

A modern, responsive web application for managing enterprise IT assets, service requests, and technical support desk operations.

Designed & Maintained by **[Viros Entrepreneurs](https://virosentrepreneurs.com)**.

---

## 🚀 Features

- **Brand-Centric Design:** Clean corporate interface tailored with official VIROS branding (Viros Cyan `#0093A7` and Deep Navy `#001938`).
- **Responsive Layout:** Optimized for mobile phones, tablets, and desktop displays.
- **Universal Reusable Toast Notification System:**
  - Lightweight, vanilla JavaScript toast notification engine (`js/toast.js` & `css/toast.css`).
  - Supports 4 notification types: `error`, `success`, `warning`, `info`.
  - Built-in entrance/exit transitions, auto-dismiss, progress indicator, and pause-on-hover.
  - Compact modern card design with low border-radius (`5px`).
- **Client-Side Form Validation:**
  - Real-time password visibility toggle (Show/Hide).
  - Email format validation with custom toast alerts.
  - Required fields verification.
- **Clean Architecture:** Simple to integrate with any backend (PHP, MySQL, REST APIs).

---

## 🛠️ Tech Stack

- **Frontend:** HTML5, CSS3, Vanilla JavaScript (ES6+)
- **Backend:** PHP (runs on Apache/XAMPP)
- **Styling:** Custom Vanilla CSS with responsive design system
- **Branding:** VIROS Entrepreneurs IT Solutions Pvt Ltd

---

## 📂 Project Structure

```text
asset_it_helpdesk/
│
├── api/
│   ├── login.php                 # Login authentication API (JSON/Form POST)
│   └── logout.php                # Session destruction and logout handler
│
├── assets/
│   └── images/
│       └── logo.png              # VIROS official brand logo
│
├── config/
│   └── db.php                    # MSSQL Server native connection (sqlsrv)
│
├── css/
│   ├── dashboard.css             # Dashboard responsive styling
│   ├── style.css                 # Login page stylesheet
│   └── toast.css                 # Standalone reusable toast notification styles
│
├── includes/
│   ├── header.php                # Reusable HTML head & container open
│   ├── sidebar.php               # Reusable sidebar navigation
│   ├── topbar.php                # Reusable sticky header & search
│   └── footer.php                # Reusable footer, modal & script tags
│
├── js/
│   ├── dashboard.js              # Sidebar toggle, table search & ticket modal
│   ├── script.js                 # Login page validation & UI interactions
│   └── toast.js                  # Standalone reusable toast notification engine
│
├── dashboard.php                 # Main dashboard page
├── index.php                     # Portal login page
└── README.md                     # Project documentation
```

---

## 🔐 Authentication & Login API

The portal features an authentication system powered by Microsoft SQL Server (`asset_helpdesk`) and secure PHP sessions.

### API Endpoint: `POST api/login.php`
- **Request Format:** Accepts JSON (`application/json`) or URL-encoded form data.
- **Payload:**
  ```json
  {
    "email": "admin@company.com",
    "password": "yourpassword",
    "remember": true
  }
  ```
- **Response Format:**
  ```json
  {
    "success": true,
    "message": "Login successful! Redirecting to dashboard...",
    "redirect": "dashboard.php",
    "user": {
      "id": 1,
      "name": "System Administrator",
      "email": "admin@company.com",
      "role": "SUPER_ADMIN",
      "department": "IT Infrastructure"
    }
  }
  ```

### Active User Account:
| Username | Email | Password | Role | Department |
| :--- | :--- | :--- | :--- | :--- |
| `admin` | `admin@company.com` | `admin123` | `Administrator` | IT Administration |


---

## 🗄️ Microsoft SQL Server Database Connection

The application connects to **Microsoft SQL Server** using native PHP SQL Server functions (`sqlsrv`).

- **Connection File:** `config/db.php`
- **Default Database:** `asset_helpdesk`
- **Driver:** Native Microsoft Drivers for PHP for SQL Server (`sqlsrv`)

### Basic Usage:
```php
require_once __DIR__ . '/config/db.php';

// Option 1: Direct query with pre-initialized $conn
$sql = "SELECT id, email, role FROM users WHERE is_active = 1";
$stmt = sqlsrv_query($conn, $sql);

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    echo $row['email'];
}
sqlsrv_free_stmt($stmt);

// Option 2: Parameterized query (prevent SQL injection)
$sql = "SELECT * FROM users WHERE email = ?";
$params = [$userEmail];
$stmt = sqlsrv_query($conn, $sql, $params);
$user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// Option 3: Using helper functions
$db = getDB(); // Returns native $conn resource
$lastId = getLastInsertId(); // Returns last IDENTITY value
$formattedDate = formatDateForSQLServer(new DateTime());
```

### Testing Connection:
Run from command line:
```bash
php config/db.php
```


---

## 🔔 Toast Notification Usage Guide

The toast system is modular and can be used on **any page** across the entire application.

### 1. Include Files in your Page:
```html
<link rel="stylesheet" href="css/toast.css">
<script src="js/toast.js"></script>
```

### 2. Triggering Notifications:

#### Function Method:
```javascript
// Error (e.g. Validation or API error)
showToast("Please enter both email and password.", "error");

// Success (e.g. Saved or Login success)
showToast("Login successful!", "success");

// Warning
showToast("Session will expire in 5 minutes.", "warning");

// Info
showToast("A new ticket has been assigned to you.", "info");
```

#### Object Method:
```javascript
toast.error("Invalid credentials entered.");
toast.success("Asset details updated successfully.");
toast.warning("Low disk space on server.");
toast.info("Ticket #1042 status changed to In Progress.");
```

---

## ⚙️ Installation & Setup

1. **Clone the repository:**
   ```bash
   git clone https://github.com/Abhisheksharma004/asset-it-helpdesk.git
   ```

2. **Move to XAMPP htdocs directory:**
   Place the project folder inside your web server's document root (e.g., `C:/xampp/htdocs/asset_it_helpdesk`).

3. **Start Apache:**
   - Open **XAMPP Control Panel**.
   - Start the **Apache** module.

4. **Access the portal:**
   Open your browser and navigate to:
   ```text
   http://localhost/asset_it_helpdesk/
   ```

---

## 📄 License & Attribution

Designed and Maintained by **[Viros Entrepreneurs - IT Solutions Pvt Ltd](https://virosentrepreneurs.com)**.  
All rights reserved.
