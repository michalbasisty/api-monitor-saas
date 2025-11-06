# Java Analytics - Agent Guide

## Commands

### Build & Run
```bash
# Build and start all services
docker compose -f docker-compose.dev.yml up --build

# Rebuild Java Analytics service only
docker compose -f docker-compose.dev.yml build java-analytics

# Start services
docker compose -f docker-compose.dev.yml up -d

# Local development (if not using Docker)
mvn clean compile
mvn spring-boot:run
```

### Testing
```bash
# Run tests
mvn test

# Package
mvn package
```

## Architecture

### Key Files
- `src/main/java/com/example/analytics/` - Source code
- `pom.xml` - Maven configuration
- `Dockerfile` - Docker build configuration

### Redis Stream Processing
- **Stream**: `api-metrics` - Consumes monitoring results from Go API
- **Consumer Group**: `analytics-group` - Ensures reliable message processing
- **Real-time Analytics**: Calculates response time averages, error rates, request counts
- **Anomaly Detection**: Identifies performance issues (configurable thresholds)
- **Data Storage**: Stores aggregated statistics in Redis hashes

### Endpoints
- `GET /analytics/ping` - Health check

### Development Notes
- Spring Boot 3.1 with Redis streams support
- Scheduled tasks for pending message reclamation
- 5-minute sliding window for metrics aggregation
- Configurable via `application.properties`
