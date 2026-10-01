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
├── assets/
│   └── images/
│       └── logo.png              # VIROS official brand logo
│
├── css/
│   ├── style.css                 # Main portal stylesheet
│   └── toast.css                 # Standalone reusable toast notification styles
│
├── js/
│   ├── toast.js                  # Standalone reusable toast notification engine
│   └── script.js                 # Login page validation & UI interactions
│
├── index.php                     # Portal login page
└── README.md                     # Project documentation
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
