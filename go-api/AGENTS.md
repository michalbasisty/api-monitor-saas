# Go API - Agent Guide

## Commands

### Build & Run
```bash
# Build and start all services
docker compose -f docker-compose.dev.yml up --build

# Rebuild Go API service only
docker compose -f docker-compose.dev.yml build go-api

# Start services
docker compose -f docker-compose.dev.yml up -d

# Local development (if not using Docker)
go mod download
go run cmd/main.go
```

### Database
```bash
# Access PostgreSQL
docker exec -it api-monitor-saas-postgres-1 psql -U appuser -d apimon
```

### Testing
```bash
# Run tests
go test ./...

# Build
go build ./cmd/main.go
```

## Architecture

### Structure
- `cmd/` - Main application entry points
- `internal/` - Private application code
- `go.mod` - Go module file

### Key Dependencies
- PostgreSQL for data storage
- Redis for caching
- Gorilla WebSocket for real-time updates

### WebSocket Features
- Real-time monitoring result broadcasts
- Client connection management
- Automatic cleanup of disconnected clients
- Buffered broadcasting channel

### Endpoints
- `GET /health` - Health check
- `GET /monitor` - Trigger monitoring (called by Symfony)
- `WS /ws` - WebSocket connection for real-time updates

### Development Notes
- Uses Go modules
- Runs on port 8080
- WebSocket server broadcasts monitoring results to connected clients
- Integrated with React frontend for live dashboard updates
