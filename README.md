# API Monitor SaaS - Docker development skeleton

This folder contains a starter Docker Compose development setup for the architecture:
- Angular (main app)
- React (dashboard)
- Symfony (core API)
- Go API
- Nginx (reverse proxy)
- Postgres
- Redis

Files added:
- `docker-compose.dev.yml` - development compose file (builds local images)
- `.env.template` - environment variables template
- `nginx/` - nginx Dockerfile and `conf/default.conf` reverse proxy
- `angular/`, `react/` - minimal static stubs served by http-server
- `symfony/` - minimal PHP/Symfony stub
- `go-api/` - minimal Go HTTP server

Quick start (Windows PowerShell):

1. Copy the template to `.env` and edit if needed:

```powershell
cp .env.template .env
```

2. Build and run the stack:

```powershell
docker compose -f docker-compose.dev.yml up --build
```

3. Verify Docker Compose file syntax:

```powershell
docker compose -f docker-compose.dev.yml config
```

Notes:
- These are minimal stubs to get you started. Replace the `/angular` and `/react` static files with actual builds or add proper build steps.
- The Symfony service is a lightweight PHP server for dev only. For production use php-fpm + nginx and proper configuration.

Next steps you may want me to do:
- Wire a real Angular/React build pipeline (npm scripts, multi-stage builds)
- Add healthchecks and wait-for-db logic for services that need DB
- Add a `docker-compose.prod.yml` with optimized images and secrets

Analytics Processing Engine (Recommended)

Where Java fits perfectly:

```
┌─────────────────────────────────────────────────────┐
│                NEW: Java Analytics Layer             │
├─────────────────────────────────────────────────────┤
│  • Complex statistical analysis                     │
│  • Machine Learning for anomaly detection           │
│  • Advanced reporting generation                    │
│  • Enterprise data processing pipelines             │
└──────────────────────┬──────────────────────────────┘
					   │ API Calls
					   ▼
┌─────────────────────────────────────────────────────┐
│                 Go Engine (Real-time)                │
│                 Symfony (Core API)                  │
└─────────────────────────────────────────────────────┘
```

Updated Stack Distribution:

- Symfony/PHP: 30% (core business logic)
- Angular: 20% (main interface)
- Java/Spring: 25% (analytics/ML engine)
- Go: 15% (real-time monitoring)
- React: 10% (performance dashboard)

