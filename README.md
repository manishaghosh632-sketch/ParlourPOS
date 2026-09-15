# Parlour POS Billing & Management System

A comprehensive web-based Point of Sale (POS), appointment scheduling, and parlour management platform built using PHP, MySQL, and Tailwind CSS. The system provides role-based access control (RBAC), multi-channel payment processing, live inventory tracking, staff commission management, and client CRM features.

---

## Features

- **Multi-Role Access Control (RBAC):** Dedicated interfaces and permission logic for Super Admin, Manager, Receptionist/Cashier, Beautician, and Customer.
- **POS Checkout & Billing:** Fast cart calculation, automated discounts/taxes, multi-method payment handling (Cash, Card, UPI with QR code), and database transaction safety.
- **Appointment Scheduling Engine:** Customer self-service booking and front-desk booking grid with real-time staff/time conflict prevention.
- **Inventory & Purchase Logging:** Real-time stock depletion during checkout, automated alerts for low stock (threshold: $\le 5$ units), and purchase order ledgers.
- **Staff Commissions:** Percentage and flat-rate performance calculation tied directly to invoice line items.
- **Client Directory & Loyalty CRM:** Automated customer lifetime value (CLV) calculation, loyalty points tracking, and appointment history.
- **Refund & Cancellation Ledger:** Secure invoice payment reversal with full processor audit logs.
- **Dynamic Business Reporting:** Daily sales, inventory turnover, and rough P&L summaries exportable to CSV and printable formats.
- **Modern UX:** Fully responsive layouts styled with Tailwind CSS, Skeleton loading states, and PRG (Post-Redirect-Get) pattern form submissions to prevent duplicate entries.

---

## Tech Stack

- **Backend:** PHP
- **Database:** MySQL
- **Frontend:** HTML5, Tailwind CSS, JavaScript
- **Email Delivery:** PHPMailer (SMTP integration)

---

## Repository Structure

```text
├── ParlourPos/
│   ├── api/                   # Async API endpoints (metrics, inventory, cart calculations)
│   ├── assets/                # CSS (Skeleton/custom), JS, and image assets
│   ├── auth/                  # Authentication & role-based redirection scripts
│   ├── config/                # Database and application constants
│   ├── customers/             # Customer booking portal, loyalty points, and profile management
│   ├── includes/              # Universal header, footer, dynamic sidebar, auth guard
│   ├── manager/               # Manager dashboards, inventory, and purchase logging
│   ├── staff/                 # Beautician & Cashier operational interfaces and POS
│   ├── super_admin/           # Master system configurations, staff roles, and analytics
│   ├── bill.firstcustomer.pdf # Sample generated invoice
│   └── index.php              # Root landing page and portal router
├── daily_report/              # Daily development logs and final project documentation
├── parlour_pos.sql           # Complete relational database export
└── README.md
