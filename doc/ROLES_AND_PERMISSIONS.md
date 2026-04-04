# TRETECH Roles and Permissions Matrix

**Document Version:** 1.0  
**Last Updated:** 2026-04-04  
**Based on:** tretech_srs_canvas.md v1.0

---

## 1. Role Overview

### 1.1 Admin

**Role Code:** `admin`  
**Description:** Full system access with configuration, master data management, and audit capabilities.

**Key Capabilities:**

- Full access to configuration and master data
- Edit consignment notes after confirmation (audit-logged)
- Assign Lot Numbers for Holding Area release
- Reopen reconciliation with mandatory reason (audit-logged)
- View audit logs (7-year retention)
- Correct immutable fields after confirmation (Ref, Lot, Batch, Expiry) with audit logging

### 1.2 Logistic Staff

**Role Code:** `logistic_staff`  
**Description:** Operational user performing stock and consignment workflows.

**Key Capabilities:**

- Perform Stock-In sessions
- Create Consignment Notes
- Execute Return Sessions
- Reprint labels with mandatory reason
- Process disposal and return-to-supplier
- View own activity and basic reporting

---

## 2. Detailed Permission Matrix

| Module                          | Permission                                   | Code                            | Admin | Logistic Staff |
| ------------------------------- | -------------------------------------------- | ------------------------------- | ----- | -------------- |
| **Master Data**                 | View Products                                | `products.view`                 | ✅    | ❌             |
|                                 | Create Products                              | `products.create`               | ✅    | ❌             |
|                                 | Edit Products                                | `products.edit`                 | ✅    | ❌             |
|                                 | Delete Products                              | `products.delete`               | ✅    | ❌             |
|                                 | View Suppliers                               | `suppliers.view`                | ✅    | ✅             |
|                                 | Manage Suppliers                             | `suppliers.manage`              | ✅    | ❌             |
|                                 | View Clients                                 | `clients.view`                  | ✅    | ✅             |
|                                 | Manage Clients                               | `clients.manage`                | ✅    | ❌             |
|                                 | View Instrument Sets                         | `instrument_sets.view`          | ✅    | ✅             |
|                                 | Manage Instrument Sets                       | `instrument_sets.manage`        | ✅    | ❌             |
| **Stock-In**                    | Create Stock-In Session                      | `stock_in.create`               | ✅    | ✅             |
|                                 | View Stock-In Sessions                       | `stock_in.view`                 | ✅    | ✅             |
|                                 | Confirm Stock-In Session                     | `stock_in.confirm`              | ✅    | ✅             |
|                                 | Edit Stock-In (Pre-confirmation)             | `stock_in.edit_draft`           | ✅    | ✅             |
|                                 | Correct Immutable Fields (Post-confirmation) | `stock_in.correct_confirmed`    | ✅    | ❌             |
| **QR Labels**                   | Print QR Labels                              | `qr_labels.print`               | ✅    | ✅             |
|                                 | Reprint QR Labels                            | `qr_labels.reprint`             | ✅    | ✅             |
|                                 | View Print Jobs                              | `qr_labels.view_jobs`           | ✅    | ✅             |
| **Consignment**                 | Create Consignment Note                      | `consignments.create`           | ✅    | ✅             |
|                                 | View Consignment Notes                       | `consignments.view`             | ✅    | ✅             |
|                                 | Confirm Consignment Note                     | `consignments.confirm`          | ✅    | ✅             |
|                                 | Edit Consignment (Pre-confirmation)          | `consignments.edit_draft`       | ✅    | ✅             |
|                                 | Edit Consignment (Post-confirmation)         | `consignments.edit_confirmed`   | ✅    | ❌             |
| **Returns & Reconciliation**    | Create Return Session                        | `returns.create`                | ✅    | ✅             |
|                                 | View Return Sessions                         | `returns.view`                  | ✅    | ✅             |
|                                 | Finalize Return Session                      | `returns.finalize`              | ✅    | ✅             |
|                                 | Reopen Reconciliation                        | `returns.reopen_reconciliation` | ✅    | ❌             |
| **Disposal & Returns**          | Dispose Units                                | `disposals.create`              | ✅    | ✅             |
|                                 | Return Units to Supplier                     | `supplier_returns.create`       | ✅    | ✅             |
|                                 | View Disposal/Return History                 | `disposals.view`                | ✅    | ✅             |
| **Holding Area**                | View Holding Area Units                      | `holding_area.view`             | ✅    | ✅             |
|                                 | Assign Lot Number                            | `holding_area.assign_lot`       | ✅    | ❌             |
| **Reporting & Analytics**       | View Stock Analytics                         | `reports.stock_analytics`       | ✅    | ✅             |
|                                 | View Consignment Reports                     | `reports.consignments`          | ✅    | ✅             |
|                                 | View Returns vs Used Analysis                | `reports.returns_analysis`      | ✅    | ✅             |
|                                 | View Disposal Reports                        | `reports.disposal`              | ✅    | ✅             |
|                                 | View Expiry Dashboard                        | `reports.expiry`                | ✅    | ✅             |
|                                 | Export Reports (CSV/XLSX/PDF)                | `reports.export`                | ✅    | ✅             |
| **Usage Summary & Integration** | View Usage Summary                           | `usage_summary.view`            | ✅    | ❌             |
|                                 | Generate Usage Summary                       | `usage_summary.generate`        | ✅    | ❌             |
|                                 | View Push Logs                               | `usage_summary.view_logs`       | ✅    | ❌             |
| **Audit & Governance**          | View Audit Logs                              | `audit.view_logs`               | ✅    | ❌             |
|                                 | Export Audit Logs                            | `audit.export_logs`             | ✅    | ❌             |
| **System Configuration**        | Configure System Settings                    | `system.configure`              | ✅    | ❌             |
|                                 | Manage User Accounts                         | `system.manage_users`           | ✅    | ❌             |
|                                 | Manage Roles & Permissions                   | `system.manage_roles`           | ✅    | ❌             |

---

## 3. Action-Level Audit Logging Rules

The following actions **MUST** be audit-logged regardless of user role:

- ✅ Stock-In confirmation
- ✅ Consignment confirmation
- ✅ Consignment post-confirmation edits (Admin)
- ✅ Return Session finalization
- ✅ Reconciliation reopening (Admin, with mandatory reason)
- ✅ Lot Number assignment in Holding Area (Admin)
- ✅ Immutable field corrections (Admin)
- ✅ Label reprint (with reason)
- ✅ Disposal and return-to-supplier (with reason)
- ✅ All master data CRUD operations
- ✅ User login/logout and failed login attempts
- ✅ Permission denials and access violations

**Audit Log Required Fields:**

- User ID
- Role
- IP Address
- Device ID
- Timestamp
- Action (Create, Update, Delete, Correct, Reopen, etc.)
- Object Type (Stock-In, Consignment, Unit, etc.)
- Object ID
- Change Details (if applicable)
- Reason (if applicable)

---

## 4. Business Rule Enforcement by Permission

### 4.1 Stock-In Session

- **Logistic Staff** can: Create, view, scan items, confirm
- **Admin** can: All Plus, correct Ref/Lot/Batch/Expiry post-confirmation
- **Rule:** Immutable fields (Ref, Lot, Batch, Expiry) locked after confirmation except for Admin corrections

### 4.2 Consignment Notes

- **Logistic Staff** can: Create, view, scan items, confirm (draft editing only)
- **Admin** can: All Plus, edit confirmed consignments (audit-logged)
- **Rule:** Cannot consign non-Available units

### 4.3 Return Sessions & Reconciliation

- **Logistic Staff** can: Create, view, scan returns, finalize
- **Admin** can: All Plus, reopen finalized reconciliations (with mandatory reason)
- **Rule:** Used items computed only during finalization; Used = Consigned − Returned

### 4.4 Holding Area

- **Logistic Staff** can: View units in Holding status
- **Admin** can: Assign Lot Numbers (audit-logged)
- **Rule:** Holding units blocked from consignment until Lot assigned

### 4.5 Audit Log Access

- **Logistic Staff**: NO access to audit logs
- **Admin**: Full audit log viewing and export (7-year retention minimum)

---

## 5. Implementation Notes

### 5.1 Database Schema

Consider these tables for role-permission system:

```
roles
├── id
├── role_code (unique) — admin, logistic_staff
├── role_name
└── timestamps

permissions
├── id
├── permission_code (unique) — e.g., products.view
├── permission_name
├── module
└── description

role_permissions (pivot)
├── role_id
└── permission_id

user_roles (pivot)
├── user_id
├── role_id
└── assigned_at
```

### 5.2 Middleware/Gates Implementation

- Create role-checking middleware (e.g., `role:admin`)
- Create permission-checking gates (e.g., `can('products.view')`)
- Enforce on all endpoints before business logic execution

### 5.3 Session Timeout

- Enforce 30-minute idle session timeout globally (non-negotiable security requirement)
- Log forced logout events in audit logs

### 5.4 Login Protections

- Implement login rate limiting (e.g., max 5 failed attempts per IP per 15 minutes)
- Log all login attempts (successful and failed) with IP and Device ID
- Lock account after repeated failed attempts (configurable threshold)

---

## 6. Future Expansion

If granular role customization becomes necessary:

1. Support dynamic role creation with selected permissions (Admin-only)
2. Maintain audit trail of role/permission changes
3. Consider role hierarchies or delegation patterns

---

## Appendix: Quick Reference by Role

### Admin Unique Permissions

- `products.*` (all product management)
- `suppliers.manage`, `clients.manage`, `instrument_sets.manage`
- `stock_in.correct_confirmed`
- `consignments.edit_confirmed`
- `returns.reopen_reconciliation`
- `holding_area.assign_lot`
- `usage_summary.*`
- `audit.*`
- `system.*`

### Logistic Staff Unique Permissions

- None (all Logistic permissions are subset of Admin)

### Common Permissions (Both Roles)

- View/Create/Confirm Stock-In Sessions
- View/Create/Confirm Consignment Notes
- Create/View Return Sessions and finalize
- QR label printing and reprinting
- Disposal and supplier returns
- Basic reporting and analytics
