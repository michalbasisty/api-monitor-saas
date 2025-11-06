# API Performance Monitor - Project Structure

## Backend Services

### symfony/
```
symfony/
├── config/
│   ├── jwt/                # JWT keys for authentication
│   ├── routes/             # API route definitions
│   └── services.yaml       # Service configuration
├── src/
│   ├── Controller/         # API endpoints
│   │   ├── Auth/          # Authentication controllers
│   │   ├── Endpoint/      # API endpoint management
│   │   └── Monitor/       # Monitoring data access
│   ├── Entity/            # Doctrine entities
│   ├── Repository/        # Database queries
│   ├── Service/           # Business logic
│   │   ├── Monitor/       # Monitoring services
│   │   └── Notification/  # Alert notification
│   └── EventSubscriber/   # Event handlers
└── tests/                 # PHPUnit tests
```

### go-api/
```
go-api/
├── cmd/
│   └── server/           # Entry point
├── internal/
│   ├── monitor/         # Monitoring engine
│   ├── websocket/       # Real-time updates
│   └── queue/           # Message processing
├── pkg/
│   ├── discovery/       # API discovery
│   └── metrics/         # Performance metrics
└── test/               # Go tests
```

### java-analytics/
```
java-analytics/
├── src/
│   ├── main/
│   │   ├── java/
│   │   │   └── com/example/analytics/    # Spring Boot app and services
│   │   │       ├── config/               # Redis configuration (Lettuce, serializer)
│   │   │       └── service/              # MetricsProcessor, pending reclaimer
│   │   └── resources/
│   │       └── application.properties    # Redis host/port and reclaimer settings
└── Dockerfile
```

## Frontend Applications

### angular/
```
angular/
├── src/
│   ├── app/
│   │   ├── auth/        # Authentication
│   │   ├── dashboard/   # Main dashboard
│   │   ├── endpoints/   # Endpoint management
│   │   └── alerts/      # Alert configuration
│   ├── core/           # Core services
│   └── shared/         # Shared components
└── tests/              # Angular tests
```

### react/
```
react/
├── src/
│   ├── components/     # UI components
│   │   ├── charts/    # Performance charts
│   │   └── alerts/    # Alert widgets
│   ├── hooks/         # Custom React hooks
│   ├── services/      # API integration
│   └── store/         # State management
└── tests/             # React tests
```

## Infrastructure

### nginx/
```
nginx/
├── conf/
│   └── default.conf   # Reverse proxy config
└── ssl/              # SSL certificates
```

### postgres/
```
postgres/
├── init/
│   └── init.sql      # Schema initialization
└── migrations/       # Database migrations
```

### redis/
```
redis/
└── conf/
    └── redis.conf    # Redis configuration
```

## Development Tools

### docker/
```
docker/
├── dev/             # Development environment
└── prod/            # Production setup
```

### docs/
```
docs/
├── api/            # API documentation
├── architecture/   # System design docs
└── deployment/     # Deployment guides
```

## Build & Deployment

- `docker-compose.dev.yml` - Development setup
- `docker-compose.prod.yml` - Production configuration
- `.env.template` - Environment variables template
- `Makefile` - Build & deployment commands