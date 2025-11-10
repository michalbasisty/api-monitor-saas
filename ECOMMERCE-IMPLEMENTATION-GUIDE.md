# E-Commerce Module Implementation Guide

## Overview

The E-Commerce module provides comprehensive monitoring capabilities for online stores including:
- Store management and configuration
- Checkout flow tracking with per-step metrics
- Payment gateway monitoring and performance analysis
- Sales metrics and lost revenue calculation
- Real-time alerting system
- Stripe webhook integration

## Architecture

### Module Structure

```
src/Modules/Ecommerce/
├── Entity/
│   ├── Store.php
│   ├── CheckoutStep.php
│   ├── CheckoutMetric.php
│   ├── PaymentGateway.php
│   ├── PaymentMetric.php
│   ├── SalesMetric.php
│   ├── Abandonment.php
│   ├── TrafficSpike.php
│   └── EcommerceAlert.php
├── Controller/
│   ├── StoreController.php
│   ├── CheckoutController.php
│   ├── PaymentController.php
│   ├── SalesController.php
│   ├── AlertController.php
│   └── WebhookController.php
├── Repository/
│   ├── StoreRepository.php
│   ├── CheckoutStepRepository.php
│   ├── PaymentGatewayRepository.php
│   ├── SalesMetricRepository.php
│   └── AlertRepository.php
├── Service/
│   ├── StoreService.php
│   ├── CheckoutService.php
│   ├── PaymentService.php
│   ├── SalesMetricsService.php
│   └── StripeService.php
├── resources/
│   └── config/
│       └── routes.yaml
└── EcommerceModule.php
```

### Database Schema

#### ecommerce_stores
Main store configuration table
- `id` (UUID): Primary key
- `user_id` (UUID): Store owner (FK to users)
- `store_name` (varchar): Display name
- `store_url` (varchar): Unique store URL
- `platform` (varchar): Shopify, WooCommerce, custom, etc.
- `currency` (varchar): Store currency (default: USD)
- `timezone` (varchar): Store timezone
- `created_at`, `updated_at`: Timestamps

#### ecommerce_checkout_steps
Checkout flow configuration
- `id` (UUID): Primary key
- `store_id` (UUID): Reference to store
- `position` (int): Step order in checkout
- `name` (varchar): Step name
- `conversion_rate` (decimal): Percentage of users completing this step
- `avg_time_ms` (int): Average time spent on this step
- `abandonment_rate` (decimal): Percentage abandoning at this step

#### ecommerce_payment_gateways
Payment gateway configuration
- `id` (UUID): Primary key
- `store_id` (UUID): Reference to store
- `provider` (varchar): Stripe, PayPal, etc.
- `config` (json): Encrypted credentials/settings
- `is_active` (boolean): Whether gateway accepts transactions
- `success_rate` (decimal): Success percentage
- `failure_rate` (decimal): Failure percentage
- `decline_rate` (decimal): Card decline percentage
- `avg_processing_time_ms` (int): Average processing time

#### ecommerce_payment_metrics
Payment transaction data
- `id` (UUID): Primary key
- `store_id` (UUID): Reference to store
- `gateway_id` (UUID): Reference to payment gateway
- `amount` (decimal): Transaction amount
- `currency` (varchar): Transaction currency
- `status` (varchar): succeeded, failed, declined, etc.
- `authorization_time_ms` (int): Authorization processing time
- `created_at` (datetime): Transaction timestamp

#### ecommerce_sales_metrics
Sales performance metrics
- `id` (UUID): Primary key
- `store_id` (UUID): Reference to store
- `timestamp` (datetime): Metric time
- `revenue` (decimal): Revenue in period
- `order_count` (int): Orders in period
- `avg_order_value` (decimal): Average order value
- `conversion_rate` (decimal): Conversion percentage

#### ecommerce_alerts
Store alerts
- `id` (UUID): Primary key
- `store_id` (UUID): Reference to store
- `type` (varchar): Alert type (high_abandonment, low_payment_success, etc.)
- `message` (text): Alert message
- `severity` (varchar): critical, warning, info
- `status` (varchar): active, resolved
- `metrics` (json): Associated metric values
- `created_at`, `resolved_at` (datetime): Timestamps

## API Endpoints

### Store Management

#### List Stores
```
GET /api/ecommerce/stores
Auth: Bearer {token}
Response:
{
  "data": [
    {
      "id": "uuid",
      "storeName": "My Store",
      "storeUrl": "https://mystore.com",
      "platform": "shopify",
      "currency": "USD",
      "timezone": "America/New_York",
      "createdAt": "2024-01-01T00:00:00Z",
      "updatedAt": null
    }
  ]
}
```

#### Create Store
```
POST /api/ecommerce/stores
Auth: Bearer {token}
Body:
{
  "storeName": "My Store",
  "storeUrl": "https://mystore.com",
  "platform": "shopify",
  "currency": "USD",
  "timezone": "America/New_York"
}
Response: 201 Created
{
  "id": "uuid",
  "storeName": "My Store",
  "storeUrl": "https://mystore.com",
  "platform": "shopify",
  "currency": "USD"
}
```

#### Get Store
```
GET /api/ecommerce/stores/{id}
Auth: Bearer {token}
Response:
{
  "id": "uuid",
  "storeName": "My Store",
  "storeUrl": "https://mystore.com",
  "platform": "shopify",
  "currency": "USD",
  "timezone": "America/New_York",
  "metadata": {},
  "createdAt": "2024-01-01T00:00:00Z",
  "updatedAt": null
}
```

#### Update Store
```
PUT /api/ecommerce/stores/{id}
Auth: Bearer {token}
Body:
{
  "storeName": "Updated Store Name",
  "timezone": "America/Los_Angeles",
  "metadata": {"custom": "data"}
}
Response: 200 OK
```

#### Delete Store
```
DELETE /api/ecommerce/stores/{id}
Auth: Bearer {token}
Response: 200 OK
```

#### Store Health
```
GET /api/ecommerce/stores/{id}/health
Auth: Bearer {token}
Response:
{
  "storeId": "uuid",
  "storeName": "My Store",
  "status": "healthy|warning",
  "checkoutStepsConfigured": 5,
  "paymentGatewaysTotal": 2,
  "paymentGatewaysActive": 2,
  "metrics": {
    "avgConversionRate": 3.5,
    "avgCheckoutTime": 180000,
    "lastUpdate": "2024-01-01T12:00:00Z"
  }
}
```

### Checkout Management

#### List Checkout Steps
```
GET /api/ecommerce/stores/{storeId}/checkout-steps
Auth: Bearer {token}
Response:
{
  "data": [
    {
      "id": "uuid",
      "name": "Cart Review",
      "position": 1,
      "conversionRate": 95.2,
      "avgTimeMs": 45000,
      "abandonmentRate": 4.8
    }
  ]
}
```

#### Add Checkout Step
```
POST /api/ecommerce/stores/{storeId}/checkout-steps
Auth: Bearer {token}
Body:
{
  "name": "Payment",
  "position": 2
}
Response: 201 Created
```

#### Update Checkout Step
```
PUT /api/ecommerce/stores/{storeId}/checkout-steps/{stepId}
Auth: Bearer {token}
Body:
{
  "name": "Payment Gateway",
  "position": 2
}
Response: 200 OK
```

#### Delete Checkout Step
```
DELETE /api/ecommerce/stores/{storeId}/checkout-steps/{stepId}
Auth: Bearer {token}
Response: 200 OK
```

#### Realtime Checkout Metrics
```
GET /api/ecommerce/stores/{storeId}/checkout/realtime
Auth: Bearer {token}
Response:
{
  "storeId": "uuid",
  "activeUsers": 42,
  "completions": 156,
  "abandonment": 12,
  "steps": [
    {
      "id": "uuid",
      "name": "Cart Review",
      "position": 1,
      "conversionRate": 95.2,
      "avgTimeMs": 45000,
      "abandonmentRate": 4.8
    }
  ],
  "timestamp": "2024-01-01T12:00:00Z"
}
```

#### Checkout Performance
```
GET /api/ecommerce/stores/{storeId}/checkout/performance
Auth: Bearer {token}
Response:
{
  "storeId": "uuid",
  "totalSteps": 5,
  "avgConversionRate": 85.4,
  "avgCheckoutTime": 125000,
  "overallAbandonmentRate": 14.6,
  "bottleneck": null
}
```

### Payment Management

#### List Payment Gateways
```
GET /api/ecommerce/stores/{storeId}/payment-gateways
Auth: Bearer {token}
Response:
{
  "data": [
    {
      "id": "uuid",
      "provider": "stripe",
      "isActive": true,
      "successRate": 98.5,
      "avgProcessingTimeMs": 2500,
      "failureRate": 1.2,
      "declineRate": 0.3
    }
  ]
}
```

#### Add Payment Gateway
```
POST /api/ecommerce/stores/{storeId}/payment-gateways
Auth: Bearer {token}
Body:
{
  "provider": "stripe",
  "config": {
    "apiKey": "sk_live_...",
    "webhookUrl": "https://mystore.com/webhooks/stripe"
  }
}
Response: 201 Created
```

#### Payment Metrics
```
GET /api/ecommerce/stores/{storeId}/payment-metrics?timeframe=24h
Auth: Bearer {token}
Response:
{
  "storeId": "uuid",
  "timeframe": "24h",
  "totalGateways": 2,
  "activeGateways": 2,
  "avgSuccessRate": 98.5,
  "avgFailureRate": 1.2,
  "totalTransactions": 1250,
  "successfulTransactions": 1231,
  "failedTransactions": 19,
  "gateways": [...]
}
```

### Sales Management

#### Realtime Sales Metrics
```
GET /api/ecommerce/stores/{storeId}/sales/realtime?interval=1h
Auth: Bearer {token}
Response:
{
  "storeId": "uuid",
  "interval": "1h",
  "data": [
    {
      "timestamp": "2024-01-01T12:00:00Z",
      "revenue": 5234.50,
      "orders": 156,
      "conversionRate": 3.2,
      "avgOrderValue": 33.55
    }
  ]
}
```

#### Lost Revenue Calculation
```
GET /api/ecommerce/stores/{storeId}/sales/lost-revenue?timeframe=24h
Auth: Bearer {token}
Response:
{
  "storeId": "uuid",
  "timeframe": "24h",
  "lostRevenueTotal": 12450.75,
  "estimatedLostOrders": 342,
  "sources": {
    "checkout_abandonment": {
      "rate": 12.5,
      "lostRevenue": 8234.50
    },
    "payment_failures": {
      "rate": 1.2,
      "lostRevenue": 4216.25
    }
  }
}
```

### Alert Management

#### List Alerts
```
GET /api/ecommerce/stores/{storeId}/alerts?status=active
Auth: Bearer {token}
Response:
{
  "data": [
    {
      "id": "uuid",
      "type": "high_abandonment",
      "message": "Checkout abandonment rate exceeds 15%",
      "severity": "critical",
      "status": "active",
      "metrics": {"abandonmentRate": 18.5},
      "createdAt": "2024-01-01T10:00:00Z",
      "resolvedAt": null
    }
  ]
}
```

#### Create Alert
```
POST /api/ecommerce/stores/{storeId}/alerts
Auth: Bearer {token}
Body:
{
  "type": "high_abandonment",
  "message": "Checkout abandonment rate exceeds 15%",
  "severity": "critical",
  "metrics": {"abandonmentRate": 18.5}
}
Response: 201 Created
```

### Webhook Management

#### Stripe Webhook
```
POST /api/ecommerce/webhooks/stripe
Header: Stripe-Signature: {signature}
Body: {stripe event JSON}
Response: 200 OK
{
  "success": true
}
```

## Setup Instructions

### 1. Build and Start Services
```bash
cd api-monitor-saas
docker compose -f docker-compose.dev.yml up --build
```

### 2. Run Database Migrations
```bash
docker exec -it api-monitor-saas-symfony-1 php bin/console doctrine:migrations:migrate
```

### 3. Module Registration Check
The module is automatically registered in `src/Kernel.php` during kernel boot. No additional setup needed.

### 4. Enable Module for User
```bash
# Via API or programmatically
$moduleRegistry->enableModule($user, 'ecommerce');
```

## Service Configuration

All services are configured in `config/packages/api_monitor_modules.yaml`:

- **ModuleRegistry**: Manages module lifecycle and permissions
- **Controllers**: Inject repositories, services, entity manager, and logger
- **Services**: Inject entity manager for database operations
- **Repositories**: Tagged as Doctrine repository services for auto-wiring

## Access Control

- Routes require `Authorization: Bearer {jwt_token}` header
- Controllers validate user ownership of stores (foreign key)
- Module respects subscription tiers: `['pro', 'business', 'enterprise']`
- Module must be enabled for user in `UserModuleSubscription` table

## Next Steps

1. **Testing**: Create integration tests for all endpoints
2. **Metrics Collection**: Implement real metrics aggregation from payment gateways
3. **Alert Rules**: Implement configurable alert thresholds
4. **Real-time Updates**: Add WebSocket support for live metrics
5. **Reporting**: Add scheduled reports for sales and performance data
6. **Integration**: Connect to Stripe, PayPal, and other payment providers
7. **Analytics**: Implement advanced analytics and forecasting

## Troubleshooting

### Module not registered
- Check `src/Kernel.php` includes `EcommerceModule` registration
- Ensure module is instantiated with required dependencies
- Check service container has `ModuleRegistry` service defined

### Database migration fails
- Ensure PostgreSQL is running and accessible
- Check database user has CREATE TABLE permissions
- Run `php bin/console doctrine:database:create` first if needed

### Controller dependencies fail
- Ensure all required services are defined in `api_monitor_modules.yaml`
- Check service names match repository and service class names
- Verify constructor parameters match service definitions
