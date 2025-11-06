# Testing Endpoint CRUD Operations

## Prerequisites
1. Complete auth setup and obtain JWT token (see TEST_AUTH.md)
2. Have the stack running: `docker compose -f docker-compose.dev.yml up`

## API Endpoints

Base URL: `http://localhost/api/endpoints`

All endpoints require authentication:
```
Authorization: Bearer {jwt_token}
```

---

## 1. Create Endpoint

**Request:**
```bash
POST /api/endpoints
Content-Type: application/json
Authorization: Bearer {token}

{
  "url": "https://api.example.com/health",
  "check_interval": 300,
  "timeout": 5000,
  "headers": {
    "Authorization": "Bearer secret-token",
    "X-Custom-Header": "value"
  },
  "is_active": true
}
```

**Response (201):**
```json
{
  "message": "Endpoint created successfully",
  "endpoint": {
    "id": "uuid-here",
    "url": "https://api.example.com/health",
    "check_interval": 300,
    "timeout": 5000,
    "headers": {
      "Authorization": "Bearer secret-token",
      "X-Custom-Header": "value"
    },
    "is_active": true,
    "created_at": "2025-10-31T14:00:00+00:00",
    "updated_at": null
  }
}
```

---

## 2. List All Endpoints

**Request:**
```bash
GET /api/endpoints
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "total": 2,
  "endpoints": [
    {
      "id": "uuid-1",
      "url": "https://api.example.com/health",
      "check_interval": 300,
      "timeout": 5000,
      "headers": {...},
      "is_active": true,
      "created_at": "2025-10-31T14:00:00+00:00",
      "updated_at": null
    },
    {
      "id": "uuid-2",
      "url": "https://api2.example.com/status",
      "check_interval": 600,
      "timeout": 3000,
      "headers": null,
      "is_active": false,
      "created_at": "2025-10-31T13:00:00+00:00",
      "updated_at": "2025-10-31T13:30:00+00:00"
    }
  ]
}
```

---

## 3. Get Single Endpoint

**Request:**
```bash
GET /api/endpoints/{endpoint_id}
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "id": "uuid-here",
  "url": "https://api.example.com/health",
  "check_interval": 300,
  "timeout": 5000,
  "headers": {...},
  "is_active": true,
  "created_at": "2025-10-31T14:00:00+00:00",
  "updated_at": null
}
```

**Response (404):**
```json
{
  "message": "Endpoint not found"
}
```

---

## 4. Update Endpoint

**Request:**
```bash
PUT /api/endpoints/{endpoint_id}
Content-Type: application/json
Authorization: Bearer {token}

{
  "url": "https://api.example.com/v2/health",
  "check_interval": 600,
  "is_active": false
}
```

**Response (200):**
```json
{
  "message": "Endpoint updated successfully",
  "endpoint": {
    "id": "uuid-here",
    "url": "https://api.example.com/v2/health",
    "check_interval": 600,
    "timeout": 5000,
    "headers": {...},
    "is_active": false,
    "created_at": "2025-10-31T14:00:00+00:00",
    "updated_at": "2025-10-31T14:30:00+00:00"
  }
}
```

---

## 5. Delete Endpoint

**Request:**
```bash
DELETE /api/endpoints/{endpoint_id}
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "message": "Endpoint deleted successfully"
}
```

---

## Validation Rules

### URL
- Required
- Must be valid URL format
- Must use HTTP or HTTPS protocol
- Max 2048 characters

### Check Interval
- Required
- Minimum: 60 seconds (to prevent abuse)

### Timeout
- Required
- Range: 100ms - 30000ms (30 seconds)

### Headers
- Optional
- Must be valid JSON object
- Key-value pairs

### Is Active
- Optional
- Boolean (true/false)
- Default: true

---

## Testing with curl (PowerShell)

```powershell
# Set your JWT token
$token = "your-jwt-token-here"

# Create endpoint
curl -X POST http://localhost/api/endpoints `
  -H "Content-Type: application/json" `
  -H "Authorization: Bearer $token" `
  -d '{\"url\":\"https://api.example.com/health\",\"check_interval\":300,\"timeout\":5000}'

# List endpoints
curl http://localhost/api/endpoints `
  -H "Authorization: Bearer $token"

# Get specific endpoint
curl http://localhost/api/endpoints/{endpoint_id} `
  -H "Authorization: Bearer $token"

# Update endpoint
curl -X PUT http://localhost/api/endpoints/{endpoint_id} `
  -H "Content-Type: application/json" `
  -H "Authorization: Bearer $token" `
  -d '{\"is_active\":false,\"check_interval\":600}'

# Delete endpoint
curl -X DELETE http://localhost/api/endpoints/{endpoint_id} `
  -H "Authorization: Bearer $token"
```

---

## Error Responses

### 400 Bad Request - Validation Failed
```json
{
  "message": "Validation failed",
  "errors": {
    "url": "Please provide a valid URL",
    "check_interval": "Check interval must be at least 60 seconds",
    "timeout": "Timeout must be between 100ms and 30000ms"
  }
}
```

### 401 Unauthorized
```json
{
  "message": "Not authenticated"
}
```

### 404 Not Found
```json
{
  "message": "Endpoint not found"
}
```

---

## Security Notes

- Users can only access their own endpoints
- ROLE_ADMIN can access all endpoints
- JWT token expires after 1 hour
- Endpoints are isolated by user_id
