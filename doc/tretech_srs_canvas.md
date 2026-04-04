# TRETECH Software Requirement Specification

## Cover
**Document:** TRETECH Inventory & Logistic System  
**Year:** 2026  
**Prepared By:** Irsyaduddin  
**Prepared For:** TREMED Surgical Solution Sdn Bhd

---

## Revision History
| Name | Date | Reason for Changes | Version |
|---|---|---|---|
| IRSYADUDDIN | 25/02/2026 | Initial creation of Software Requirements Specification (SRS) document | 1.0 |

---

## 1. Introduction

### 1.1 Purpose
This document defines the functional and non-functional requirements for the TRETECH Inventory & Logistics System, a hybrid web and mobile solution used to manage unit-level medical supply inventory, consignment workflows, reconciliation, and governance traceability.

This SRS covers the complete system scope for Version 1.0.

### 1.2 Intended Audience
- Client Representatives
- Developers
- Testers
- System Administrators

### 1.3 Project Scope
TRETECH shall:
- Track every unit (implant/instrument) as an individual record.
- Support Stock-In by session with mandatory Supplier Batch Code.
- Print internal QR labels after Stock-In confirmation.
- Support editable consignment notes (Admin only post-confirmation).
- Support Return Sessions with returned-only scanning.
- Automatically compute Used items upon reconciliation finalization.
- Provide disposal and return-to-supplier processing with mandatory reason logging.
- Provide a Holding Area for items missing Lot Number.
- Provide expiry intelligence (30/60/90 days).
- Provide Admin-only audit logs retained for at least 7 years.

**Out of Scope:**
- Full accounting (GL, AR/AP)
- Invoice issuing
- Procurement approvals
- Hospital EHR integration
- Offline-first operation

---

## 2. Overall Description

### 2.1 Product Perspective
TRETECH is a hybrid system composed of:
- Mobile Application (Flutter – Android primary) for scanning, stock operations, and printing.
- Web Application (React) for administration, reporting, and audit access.
- Backend API (Laravel) handling business logic and integrations.
- MySQL database with strict uniqueness and auditability enforcement.

### 2.2 User Classes and Characteristics
#### Admin
- Full access to configuration and master data.
- May edit consignment notes after confirmation.
- May assign Lot Numbers for Holding Area release.
- May reopen reconciliation with mandatory reason.
- May view audit logs.

#### Logistic Staff
- Perform Stock-In sessions.
- Create Consignment Notes.
- Execute Return Sessions.
- Reprint labels.
- Process disposal and return-to-supplier.

### 2.3 Operating Environment
- Android 10+ (configurable).
- Modern browsers (Chrome, Edge latest stable).
- Always-online network operation.
- Zywell Z909 label printer via Android Bluetooth.
- Printing via TSPL templates.

---

## 3. System Features

### 3.1 Master Data Governance
The system shall:
- Allow creation of Products with unique Reference Number (Ref Num).
- Enforce global uniqueness of Ref Num.
- Support Supplier registry CRUD.
- Support Client registry CRUD (Hospital / Other classification).
- Support Instrument Set Management.
- Generate Master Lot Number for sets lacking manufacturer identifiers.

### 3.2 Stock-In Session
The system shall:
- Allow creation of a Stock-In Session capturing Supplier, DO Number, Date/Time, and PIC.
- Require mandatory Supplier Batch Code per unit.
- Allow multiple Supplier Batch Codes within a single session.
- Support scanning of Lot Number and Expiry Date.
- Allow manual Lot/Expiry entry when scan fails (audit-logged).
- Enforce global uniqueness of Lot Number.
- Prevent duplicate capture within database or session.
- Allow review before confirmation.
- Set new units to status "Available" upon confirmation.
- Make Ref, Lot, Batch, and Expiry immutable after confirmation (Admin-only corrections audit-logged).

### 3.3 Internal Labelling & QR Printing
**QR Payload (v1 Canonical Format)**  
`V=1;REF={RefNum};LOT={LotNumber};BATCH={SupplierBatchCode};EXP={YYYY-MM-DD|-}`

**Rules:**
- REF, LOT, and BATCH are mandatory.
- EXP is either YYYY-MM-DD or "-".
- LOT is the primary lookup key.
- Invalid payloads must be rejected.
- Unknown LOT must be rejected.
- QR/DB mismatches must be blocked and require Admin resolution.

**Printing Requirements:**
- One label per unit upon confirmation.
- Support label reprint with mandatory reason.
- Track print job status (queued/printed/failed).
- Default label size: 40×60 mm landscape.
- TSPL commands over Bluetooth.

### 3.4 Consignment (Stock-Out)
The system shall:
- Allow creation of Consignment Note with destination, date/time, PIC.
- Allow scanning of units into consignment.
- Set status to "Supplied" upon confirmation.
- Prevent consignment of non-Available units.
- Allow Admin-only edits post-confirmation (audit-logged).

### 3.5 Return Session & Reconciliation
The system shall:
- Allow Return Session linked to Consignment Note.
- Accept scanning of returned items only.
- Validate eligibility (must be Supplied and belong to that note).
- Support partial returns.
- Compute Used Items only during Finalization.
- Used = Consigned − Returned.
- Lock Used units after finalization.
- Prevent over-return.
- Allow Admin reopen with mandatory reason (audit-logged).

### 3.6 Usage Summary & ERP Integration
The system shall:
- Generate Usage Summary at reconciliation finalization.
- Export PDF, CSV and XLSX.
- Push via REST API (POST JSON).
- Use Idempotency-Key header.
- Exclude pricing fields.
- Retry automatically for retryable errors.
- Log each push attempt.

### 3.7 Disposal & Return-to-Supplier
The system shall:
- Support unit-level disposal and return-to-supplier.
- Require mandatory reason.
- Provide categories: Expired, Damaged, Lost, Other.
- Maintain full movement history per unit.

### 3.8 Holding Area
The system shall:
- Place units without Lot Number into Holding status.
- Block Holding units from consignment.
- Allow Admin to assign Lot Number.
- Audit-log Lot assignment events.

### 3.9 Reporting & Analytics
The system shall provide:
- Stock-In analytics by supplier.
- Consignment reporting.
- Returns vs Used analysis.
- Disposal & loss reports.
- Expiry dashboards (30/60/90-day windows).
- Filtering by date, supplier, client, product reference.
- CSV and PDF export.

### 3.10 Governance & Audit
The system shall:
- Audit all create/update/delete and stock movements.
- Log User ID, Role, IP, Device ID, Timestamp, Action, Object Type, Object ID.
- Restrict audit log viewing to Admin only.
- Retain audit logs for at least 7 years.

### 3.11 Authentication & Access Control
The system shall:
- Support local user accounts.
- Enforce role-based access control.
- Enforce session timeout and login protections.

---

## 4. External Interface Requirements

### 4.1 User Interfaces
#### Mobile (Operational)
- Scan-first Stock-In flow.
- Consignment creation.
- Return sessions.
- Print status & retry handling.
- Disposal & return screens.

#### Web (Admin/Reporting)
- Dashboard (expiry alerts, pending reconciliations, failed prints).
- Master data CRUD.
- Stock Management.
- Inventory ledger search.
- Consignment & Return views.
- Audit log viewer.

### 4.2 Hardware Interfaces
- Zywell Z909 printer via Android Bluetooth.
- TSPL-compatible templates.
- 40×60 mm label support.

### 4.3 Software Interfaces
- CSV, XLSX and PDF export.
- REST API JSON push.
- HTTPS (TLS 1.2+).
- API Key or Bearer Token authentication.

---

## 5. Non-Functional Requirements

### 5.1 Performance
- Lot search with full history within 3 seconds under normal load.
- Scan-to-save average under 2 seconds.
- Support minimum 50 concurrent users.
- Support at least 500,000 unit records.
- Support at least 10 million audit logs.

### 5.2 Security
- All traffic over HTTPS.
- Strong password hashing.
- Role-based access enforcement.
- Login rate limiting.
- Session idle timeout (30 minutes).
- Secrets stored server-side only.

### 5.3 Availability & Backup
- Target 99% monthly uptime.
- Default database backup frequency: weekly. Daily backups available upon request and may incur additional cost.
- Minimum 30-day backup retention.
- RPO: 24 hours.
- RTO: 8 hours.

---

## Appendix A: Glossary
| Term | Definition |
|---|---|
| Unit | Single trackable item record |
| Lot Number | Globally unique unit identifier |
| Supplier Batch Code | Supplier batch identifier |
| Stock-In Session | Intake transaction grouping |
| Consignment Note | Stock-Out record |
| Return Session | Returned-only workflow |
| Usage Summary | Used items export dataset |
| Holding Area | Units missing Lot Number |

## Appendix B: Status Codes
- Available
- Supplied
- Used
- Disposed
- Holding

