# Permission System - Implementation & Usage Guide

## Created Migration Files

### 1. `create_permissions_table.php`

- **Location**: `database/migrations/2026_04_04_120001_create_permissions_table.php`
- **Columns**:
    - `id`: Primary key
    - `permission_code`: Unique code (e.g., `products.view`)
    - `permission_name`: Human-readable name
    - `module`: Category (e.g., Master Data, Stock-In)
    - `description`: Detailed description
    - `timestamps`: Created/updated at

### 2. `create_role_permissions_table.php`

- **Location**: `database/migrations/2026_04_04_120002_create_role_permissions_table.php`
- **Purpose**: Pivot table linking roles and permissions
- **Columns**:
    - `id`: Primary key
    - `role_id`: Foreign key to roles table
    - `permission_id`: Foreign key to permissions table
    - `timestamps`: Created/updated at
    - Unique constraint on `(role_id, permission_id)`

---

## Created Models

### 1. `Permission` Model

- **Location**: `app/Models/Permission.php`
- **Methods**:
    - `roles()`: BelongsToMany relationship to Role
    - Properties: `permission_code`, `permission_name`, `module`, `description`

### 2. Updated `Role` Model

- **Location**: `app/Models/Role.php`
- **New Methods**:
    - `permissions()`: BelongsToMany relationship to Permission
    - `hasPermission($code)`: Check if role has specific permission
    - `getPermissionCodes()`: Get array of all permission codes

### 3. Updated `User` Model

- **Location**: `app/Models/User.php`
- **New Permission Methods**:
    - `hasPermission($code)`: Check if user has permission
    - `hasAnyPermission($codes)`: Check if user has any of given permissions
    - `hasAllPermissions($codes)`: Check if user has all given permissions
    - `getPermissionCodes()`: Get all user's permission codes
    - `getRoleCode()`: Get user's role code
    - `isAdmin()`: Check if user is admin
    - `isLogisticStaff()`: Check if user is logistic staff

---

## Created Seeder

### `PermissionSeeder`

- **Location**: `database/seeders/PermissionSeeder.php`
- **Functionality**:
    - Creates all 45 permissions (if not exist)
    - Creates both roles (admin, logistic_staff) if not exist
    - Assigns permissions to roles based on ROLES_AND_PERMISSIONS.md matrix
    - Uses `firstOrCreate()` to prevent duplicates on re-run
    - Uses `sync()` to update permissions for existing roles

**Permissions Created**:

- Master Data (10 permissions)
- Stock-In (5 permissions)
- QR Labels (3 permissions)
- Consignment (5 permissions)
- Returns & Reconciliation (4 permissions)
- Disposal & Returns (3 permissions)
- Holding Area (2 permissions)
- Reporting & Analytics (6 permissions)
- Usage Summary & Integration (3 permissions)
- Audit & Governance (2 permissions)
- System Configuration (3 permissions)

---

## Running the Migrations & Seeder

### Step 1: Run Migrations

```bash
php artisan migrate
```

This will create `permissions` and `role_permissions` tables.

### Step 2: Run Seeders

```bash
php artisan db:seed
```

Or specifically:

```bash
php artisan db:seed --class=PermissionSeeder
```

This will:

1. Create all roles and permissions
2. Assign permissions to roles
3. Display summary in console

---

## Using Permissions in Code

### Check Permission on User

```php
$user = User::find(1);

// Single permission check
if ($user->hasPermission('products.view')) {
    // User can view products
}

// Multiple permission check (any)
if ($user->hasAnyPermission(['products.view', 'suppliers.view'])) {
    // User has at least one of these permissions
}

// Multiple permission check (all)
if ($user->hasAllPermissions(['products.view', 'products.create'])) {
    // User has all of these permissions
}

// Role checks
if ($user->isAdmin()) {
    // User is admin
}

if ($user->isLogisticStaff()) {
    // User is logistic staff
}

// Get all user's permissions
$permissions = $user->getPermissionCodes();
```

### Check Permission on Role

```php
$role = Role::where('role_code', 'admin')->first();

if ($role->hasPermission('audit.view_logs')) {
    // This role has this permission
}

$permissionCodes = $role->getPermissionCodes();
```

### Get Permissions with Details

```php
$user = User::find(1);
$permissions = $user->role->permissions()
    ->get()
    ->groupBy('module');

// Returns permissions grouped by module
```

---

## Authorization in Controllers

### Using Middleware (Future Implementation)

```php
// In routes/api.php
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('/products', [ProductController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'permission:products.view'])->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
});
```

### Using Gates (Future Implementation)

```php
// In AuthServiceProvider.php
Gate::define('view-products', function (User $user) {
    return $user->hasPermission('products.view');
});

Gate::define('manage-suppliers', function (User $user) {
    return $user->hasPermission('suppliers.manage');
});
```

### Using in Controllers

```php
public function index(Request $request)
{
    if (!$request->user()->hasPermission('products.view')) {
        abort(403, 'Unauthorized');
    }

    // Continue with logic
}
```

---

## Database Diagram

```
┌─────────────┐
│    users    │
├─────────────┤
│ id (PK)     │
│ role_id (FK)├─────────┐
│ ...         │         │
└─────────────┘         │
                        │ 1:N
                        │
                    ┌───┴──────────┐
                    │    roles     │
                    ├──────────────┤
                    │ id (PK)      │
                    │ role_code    │
                    │ role_name    │
                    └───┬──────────┘
                        │
                        │ N:M
              ┌─────────┴─────────┐
              │ role_permissions  │
              ├───────────────────┤
              │ id (PK)           │
              │ role_id (FK)      │
              │ permission_id (FK)│
              └─────────┬─────────┘
                        │
                        │ N:1
                    ┌───┴──────────────┐
                    │  permissions     │
                    ├──────────────────┤
                    │ id (PK)          │
                    │ permission_code  │
                    │ permission_name  │
                    │ module           │
                    │ description      │
                    └──────────────────┘
```

---

## Permission Codes Quick Reference

See [ROLES_AND_PERMISSIONS.md](ROLES_AND_PERMISSIONS.md) for complete matrix.

**Admin-only permissions**:

- `products.*` (all product management)
- `suppliers.manage`, `clients.manage`, `instrument_sets.manage`
- `stock_in.correct_confirmed`
- `consignments.edit_confirmed`
- `returns.reopen_reconciliation`
- `holding_area.assign_lot`
- `usage_summary.*`
- `audit.*`
- `system.*`

**Shared permissions** (both roles):

- `suppliers.view`, `clients.view`, `instrument_sets.view`
- `stock_in` (create, view, confirm, edit_draft)
- `qr_labels` (print, reprint, view_jobs)
- `consignments` (create, view, confirm, edit_draft)
- `returns` (create, view, finalize)
- `disposals` (create, view), `supplier_returns.create`
- `holding_area.view`
- `reports.*`

---

## Notes

1. **Idempotent Seeding**: The seeder uses `firstOrCreate()` for permissions and roles, so it's safe to run multiple times.
2. **Permission Sync**: Use `sync()` method to update role permissions, which removes old and adds new ones atomically.
3. **User Caching**: If implementing caching for permissions, remember to clear cache when role permissions change.
4. **Audit Logging**: Consider logging all permission checks and denials for security audit purposes.

---

## Next Steps

1. ✅ Create migrations
2. ✅ Create models with relationships
3. ✅ Create seeder
4. 📋 Create Authorization Middleware (optional)
5. 📋 Create Permission Gates (optional)
6. 📋 Create API endpoints for permission management (optional)
7. 📋 Add permission checks to controllers and routes
