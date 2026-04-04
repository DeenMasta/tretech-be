# API Response Standard & Base Middleware

## Overview

The project now includes standardized API responses and base middleware for consistent API handling.

## Files Created

### 1. **ApiResponse Class**

- **File**: `app/Http/Responses/ApiResponse.php`
- Core response formatting class with static methods for various response types

### 2. **ApiResponseTrait**

- **File**: `app/Http/Traits/ApiResponseTrait.php`
- Use this trait in your controllers for easy access to response methods

### 3. **Middleware**

- **ApiBaseMiddleware**: Sets standard API headers and request tracking
- **LogApiRequests**: Logs all API requests/responses for debugging
- **CheckPermission**: Checks if user has ANY required permission (OR logic)
- **CheckAllPermissions**: Checks if user has ALL required permissions (AND logic)

---

## Response Standard

### Success Response Format

```json
{
    "success": true,
    "message": "Success",
    "status_code": 200,
    "data": {},
    "timestamp": "2026-04-04T10:30:00+00:00"
}
```

### Error Response Format

```json
{
    "success": false,
    "message": "An error occurred",
    "status_code": 400,
    "errors": {},
    "timestamp": "2026-04-04T10:30:00+00:00"
}
```

### Paginated Response Format

```json
{
    "success": true,
    "message": "Success",
    "status_code": 200,
    "data": [],
    "pagination": {
        "total": 100,
        "per_page": 15,
        "current_page": 1,
        "last_page": 7,
        "from": 1,
        "to": 15
    },
    "timestamp": "2026-04-04T10:30:00+00:00"
}
```

---

## Usage Examples

### Controller Usage with ApiResponseTrait

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;

class UserController extends Controller
{
    /**
     * Get all users (paginated)
     */
    public function index()
    {
        $perPage = request()->input('per_page', 15);
        $page = request()->input('page', 1);

        $users = User::paginate($perPage, ['*'], 'page', $page);

        return $this->paginatedResponse(
            items: $users->items(),
            total: $users->total(),
            perPage: $users->perPage(),
            currentPage: $users->currentPage(),
            message: 'Users retrieved successfully'
        );
    }

    /**
     * Store a new user
     */
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        $user = User::create($validated);

        return $this->successResponse(
            data: $user,
            message: 'User created successfully',
            statusCode: 201
        );
    }

    /**
     * Get a specific user
     */
    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->notFoundResponse('User not found');
        }

        return $this->successResponse(
            data: $user,
            message: 'User retrieved successfully'
        );
    }

    /**
     * Update a user
     */
    public function update(UpdateUserRequest $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->notFoundResponse();
        }

        $user->update($request->validated());

        return $this->successResponse(
            data: $user,
            message: 'User updated successfully'
        );
    }

    /**
     * Delete a user
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->notFoundResponse();
        }

        $user->delete();

        return $this->successResponse(
            message: 'User deleted successfully'
        );
    }

    /**
     * Handle validation errors manually
     */
    public function handleValidation()
    {
        $errors = [
            'email' => ['Email must be unique'],
            'password' => ['Password must be at least 8 characters'],
        ];

        return $this->validationErrorResponse(
            errors: $errors,
            message: 'Validation failed'
        );
    }

    /**
     * Handle authorization failures
     */
    public function handleUnauthorized()
    {
        return $this->unauthorizedResponse('Please login to continue');
    }

    /**
     * Handle permission denied
     */
    public function handleForbidden()
    {
        return $this->forbiddenResponse('You do not have permission to perform this action');
    }
}
```

---

## Middleware Usage

### In Routes (api.php)

```php
<?php

// The api.base and api.log middleware are automatically applied to all API routes

// Single permission requirement (any one permission)
Route::post('/users', [UserController::class, 'store'])
    ->middleware('permission:create-users');

// Multiple permissions (user needs ANY of these)
Route::get('/reports', [ReportController::class, 'index'])
    ->middleware('permission:view-reports,export-reports');

// All permissions required
Route::delete('/users/{id}', [UserController::class, 'destroy'])
    ->middleware('all-permissions:delete-users,audit-logs');

// Combine with permission checks
Route::group(['middleware' => ['auth:sanctum', 'permission:admin']], function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
    Route::post('/admin/settings', [AdminController::class, 'updateSettings']);
});
```

---

## Response Methods Available

### In Controllers (via ApiResponseTrait)

```php
// Success response
$this->successResponse($data, $message, $statusCode, $additional);

// Error response
$this->errorResponse($message, $statusCode, $errors, $additional);

// Paginated response
$this->paginatedResponse($items, $total, $perPage, $currentPage, $message, $statusCode);

// Specific error responses
$this->validationErrorResponse($errors, $message);
$this->unauthorizedResponse($message);
$this->forbiddenResponse($message);
$this->notFoundResponse($message);
```

### Direct ApiResponse Usage

```php
use App\Http\Responses\ApiResponse;

$response = ApiResponse::success($data, 'Success', 200);
$response = ApiResponse::error('Error message', 400, $errors);
$response = ApiResponse::paginated($items, $total, $perPage, $page);
$response = ApiResponse::validationError($errors);
$response = ApiResponse::unauthorized();
$response = ApiResponse::forbidden();
$response = ApiResponse::notFound();
```

---

## Logging

API requests are logged to the `api` channel with:

- Method, path, IP address
- User ID (if authenticated)
- Query parameters
- Response status code
- Request duration in milliseconds

Configure the log channel in `config/logging.php` if needed.

---

## Headers Added by ApiBaseMiddleware

- `Content-Type`: application/json
- `X-API-Version`: 1.0
- `X-Request-ID`: Unique request identifier (generated or from header)

---

## Best Practices

1. **Always use response methods** - Ensures consistent formatting across the API
2. **Include meaningful messages** - Help clients understand what happened
3. **Use appropriate status codes** - 200 (success), 201 (created), 400 (bad request), 401 (unauthorized), 403 (forbidden), 404 (not found), 422 (validation error)
4. **Validate before returning** - Use FormRequest classes for automatic validation
5. **Handle paginated results** - Use `paginatedResponse()` for list endpoints
6. **Return created resources** - Include the new resource in 201 responses
7. **Log sensitive operations** - Use the permission middleware to log who did what
