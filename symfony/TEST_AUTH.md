# Testing the Auth System

## Prerequisites
1. Start Docker Desktop
2. Build and run the stack:
```powershell
docker compose -f docker-compose.dev.yml up --build
```

## API Endpoints

### 1. Register a New User
```bash
POST http://localhost/api/auth/register
Content-Type: application/json

{
  "email": "test@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "message": "User registered successfully. Please verify your email.",
  "id": "uuid-here",
  "verification_token": "token-here"
}
```

### 2. Verify Email
```bash
GET http://localhost/api/auth/verify-email/{verification_token}
```

**Response:**
```json
{
  "message": "Email verified successfully",
  "user": {
    "id": "uuid-here",
    "email": "test@example.com",
    "is_verified": true
  }
}
```

### 3. Login
```bash
POST http://localhost/api/auth/login
Content-Type: application/json

{
  "username": "test@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": "uuid-here",
    "email": "test@example.com",
    "roles": ["ROLE_USER"],
    "subscription_tier": "free",
    "is_verified": true
  }
}
```

### 4. Get Current User (Protected Route)
```bash
GET http://localhost/api/auth/me
Authorization: Bearer {token}
```

**Response:**
```json
{
  "id": "uuid-here",
  "email": "test@example.com",
  "roles": ["ROLE_USER"],
  "subscription_tier": "free",
  "is_verified": true,
  "company_id": null,
  "created_at": "2025-10-31T14:00:00+00:00",
  "last_login_at": "2025-10-31T14:05:00+00:00"
}
```

## Testing with curl

```powershell
# Register
curl -X POST http://localhost/api/auth/register `
  -H "Content-Type: application/json" `
  -d '{\"email\":\"test@example.com\",\"password\":\"password123\"}'

# Verify (replace {token} with actual token from registration)
curl http://localhost/api/auth/verify-email/{token}

# Login
curl -X POST http://localhost/api/auth/login `
  -H "Content-Type: application/json" `
  -d '{\"username\":\"test@example.com\",\"password\":\"password123\"}'

# Get current user (replace {jwt_token} with token from login)
curl http://localhost/api/auth/me `
  -H "Authorization: Bearer {jwt_token}"
```

## Validation Rules
- **Email**: Must be valid email format, unique
- **Password**: Minimum 8 characters
- **JWT Token**: Expires after 1 hour
- **Verification Token**: Expires after 24 hours

## Role Hierarchy
- `ROLE_USER` - Basic user (default)
- `ROLE_ADMIN` - Admin user (includes ROLE_USER)
- `ROLE_SUPER_ADMIN` - Super admin (includes ROLE_ADMIN and ROLE_USER)
