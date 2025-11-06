# Java Analytics (skeleton)

This folder contains a minimal Spring Boot service used as a skeleton analytics/ML engine.

Quick notes:
- Builds with Maven and runs on Java 17.
- Exposes port 9000 and provides `/analytics/ping` for a simple health check.

To build locally (from the project root):

```powershell
docker compose -f docker-compose.dev.yml build java-analytics
docker compose -f docker-compose.dev.yml up -d java-analytics
```
