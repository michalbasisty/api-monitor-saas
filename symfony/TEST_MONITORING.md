# Testing Monitoring System

## Prerequisites
1. Complete auth and endpoint setup
2. Have at least one endpoint created
3. Docker stack running

---

## Console Commands

### 1. Check All Active Endpoints
Checks all endpoints regardless of their schedule:

```powershell
# From host machine
docker exec -it api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints --all

# Or with alias
docker exec -it api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints -a
```

**Output:**
```
Endpoint Monitoring
===================

 ! [NOTE] Checking all active endpoints...

 [OK] Monitoring complete!
      Total checks: 5
      Successful: 4
      Failed: 1
      Duration: 2.34s
```

### 2. Check Only Due Endpoints
Checks only endpoints that are due based on their `check_interval`:

```powershell
docker exec -it api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints --due-only
# or
docker exec -it api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints -d
```

### 3. Default Behavior
Without flags, checks due endpoints:

```powershell
docker exec -it api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints
```

---

## API Endpoints

All require authentication: `Authorization: Bearer {token}`

### 1. Get Monitoring Results

**Request:**
```bash
GET /api/monitoring/endpoints/{endpoint_id}/results?limit=50
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "endpoint_id": "uuid-here",
  "total": 50,
  "results": [
    {
      "id": "result-uuid",
      "response_time": 234,
      "status_code": 200,
      "error_message": null,
      "is_successful": true,
      "checked_at": "2025-10-31T14:30:00+00:00",
      "created_at": "2025-10-31T14:30:00+00:00"
    },
    {
      "id": "result-uuid-2",
      "response_time": 1523,
      "status_code": null,
      "error_message": "Connection failed - could not reach the endpoint",
      "is_successful": false,
      "checked_at": "2025-10-31T14:25:00+00:00",
      "created_at": "2025-10-31T14:25:00+00:00"
    }
  ]
}
```

**Query Parameters:**
- `limit` (optional): Max results to return (default: 100, max: 1000)

---

### 2. Get Endpoint Statistics

**Request:**
```bash
GET /api/monitoring/endpoints/{endpoint_id}/stats?hours=24
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "endpoint_id": "uuid-here",
  "period_hours": 24,
  "latest_check": {
    "status_code": 200,
    "response_time": 234,
    "is_successful": true,
    "error_message": null,
    "checked_at": "2025-10-31T14:30:00+00:00"
  },
  "average_response_time": 312.45,
  "uptime_percentage": 99.2
}
```

**Query Parameters:**
- `hours` (optional): Time period for stats (default: 24, max: 168 = 7 days)

---

### 3. Get Timeline Data

**Request:**
```bash
GET /api/monitoring/endpoints/{endpoint_id}/timeline?hours=12
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "endpoint_id": "uuid-here",
  "from": "2025-10-31T02:30:00+00:00",
  "to": "2025-10-31T14:30:00+00:00",
  "total": 144,
  "timeline": [
    {
      "checked_at": "2025-10-31T02:30:00+00:00",
      "status_code": 200,
      "response_time": 245,
      "is_successful": true
    },
    {
      "checked_at": "2025-10-31T02:35:00+00:00",
      "status_code": 503,
      "response_time": 156,
      "is_successful": false
    }
  ]
}
```

**Query Parameters:**
- `hours` (optional): Time period (default: 24, max: 168)

---

## Testing with curl (PowerShell)

```powershell
# Set your JWT token and endpoint ID
$token = "your-jwt-token"
$endpointId = "your-endpoint-uuid"

# Get latest results
curl "http://localhost/api/monitoring/endpoints/$endpointId/results?limit=10" `
  -H "Authorization: Bearer $token"

# Get statistics
curl "http://localhost/api/monitoring/endpoints/$endpointId/stats?hours=24" `
  -H "Authorization: Bearer $token"

# Get timeline
curl "http://localhost/api/monitoring/endpoints/$endpointId/timeline?hours=12" `
  -H "Authorization: Bearer $token"
```

---

## Scheduling Checks

### Using Cron (Linux/Production)

Add to crontab:
```bash
# Check every minute
* * * * * docker exec api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints -d

# Or check all every 5 minutes
*/5 * * * * docker exec api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints -a
```

### Using Windows Task Scheduler

Create a task that runs:
```powershell
docker exec -it api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints -d
```

### Manual Testing

1. **Create an endpoint:**
```powershell
curl -X POST http://localhost/api/endpoints `
  -H "Content-Type: application/json" `
  -H "Authorization: Bearer $token" `
  -d '{\"url\":\"https://httpbin.org/status/200\",\"check_interval\":60,\"timeout\":5000}'
```

2. **Run monitoring:**
```powershell
docker exec -it api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints -a
```

3. **Check results:**
```powershell
curl "http://localhost/api/monitoring/endpoints/$endpointId/results" `
  -H "Authorization: Bearer $token"
```

---

## Monitoring Result Fields

- **response_time**: Time in milliseconds (null on connection errors)
- **status_code**: HTTP status code (null on connection errors)
- **error_message**: Error description (null on success)
- **is_successful**: `true` if status_code is 2xx and no errors
- **checked_at**: When the check was performed
- **created_at**: When the result was stored

---

## Common Error Messages

- `"Connection failed - could not reach the endpoint"` - Network/DNS issue
- `"Request timeout - endpoint did not respond in time"` - Exceeded timeout setting
- `"Too many redirects"` - More than 5 redirects
- Custom exception messages for other errors

---

## Performance Notes

- Checks run sequentially (not parallel yet)
- Default timeout from endpoint settings (100ms - 30s)
- Results stored indefinitely (cleanup command TBD)
- Max 1000 results returned per API call
