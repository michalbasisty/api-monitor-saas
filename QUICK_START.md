# 🚀 Quick Start Guide

## Get Running in 5 Minutes!

### Step 1: Start Docker Desktop
Make sure Docker Desktop is running on your machine.

### Step 2: Start the Application
```powershell
cd "f:\projects\Micro-SaaS for API Performance Monitoring\api-monitor-saas"

# Build and start everything
docker compose -f docker-compose.dev.yml up --build -d

# Wait for services to start (~2-3 minutes)
```

### Step 3: Access the Application
Open your browser and go to: **http://localhost**

### Step 4: Create an Account
1. Click "Register here"
2. Enter email: `admin@example.com`
3. Enter password: `password123`
4. Click "Register"

### Step 5: Verify Email (Development)
```powershell
# Get verification token from response or database
docker exec -it api-monitor-saas-postgres-1 psql -U appuser -d apimon -c "SELECT verification_token FROM users WHERE email='admin@example.com';"

# Or just verify via API with the token shown on registration
# curl http://localhost/api/auth/verify-email/{token}
```

**For quick testing**: Use the API directly to verify:
```powershell
# The registration response includes the verification token
# Use it in the browser: http://localhost/api/auth/verify-email/{TOKEN}
```

### Step 6: Login
1. Go back to login page
2. Enter your credentials
3. Click "Login"
4. You're in! 🎉

### Step 7: Add Your First Endpoint
1. Click "+ Add Endpoint" button
2. Fill in:
   - URL: `https://httpbin.org/status/200`
   - Check Interval: `60` (seconds)
   - Timeout: `5000` (milliseconds)
3. Click "Create Endpoint"

### Step 8: Run Monitoring
```powershell
# Check your endpoints
docker exec -it api-monitor-saas-symfony-1 php bin/console app:monitor:endpoints -a

# View the results in the browser
# Go to Endpoints → Click your endpoint → See monitoring results!
```

---

## What You Have Now

✅ **Full-stack API Monitoring SaaS**
- Angular 20 frontend
- Symfony 6.3 backend
- PostgreSQL database
- Redis caching
- JWT authentication
- Complete CRUD for endpoints
- Monitoring with stats
- Alert system

---

## Common Commands

### View Logs
```powershell
docker compose -f docker-compose.dev.yml logs -f
```

### Stop Everything
```powershell
docker compose -f docker-compose.dev.yml down
```

### Restart a Service
```powershell
docker compose -f docker-compose.dev.yml restart symfony
```

### Access Database
```powershell
docker exec -it api-monitor-saas-postgres-1 psql -U appuser -d apimon
```

---

## Troubleshooting

**Problem**: Can't access http://localhost
- Check Docker is running: `docker ps`
- Check Angular container: `docker logs api-monitor-saas-angular-1`

**Problem**: Login doesn't work
- Check JWT keys exist: `docker exec -it api-monitor-saas-symfony-1 ls -la config/jwt/`
- Rebuild Symfony: `docker compose -f docker-compose.dev.yml build symfony`

**Problem**: Database connection error
- Wait 30 seconds for PostgreSQL to fully start
- Check: `docker compose -f docker-compose.dev.yml ps postgres`

---

## Next Steps

1. ✅ [Read Full Testing Guide](TESTING.md)
2. ✅ [Set Up Monitoring Automation](MONITORING_AUTOMATION.md)
3. ✅ [Deploy to Production](DEPLOYMENT.md)
4. ✅ Build Go real-time engine (Phase 2)
5. ✅ Build React dashboard (Phase 3)

---

## Architecture Overview

```
┌─────────────────────────────────────────┐
│         Angular 20 Frontend             │
│     (http://localhost)                  │
│  - Login/Register                       │
│  - Dashboard                            │
│  - Endpoint Management                  │
│  - Monitoring Results Display           │
└──────────────────┬──────────────────────┘
                   │ HTTP/REST API
┌──────────────────▼──────────────────────┐
│       Symfony 6.3 Backend               │
│     (http://localhost/api)              │
│  - JWT Authentication                   │
│  - Endpoint CRUD                        │
│  - Monitoring Service                   │
│  - Alert System                         │
└──────────────────┬──────────────────────┘
                   │
    ┌──────────────┴──────────────┐
    │                             │
┌───▼────────┐           ┌────────▼─────┐
│ PostgreSQL │           │    Redis     │
│  Database  │           │    Cache     │
└────────────┘           └──────────────┘
```

---

## Key Features

### Authentication
- ✅ User registration with validation
- ✅ Email verification system
- ✅ JWT token-based authentication
- ✅ Role-based access control

### Endpoint Management
- ✅ Add/Edit/Delete API endpoints
- ✅ Configure check intervals
- ✅ Set timeout values
- ✅ Custom headers support
- ✅ Active/Inactive toggle

### Monitoring
- ✅ Automated health checks
- ✅ Response time tracking
- ✅ Status code monitoring
- ✅ Error message capture
- ✅ Uptime percentage calculation
- ✅ Historical data storage

### Alerts
- ✅ Response time thresholds
- ✅ Status code alerts
- ✅ Availability monitoring
- ✅ Multi-channel notifications (email/slack/webhook)
- ✅ Alert history tracking

### Dashboard
- ✅ Overview statistics
- ✅ Recent endpoints display
- ✅ Quick actions
- ✅ Real-time status indicators

---

## API Endpoints Reference

### Authentication
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login
- `GET /api/auth/verify-email/{token}` - Verify email
- `GET /api/auth/me` - Get current user

### Endpoints
- `GET /api/endpoints` - List all endpoints
- `POST /api/endpoints` - Create endpoint
- `GET /api/endpoints/{id}` - Get endpoint details
- `PUT /api/endpoints/{id}` - Update endpoint
- `DELETE /api/endpoints/{id}` - Delete endpoint

### Monitoring
- `GET /api/monitoring/endpoints/{id}/results` - Get monitoring results
- `GET /api/monitoring/endpoints/{id}/stats` - Get statistics
- `GET /api/monitoring/endpoints/{id}/timeline` - Get timeline data

### Alerts
- `GET /api/alerts` - List all alerts
- `POST /api/alerts` - Create alert
- `GET /api/alerts/{id}` - Get alert details
- `PUT /api/alerts/{id}` - Update alert
- `DELETE /api/alerts/{id}` - Delete alert

---

## Development vs Production

### Development (Current)
- Debug mode enabled
- Detailed error messages
- No SSL
- All services in Docker

### Production (See DEPLOYMENT.md)
- Debug mode OFF
- Error logging only
- SSL/HTTPS required
- Environment variables secured
- Automated backups
- Monitoring automation
- Firewall configured

---

## Support

- **Testing**: See [TESTING.md](TESTING.md)
- **Deployment**: See [DEPLOYMENT.md](DEPLOYMENT.md)
- **Monitoring Setup**: See [MONITORING_AUTOMATION.md](MONITORING_AUTOMATION.md)
- **Symfony Docs**: [symfony/TEST_AUTH.md](symfony/TEST_AUTH.md)
- **Endpoint API**: [symfony/TEST_ENDPOINTS.md](symfony/TEST_ENDPOINTS.md)
- **Monitoring API**: [symfony/TEST_MONITORING.md](symfony/TEST_MONITORING.md)
- **Alert API**: [symfony/TEST_ALERTS.md](symfony/TEST_ALERTS.md)

---

**You're all set! Happy monitoring! 🎉**
