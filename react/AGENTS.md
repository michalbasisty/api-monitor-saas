# React Frontend - Agent Guide

## Commands

### Build & Run
```bash
# Build and start all services
docker compose -f docker-compose.dev.yml up --build

# Rebuild React service only
docker compose -f docker-compose.dev.yml build react

# Start services
docker compose -f docker-compose.dev.yml up -d

# Local development (if not using Docker)
npm install
npm run dev
```

### Testing
```bash
# Run tests
npm test

# Build for production
npm run build
```

## Architecture

### Key Files
- `src/` - Source code
- `vite.config.js` - Vite configuration
- `package.json` - Dependencies
- `tsconfig.json` - TypeScript configuration

### WebSocket Integration
- Real-time monitoring updates from Go API
- Automatic reconnection on disconnect
- Live dashboard updates without polling
- Connection status indicators

### Development Notes
- Uses Vite for development and building
- Runs on port 3000 in development
- Hot reload enabled
- WebSocket connection to Go API for real-time data
