# TRETECH API - Postman Testing Guide

## Table of Contents

1. [Setup & Configuration](#setup--configuration)
2. [Authentication](#authentication)
3. [Environment Variables](#environment-variables)
4. [Stock-In Endpoints](#stock-in-endpoints)
5. [Inventory Endpoints](#inventory-endpoints)
6. [QR Labels Endpoints](#qr-labels-endpoints)
7. [Print Jobs Endpoints](#print-jobs-endpoints)
8. [Audit Logs Endpoints](#audit-logs-endpoints)
9. [Error Logs Endpoints](#error-logs-endpoints)
10. [Testing Workflow](#testing-workflow)
11. [Error Handling](#error-handling)

---

## Setup & Configuration

### Prerequisites

- Postman installed (Desktop or Web version)
- Local Laravel backend running (`php artisan serve`)
- Database seeded with initial data
- User account with appropriate permissions

### Base URL

```
http://localhost:8000/api/v1
```

### Import Collection

1. Open Postman
2. Click **Import** → **Link** (or **File** if exported)
3. Use the JSON collection provided below, or manually create requests following this guide

---

## Authentication

### Sanctum Token Setup

#### Step 1: Get Authentication Token

**Request Type:** `POST`  
**Endpoint:** `http://localhost:8000/api/v1/auth/login`

**Body (JSON):**

```json
{
    "email": "admin@tretech.com",
    "password": "password"
}
```

**Response:**

```json
{
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "user": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@tretech.com",
        "roles": ["admin"],
        "permissions": ["stock_in.view", "stock_in.create", "inventory.view"]
    }
}
```

#### Step 2: Set Authorization Header

1. In Postman, open **Environment Variables** (bottom-left gear icon)
2. Set variable: `token` = `<paste_your_token_here>`
3. In **Authorization** tab of each request:
    - Type: **Bearer Token**
    - Token: `{{token}}`

---

## Environment Variables

### Create Postman Environment

1. Click **Environments** (left sidebar)
2. Click **Create New Environment**
3. Name it: **TRETECH-Dev**
4. Add the following variables:

| Variable       | Initial Value                  | Example                        |
| -------------- | ------------------------------ | ------------------------------ |
| `base_url`     | `http://localhost:8000/api/v1` | http://localhost:8000/api/v1   |
| `token`        | ``                             | eyJ0eXAiOiJKV1QiLCJhbGc...     |
| `client_id`    | `1`                            | 1                              |
| `supplier_id`  | `1`                            | 1                              |
| `product_id`   | `1`                            | 1                              |
| `session_id`   | `1`                            | 1 (update after creating)      |
| `lot_id`       | `1`                            | 1 (get from finalize response) |
| `qr_label_id`  | `1`                            | 1 (get from QR show response)  |
| `print_job_id` | `1`                            | 1 (get from print job create)  |
| `device_id`    | `mobile-printer-001`           | Mobile device identifier       |

### Usage in Requests

- Base URL in requests: `{{base_url}}`
- Token in headers: `{{token}}`
- Query parameters: `?client_id={{client_id}}`

---

## Stock-In Endpoints

### 1. List Stock-In Sessions

**GET** `{{base_url}}/stock-in-sessions`

**Authorization:** Bearer `{{token}}`

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `page` | integer | No | Page number (default: 1) |
| `per_page` | integer | No | Records per page (default: 15) |
| `status` | string | No | Filter by status: `draft`, `finalized` |
| `client_id` | integer | No | Filter by client |
| `sort` | string | No | Sort field (e.g., `-created_at`) |

**Example Request:**

```
GET {{base_url}}/stock-in-sessions?page=1&per_page=10&status=draft
```

**Expected Response (200 OK):**

```json
{
    "data": [
        {
            "id": 1,
            "session_no": "SIN-2026-001",
            "client_id": 1,
            "status": "draft",
            "total_items": 5,
            "created_at": "2026-04-06T10:00:00Z",
            "updated_at": "2026-04-06T10:15:00Z",
            "client": {
                "id": 1,
                "name": "Client A"
            }
        }
    ],
    "links": {
        "first": "http://localhost:8000/api/v1/stock-in-sessions?page=1",
        "last": "http://localhost:8000/api/v1/stock-in-sessions?page=1",
        "prev": null,
        "next": null
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 1,
        "per_page": 10,
        "total": 1
    }
}
```

---

### 2. Create Stock-In Session

**POST** `{{base_url}}/stock-in-sessions`

**Authorization:** Bearer `{{token}}`

**Body (JSON):**

```json
{
    "client_id": 1,
    "remarks": "Q2 2026 Stock In"
}
```

**Expected Response (201 Created):**

```json
{
    "data": {
        "id": 2,
        "session_no": "SIN-2026-002",
        "client_id": 1,
        "status": "draft",
        "total_items": 0,
        "created_at": "2026-04-06T10:30:00Z",
        "updated_at": "2026-04-06T10:30:00Z",
        "client": {
            "id": 1,
            "name": "Client A"
        }
    }
}
```

---

### 3. Get Stock-In Session Details

**GET** `{{base_url}}/stock-in-sessions/{{session_id}}`

**Authorization:** Bearer `{{token}}`

**Expected Response (200 OK):**

```json
{
    "data": {
        "id": 1,
        "session_no": "SIN-2026-001",
        "client_id": 1,
        "status": "draft",
        "total_items": 5,
        "remarks": "Q1 Stock In",
        "created_at": "2026-04-06T10:00:00Z",
        "updated_at": "2026-04-06T10:15:00Z",
        "client": {
            "id": 1,
            "name": "Client A"
        },
        "items": [
            {
                "id": 1,
                "session_id": 1,
                "product_id": 1,
                "lot_number": "LOT-001",
                "quantity": 100,
                "unit_price": 25.5,
                "supplier_id": 1,
                "reference_number": "REF-001",
                "created_at": "2026-04-06T10:10:00Z",
                "product": {
                    "id": 1,
                    "name": "Product A"
                }
            }
        ]
    }
}
```

---

### 4. Update Stock-In Session

**PATCH** `{{base_url}}/stock-in-sessions/{{session_id}}`

**Authorization:** Bearer `{{token}}`

**Body (JSON):**

```json
{
    "remarks": "Updated remarks"
}
```

**Expected Response (200 OK):**

```json
{
    "data": {
        "id": 1,
        "session_no": "SIN-2026-001",
        "client_id": 1,
        "status": "draft",
        "remarks": "Updated remarks",
        "total_items": 5,
        "created_at": "2026-04-06T10:00:00Z",
        "updated_at": "2026-04-06T10:35:00Z",
        "client": {
            "id": 1,
            "name": "Client A"
        }
    }
}
```

---

### 5. Review Stock-In Session

**POST** `{{base_url}}/stock-in-sessions/{{session_id}}/review`

**Authorization:** Bearer `{{token}}`

**Body (JSON):**

```json
{
    "remarks": "Reviewed and ready for finalization"
}
```

**Expected Response (200 OK):**

```json
{
    "data": {
        "id": 1,
        "session_no": "SIN-2026-001",
        "status": "reviewed",
        "message": "Session marked as reviewed"
    }
}
```

---

### 6. Finalize Stock-In Session

**POST** `{{base_url}}/stock-in-sessions/{{session_id}}/finalize`

**Authorization:** Bearer `{{token}}`

**Body (JSON):**

```json
{
    "remarks": "Finalization completed"
}
```

**Expected Response (200 OK):**

```json
{
    "data": {
        "id": 1,
        "session_no": "SIN-2026-001",
        "status": "finalized",
        "total_items": 5,
        "created_lots_count": 5,
        "message": "Session finalized successfully",
        "created_lots": [
            {
                "id": 101,
                "lot_number": "LOT-001",
                "product_id": 1,
                "quantity": 100
            }
        ]
    }
}
```

---

### 7. List Session Items

**GET** `{{base_url}}/stock-in-sessions/{{session_id}}/items`

**Authorization:** Bearer `{{token}}`

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `page` | integer | No | Page number |
| `per_page` | integer | No | Records per page |

**Expected Response (200 OK):**

```json
{
    "data": [
        {
            "id": 1,
            "session_id": 1,
            "product_id": 1,
            "lot_number": "LOT-001",
            "quantity": 100,
            "unit_price": 25.5,
            "supplier_id": 1,
            "reference_number": "REF-001",
            "created_at": "2026-04-06T10:10:00Z",
            "product": {
                "id": 1,
                "name": "Product A"
            }
        }
    ]
}
```

---

### 8. Add Stock-In Item

**POST** `{{base_url}}/stock-in-sessions/{{session_id}}/items`

**Authorization:** Bearer `{{token}}`

**Body (JSON):**

```json
{
    "product_id": 1,
    "lot_number": "LOT-002",
    "quantity": 50,
    "unit_price": 30.0,
    "supplier_id": 1,
    "reference_number": "REF-002"
}
```

**Expected Response (201 Created):**

```json
{
    "data": {
        "id": 2,
        "session_id": 1,
        "product_id": 1,
        "lot_number": "LOT-002",
        "quantity": 50,
        "unit_price": 30.0,
        "supplier_id": 1,
        "reference_number": "REF-002",
        "created_at": "2026-04-06T10:40:00Z",
        "product": {
            "id": 1,
            "name": "Product A"
        }
    }
}
```

---

### 9. Update Stock-In Item

**PATCH** `{{base_url}}/stock-in-sessions/{{session_id}}/items/{{item_id}}`

**Authorization:** Bearer `{{token}}`

**Body (JSON):**

```json
{
    "quantity": 75,
    "unit_price": 28.0
}
```

**Expected Response (200 OK):**

```json
{
    "data": {
        "id": 1,
        "session_id": 1,
        "product_id": 1,
        "lot_number": "LOT-001",
        "quantity": 75,
        "unit_price": 28.0,
        "supplier_id": 1,
        "reference_number": "REF-001",
        "updated_at": "2026-04-06T10:45:00Z",
        "product": {
            "id": 1,
            "name": "Product A"
        }
    }
}
```

---

### 10. Delete Stock-In Item

**DELETE** `{{base_url}}/stock-in-sessions/{{session_id}}/items/{{item_id}}`

**Authorization:** Bearer `{{token}}`

**Expected Response (204 No Content):**

```
(empty body)
```

---

## Inventory Endpoints

### 1. List Inventory Units (with Filters)

**GET** `{{base_url}}/inventory-units`

**Authorization:** Bearer `{{token}}`

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `page` | integer | No | Page number (default: 1) |
| `per_page` | integer | No | Records per page (default: 15) |
| `status` | string | No | Filter by lot status: `active`, `on_hold`, `used`, `disposed` |
| `supplier_id` | integer | No | Filter by supplier ID |
| `product_id` | integer | No | Filter by product ID |
| `instrument_set_id` | integer | No | Filter by instrument set ID |
| `expiry_from` | string | No | Earliest expiry date (Y-m-d format) |
| `expiry_to` | string | No | Latest expiry date (Y-m-d format) |
| `sort` | string | No | Sort field with direction (e.g., `-expiry_date`, `created_at`) |

**Example Requests:**

```
GET {{base_url}}/inventory-units?page=1&status=active&supplier_id=1
GET {{base_url}}/inventory-units?expiry_from=2026-04-01&expiry_to=2026-12-31&per_page=20
GET {{base_url}}/inventory-units?instrument_set_id=5&sort=-expiry_date
```

**Expected Response (200 OK):**

```json
{
    "data": [
        {
            "id": 1,
            "lot_number": "LOT-001",
            "original_lot_number": "LOT-001-ORIG",
            "is_system_generated_lot": false,
            "ref_num": "REF-001",
            "quantity": 100,
            "available_quantity": 85,
            "used_quantity": 10,
            "disposed_quantity": 5,
            "expiry_date": "2027-04-06",
            "status": "active",
            "current_location_type": "warehouse",
            "current_location_id": 1,
            "remarks": "Stock from Q2 2026",
            "received_at": "2026-04-06T10:00:00Z",
            "supplier_id": 1,
            "product_id": 1,
            "instrument_set_id": null,
            "supplier": {
                "id": 1,
                "name": "Supplier A"
            },
            "product": {
                "id": 1,
                "name": "Product A",
                "sku": "SKU-001",
                "product_type": "medical_supply",
                "uom": "units"
            },
            "instrument_set": null,
            "qr_label": {
                "id": 1,
                "qr_payload": "V=1;REF=REF-001;LOT=LOT-001;BATCH=BATCH-2026-Q2;EXP=2027-04-06",
                "generated_at": "2026-04-06T10:05:00Z"
            },
            "lot_holding": null,
            "lot_movements_count": 3
        }
    ],
    "links": {
        "first": "http://localhost:8000/api/v1/inventory-units?page=1",
        "last": "http://localhost:8000/api/v1/inventory-units?page=1",
        "prev": null,
        "next": null
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 1
    }
}
```

---

### 2. Get Inventory Summary (Dashboard Cards)

**GET** `{{base_url}}/inventory-units/summary`

**Authorization:** Bearer `{{token}}`

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `supplier_id` | integer | No | Filter summary by supplier |
| `product_id` | integer | No | Filter summary by product |
| `instrument_set_id` | integer | No | Filter summary by instrument set |

**Expected Response (200 OK):**

```json
{
    "data": {
        "total": 1250,
        "available": 850,
        "supplied": 1200,
        "used": 180,
        "disposed": 70,
        "on_hold": 150
    }
}
```

**Usage:** Use these counts for dashboard cards showing inventory breakdown by status.

---

### 3. List Expiring Soon Items

**GET** `{{base_url}}/inventory-units/expiring-soon`

**Authorization:** Bearer `{{token}}`

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `days` | integer | No | Days until expiry (default: 30) |
| `page` | integer | No | Page number (default: 1) |
| `per_page` | integer | No | Records per page (default: 15) |
| `supplier_id` | integer | No | Filter by supplier ID |
| `product_id` | integer | No | Filter by product ID |
| `sort` | string | No | Sort by field (e.g., `expiry_date` for earliest first) |

**Example Requests:**

```
GET {{base_url}}/inventory-units/expiring-soon?days=30
GET {{base_url}}/inventory-units/expiring-soon?days=60&supplier_id=1&sort=expiry_date
```

**Expected Response (200 OK):**

```json
{
    "data": [
        {
            "id": 5,
            "lot_number": "LOT-005",
            "expiry_date": "2026-05-15",
            "quantity": 120,
            "available_quantity": 100,
            "days_until_expiry": 39,
            "status": "active",
            "product": {
                "id": 2,
                "name": "Product B",
                "sku": "SKU-002"
            },
            "supplier": {
                "id": 1,
                "name": "Supplier A"
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "total": 5
    }
}
```

**Usage:** Use for inventory expiry alert system; reorder stock or plan disposals.

---

### 4. Get Inventory Unit Details (with Full Audit Trail)

**GET** `{{base_url}}/inventory-units/{{lot_id}}`

**Authorization:** Bearer `{{token}}`

**Expected Response (200 OK):**

```json
{
    "data": {
        "id": 1,
        "lot_number": "LOT-001",
        "original_lot_number": "LOT-001-ORIG",
        "is_system_generated_lot": false,
        "ref_num": "REF-001",
        "quantity": 100,
        "available_quantity": 85,
        "used_quantity": 10,
        "disposed_quantity": 5,
        "expiry_date": "2027-04-06",
        "status": "active",
        "current_location_type": "warehouse",
        "current_location_id": 1,
        "received_at": "2026-04-06T10:00:00Z",
        "remarks": "Stock from Q2 2026",
        "product_id": 1,
        "supplier_id": 1,
        "instrument_set_id": null,
        "product": {
            "id": 1,
            "name": "Product A",
            "sku": "SKU-001",
            "category": "Electronics",
            "product_type": "medical_supply",
            "uom": "units"
        },
        "supplier": {
            "id": 1,
            "name": "Supplier A",
            "contact": "contact@supplier.com"
        },
        "instrument_set": null,
        "qr_label": {
            "id": 1,
            "qr_payload": "V=1;REF=REF-001;LOT=LOT-001;BATCH=BATCH-2026-Q2;EXP=2027-04-06",
            "generated_at": "2026-04-06T10:05:00Z",
            "generated_by_user_id": 1
        },
        "lot_holding": null,
        "lot_movements_count": 3,
        "lot_movements": [
            {
                "id": 1,
                "lot_id": 1,
                "movement_type": "INBOUND",
                "reference_type": "stock_in_session",
                "reference_id": 1,
                "from_status": null,
                "to_status": "active",
                "from_location": null,
                "to_location": "warehouse_A",
                "quantity_moved": 100,
                "performed_at": "2026-04-06T10:00:00Z",
                "performed_by_user_id": 1,
                "remarks": "Stock In from supplier",
                "performed_by_user": {
                    "id": 1,
                    "name": "Admin User"
                }
            }
        ]
    }
}
```

**Error Response (404 Not Found):**

```json
{
    "message": "Lot not found"
}
```

---

### 5. Lookup Inventory by Lot Number

**GET** `{{base_url}}/inventory-units/lookup/by-lot/{{lot_number}}`

**Authorization:** Bearer `{{token}}`

**Example Request:**

```
GET {{base_url}}/inventory-units/lookup/by-lot/LOT-001
```

**Expected Response (200 OK):**

```json
{
    "data": {
        "id": 1,
        "lot_number": "LOT-001",
        "ref_num": "REF-001",
        "quantity": 100,
        "available_quantity": 85,
        "status": "active",
        "expiry_date": "2027-04-06",
        "product": {
            "id": 1,
            "name": "Product A",
            "sku": "SKU-001"
        },
        "supplier": {
            "id": 1,
            "name": "Supplier A"
        },
        "qr_label": {
            "qr_payload": "V=1;REF=REF-001;LOT=LOT-001;BATCH=BATCH-2026-Q2;EXP=2027-04-06",
            "generated_at": "2026-04-06T10:05:00Z"
        },
        "lot_holding": null
    }
}
```

---

### 6. Lookup Inventory by Reference Number

**GET** `{{base_url}}/inventory-units/lookup/by-ref/{{ref_number}}`

**Authorization:** Bearer `{{token}}`

**Example Request:**

```
GET {{base_url}}/inventory-units/lookup/by-ref/REF-001
```

**Expected Response (200 OK) - Single Reference:**

```json
{
    "data": {
        "id": 1,
        "lot_number": "LOT-001",
        "ref_num": "REF-001",
        "quantity": 100,
        "status": "active",
        "product": {
            "id": 1,
            "name": "Product A"
        },
        "qr_label": {
            "qr_payload": "V=1;REF=REF-001;LOT=LOT-001;BATCH=BATCH-2026-Q2;EXP=2027-04-06"
        }
    }
}
```

**Expected Response (200 OK) - Multiple References:**

```json
{
    "data": [
        {
            "id": 1,
            "lot_number": "LOT-001",
            "ref_num": "REF-001",
            "quantity": 100,
            "status": "active"
        },
        {
            "id": 2,
            "lot_number": "LOT-002",
            "ref_num": "REF-001",
            "quantity": 50,
            "status": "active"
        }
    ]
}
```

---

### 7. Get Per-Lot Movement Timeline

**GET** `{{base_url}}/inventory-units/{{lot_id}}/movements`

**Authorization:** Bearer `{{token}}`

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `page` | integer | No | Page number (default: 1) |
| `per_page` | integer | No | Records per page (default: 15) |
| `movement_type` | string | No | Filter by type: `INBOUND`, `OUTBOUND`, `RETURN`, `HOLD`, etc. |
| `from_date` | string | No | Start date (Y-m-d) |
| `to_date` | string | No | End date (Y-m-d) |
| `sort` | string | No | Sort field (default: `-performed_at` for newest first) |

**Example Requests:**

```
GET {{base_url}}/inventory-units/1/movements
GET {{base_url}}/inventory-units/1/movements?movement_type=OUTBOUND&sort=-performed_at
GET {{base_url}}/inventory-units/1/movements?from_date=2026-04-01&to_date=2026-04-30
```

**Expected Response (200 OK):**

```json
{
    "data": [
        {
            "id": 3,
            "lot_id": 1,
            "movement_type": "OUTBOUND",
            "reference_type": "return_session",
            "reference_id": 5,
            "from_status": "active",
            "to_status": "used",
            "from_location": "warehouse_A",
            "to_location": "disposal",
            "quantity_moved": 10,
            "performed_at": "2026-04-08T14:30:00Z",
            "performed_by_user_id": 2,
            "remarks": "Return from client due to expiry",
            "performed_by_user": {
                "id": 2,
                "name": "Manager User"
            }
        },
        {
            "id": 2,
            "lot_id": 1,
            "movement_type": "INBOUND",
            "reference_type": "stock_in_session",
            "reference_id": 1,
            "from_status": null,
            "to_status": "active",
            "from_location": null,
            "to_location": "warehouse_A",
            "quantity_moved": 100,
            "performed_at": "2026-04-06T10:00:00Z",
            "performed_by_user_id": 1,
            "remarks": "Stock In from supplier",
            "performed_by_user": {
                "id": 1,
                "name": "Admin User"
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "total": 3
    }
}
```

---

### 8. Inventory Movement Ledger (Global)

**GET** `{{base_url}}/inventory-ledger`

**Authorization:** Bearer `{{token}}`

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `page` | integer | No | Page number |
| `per_page` | integer | No | Records per page |
| `lot_id` | integer | No | Filter by lot ID |
| `lot_number` | string | No | Filter by lot number |
| `movement_type` | string | No | `INBOUND`, `OUTBOUND`, `RETURN`, `HOLD`, etc. |
| `from_date` | string | No | Start date (Y-m-d) |
| `to_date` | string | No | End date (Y-m-d) |

**Example Request:**

```
GET {{base_url}}/inventory-ledger?lot_id=1&movement_type=INBOUND&from_date=2026-04-01&to_date=2026-04-30
```

**Expected Response (200 OK):**

```json
{
    "data": [
        {
            "id": 1,
            "lot_id": 1,
            "lot_number": "LOT-001",
            "movement_type": "INBOUND",
            "reference_type": "stock_in_session",
            "reference_id": 1,
            "from_status": null,
            "to_status": "active",
            "from_location": null,
            "to_location": "warehouse_A",
            "quantity_moved": 100,
            "performed_at": "2026-04-06T10:00:00Z",
            "performed_by_user_id": 1,
            "remarks": "Stock In from supplier",
            "performed_by_user": {
                "id": 1,
                "name": "Admin User"
            },
            "lot": {
                "id": 1,
                "lot_number": "LOT-001",
                "product_id": 1,
                "quantity": 100,
                "product": {
                    "id": 1,
                    "name": "Product A"
                }
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "total": 15
    }
}
```

---

## QR Labels Endpoints

### 1. Get or Create QR Label for Lot

**GET** `{{base_url}}/qr-labels/{{lot_id}}`

**Authorization:** Bearer `{{token}}`

**Description:** Idempotent endpoint that fetches the QR label if it exists, or creates one if not. This is safe to call multiple times without side effects.

**Expected Response (200 OK - Label Exists):**

```json
{
    "data": {
        "id": 1,
        "lot_id": 1,
        "qr_payload": "V=1;REF=REF-001;LOT=LOT-001;BATCH=BATCH-2026-Q2;EXP=2027-04-06",
        "generated_at": "2026-04-06T10:05:00Z",
        "generated_by_user_id": 1,
        "lot": {
            "id": 1,
            "lot_number": "LOT-001",
            "product_id": 1,
            "quantity": 100,
            "expiry_date": "2027-04-06"
        }
    }
}
```

**Expected Response (201 Created - New Label Generated):**

```json
{
    "data": {
        "id": 2,
        "lot_id": 2,
        "qr_payload": "V=1;REF=REF-002;LOT=LOT-002;BATCH=BATCH-2026-Q2;EXP=2027-04-06",
        "generated_at": "2026-04-06T10:10:00Z",
        "generated_by_user_id": 1,
        "lot": {
            "id": 2,
            "lot_number": "LOT-002"
        }
    }
}
```

**QR Payload Format Reference:**

```
V=1;REF={RefNumber};LOT={LotNumber};BATCH={ManufacturingDate};EXP={YYYY-MM-DD (or - if no expiry)}
```

**Usage:** Call after finalization to ensure every lot has a corresponding QR label. Used by mobile apps for barcode generation and printing.

---

### 2. Preview QR Label (Without Creating)

**GET** `{{base_url}}/qr-labels/{{lot_id}}/preview`

**Authorization:** Bearer `{{token}}`

**Description:** Generates QR payload and TSPL printer commands without persisting to database. Useful for testing, previewing, or debugging label generation.

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `printer_name` | string | No | Name of printer (for TSPL sizing) |
| `label_width` | integer | No | Label width in mm (default: 40) |
| `label_height` | integer | No | Label height in mm (default: 30) |

**Expected Response (200 OK):**

```json
{
    "data": {
        "lot_id": 1,
        "qr_payload": "V=1;REF=REF-001;LOT=LOT-001;BATCH=BATCH-2026-Q2;EXP=2027-04-06",
        "tspl_payload": "SIZE 40 mm, 30 mm\nBART 10,10,25,10,LOT001\nTEXT 5,50,0,1,1,1,REF-001\nTEXT 5,70,0,1,1,1,EXP:2027-04-06\nPRINT 1\n",
        "qr_code_data": "V=1;REF=REF-001;LOT=LOT-001;BATCH=BATCH-2026-Q2;EXP=2027-04-06",
        "label_config": {
            "width_mm": 40,
            "height_mm": 30,
            "has_expiry": true,
            "format": "TSPL"
        }
    }
}
```

**Usage:** For testing label layout before printing, or for mobile apps to preview what label will produce without database changes.

---

## Print Jobs Endpoints

### 1. List Print Jobs (with Filters & Status Polling)

**GET** `{{base_url}}/print-jobs`

**Authorization:** Bearer `{{token}}`

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `page` | integer | No | Page number (default: 1) |
| `per_page` | integer | No | Records per page (default: 15) |
| `status` | string | No | Filter by status: `queued`, `printed`, `failed` |
| `action_type` | string | No | Filter by action: `print`, `reprint` |
| `device_id` | string | No | Filter by device ID (e.g., mobile printer device_id) |
| `lot_id` | integer | No | Filter by lot ID |
| `from_date` | string | No | Start date (Y-m-d) |
| `to_date` | string | No | End date (Y-m-d) |
| `sort` | string | No | Sort field (default: `-requested_at`) |

**Example Requests:**

```
GET {{base_url}}/print-jobs?status=queued&device_id=mobile-printer-001
GET {{base_url}}/print-jobs?device_id={{device_id}}&status=queued
GET {{base_url}}/print-jobs?action_type=reprint&sort=-requested_at
```

**Expected Response (200 OK):**

```json
{
    "data": [
        {
            "id": 1,
            "lot_id": 1,
            "action_type": "print",
            "status": "queued",
            "printer_name": "Mobile Printer A",
            "device_id": "mobile-printer-001",
            "tspl_payload": "SIZE 40 mm, 30 mm\nBART 10,10,25,10,LOT001\nTEXT 5,50,0,1,1,1,REF-001\nPRINT 1\n",
            "requested_at": "2026-04-06T10:05:00Z",
            "requested_by_user_id": 1,
            "printed_at": null,
            "failed_at": null,
            "error_message": null,
            "lot": {
                "id": 1,
                "lot_number": "LOT-001",
                "product_id": 1
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "total": 5
    }
}
```

**Usage (Mobile Workflow):**

1. Mobile app polls this endpoint with `device_id={{device_id}}&status=queued` every 2-5 seconds
2. Receives TSPL commands in `tspl_payload` field
3. Sends TSPL to BLE printer via Bluetooth Low Energy
4. Calls mark-printed or mark-failed endpoint

---

### 2. Get Single Print Job (with TSPL Payload)

**GET** `{{base_url}}/print-jobs/{{print_job_id}}`

**Authorization:** Bearer `{{token}}`

**Expected Response (200 OK):**

```json
{
    "data": {
        "id": 1,
        "lot_id": 1,
        "action_type": "print",
        "status": "queued",
        "printer_name": "Mobile Printer A",
        "device_id": "mobile-printer-001",
        "tspl_payload": "SIZE 40 mm, 30 mm\nBART 10,10,25,10,LOT001\nTEXT 5,50,0,1,1,1,REF-001\nTEXT 5,70,0,1,1,1,EXP:2027-04-06\nPRINT 1\n",
        "reprint_reason": null,
        "requested_at": "2026-04-06T10:05:00Z",
        "requested_by_user_id": 1,
        "printed_at": null,
        "failed_at": null,
        "error_message": null,
        "lot": {
            "id": 1,
            "lot_number": "LOT-001",
            "product": {
                "id": 1,
                "name": "Product A"
            }
        },
        "requested_by_user": {
            "id": 1,
            "name": "Admin User"
        }
    }
}
```

---

### 3. Create Print Job (Manual or After Finalization)

**POST** `{{base_url}}/print-jobs`

**Authorization:** Bearer `{{token}}`

**Body (JSON):**

```json
{
    "lot_id": 1,
    "device_id": "mobile-printer-001",
    "printer_name": "Mobile Printer A",
    "action_type": "print"
}
```

**Expected Response (201 Created):**

```json
{
    "data": {
        "id": 2,
        "lot_id": 1,
        "action_type": "print",
        "status": "queued",
        "printer_name": "Mobile Printer A",
        "device_id": "mobile-printer-001",
        "tspl_payload": "SIZE 40 mm, 30 mm\nBART 10,10,25,10,LOT001\nTEXT 5,50,0,1,1,1,REF-001\nPRINT 1\n",
        "requested_at": "2026-04-06T10:15:00Z",
        "requested_by_user_id": 1,
        "status": "queued"
    }
}
```

**Notes:**

- Auto-generates TSPL commands based on QR label data
- Queued status ready for mobile polling
- Device ID used to route to correct physical printer

---

### 4. Create Reprint Job (with Mandatory Reason)

**POST** `{{base_url}}/print-jobs/reprint`

**Authorization:** Bearer `{{token}}`

**Body (JSON):**

```json
{
    "lot_id": 1,
    "device_id": "mobile-printer-001",
    "printer_name": "Mobile Printer A",
    "reprint_reason": "First label smudged during print, reprinting for label quality"
}
```

**Validation Rules:**

- `reprint_reason` required, minimum 5 characters
- `lot_id` must be valid
- `device_id` must be provided for routing

**Expected Response (201 Created):**

```json
{
    "data": {
        "id": 3,
        "lot_id": 1,
        "action_type": "reprint",
        "status": "queued",
        "printer_name": "Mobile Printer A",
        "device_id": "mobile-printer-001",
        "tspl_payload": "SIZE 40 mm, 30 mm\nBART 10,10,25,10,LOT001\nTEXT 5,50,0,1,1,1,REF-001\nPRINT 1\n",
        "reprint_reason": "First label smudged during print, reprinting for label quality",
        "requested_at": "2026-04-06T10:20:00Z",
        "requested_by_user_id": 1,
        "status": "queued"
    }
}
```

**Error Response (422 Validation Error):**

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "reprint_reason": ["The reprint_reason must be at least 5 characters."]
    }
}
```

---

### 5. Mark Print Job as Successfully Printed

**PATCH** `{{base_url}}/print-jobs/{{print_job_id}}/mark-printed`

**Authorization:** Bearer `{{token}}`

**Body (JSON):**

```json
{
    "remarks": "Label printed successfully on thermal printer"
}
```

**Expected Response (200 OK):**

```json
{
    "data": {
        "id": 1,
        "lot_id": 1,
        "status": "printed",
        "action_type": "print",
        "printed_at": "2026-04-06T10:22:00Z",
        "requested_at": "2026-04-06T10:05:00Z",
        "error_message": null,
        "lot": {
            "id": 1,
            "lot_number": "LOT-001"
        }
    }
}
```

**Mobile Workflow:**

1. After BLE printer returns success signal
2. Mobile app calls this endpoint to confirm
3. Backend transitions status from `queued` to `printed`
4. Records completion timestamp for auditing

---

### 6. Mark Print Job as Failed

**PATCH** `{{base_url}}/print-jobs/{{print_job_id}}/mark-failed`

**Authorization:** Bearer `{{token}}`

**Body (JSON):**

```json
{
    "error_message": "Bluetooth connection lost, printer offline"
}
```

**Validation Rules:**

- `error_message` required, minimum 5 characters, max 500 characters

**Expected Response (200 OK):**

```json
{
    "data": {
        "id": 1,
        "lot_id": 1,
        "status": "failed",
        "action_type": "print",
        "requested_at": "2026-04-06T10:05:00Z",
        "printed_at": null,
        "failed_at": "2026-04-06T10:23:00Z",
        "error_message": "Bluetooth connection lost, printer offline",
        "lot": {
            "id": 1,
            "lot_number": "LOT-001"
        }
    }
}
```

**Error Response (422 Validation Error):**

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "error_message": ["The error_message must be at least 5 characters."]
    }
}
```

**Mobile Workflow:**

1. If BLE printer returns error or times out
2. Mobile app captures error message
3. Calls this endpoint to log failure
4. Backend stores error and failed timestamp
5. Print job can be retried via create reprint job endpoint

---

## Audit Logs Endpoints

> **Permission required:** `system.manage_roles` (admin only)

### List Audit Logs

**Request Type:** `GET`  
**Endpoint:** `{{base_url}}/audit-logs`

**Headers:**

```
Authorization: Bearer {{auth_token}}
Accept: application/json
```

**Query Parameters (all optional):**

| Parameter        | Type         | Description                                 |
| ---------------- | ------------ | ------------------------------------------- |
| `user_id`        | integer      | Filter by user who performed the action     |
| `auditable_type` | string       | Model class (e.g. `App\Models\StockIn`)     |
| `auditable_id`   | integer      | ID of the audited record                    |
| `action_type`    | string       | Action constant (e.g. `stock_in.finalized`) |
| `ip_address`     | string       | Filter by originating IP                    |
| `device_id`      | string       | Filter by mobile device identifier          |
| `from_date`      | date (Y-m-d) | Start of date range                         |
| `to_date`        | date (Y-m-d) | End of date range                           |
| `per_page`       | integer      | Records per page (default: 20)              |

**Example URL:**

```
{{base_url}}/audit-logs?action_type=stock_in.finalized&from_date=2025-01-01&per_page=10
```

**Successful Response (200):**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "action_type": "stock_in.finalized",
            "description": "Stock-in session SI-20250101-001 finalized — 5 lot(s) created.",
            "auditable_type": "App\\Models\\StockIn",
            "auditable_id": 12,
            "user_id": 2,
            "role_code_snapshot": "warehouse_staff",
            "ip_address": "127.0.0.1",
            "device_id": null,
            "before_json": null,
            "after_json": {
                "status": "finalized",
                "total_lots": 5,
                "confirmed_at": "2025-01-01T10:00:00+00:00"
            },
            "server_timestamp": "2025-01-01T10:00:00+00:00",
            "created_at": "2025-01-01T10:00:00+00:00",
            "user": {
                "id": 2,
                "full_name": "Jane Doe",
                "email": "jane@tretech.com"
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 20,
        "total": 58
    }
}
```

---

### Get Single Audit Log

**Request Type:** `GET`  
**Endpoint:** `{{base_url}}/audit-logs/{{audit_log_id}}`

**Headers:**

```
Authorization: Bearer {{auth_token}}
Accept: application/json
```

**Successful Response (200):**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "action_type": "stock_in.finalized",
        "description": "Stock-in session SI-20250101-001 finalized — 5 lot(s) created.",
        "auditable_type": "App\\Models\\StockIn",
        "auditable_id": 12,
        "user_id": 2,
        "role_code_snapshot": "warehouse_staff",
        "ip_address": "127.0.0.1",
        "device_id": null,
        "before_json": null,
        "after_json": {
            "status": "finalized",
            "total_lots": 5,
            "confirmed_at": "2025-01-01T10:00:00+00:00"
        },
        "server_timestamp": "2025-01-01T10:00:00+00:00",
        "created_at": "2025-01-01T10:00:00+00:00",
        "user": {
            "id": 2,
            "full_name": "Jane Doe",
            "email": "jane@tretech.com"
        }
    }
}
```

**Error Response (404):**

```json
{
    "success": false,
    "message": "Audit log not found."
}
```

---

### Action Type Reference

Common values for the `action_type` filter:

| Domain      | Action Type                                                                                |
| ----------- | ------------------------------------------------------------------------------------------ |
| Stock-In    | `stock_in.created`, `stock_in.finalized`                                                   |
| Lot         | `lot.created`, `lot.moved`, `lot.held`, `lot.released`                                     |
| QR Labels   | `qr_label.created`, `qr_label.reprinted`                                                   |
| Print Jobs  | `print_job.created`, `print_job.printed`, `print_job.marked_failed`, `print_job.reprinted` |
| Users       | `user.logged_in`, `user.logged_out`, `user.created`, `user.updated`                        |
| Master Data | `product.created`, `product.updated`, `supplier.created`, `client.created`                 |

---

## Error Logs Endpoints

> **Permission required:** `system.manage_roles` (admin only)

### List Error Logs

**Request Type:** `GET`  
**Endpoint:** `{{base_url}}/error-logs`

**Headers:**

```
Authorization: Bearer {{auth_token}}
Accept: application/json
```

**Query Parameters (all optional):**

| Parameter   | Type         | Description                                                          |
| ----------- | ------------ | -------------------------------------------------------------------- |
| `source`    | string       | Subsystem that generated the error (e.g. `app`, `stock_in.finalize`) |
| `source_id` | string       | ID of the related record                                             |
| `from_date` | date (Y-m-d) | Start of date range                                                  |
| `to_date`   | date (Y-m-d) | End of date range                                                    |
| `per_page`  | integer      | Records per page (default: 20)                                       |

**Example URL:**

```
{{base_url}}/error-logs?source=app&from_date=2025-01-01
```

**Successful Response (200):**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "source": "app",
            "source_id": null,
            "message": "SQLSTATE[23000]: Integrity constraint violation",
            "details": {
                "exception": "Illuminate\\Database\\QueryException",
                "file": "/var/www/app/Services/StockIn/StockInFinalizeService.php",
                "line": 82,
                "url": "http://localhost:8000/api/v1/stock-in-sessions/12/finalize",
                "method": "POST",
                "user": 2
            },
            "created_at": "2025-01-01T10:05:30+00:00"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 20,
        "total": 1
    }
}
```

---

### Get Single Error Log

**Request Type:** `GET`  
**Endpoint:** `{{base_url}}/error-logs/{{error_log_id}}`

**Headers:**

```
Authorization: Bearer {{auth_token}}
Accept: application/json
```

**Successful Response (200):**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "source": "app",
        "source_id": null,
        "message": "SQLSTATE[23000]: Integrity constraint violation",
        "details": {
            "exception": "Illuminate\\Database\\QueryException",
            "file": "/var/www/app/Services/StockIn/StockInFinalizeService.php",
            "line": 82,
            "trace": [
                "#0 /var/www/vendor/laravel/framework/.../Connection.php(760)",
                "..."
            ],
            "url": "http://localhost:8000/api/v1/stock-in-sessions/12/finalize",
            "method": "POST",
            "user": 2
        },
        "created_at": "2025-01-01T10:05:30+00:00"
    }
}
```

**Error Response (404):**

```json
{
    "success": false,
    "message": "Error log not found."
}
```

---

## Testing Workflow

### Recommended Testing Sequence

#### Phase 1: Authentication

1. ✅ Get authentication token ([POST] `/auth/login`)
2. ✅ Save token in environment variable

#### Phase 2: Stock-In Session Workflow

1. ✅ Create new session ([POST] `/stock-in-sessions`)
2. ✅ Save `session_id` in environment variable
3. ✅ Get session details ([GET] `/stock-in-sessions/{{session_id}}`)
4. ✅ Add items ([POST] `/stock-in-sessions/{{session_id}}/items`)
5. ✅ Update item ([PATCH] `/stock-in-sessions/{{session_id}}/items/{{item_id}}`)
6. ✅ List items ([GET] `/stock-in-sessions/{{session_id}}/items`)
7. ✅ Review session ([POST] `/stock-in-sessions/{{session_id}}/review`)
8. ✅ **Finalize session** ([POST] `/stock-in-sessions/{{session_id}}/finalize`) — **This auto-creates lots & print jobs**
9. ✅ Save `lot_id` from finalize response to environment variables
10. ✅ List sessions ([GET] `/stock-in-sessions`)

#### Phase 3: QR Labels Workflow

1. ✅ Create/Get QR Label ([GET] `/qr-labels/{{lot_id}}`) — idempotent, creates if doesn't exist
2. ✅ Save `qr_label_id` from response
3. ✅ Preview QR Label ([GET] `/qr-labels/{{lot_id}}/preview?printer_name=test`) — test TSPL commands without database changes
4. ✅ Verify label payload format: `V=1;REF=...;LOT=...;BATCH=...;EXP=...`

**Expected: After finalize, lot should automatically have a QR label created**

#### Phase 4: Print Jobs Workflow (Mobile Testing)

1. ✅ List queued print jobs ([GET] `/print-jobs?status=queued&device_id={{device_id}}`) — simulate mobile polling
2. ✅ Get single print job ([GET] `/print-jobs/{{print_job_id}}`) — verify TSPL payload
3. ✅ Create manual print job ([POST] `/print-jobs`) — test on-demand printing
4. ✅ Save `print_job_id` from response
5. ✅ Create reprint job ([POST] `/print-jobs/reprint`) — test reprint workflow with reason
6. ✅ Mark printed ([PATCH] `/print-jobs/{{print_job_id}}/mark-printed`) — confirm successful print
7. ✅ Create another print job and Mark failed ([PATCH] `/print-jobs/{{print_job_id}}/mark-failed`) — test error handling

**Expected: TSPL payload ready to send to BLE thermal printer immediately**

#### Phase 5: Inventory Queries

1. ✅ List inventory units ([GET] `/inventory-units`) — verify newly created lots appear
2. ✅ Test filters: `status=active`, `supplier_id=1`, `expiry_from=2026-04-01&expiry_to=2027-12-31`
3. ✅ Get inventory summary ([GET] `/inventory-units/summary`) — dashboard counts
4. ✅ List expiring soon ([GET] `/inventory-units/expiring-soon?days=30`) — alert system
5. ✅ Get unit details ([GET] `/inventory-units/{{lot_id}}`) — full audit trail including qr_label and lot_movements
6. ✅ Lookup by lot number ([GET] `/inventory-units/lookup/by-lot/LOT-001`)
7. ✅ Lookup by ref number ([GET] `/inventory-units/lookup/by-ref/REF-001`)
8. ✅ Get per-lot movements ([GET] `/inventory-units/{{lot_id}}/movements`) — per-lot timeline
9. ✅ View ledger ([GET] `/inventory-ledger?lot_id={{lot_id}}`)
10. ✅ Test ledger filters: `movement_type=INBOUND`, date ranges

**Expected: Finalization creates movement records for all new lots**

---

## Mobile App Print Job Polling Workflow

This workflow documents how a Flutter mobile app (or any client) should interact with the print job endpoints:

### Initial Setup

1. Mobile app stores `device_id` in persistent storage (UUID or device identifier)
2. On app launch, register with backend or use static device_id

### Print Job Lifecycle (from Mobile App Perspective)

```
┌─────────────────────┐
│   Stock-In Finalized│ ← Backend creates lots, QR labels, print jobs
└──────────┬──────────┘
           │
           ▼
┌──────────────────────────┐
│ Poll /print-jobs?       │ ← Check for queued jobs every 2-5 seconds
│   status=queued&        │
│   device_id=<my_id>     │
└──────────┬───────────────┘
           │
           ├─→ Return [] ? Wait and retry
           │
           └─→ Return jobs with tspl_payload
               │
               ▼
┌──────────────────────────┐
│ Send TSPL to BLE Printer│ ← Via Bluetooth Low Energy
└──────────┬───────────────┘
           │
           ├─→ Success?     → PATCH /print-jobs/{id}/mark-printed
           │
           └─→ Error?       → PATCH /print-jobs/{id}/mark-failed
```

### Polling Strategy

**Endpoint:** `GET {{base_url}}/print-jobs?status=queued&device_id={{device_id}}&per_page=10`

```javascript
// Pseudo-code for mobile app
async function pollPrintJobs() {
    const url = `${BASE_URL}/print-jobs?status=queued&device_id=${deviceId}`;

    while (true) {
        const response = await fetch(url, {
            headers: { Authorization: `Bearer ${token}` },
        });

        const jobs = response.json().data;

        if (jobs.length > 0) {
            for (const job of jobs) {
                await sendTsplToPrinter(job.tspl_payload);
                await markPrinterStatus(job.id);
            }
        }

        // Wait 3 seconds before next poll
        await sleep(3000);
    }
}
```

### Error Handling in Mobile

**Scenario 1: BLE Printer Offline**

```javascript
try {
    await printer.write(tsplPayload);
    // Success
    await markPrinted(printJobId);
} catch (error) {
    // Bluetooth failed
    await markFailed(printJobId, error.message);
    // Can retry later via reprint endpoint
}
```

**Scenario 2: Retry Failed Print Jobs**

```javascript
// User taps "Retry" button on failed job
const response = await fetch(`${BASE_URL}/print-jobs/reprint`, {
    method: "POST",
    body: JSON.stringify({
        lot_id: failedJob.lot_id,
        device_id: deviceId,
        printer_name: printerName,
        reprint_reason: "User manually retried failed print job",
    }),
});
```

### Test with Postman (Simulating Mobile)

1. **Set device_id in environment:**
    - Open Environments → TRETECH-Dev
    - Add/Update: `device_id` = `mobile-printer-001`

2. **Simulate polling loop:**

    ```
    GET {{base_url}}/print-jobs?status=queued&device_id={{device_id}}
    ```

    Repeat this request in Collection Runner with `3 second` delay

3. **Simulate marking printed:**

    ```
    PATCH {{base_url}}/print-jobs/{{print_job_id}}/mark-printed
    ```

4. **Verify status changed:**
    ```
    GET {{base_url}}/print-jobs?status=printed&device_id={{device_id}}
    ```

---

## Error Handling

### Common HTTP Status Codes

| Code    | Scenario                       | Example Response                          |
| ------- | ------------------------------ | ----------------------------------------- |
| **200** | Success (GET/PATCH)            | See endpoint responses above              |
| **201** | Created (POST)                 | See endpoint responses above              |
| **204** | No Content (DELETE)            | Empty response                            |
| **400** | Bad Request                    | `{"message": "Invalid query parameters"}` |
| **401** | Unauthorized (no token)        | `{"message": "Unauthenticated"}`          |
| **403** | Forbidden (missing permission) | `{"message": "Unauthorized action"}`      |
| **404** | Resource not found             | `{"message": "Lot not found"}`            |
| **422** | Validation error               | See below                                 |
| **500** | Server error                   | `{"message": "Server error"}`             |

### Validation Error Response (422)

**Example:**

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "client_id": ["The client_id field is required."],
        "lot_number": [
            "The lot_number has already been taken for this session."
        ]
    }
}
```

### Authentication Error

**Scenario:** Missing or invalid token

```json
{
    "message": "Unauthenticated"
}
```

**Fix:**

1. Get new token from `/auth/login`
2. Update `{{token}}` in environment variables
3. Ensure Authorization header is set to `Bearer {{token}}`

### Permission Error

**Scenario:** User lacks required permission

```json
{
    "message": "Unauthorized action"
}
```

**Fix:**

1. Ensure user has `stock_in.view` or `inventory.view` permission
2. Check user roles in database:
    ```sql
    SELECT p.* FROM permissions p
    JOIN role_permission rp ON p.id = rp.permission_id
    JOIN user_role ur ON rp.role_id = ur.role_id
    WHERE ur.user_id = 1;
    ```

---

## Tips & Tricks

### 1. Bulk Testing with Collections

- Create a Postman Collection with all endpoints
- Use **Tests** tab to automatically assert responses
- Run entire collection with **Collection Runner**

### 2. Environment Variables for Dynamic Values

```javascript
// In request body
{
  "client_id": "{{client_id}}",
  "product_id": "{{product_id}}"
}

// In URL
GET {{base_url}}/stock-in-sessions?page=1&per_page={{per_page}}
```

### 3. Extract Values from Response

In **Tests** tab of a request:

```javascript
// Save session_id for next request
pm.environment.set("session_id", pm.response.json().data.id);

// Save token from login response
pm.environment.set("token", pm.response.json().token);
```

### 4. Test Status Code

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
```

### 5. Test Response Structure

```javascript
pm.test("Response has data field", function () {
    pm.expect(pm.response.json()).to.have.property("data");
});
```

### 6. Date Range Testing

For ledger endpoint with date filters:

```
GET {{base_url}}/inventory-ledger?from_date=2026-04-01&to_date=2026-04-30
```

Dates should be in `YYYY-MM-DD` format.

---

## Troubleshooting

### Issue: 401 Unauthenticated

- **Cause:** Missing or expired token
- **Solution:** Re-run login endpoint, update `{{token}}` variable

### Issue: 404 Not Found

- **Cause:** Resource doesn't exist or wrong ID
- **Solution:** Verify ID exists in database, double-check endpoint URL

### Issue: 422 Validation Error

- **Cause:** Invalid or missing required fields
- **Solution:** Check error message for specific field, review "Add Item" or "Create Session" examples above

### Issue: Database Lock

- **Cause:** Concurrent requests during finalization
- **Solution:** Wait for finalization to complete before running other requests on same session

### Issue: Data Not Updated

- **Cause:** Using old environment variable values
- **Solution:** Refresh environment variables from database or re-run dependent requests

---

## Database Queries for Testing

### View All Sessions

```sql
SELECT * FROM stock_in_sessions ORDER BY created_at DESC LIMIT 10;
```

### View All Lots

```sql
SELECT l.id, l.lot_number, l.ref_num, p.name, l.quantity, l.status
FROM lots l
JOIN products p ON l.product_id = p.id
ORDER BY l.created_at DESC
LIMIT 10;
```

### View Movement Ledger

```sql
SELECT lm.id, lm.lot_id, l.lot_number, lm.movement_type, lm.created_at
FROM lot_movements lm
JOIN lots l ON lm.lot_id = l.id
ORDER BY lm.created_at DESC
LIMIT 20;
```

### Check User Permissions

```sql
SELECT DISTINCT p.name
FROM permissions p
JOIN role_permission rp ON p.id = rp.permission_id
JOIN roles r ON rp.role_id = r.id
JOIN user_role ur ON r.id = ur.role_id
WHERE ur.user_id = 1;
```

---

## Postman Collection Export

Save this JSON as `TRETECH-API.postman_collection.json` and import into Postman:

[See "Postman Collection Template" section below for complete JSON]

---

## Contact & Support

For API issues or questions:

- Check `/doc/API_RESPONSE_STANDARD.md`
- Check `/doc/EXCEPTION_HANDLING.md`
- Review test cases in `/tests/Feature/Api/V1/InventoryEndpointsTest.php`
