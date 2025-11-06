# End-to-End Testing Guide

## Pre-requisites
- ✅ Docker Desktop running
- ✅ PowerShell (Windows) or Bash (Linux/Mac)
- ✅ All files created (Symfony backend, Angular frontend)

## Step 1: Start the Full Stack

### 1.1 Start Docker Desktop
Make sure Docker Desktop is running.

### 1.2 Build and Start All Services
```powershell
cd "f:\projects\Micro-SaaS for API Performance Monitoring\api-monitor-saas"

# Build all services
docker compose -f docker-compose.dev.yml build

# Start all services
docker compose -f docker-compose.dev.yml up -d

# Watch logs
docker compose -f docker-compose.dev.yml logs -f
```

### 1.3 Verify All Services Are Running
```powershell
docker compose -f docker-compose.dev.yml ps
```

**Expected output:**
- ✅ nginx (port 80)
- ✅ angular (nginx:alpine)
- ✅ symfony (port 8000)
- ✅ postgres (port 5432)
- ✅ redis
- ✅ go-api (port 8080)
- ✅ java-analytics (port 9000)

### 1.4 Check Service Health
```powershell
# Check Symfony API
curl http://localhost/api/health

# Check Angular (should return HTML)
curl http://localhost

# Check Postgres
docker exec -it api-monitor-saas-postgres-1 psql -U appuser -d apimon -c "SELECT 1;"
```

---

## Step 2: Database Setup

### 2.1 Verify Database Schema
```powershell
docker exec -it api-monitor-saas-postgres-1 psql -U appuser -d apimon

# Run these queries:
\dt                                    # List tables
SELECT * FROM users LIMIT 1;          # Check users table
\q                                     # Exit
```

**Expected tables:**
- users
- companies
- api_endpoints
- monitoring_results
- alerts
- subscriptions

---

## Step 3: Backend API Testing

### 3.1 Test User Registration
```powershell
curl -X POST http://localhost/api/auth/register `
  -H "Content-Type: application/json" `
  -d '{
    "email": "test@example.com",
    "password": "password123"
  }'
```

**Expected response:**
```json
{
  "message": "User registered successfully. Please verify your email.",
  "id": "uuid-here",
  "verification_token": "token-here"
}
```

### 3.2 Verify Email (Copy token from registration response)
```powershell
# Replace {token} with actual token
curl http://localhost/api/auth/verify-email/{token}
```

**Expected response:**
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

### 3.3 Test Login
```powershell
curl -X POST http://localhost/api/auth/login `
  -H "Content-Type: application/json" `
  -d '{
    "username": "test@example.com",
    "password": "password123"
  }'
```

**Expected response:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": "uuid-here",
    "email": "test@example.com",
    "roles": ["ROLE_USER"],
    "is_verified": true
  }
}
```

**SAVE THE TOKEN** - you'll need it for the next steps!

### 3.4 Test Protected Endpoint
```powershell
$token = "your-jwt-token-here"

curl http://localhost/api/auth/me `
  -H "Authorization: Bearer $token"
```

### 3.5 Create Test Endpoint
```powershell
curl -X POST http://localhost/api/endpoints `
  -H "Content-Type: application/json" `
  -H "Authorization: Bearer $token" `
  -d '{
    "url": "https://httpbin.org/status/200",
    "check_interval": 300,
    "timeout": 5000,
    "is_active": true
  }'
```

**Save the endpoint ID from response!**

### 3.6 Run Monitoring Check
```powershell
docker exec -it api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints -a
```

**Expected output:**
```
Endpoint Monitoring
===================

 ! [NOTE] Checking all active endpoints...

 [OK] Monitoring complete!
      Total checks: 1
      Successful: 1
      Failed: 0
      Duration: 0.5s
```

### 3.7 Check Monitoring Results
```powershell
# Replace {endpoint_id} with your endpoint ID
curl http://localhost/api/monitoring/endpoints/{endpoint_id}/results `
  -H "Authorization: Bearer $token"
```

### 3.8 Create Alert
```powershell
curl -X POST http://localhost/api/alerts `
  -H "Content-Type: application/json" `
  -H "Authorization: Bearer $token" `
  -d '{
    "endpoint_id": "your-endpoint-id",
    "alert_type": "response_time",
    "threshold": {"max_response_time": 1000},
    "notification_channels": ["email"]
  }'
```

---

## Step 4: Frontend Testing

### 4.1 Access Angular App
Open browser: **http://localhost**

### 4.2 Test Registration Flow
1. Click "Register here"
2. Enter email: `frontend@example.com`
3. Enter password: `password123`
4. Click "Register"
5. Should see success message

### 4.3 Test Login Flow
1. Go back to login page
2. Enter registered email
3. Enter password
4. Click "Login"
5. Should redirect to Dashboard

### 4.4 Test Dashboard
- Should see stats (Total Endpoints, Active, Inactive)
- Should see "Add Endpoint" button
- Navigation bar should show email

### 4.5 Test Add Endpoint
1. Click "+ Add Endpoint"
2. Fill form:
   - URL: `https://httpbin.org/delay/2`
   - Check Interval: `60`
   - Timeout: `5000`
   - Active: checked
3. Click "Create Endpoint"
4. Should redirect to endpoints list

### 4.6 Test Endpoint List
- Should see the created endpoint
- Should show status badge (Active)
- Click "View Details"

### 4.7 Test Endpoint Details
- Should show configuration
- Should show "No monitoring results yet" (if not monitored)
- Click "Edit Endpoint"

### 4.8 Test Edit Endpoint
- Modify check interval to `120`
- Click "Update Endpoint"
- Should redirect back to list

### 4.9 Test Monitoring Results Display
```powershell
# Run monitoring manually
docker exec -it api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints -a

# Refresh endpoint detail page in browser
# Should now show monitoring results
```

### 4.10 Test Logout
- Click "Logout" in navbar
- Should redirect to login page
- Try accessing `/dashboard` directly
- Should redirect back to login

---

## Step 5: Integration Testing

### 5.1 Complete User Flow
1. **Register** → Create account via frontend
2. **Login** → Login via frontend
3. **Add Endpoint** → Create endpoint via frontend
4. **Monitor** → Run monitoring via CLI
5. **View Results** → Check results in frontend
6. **Create Alert** → Set up alert via API
7. **Trigger Alert** → Create endpoint that fails
8. **Check Logs** → View alert in Docker logs

### 5.2 Test Monitoring Automation
```powershell
# Add endpoint that will fail
curl -X POST http://localhost/api/endpoints `
  -H "Content-Type: application/json" `
  -H "Authorization: Bearer $token" `
  -d '{
    "url": "https://httpbin.org/status/500",
    "check_interval": 60,
    "timeout": 5000
  }'

# Run monitoring
docker exec -it api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints -a

# Check logs for errors
docker logs api-monitor-saas-symfony-1 --tail 50
```

---

## Step 6: Performance Testing

### 6.1 Create Multiple Endpoints
Create 5-10 endpoints to test performance.

### 6.2 Monitor All Endpoints
```powershell
# Measure time
Measure-Command {
  docker exec -it api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints -a
}
```

### 6.3 Check Database Performance
```powershell
docker exec -it api-monitor-saas-postgres-1 psql -U appuser -d apimon

SELECT COUNT(*) FROM monitoring_results;
SELECT COUNT(*) FROM api_endpoints;
SELECT COUNT(*) FROM alerts;
\q
```

---

## Common Issues & Solutions

### Issue: "Connection refused" on API calls
**Solution:**
```powershell
# Check Symfony is running
docker logs api-monitor-saas-symfony-1

# Restart Symfony
docker compose -f docker-compose.dev.yml restart symfony
```

### Issue: JWT keys not generated
**Solution:**
```powershell
docker exec -it api-monitor-saas-symfony-1 ls -la config/jwt/

# If missing, rebuild
docker compose -f docker-compose.dev.yml build symfony
docker compose -f docker-compose.dev.yml up -d symfony
```

### Issue: Database connection error
**Solution:**
```powershell
# Check postgres is healthy
docker compose -f docker-compose.dev.yml ps postgres

# Check connection
docker exec -it api-monitor-saas-postgres-1 psql -U appuser -d apimon -c "SELECT 1;"
```

### Issue: Angular app shows blank page
**Solution:**
```powershell
# Check Angular build
docker logs api-monitor-saas-angular-1

# Check nginx config
docker exec -it api-monitor-saas-angular-1 cat /etc/nginx/conf.d/default.conf

# Rebuild
docker compose -f docker-compose.dev.yml build angular
docker compose -f docker-compose.dev.yml up -d angular
```

### Issue: CORS errors
**Solution:**
The nginx.conf in Angular container should proxy `/api` to Symfony. Check:
```powershell
docker exec -it api-monitor-saas-angular-1 cat /etc/nginx/conf.d/default.conf
```

---

## Success Criteria

✅ All services running without errors
✅ Can register and login users
✅ Can create/read/update/delete endpoints
✅ Monitoring checks execute successfully
✅ Results are stored in database
✅ Frontend displays all data correctly
✅ Alerts can be created and triggered
✅ No errors in Docker logs

---

## Next Steps After Testing

1. ✅ Verify all tests pass
2. Set up automated monitoring (cron)
3. Configure production environment
4. Set up SSL/HTTPS
5. Configure email service for notifications
6. Set up backups
7. Deploy to production server

---

## Test Checklist

- [ ] All Docker services start successfully
- [ ] Database schema is correct
- [ ] User registration works
- [ ] Email verification works
- [ ] Login returns JWT token
- [ ] Protected endpoints require auth
- [ ] Can create endpoints
- [ ] Can list endpoints
- [ ] Can update endpoints
- [ ] Can delete endpoints
- [ ] Monitoring command runs
- [ ] Results are stored
- [ ] Frontend login works
- [ ] Frontend dashboard displays
- [ ] Can add endpoints via UI
- [ ] Can view endpoint details
- [ ] Monitoring results display in UI
- [ ] Can logout
- [ ] Auth guard redirects properly
- [ ] Alerts can be created
- [ ] Alerts are evaluated
- [ ] No CORS errors
- [ ] No console errors in browser
