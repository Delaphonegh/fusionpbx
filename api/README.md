# FusionPBX API Documentation

This directory contains the REST API endpoints for FusionPBX, allowing programmatic interaction with the system without using the web UI.

## Base URL

All API endpoints are rooted at `/api/`

Example: `https://your-domain.com/api/users`

## Authentication

The API currently uses session-based authentication. You must be logged into the FusionPBX web interface to use the API endpoints.

### Getting Your Session ID

1. Log in to FusionPBX in your browser
2. Open Developer Tools (F12)
3. Navigate to Application/Storage → Cookies
4. Copy the `PHPSESSID` value

Alternatively, you can use curl to login and save cookies:

```bash
curl -c cookies.txt -X POST https://your-domain.com/login.php \
  -d "username=admin&password=yourpassword"
```

## Endpoints

### Users

#### Create User

**Endpoint:** `POST /api/users`

**Description:** Creates a new user in the FusionPBX system.

**Authentication:** Required (session-based)

**Permissions:** Requires `user_add` permission

**Request Body (JSON):**

Required fields:
- `username` (string) - Username for the new user
- `password` (string) - User password (must meet system password requirements)
- `user_email` (string) - Valid email address
- `group_uuid` (string) - UUID of the group to assign the user to

Optional fields:
- `domain_uuid` (string) - Domain UUID (defaults to session domain)
- `user_language` (string) - Language code (e.g., "en-us")
- `user_time_zone` (string) - Timezone (e.g., "America/New_York")
- `user_type` (string) - User type (default: "user")
- `user_enabled` (string) - Enable/disable user (default: "true")
- `user_status` (string) - User status (e.g., "Available", "On Break", "Do Not Disturb")
- `contact_organization` (string) - Contact organization name
- `contact_name_given` (string) - First name
- `contact_name_family` (string) - Last name
- `group_uuid_name` (string) - Alternative format: "uuid|name" for group assignment

**Example Request:**

```bash
curl -X POST https://your-domain.com/api/users \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your-session-id" \
  -d '{
    "username": "john.doe",
    "password": "SecurePass123!",
    "user_email": "john.doe@example.com",
    "group_uuid": "12345678-1234-1234-1234-123456789abc",
    "user_language": "en-us",
    "user_time_zone": "America/New_York",
    "contact_name_given": "John",
    "contact_name_family": "Doe"
  }'
```

**Success Response (201 Created):**

```json
{
  "status": "success",
  "message": "User created successfully",
  "user_uuid": "87654321-4321-4321-4321-cba987654321",
  "username": "john.doe",
  "user_email": "john.doe@example.com"
}
```

**Error Responses:**

**400 Bad Request** - Missing required fields:
```json
{
  "status": "error",
  "message": "Missing required fields",
  "missing_fields": ["group_uuid"]
}
```

**400 Bad Request** - Invalid email:
```json
{
  "status": "error",
  "message": "Invalid email address format"
}
```

**400 Bad Request** - Password requirements not met:
```json
{
  "status": "error",
  "message": "Password does not meet requirements",
  "password_errors": [
    "Password must be at least 12 characters",
    "Password must contain at least one uppercase letter"
  ]
}
```

**401 Unauthorized** - Not authenticated:
```json
{
  "status": "error",
  "message": "Unauthorized - Authentication required"
}
```

**403 Forbidden** - Insufficient permissions:
```json
{
  "status": "error",
  "message": "Forbidden - Insufficient permissions"
}
```

**403 Forbidden** - User limit reached:
```json
{
  "status": "error",
  "message": "Maximum user limit reached: 100"
}
```

**409 Conflict** - Username already exists:
```json
{
  "status": "error",
  "message": "Username already exists"
}
```

**500 Internal Server Error** - Server error:
```json
{
  "status": "error",
  "message": "Failed to create user",
  "error": "Database error message"
}
```

## Password Requirements

The API enforces the same password requirements as the FusionPBX web interface. These are configurable in the system settings and may include:

- Minimum length (default: 12 characters)
- At least one number
- At least one lowercase letter
- At least one uppercase letter
- At least one special character

## Username Format

The system may enforce specific username formats based on configuration:

- `any` - Any format allowed (default)
- `email` - Must be a valid email address
- `no_email` - Must NOT be an email address

## Group Assignment

Users must be assigned to at least one group. You can provide the group UUID in two formats:

1. **UUID only:** `"group_uuid": "12345678-1234-1234-1234-123456789abc"`
2. **UUID with name:** `"group_uuid_name": "12345678-1234-1234-1234-123456789abc|Group Name"`

The API will automatically look up the group name if only the UUID is provided.

## Finding Group UUIDs

To find available group UUIDs, you can:

1. Log into FusionPBX web interface
2. Navigate to Users → Groups
3. View the group details to find the UUID
4. Or query the database: `SELECT group_uuid, group_name FROM v_groups WHERE domain_uuid = 'your-domain-uuid'`

## Examples

### Minimal Request

```bash
curl -X POST https://your-domain.com/api/users \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=abc123" \
  -d '{
    "username": "testuser",
    "password": "SecurePass123!",
    "user_email": "test@example.com",
    "group_uuid": "group-uuid-here"
  }'
```

### Complete Request with All Fields

```bash
curl -X POST https://your-domain.com/api/users \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=abc123" \
  -d '{
    "username": "jane.smith",
    "password": "MySecureP@ssw0rd!",
    "user_email": "jane.smith@example.com",
    "group_uuid": "12345678-1234-1234-1234-123456789abc",
    "domain_uuid": "domain-uuid-here",
    "user_language": "en-us",
    "user_time_zone": "America/New_York",
    "user_type": "user",
    "user_enabled": "true",
    "user_status": "Available",
    "contact_organization": "Acme Corp",
    "contact_name_given": "Jane",
    "contact_name_family": "Smith"
  }'
```

### Using a JSON File

Create `user.json`:
```json
{
  "username": "api.user",
  "password": "TestPass123!",
  "user_email": "api.user@example.com",
  "group_uuid": "12345678-1234-1234-1234-123456789abc",
  "user_language": "en-us",
  "contact_name_given": "API",
  "contact_name_family": "User"
}
```

Then make the request:
```bash
curl -X POST https://your-domain.com/api/users \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=abc123" \
  -d @user.json
```

### Using Saved Cookies

```bash
# Step 1: Login and save cookies
curl -c cookies.txt -X POST https://your-domain.com/login.php \
  -d "username=admin&password=yourpassword"

# Step 2: Use saved cookies for API call
curl -b cookies.txt -X POST https://your-domain.com/api/users \
  -H "Content-Type: application/json" \
  -d '{
    "username": "newuser",
    "password": "SecurePass123!",
    "user_email": "newuser@example.com",
    "group_uuid": "group-uuid-here"
  }'
```

## API Root

**Endpoint:** `GET /api/`

**Description:** Returns information about available API endpoints.

**Example Request:**

```bash
curl https://your-domain.com/api/
```

**Response:**

```json
{
  "status": "success",
  "message": "FusionPBX API",
  "endpoints": {
    "/api/users": "User management (POST to create)"
  }
}
```

## Error Handling

All API endpoints return JSON responses with a consistent structure:

- `status` - Either "success" or "error"
- `message` - Human-readable message
- Additional fields may be present based on the endpoint and error type

HTTP status codes:
- `200` - Success (GET requests)
- `201` - Created (POST requests that create resources)
- `400` - Bad Request (validation errors, missing fields)
- `401` - Unauthorized (authentication required)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found (endpoint doesn't exist)
- `409` - Conflict (resource already exists)
- `500` - Internal Server Error

## Development

### Local Development

For local development, use:

```bash
curl -X POST http://localhost/fusionpbx/api/users \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your-session-id" \
  -d '{
    "username": "test",
    "password": "Test123!",
    "user_email": "test@local.com",
    "group_uuid": "your-group-uuid"
  }' \
  -v
```

The `-v` flag shows verbose output including request/response headers for debugging.

## Notes

- All timestamps are in UTC format
- User UUIDs are automatically generated
- The API respects all FusionPBX permission settings
- Password hashing is handled automatically using PHP's `password_hash()` function
- User settings (language, timezone) are stored separately from the main user record

## Future Enhancements

Potential future API endpoints:
- GET /api/users - List users
- GET /api/users/{uuid} - Get user details
- PUT /api/users/{uuid} - Update user
- DELETE /api/users/{uuid} - Delete user
- API key authentication support
- OAuth2 authentication support

