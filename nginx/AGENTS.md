# Nginx - Agent Guide

## Commands

### Build & Run
```bash
# Build and start all services
docker compose -f docker-compose.dev.yml up --build

# Rebuild Nginx service only
docker compose -f docker-compose.dev.yml build nginx

# Start services
docker compose -f docker-compose.dev.yml up -d
```

### Configuration
```bash
# Reload configuration (inside container)
nginx -s reload

# Test configuration
nginx -t
```

## Architecture

### Key Files
- `conf/` - Nginx configuration files
- `Dockerfile` - Docker build configuration

### Development Notes
- Acts as reverse proxy for frontend and backend services
- Runs on port 80 by default
- Routes requests to Angular, React, Symfony, and Go API
