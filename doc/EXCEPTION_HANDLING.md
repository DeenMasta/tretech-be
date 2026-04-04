# Exception Handling Pattern

## Overview

A comprehensive exception handling system that provides consistent API error responses with proper HTTP status codes.

## Exception Classes

### 1. **ApiException** (Base Class)

- **File**: `app/Exceptions/ApiException.php`
- Base class for all API exceptions
- Customizable status code, message, errors, and additional data

### 2. **ValidationException** (422)

- **When to use**: Form/request validation failures
- **Status Code**: 422 Unprocessable Entity
- **Example**: When form data doesn't meet requirements

```php
throw new ValidationException([
    'email' => ['Email must be a valid email address'],
    'password' => ['Password must be at least 8 characters'],
]);
```

### 3. **ResourceNotFoundException** (404)

- **When to use**: Resource doesn't exist
- **Status Code**: 404 Not Found
- **Example**: User, product, or any model not found

```php
throw new ResourceNotFoundException('User', $userId);
// Message: "User not found (ID: 123)"

throw new ResourceNotFoundException('Product');
// Message: "Product not found"
```

### 4. **UnauthorizedException** (401)

- **When to use**: User is not authenticated
- **Status Code**: 401 Unauthorized
- **Example**: No valid token or session

```php
throw new UnauthorizedException('Token expired, please login again');
```

### 5. **ForbiddenException** (403)

- **When to use**: User lacks required permissions
- **Status Code**: 403 Forbidden
- **Example**: User doesn't have permission to perform action

```php
throw new ForbiddenException('You do not have permission to delete this user');
```

### 6. **ConflictException** (409)

- **When to use**: Resource conflict or duplicate
- **Status Code**: 409 Conflict
- **Example**: Email already exists, duplicate record

```php
throw new ConflictException('Email already registered', [
    'email' => ['This email is already registered'],
]);
```

### 7. **BusinessLogicException** (400)

- **When to use**: Business logic violations
- **Status Code**: 400 Bad Request
- **Example**: Invalid state transition, insufficient inventory

```php
throw new BusinessLogicException('Cannot confirm stock in with 0 items', [
    'items_count' => ['At least one item is required'],
]);
```

### 8. **ThrottleException** (429)

- **When to use**: Rate limiting
- **Status Code**: 429 Too Many Requests
- **Example**: User exceeded API rate limit

```php
throw new ThrottleException(retryAfter: 60, message: 'Too many login attempts');
```

## Handler Features

The `Handler` class automatically converts:

### Built-in Exception Handling:

- **LaravelValidationException** → 422 with validation errors
- **AuthenticationException** → 401 Unauthenticated
- **AuthorizationException** → 403 Forbidden
- **ModelNotFoundException** → 404 with model name
- **Database Errors** → 409 (constraint violation) or 500 (generic)
- **Other Exceptions** → 500 Internal Server Error

### Development vs Production:

- **Development**: Shows detailed error info (file, line, trace)
- **Production**: Generic error messages for security

## Usage Examples

### In Controllers

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Exceptions\ValidationException;
use App\Exceptions\ResourceNotFoundException;
use App\Exceptions\ForbiddenException;

class UserController extends Controller
{
    public function store(StoreUserRequest $request)
    {
        // Check for duplicate
        if (User::where('email', $request->email)->exists()) {
            throw new \App\Exceptions\ConflictException(
                'Email already exists',
                ['email' => ['This email is already registered']]
            );
        }

        $user = User::create($request->validated());

        return $this->successResponse(
            data: $user,
            message: 'User created successfully',
            statusCode: 201
        );
    }

    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            throw new ResourceNotFoundException('User', $id);
        }

        return $this->successResponse(data: $user);
    }

    public function update(UpdateUserRequest $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            throw new ResourceNotFoundException('User', $id);
        }

        // Check permission
        if (!auth()->user()->can('update', $user)) {
            throw new ForbiddenException('You cannot update this user');
        }

        $user->update($request->validated());

        return $this->successResponse(data: $user, message: 'User updated successfully');
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            throw new ResourceNotFoundException('User', $id);
        }

        // Check permission
        if (!auth()->user()->can('delete', $user)) {
            throw new ForbiddenException('You cannot delete this user');
        }

        $user->delete();

        return $this->successResponse(message: 'User deleted successfully');
    }
}
```

### In Services/Business Logic

```php
<?php

namespace App\Services;

use App\Models\StockIn;
use App\Exceptions\BusinessLogicException;
use App\Exceptions\ResourceNotFoundException;

class StockInService
{
    public function confirmStockIn($id)
    {
        $stockIn = StockIn::find($id);

        if (!$stockIn) {
            throw new ResourceNotFoundException('Stock In', $id);
        }

        // Business logic validation
        if ($stockIn->items->isEmpty()) {
            throw new BusinessLogicException(
                'Cannot confirm stock in with no items',
                ['items' => ['At least one item is required']]
            );
        }

        if ($stockIn->status !== 'pending') {
            throw new BusinessLogicException(
                'Only pending stock ins can be confirmed',
                ['status' => ['Invalid status for confirmation']]
            );
        }

        $stockIn->update(['status' => 'confirmed']);
        return $stockIn;
    }
}
```

### In FormRequests

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Exceptions\ValidationException;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ];
    }

    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new ValidationException($validator->errors()->getMessages());
    }
}
```

## Response Format

All exceptions return consistent JSON:

```json
{
    "success": false,
    "message": "User not found (ID: 123)",
    "status_code": 404,
    "errors": {},
    "timestamp": "2026-04-04T10:30:00+00:00"
}
```

With validation errors:

```json
{
    "success": false,
    "message": "Validation failed",
    "status_code": 422,
    "errors": {
        "email": ["Email must be a valid email address"],
        "password": ["Password must be at least 8 characters"]
    },
    "timestamp": "2026-04-04T10:30:00+00:00"
}
```

## HTTP Status Codes

| Exception                 | Status | Use Case                        |
| ------------------------- | ------ | ------------------------------- |
| ValidationException       | 422    | Form validation fails           |
| UnauthorizedException     | 401    | Not logged in or token expired  |
| ForbiddenException        | 403    | Authenticated but no permission |
| ResourceNotFoundException | 404    | Resource doesn't exist          |
| ConflictException         | 409    | Conflict or duplicate record    |
| BusinessLogicException    | 400    | Business rule violated          |
| ThrottleException         | 429    | Rate limit exceeded             |
| Other Exceptions          | 500    | Server error                    |

## Best Practices

1. **Be Specific**: Use the most appropriate exception for the situation
2. **Include Context**: Provide meaningful messages and identifiers
3. **Add Errors Array**: Include specific field errors when relevant
4. **Use Status Codes**: Choose the correct HTTP status code
5. **Log Appropriately**: Critical errors should be logged
6. **Don't Expose Internals**: In production, hide technical details
7. **Be Consistent**: Use the same patterns across all endpoints

## Testing Exception Handling

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class UserControllerTest extends TestCase
{
    public function test_validation_exception()
    {
        $response = $this->postJson('/api/users', [
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Validation failed',
            'status_code' => 422,
        ]);
    }

    public function test_not_found_exception()
    {
        $response = $this->getJson('/api/users/99999');

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'User not found (ID: 99999)',
            'status_code' => 404,
        ]);
    }

    public function test_forbidden_exception()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->deleteJson('/api/users/1');

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'status_code' => 403,
        ]);
    }
}
```
