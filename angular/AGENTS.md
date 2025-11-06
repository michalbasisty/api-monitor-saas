# Angular Frontend - Agent Guide

## Commands

### Build & Run
```bash
# Build and start all services
docker compose -f docker-compose.dev.yml up --build

# Rebuild Angular service only
docker compose -f docker-compose.dev.yml build angular

# Start services
docker compose -f docker-compose.dev.yml up -d

# Local development (if not using Docker)
npm install
ng serve --host 0.0.0.0 --port 4200
```

### Testing
```bash
# Run tests
ng test

# Linting
ng lint

# Build for production
ng build --configuration production
```

## Architecture

### Key Files
- `src/` - Source code
- `angular.json` - Angular configuration
- `package.json` - Dependencies
- `tsconfig.json` - TypeScript configuration

### Development Notes
- Uses Angular CLI for development
- Runs on port 4200 in development
- Served via Nginx in production
