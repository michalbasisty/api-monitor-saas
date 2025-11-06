# PostgreSQL Database - Agent Guide

## Commands

### Access Database
```bash
# Connect to PostgreSQL
docker exec -it api-monitor-saas-postgres-1 psql -U appuser -d apimon

# View running processes
docker exec -it api-monitor-saas-postgres-1 psql -U appuser -d apimon -c "SELECT * FROM pg_stat_activity;"

# Check database size
docker exec -it api-monitor-saas-postgres-1 psql -U appuser -d apimon -c "SELECT pg_size_pretty(pg_database_size('apimon'));"
```

### Management
```bash
# Restart PostgreSQL service
docker compose -f docker-compose.dev.yml restart postgres

# View logs
docker compose -f docker-compose.dev.yml logs postgres
```

## Architecture

### Configuration
- PostgreSQL 15
- Database: apimon
- User: appuser
- Initialization script: init.sql

### Key Files
- `init.sql` - Database initialization script

### Development Notes
- Runs on port 5432
- Data persisted in Docker volume pgdata
- Health check enabled
