# Module Refactoring - Deployment Guide

## Pre-Deployment Checklist

- [ ] All code changes committed to git
- [ ] Tests passing locally
- [ ] Docker Compose builds successfully
- [ ] No PHP syntax errors

## Deployment Steps

### 1. Build Docker Image

```bash
docker compose -f docker-compose.dev.yml build symfony
```

### 2. Start Services

```bash
docker compose -f docker-compose.dev.yml up -d
```

### 3. Run Migrations

```bash
docker exec api-monitor-saas-symfony-1 php bin/console doctrine:migrations:migrate
```

If migrations have issues:
```bash
# Check migration status
docker exec api-monitor-saas-symfony-1 php bin/console doctrine:migrations:status

# Rollback if needed
docker exec api-monitor-saas-symfony-1 php bin/console doctrine:migrations:migrate prev
```

### 4. Clear Cache

```bash
docker exec api-monitor-saas-symfony-1 php bin/console cache:clear
docker exec api-monitor-saas-symfony-1 php bin/console cache:warmup
```

### 5. Verify Installation

Check that the kernel boots without errors:
```bash
docker exec api-monitor-saas-symfony-1 php bin/console about
```

## Testing Module Access

### 1. Create a Test User with Pro Tier

```bash
# Via API
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "TestPassword123"
  }'
```

### 2. Enable Ecommerce Module

Via direct database:
```bash
docker exec api-monitor-saas-postgres-1 psql -U appuser -d apimon -c "
INSERT INTO user_module_subscriptions (user_id, module_name, tier, enabled, created_at) 
SELECT u.id, 'ecommerce', 'pro', true, NOW() 
FROM users u 
WHERE u.email = 'test@example.com';
"
```

Or via application API (if implemented):
```bash
curl -X POST http://localhost:8000/api/modules/ecommerce/enable \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json"
```

### 3. Test Module Routes

**Should be accessible:**
```bash
curl -X GET http://localhost:8000/api/ecommerce/stores \
  -H "Authorization: Bearer {TOKEN}"
# Expected: 200 OK with empty array
```

**Should be denied (403):**
1. For users without ecommerce subscription
2. Request to unauthorized module

```bash
# Test with user who doesn't have ecommerce
curl -X GET http://localhost:8000/api/ecommerce/stores \
  -H "Authorization: Bearer {FREE_TIER_TOKEN}"
# Expected: 403 Forbidden
```

## Rollback Procedure

If deployment fails:

```bash
# Stop all services
docker compose down

# Revert database to previous migration
docker exec api-monitor-saas-postgres-1 psql -U appuser -d apimon -c "
  SELECT * FROM doctrine_migration_versions 
  ORDER BY executed_at DESC LIMIT 5;
"

# Run previous migration
docker exec api-monitor-saas-symfony-1 php bin/console doctrine:migrations:migrate prev

# Restart with previous code
git checkout previous_commit
docker compose -f docker-compose.dev.yml up -d
```

## Production Considerations

1. **Environment Variables**
   - Ensure all required environment variables are set
   - Database credentials must be secure
   - JWT secret keys must be generated

2. **Permissions**
   - Ensure Symfony cache and log directories are writable
   - Database user should have limited permissions

3. **SSL/TLS**
   - All API endpoints should use HTTPS in production
   - Update webhook URLs to use HTTPS

4. **Rate Limiting**
   - Consider implementing rate limiting for webhook endpoints
   - Protect against DDoS attacks

5. **Monitoring**
   - Monitor failed module access attempts
   - Track webhook processing errors
   - Alert on database migration failures

## Troubleshooting

### Module Not Loading

**Error:** `Module ecommerce not found`

**Solution:**
1. Check `src/Kernel.php` has module registration
2. Verify module class exists and implements `ModuleInterface`
3. Check console output for registration errors

```bash
docker exec api-monitor-saas-symfony-1 php bin/console debug:container --all | grep -i module
```

### Routes Not Found

**Error:** `No route found for "GET /api/ecommerce/stores"`

**Solution:**
1. Verify routes file exists: `src/Modules/Ecommerce/resources/config/routes.yaml`
2. Check Kernel's `configureRoutes()` is loading module routes
3. Clear route cache

```bash
docker exec api-monitor-saas-symfony-1 php bin/console cache:clear
docker exec api-monitor-saas-symfony-1 php bin/console router:match /api/ecommerce/stores
```

### Database Migration Issues

**Error:** `SQLSTATE[HY000]: General error: 1030 Got error from storage engine`

**Solution:**
1. Check database disk space
2. Verify table creation syntax
3. Check foreign key constraints

```bash
docker exec api-monitor-saas-postgres-1 psql -U appuser -d apimon -c "
  SHOW max_connections;
  SELECT datname, pg_database_size(datname) FROM pg_database;
"
```

### Service Autowiring Issues

**Error:** `Cannot autowire service "App\Modules\Ecommerce\Service\CheckoutService"`

**Solution:**
1. Verify service class exists and is in `src/` directory
2. Check constructor parameters are type-hinted
3. Rebuild container

```bash
docker exec api-monitor-saas-symfony-1 php bin/console cache:clear --env=dev
docker exec api-monitor-saas-symfony-1 php bin/console debug:container CheckoutService
```

## Post-Deployment Verification

```bash
# 1. Check kernel boots
docker exec api-monitor-saas-symfony-1 php bin/console about

# 2. Verify all modules registered
docker exec api-monitor-saas-symfony-1 php bin/console debug:container ModuleRegistry

# 3. Check routes loaded
docker exec api-monitor-saas-symfony-1 php bin/console router:match /api/ecommerce/stores

# 4. Test database connection
docker exec api-monitor-saas-symfony-1 php bin/console doctrine:query:dql "SELECT u FROM App\Entity\User u"

# 5. Check migrations applied
docker exec api-monitor-saas-symfony-1 php bin/console doctrine:migrations:status

# 6. Verify services registered
docker exec api-monitor-saas-symfony-1 php bin/console debug:container | grep -i ecommerce
```

## Monitoring in Production

### Log Files

```bash
# Real-time logs
docker logs -f api-monitor-saas-symfony-1

# Specific time range
docker logs --since 2025-11-10T12:00:00 api-monitor-saas-symfony-1

# Show last 100 lines
docker logs --tail 100 api-monitor-saas-symfony-1
```

### Metrics to Monitor

- Module access denied count (403 responses)
- Webhook processing errors
- Database migration execution time
- API response times per module
- Service initialization time

### Alerts to Set Up

- Failed migrations
- Webhook processing failures > threshold
- Module access denied spike
- Database connection pool exhaustion
