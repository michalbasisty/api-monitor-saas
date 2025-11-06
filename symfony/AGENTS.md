# Symfony API - Agent Guide

## Commands

### Build & Run
```powershell
# Build and start all services
docker compose -f docker-compose.dev.yml up --build

# Rebuild Symfony service only
docker compose -f docker-compose.dev.yml build symfony

# Start services
docker compose -f docker-compose.dev.yml up -d
```

### Database
```powershell
# Access PostgreSQL
docker exec -it api-monitor-saas-postgres-1 psql -U appuser -d apimon

# Run migrations (when inside container)
php bin/console doctrine:migrations:migrate

# Create migration
php bin/console make:migration
```

### Testing
```bash
# Run tests
php bin/phpunit

# Check syntax
php vendor/bin/phpstan analyse src
```

## Architecture

### Authentication Flow
1. **Registration** → `/api/auth/register` (POST)
   - Email validation (min 8 chars password)
   - Password hashing
   - Verification token generation
   - Returns: user ID + verification token

2. **Email Verification** → `/api/auth/verify-email/{token}` (GET)
   - Token validation (24h expiry)
   - Sets `is_verified = true`

3. **Login** → `/api/auth/login` (POST)
   - JSON: `{"username": "email", "password": "password"}`
   - Returns: JWT token + user data
   - Updates `last_login_at`

4. **Protected Routes** → Require `Authorization: Bearer {token}`
   - `/api/auth/me` - Get current user
   - All other `/api/*` routes

### Security
- JWT keys auto-generated in Docker
- Password hashing: bcrypt (auto)
- Role hierarchy: ROLE_USER → ROLE_ADMIN → ROLE_SUPER_ADMIN
- Email verification required for full access
- Token TTL: 1 hour

### Key Files
- `src/Entity/User.php` - User entity with validation
- `src/Controller/AuthController.php` - Auth endpoints
- `src/Service/EmailVerificationService.php` - Email verification logic
- `src/EventSubscriber/LoginSuccessSubscriber.php` - Login tracking
- `src/Security/Voter/EndpointVoter.php` - Resource authorization
- `config/security.yaml` - Security configuration

## Database Schema

Users table includes:
- `id` (UUID)
- `email` (unique, validated)
- `password` (hashed)
- `roles` (JSONB array)
- `company_id` (UUID, nullable)
- `subscription_tier` (default: 'free')
- `is_verified` (boolean)
- `verification_token` (string, nullable)
- `verification_token_expires_at` (timestamp, nullable)
- `created_at`, `updated_at`, `last_login_at`

## Next Steps
- [x] Implement endpoint CRUD operations
- [x] Add monitoring scheduler
- [x] Integrate with Go real-time engine
- [x] Email service for verification emails
- [x] Stripe billing integration
- [x] Usage limits by subscription tier
- [x] API documentation (Swagger)
