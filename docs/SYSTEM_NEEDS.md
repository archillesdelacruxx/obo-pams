# OBO-PAMS — System Requirements & Documentation

**Office of the Building Official — Permit Application Management System**

This document describes the current state of the system after the security,
functional, and mobile audit-and-fix pass. It is written in English and is the
authoritative reference for setup, roles, modules, the security model, and the
mobile API.

---

## 1. Overview

OBO-PAMS is a web + mobile system used by the Office of the Building Official
(OBO) to:

- Encode and manage permit applications (order of payment → workflow →
  approval → releasing).
- Run on-site ocular inspection checklists (web and mobile).
- Review, approve, or reject inspection checklists.
- Manage team leaders, announcements, notifications, users, and reports.

### Two surfaces

| Surface | Stack | Purpose |
|---------|-------|---------|
| **Web app** | PHP 8, MySQL/MariaDB, vanilla JS, custom CSS | All staff: encoding, workflow, approvals, releasing, inspections, management. Session-based auth. |
| **Mobile app** | React Native / Expo (SDK 57), expo-sqlite | Inspectors: offline-first inspection checklists, photos, review (Approve/Reject), notifications. Token-based auth. |

---

## 2. Architecture

```
obo-pams/
├── api/                 # JSON API (web + mobile)
│   ├── index.php        # Module/action router (single entry point)
│   ├── login.php        # Mobile token login
│   ├── logout.php
│   └── uploads.php      # File uploads (photos, signatures)
├── assets/
│   ├── css/             # Shared styles
│   └── js/              # api.js (CSRF-aware client), app.js, user-app.js, etc.
├── config/
│   ├── app.php          # MODULES map, session/lockout constants, encryption key/method
│   ├── database.php     # DB connection (getDB())
│   └── encryption.php   # AES-256-CBC encrypt/decrypt helpers
├── includes/
│   ├── auth.php         # Sessions, roles, permissions, login lockout
│   ├── csrf.php         # CSRF token generation/validation, csrfMeta()
│   ├── functions.php    # jsonResponse, logActivity, signatures, exports
│   ├── session.php, user-shell.php, admin-shell.php, dev-shell.php
│   └── XlsxWriter.php   # Excel export
├── pages/               # Web screens (admin/developer + pages/user/* for staff)
├── sql/
│   ├── schema.sql       # Full consolidated schema + seed data (authoritative)
│   └── *.sql            # Idempotent incremental migrations (kept for reference)
├── uploads/             # inspection_photos/, inspection_signatures/, profile_photos/
└── mobile/              # Expo app (src/api, src/db, src/screens, src/navigation)
```

### Single API router

`api/index.php` is a single entry point:

```
?module=<module>&action=<action>
```

- Every case checks `requirePermission(...)` (server-side RBAC).
- Every mutating (non-read-only) route requires a valid **CSRF token** for the
  web session, **unless** the request is authenticated with a valid mobile
  Bearer token (which sets `$_SESSION['api_token_auth'] = true`).
- All responses are JSON via `jsonResponse()`. Errors are generic; details go
  to the PHP error log only.

---

## 3. Roles & Access Control

| Role | Admin flag | Effective access |
|------|-----------|------------------|
| `developer` | is_admin=1 | Everything, including system settings, module availability, AI key, announcements, staff reports. |
| `admin` | is_admin=1 | Everything except the `developer`-only system-management gates. |
| `admin_aid` | is_admin=0 | Encoding modules only (`MODULES_ENCODING`). |
| `inspector-admin` | is_admin=0 | Inspection modules + team leaders (granted via `user_permissions`). |
| `inspector` | is_admin=0 | Inspection modules (`MODULES_INSPECTION`). |

### Registration hierarchy

| Registrar | Can register |
|-----------|--------------|
| developer | admin, admin_aid, inspector-admin, inspector |
| admin     | admin_aid |
| inspector-admin | inspector |
| admin_aid, inspector | none |

### Module groups

- `MODULES_ENCODING` (admin_aid): order-of-payment, op-records, permit-workflow,
  workflow-details, permit-approval-encoding, permit-approval-records, releasing,
  releasing-records.
- `MODULES_INSPECTION` (inspector): inspection-checklist, inspection-reports,
  inspection-review, inspection-edit, inspection-delete, team-leaders.
- `admin` / `developer` / `inspector-admin`: unrestricted — all modules assignable.
- Always visible: dashboard, notifications, announcements, profile, settings.

### Key permission gates

- `requireSystemAdmin()` — developer/admin **only** (excludes admin_aid):
  settings/modules, settings/ai-get, settings/ai-save, settings/toggle-module,
  announcements create/delete, dashboard/export-csv, dashboard/staff-summary.
- `canManageTeamLeaders()` — developer/admin/inspector-admin or explicit
  `team-leaders` grant.
- `inspection-edit` — required to edit *any* inspection record and to review
  (Approve/Reject). Without it, an inspector may only edit their **own** records
  while status is `Draft` or `Rejected`.
- `inspection-delete` — required to delete inspection records.
- `export/csv` — per-table permission gate.

---

## 4. Modules

| Module | Web page | Notes |
|--------|----------|-------|
| Dashboard | `pages/dashboard.php`, `pages/user/dashboard.php` | Stats, recent records, Generate Report → `reports.php`. |
| Order of Payment | `pages/user/order-of-payment.php`, `op-records.php` | CRUD + records. |
| Permit Workflow | `pages/user/permit-workflow.php`, `workflow-details.php` | Multi-round workflow; supports "no last out" rounds. |
| Permit Approval | `pages/user/permit-approval-encoding.php`, `permit-approval-records.php` | CRUD + records. |
| Releasing | `pages/user/releasing.php`, `releasing-records.php` | CRUD + records. |
| Inspection Checklist | `pages/user/inspection-checklist.php` | Web checklist list + detail + review. |
| Inspection Review | `pages/user/inspection-review.php` | Under-Review queue (Approve/Reject). |
| Monitoring Reports | `pages/user/inspection-reports.php`, `pages/reports.php` | Dashboard/generate-report entry. |
| Team Leaders | `pages/user/team-leaders.php` | Roster teams 1 & 2 (synced to mobile). |
| Announcements | `pages/user/announcements.php` | Notifications fan-out to users. |
| Notifications | `pages/notifications.php` | Mark read / mark all read. |
| Activity Logs | `pages/activity-logs.php` | Admin audit trail. |
| User Management | `pages/user-management.php` | Role-aware user CRUD, permissions. |
| Settings | `pages/settings.php`, `pages/user/settings.php` | System settings + AI key (system admin only). Profile merged into Settings for non-developers ("Profile Settings"). |
| Modules / Module Access | `pages/modules.php`, `pages/module-access.php` | Developer-only module availability + per-user grants. |

---

## 5. Security Model

### Web (session)

- Sessions regenerated on login; `SESSION_LIFETIME` (2h) enforced server-side.
- **CSRF**: every page head includes
  `<meta name="csrf-token" content="...">`; `api.js` injects `_csrf_token` into
  every JSON/FormData POST. The API rejects mutating requests without a valid
  token (403). Login form also validates CSRF.
- **RBAC** on every API case and page.
- **Passwords**: bcrypt (`password_hash`/`password_verify`).
- **Login lockout**: 6 failed attempts → 5-minute lock (`login_attempts`),
  returned to the mobile client as `locked` / `locked_until` (Unix seconds).

### Mobile (Bearer token)

- `api/login.php` authenticates **inspector accounts only** and issues a
  random 64-char token (`api_tokens`, 12h / 30 days if "remember").
- Requests send `Authorization: Bearer <token>`; the API validates it against
  `api_tokens` and expiry, then bypasses CSRF.
- Expired/revoked tokens → `401` → mobile signs the user out automatically.

### Data

- AI provider key (`system_settings.ai_api_key`) is stored **encrypted**
  (AES-256-CBC, `config/encryption.php`) and decrypted at read time.
- All dynamic HTML output is escaped (`escape()`); XSS surfaces reviewed.
- File uploads validated with `getimagesize()` and restricted extensions;
  `uploads/` directories block PHP execution via `.htaccess`.
- All queries use prepared statements.

---

## 6. Database

Authoritative schema: `sql/schema.sql` (regenerated to match the live DB).

Tables:

- `users`, `user_permissions`, `login_attempts`, `api_tokens`
- `order_of_payments`
- `permit_workflows`, `workflow_rounds` (includes `no_last_out`)
- `permit_approvals`, `releasing_plans`
- `notifications`, `announcements`, `comments`, `activity_logs`, `system_settings`
- `inspection_schedules`, `team_leaders`, `inspection_records`
  (includes `permit_date_issued`, `team_leader_1/2` FKs to `team_leaders`),
  `inspection_template_items`, `inspection_results`, `inspection_photos`

### Seed accounts (ids preserved)

| id | username | role | is_admin |
|----|----------|------|----------|
| 1 | admin | developer | 1 |
| 2 | archillesdc | inspector | 0 |
| 3 | archillesdcc | developer | 1 |
| 4 | awayann | inspector-admin | 0 |
| 5 | archelldc | admin | 1 |

Passwords match the live deployment; `admin`'s password is `admin123`.

---

## 7. Mobile App

### Offline-first sync

`mobile/src/db/sync.ts` runs on a timer and on demand:

1. **Push** local `pending`/`error` records → `checklist/create` or
   `checklist/update`, then photo uploads, then `checklist/submit` when the
   local status is `Under Review`.
2. **Push review decisions** — a record reviewed on-device
   (Approve/Reject) is flagged `review_pending` and pushed via
   `checklist/review` / `checklist/reject`, gated by `inspection-edit`.
3. **Pull** the admin's web review decisions (`sync/pull`) back to the device.
4. **Pull** team leaders (`teamleaders/roster`) and the checklist template
   (`inspection/template`) into local SQLite.

### Key screens

- `LoginScreen` — lockout countdown ticks live from `locked_until`.
- `DashboardScreen` — stats, recent checklists, notifications modal (bell).
- `InspectionFormScreen` / `useInspectionForm` — multi-step checklist form;
  locked records (status other than Draft/Rejected) are read-only on-device
  and cannot be saved.
- `InspectionDetailScreen` — view + Approve/Reject (requires `inspection-edit`),
  which syncs to the web.

### Mobile database

SQLite (`pams.db`) via `expo-sqlite`, schema in `src/db/database.ts`
(`DATABASE_VERSION = 4`). Tables: `template_items`, `team_leaders`,
`inspection_records`, `inspection_results`, `inspection_photos`.

---

## 8. Installation (fresh setup)

1. Install XAMPP (Apache + MySQL/MariaDB, PHP 8+).
2. Copy the project into `htdocs/obo-pams`.
3. Create the database and schema:
   ```
   mysql -uroot -e "source C:/xampp/htdocs/obo-pams/sql/schema.sql" pams_db
   ```
   (or run `schema.sql` from any client that supports `source`).
4. Verify `config/database.php` credentials.
5. Start Apache + MySQL, open `http://localhost/obo-pams/`, log in as `admin`.
6. (Optional) Set the Groq AI key in Settings → System Settings → AI.
   The field is blank by default and must be re-entered if ever cleared.
7. Mobile: `cd mobile && npm install && npx expo start`. Use the dev build
   (`npx expo run:android` / `run:ios`) for full file-system access.

### Incremental migrations

Older one-off files in `sql/` (e.g. `permit-workflow-columns.sql`) are
idempotent and safe to run on any existing install; `schema.sql` already
includes all of their changes.

---

## 9. Common operational notes

- **Groq AI key**: stored encrypted; if the field ever shows blank, re-enter it.
- **Review permissions**: to let an inspector review on the mobile app, grant
  them `inspection-edit` in User Management / Module Access.
- **Team leaders**: managed on the web; mobile pulls the roster on sync.
- **Template**: edited on the web (`inspection_template_items`); mobile pulls
  it on sync.
- **Export** (`dashboard/export-csv`) is restricted to system admins.