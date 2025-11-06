# Testing Alert System

## Prerequisites
1. Complete auth, endpoint, and monitoring setup
2. Have endpoints created and monitored
3. Docker stack running

---

## API Endpoints

Base URL: `http://localhost/api/alerts`

All endpoints require authentication: `Authorization: Bearer {token}`

---

## 1. Create Alert

### Response Time Alert
```bash
POST /api/alerts
Content-Type: application/json
Authorization: Bearer {token}

{
  "endpoint_id": "your-endpoint-uuid",
  "alert_type": "response_time",
  "threshold": {
    "max_response_time": 1000
  },
  "is_active": true,
  "notification_channels": ["email"]
}
```

**Response (201):**
```json
{
  "message": "Alert created successfully",
  "alert": {
    "id": "alert-uuid",
    "endpoint_id": "endpoint-uuid",
    "alert_type": "response_time",
    "threshold": {
      "max_response_time": 1000
    },
    "is_active": true,
    "notification_channels": ["email"],
    "last_triggered_at": null,
    "created_at": "2025-10-31T15:00:00+00:00",
    "updated_at": null
  }
}
```

### Status Code Alert
```json
{
  "endpoint_id": "your-endpoint-uuid",
  "alert_type": "status_code",
  "threshold": {
    "expected_codes": [200, 201, 204]
  },
  "notification_channels": ["email", "slack"]
}
```

**Alternative - Status Code Range:**
```json
{
  "endpoint_id": "your-endpoint-uuid",
  "alert_type": "status_code",
  "threshold": {
    "min_code": 200,
    "max_code": 299
  }
}
```

**Alert on Connection Failure:**
```json
{
  "endpoint_id": "your-endpoint-uuid",
  "alert_type": "status_code",
  "threshold": {
    "expected_codes": [200],
    "alert_on_null": true
  }
}
```

### Availability Alert
```json
{
  "endpoint_id": "your-endpoint-uuid",
  "alert_type": "availability",
  "threshold": {
    "min_uptime_percentage": 99.0,
    "period_hours": 24
  },
  "notification_channels": ["email"]
}
```

---

## 2. List All Alerts

**Request:**
```bash
GET /api/alerts
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "total": 3,
  "alerts": [
    {
      "id": "uuid-1",
      "endpoint_id": "endpoint-uuid",
      "alert_type": "response_time",
      "threshold": {"max_response_time": 1000},
      "is_active": true,
      "notification_channels": ["email"],
      "last_triggered_at": "2025-10-31T14:30:00+00:00",
      "created_at": "2025-10-31T10:00:00+00:00",
      "updated_at": null
    }
  ]
}
```

---

## 3. Get Alerts for Specific Endpoint

**Request:**
```bash
GET /api/alerts/endpoint/{endpoint_id}
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "endpoint_id": "endpoint-uuid",
  "total": 2,
  "alerts": [...]
}
```

---

## 4. Update Alert

**Request:**
```bash
PUT /api/alerts/{alert_id}
Content-Type: application/json
Authorization: Bearer {token}

{
  "threshold": {
    "max_response_time": 2000
  },
  "is_active": false
}
```

**Response (200):**
```json
{
  "message": "Alert updated successfully",
  "alert": {...}
}
```

---

## 5. Delete Alert

**Request:**
```bash
DELETE /api/alerts/{alert_id}
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "message": "Alert deleted successfully"
}
```

---

## Alert Types & Thresholds

### 1. Response Time Alert
Triggers when response time exceeds threshold.

**Threshold Format:**
```json
{
  "max_response_time": 1000  // milliseconds
}
```

**Example Use Cases:**
- Alert if response > 1000ms
- Alert if response > 5000ms (slow API)

---

### 2. Status Code Alert
Triggers on unexpected status codes.

**Threshold Format (Expected Codes):**
```json
{
  "expected_codes": [200, 201, 204]
}
```

**Threshold Format (Range):**
```json
{
  "min_code": 200,
  "max_code": 299
}
```

**With Null Handling:**
```json
{
  "expected_codes": [200],
  "alert_on_null": true  // Alert if connection fails
}
```

**Example Use Cases:**
- Alert on 5xx errors
- Alert on 4xx errors
- Alert on any non-200 response
- Alert on connection failures

---

### 3. Availability Alert
Triggers when uptime % drops below threshold.

**Threshold Format:**
```json
{
  "min_uptime_percentage": 99.0,  // 99%
  "period_hours": 24              // Over last 24 hours
}
```

**Example Use Cases:**
- Alert if uptime < 99% in last 24h
- Alert if uptime < 95% in last 1h
- Alert if uptime < 99.9% in last 7 days (168h)

---

## Notification Channels

Currently supported (logged, not actually sent):
- `email` - Email notification
- `slack` - Slack notification
- `webhook` - Webhook notification

**Example:**
```json
{
  "notification_channels": ["email", "slack", "webhook"]
}
```

---

## Testing Alert Evaluation

### 1. Create a Test Endpoint
```bash
curl -X POST http://localhost/api/endpoints \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $token" \
  -d '{
    "url": "https://httpbin.org/delay/3",
    "check_interval": 60,
    "timeout": 5000
  }'
```

### 2. Create Response Time Alert
```bash
curl -X POST http://localhost/api/alerts \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $token" \
  -d '{
    "endpoint_id": "endpoint-uuid",
    "alert_type": "response_time",
    "threshold": {"max_response_time": 1000},
    "notification_channels": ["email"]
  }'
```

### 3. Run Monitoring Check
```powershell
docker exec -it api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints -a
```

### 4. Check Logs for Alert
```powershell
docker logs api-monitor-saas-symfony-1 | Select-String "Alert triggered"
```

---

## Testing Different Scenarios

### Slow Response Test
```bash
# Endpoint that delays 3 seconds
https://httpbin.org/delay/3

# Alert threshold: 1000ms
# Result: WILL TRIGGER
```

### Status Code Test
```bash
# Endpoint that returns 404
https://httpbin.org/status/404

# Alert threshold: expected_codes [200]
# Result: WILL TRIGGER
```

### Connection Failure Test
```bash
# Invalid endpoint
https://invalid-domain-that-does-not-exist.com

# Alert threshold: alert_on_null = true
# Result: WILL TRIGGER
```

---

## PowerShell Testing Script

```powershell
$token = "your-jwt-token"
$endpointId = "your-endpoint-uuid"

# Create response time alert
curl -X POST http://localhost/api/alerts `
  -H "Content-Type: application/json" `
  -H "Authorization: Bearer $token" `
  -d "{`"endpoint_id`":`"$endpointId`",`"alert_type`":`"response_time`",`"threshold`":{`"max_response_time`":1000},`"notification_channels`":[`"email`"]}"

# List all alerts
curl http://localhost/api/alerts `
  -H "Authorization: Bearer $token"

# Get alerts for endpoint
curl "http://localhost/api/alerts/endpoint/$endpointId" `
  -H "Authorization: Bearer $token"

# Run monitoring check
docker exec -it api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints -a

# Check for triggered alerts in logs
docker logs api-monitor-saas-symfony-1 --tail 50 | Select-String "Alert"
```

---

## Validation Rules

### Alert Type
- Required
- Must be one of: `response_time`, `status_code`, `availability`

### Threshold
- Required
- Must be valid JSON object
- Format depends on alert_type

### Notification Channels
- Required
- Must be array
- Default: `["email"]`

### Is Active
- Optional
- Boolean
- Default: `true`

---

## Notes

- Alerts are evaluated automatically during monitoring checks
- `last_triggered_at` is updated each time alert triggers
- Notifications are currently logged (not actually sent)
- Users can only manage their own alerts
- Alerts are tied to specific endpoints (deleted when endpoint is deleted)
