# OBO-PAMS (Permit Application Management System)

## Overview

**OBO-PAMS (Office of the Building Official - Permit Application Management System)** is a web-based application designed to streamline the encoding, management, tracking, and monitoring of permit applications within the Office of the Building Official (OBO). The system replaces manual recording processes with a centralized digital platform, improving efficiency, accuracy, and accessibility of permit records.

---

# Objectives

* Digitize the permit encoding process.
* Reduce manual errors during data entry.
* Provide centralized storage for permit records.
* Improve searching and retrieval of permit information.
* Generate reports quickly and accurately.
* Monitor permit application status in real time.
* Enhance productivity of OBO personnel.

---

# Features

## Dashboard

* Overview of total permits
* Daily encoded permits
* Monthly statistics
* Pending applications
* Approved permits
* Rejected permits
* Recent activities

---

## Permit Encoding

Supports encoding of:

* Building Permit
* Occupancy Permit
* Ancillary Permit

Each record includes:

* Permit Number
* Applicant Name
* Project Location
* Owner Information
* Contractor
* Date Filed
* Date Issued
* Permit Status
* Remarks

---

## Permit Management

* Create Permit
* Read Permit Details
* Update Permit Information
* Delete Permit Record
* View Permit History

---

## Search System

Search permits by:

* Permit Number
* Applicant Name
* Owner Name
* Location
* Date
* Permit Type
* Status

---

## Filtering

Filter records using:

* Month
* Year
* Permit Type
* Status

---

## Exporting

Generate reports in:

* Excel (.xlsx)
* PDF

Reports may include:

* Daily Report
* Weekly Report
* Monthly Report
* Annual Report

---

## User Management

Roles:

### Administrator

* Full system access
* Manage users
* Manage permit records
* Generate reports
* Configure system settings

### Encoder

* Encode permit applications
* Update assigned records
* Search records
* Print reports

---

## Authentication

* Secure Login
* Password Encryption
* Session Management
* Role-Based Access Control (RBAC)

---

# System Modules

## Authentication Module

* Login
* Logout
* Session Validation

---

## Dashboard Module

Displays:

* Total Permits
* Building Permits
* Occupancy Permits
* Ancillary Permits
* Monthly Statistics
* Recent Activities

---

## Permit Module

Functions:

* Add Permit
* Edit Permit
* Delete Permit
* View Details
* Search
* Filter

---

## Reports Module

Generate:

* Permit Summary
* Monthly Reports
* Annual Reports
* Export to Excel
* Export to PDF

---

## User Management Module

* Add User
* Edit User
* Delete User
* Assign Roles
* Reset Password

---

# Workflow

1. User logs into the system.
2. Encoder enters permit application details.
3. System validates the information.
4. Permit record is saved to the database.
5. Records become searchable immediately.
6. Administrator reviews and manages records.
7. Reports can be generated at any time.

---

# Technologies

### Frontend

* HTML5
* CSS3
* JavaScript
* Bootstrap

### Backend

* PHP

### Database

* MySQL

### Web Server

* Apache (XAMPP/WAMP)

---

# Database Tables (Example)

* users
* permits
* applicants
* permit_types
* statuses
* activity_logs

---

# Security Features

* Password Hashing
* SQL Injection Protection
* Input Validation
* Session Timeout
* Authentication Middleware
* Authorization by User Role
* Audit Logs
* CSRF Protection
* XSS Protection

---

# Project Structure

```
obo-pams/
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── config/
│   └── database.php
│
├── controllers/
│
├── models/
│
├── views/
│
├── modules/
│   ├── dashboard/
│   ├── permits/
│   ├── reports/
│   ├── users/
│   └── authentication/
│
├── exports/
│
├── uploads/
│
├── database/
│   └── obo_pams.sql
│
├── index.php
└── README.md
```

---

# Installation

1. Install XAMPP or WAMP.
2. Clone or download the project.
3. Move the project into the `htdocs` directory.
4. Import the `obo_pams.sql` database into MySQL.
5. Update the database configuration in `config/database.php`.
6. Start Apache and MySQL.
7. Open the application in your browser.

Example:

```
http://localhost/obo-pams
```

---

# System Requirements

* PHP 8.0+
* MySQL 8.0+
* Apache Server
* XAMPP or WAMP
* Modern Web Browser (Chrome, Edge, Firefox)

---

# Benefits

* Faster permit encoding
* Centralized permit database
* Reduced paperwork
* Improved report generation
* Easier monitoring of permit applications
* Increased data accuracy
* Better operational efficiency

---

# Future Enhancements

* Online permit application portal
* Email notifications
* SMS notifications
* QR Code verification
* Barcode support
* Digital signatures
* GIS/Map integration
* Document attachment management
* Analytics dashboard
* Backup and restore functionality

---

# License

This project is intended for educational and institutional use. Modify and distribute according to your organization's policies.

---

# Authors

**Office of the Building Official (OBO)**

**Permit Application Management System (OBO-PAMS)**

Developed to modernize the permit encoding and management process through a secure, efficient, and user-friendly web application.
