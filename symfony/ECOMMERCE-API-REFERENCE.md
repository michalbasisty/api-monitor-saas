# E-Commerce Module - API Reference

## Authentication
All endpoints require `Authorization: Bearer {JWT_TOKEN}` header and `ROLE_USER` role.

---

## Store Management

### List Stores
```
GET /api/ecommerce/stores
Response: 200 OK
{
  "stores": [
    {
      "id": "uuid",
      "store_name": "My Store",
      "store_url": "https://mystore.com",
      "platform": "shopify|woocommerce|custom|magento",
      "currency": "USD",
      "timezone": "America/New_York",
      "created_at": "2024-01-15T10:30:00+00:00"
    }
  ]
}
```

### Create Store
```
POST /api/ecommerce/stores
Content-Type: application/json

{
  "store_name": "My Store",
  "store_url": "https://mystore.com",
  "platform": "shopify",
  "currency": "USD",
  "timezone": "America/New_York"
}

Response: 201 Created
{
  "id": "uuid",
  "store_name": "My Store",
  "store_url": "https://mystore.com",
  "platform": "shopify",
  "created_at": "2024-01-15T10:30:00+00:00"
}
```

### Get Store Details
```
GET /api/ecommerce/stores/{id}
Response: 200 OK
{
  "id": "uuid",
  "store_name": "My Store",
  "store_url": "https://mystore.com",
  "platform": "shopify",
  "currency": "USD",
  "timezone": "America/New_York",
  "created_at": "2024-01-15T10:30:00+00:00"
}
```

### Update Store
```
PUT /api/ecommerce/stores/{id}
Content-Type: application/json

{
  "store_name": "Updated Name",
  "currency": "EUR",
  "timezone": "Europe/London"
}

Response: 200 OK
{
  "id": "uuid",
  "store_name": "Updated Name",
  "currency": "EUR",
  "timezone": "Europe/London",
  "updated_at": "2024-01-15T10:35:00+00:00"
}
```

### Get Store Health Status
```
GET /api/ecommerce/stores/{id}/health
Response: 200 OK
{
  "status": "healthy|degraded|down",
  "uptime_percentage": 99.9,
  "revenue_per_minute": 125.50,
  "error_rate": 0.1
}
```

---

## Checkout Configuration

### List Checkout Steps
```
GET /api/ecommerce/stores/{storeId}/checkout-steps
Response: 200 OK
{
  "steps": [
    {
      "id": "uuid",
      "step_number": 1,
      "step_name": "cart",
      "endpoint_url": "https://mystore.com/cart",
      "expected_load_time_ms": 1000,
      "alert_threshold_ms": 2000,
      "enabled": true
    }
  ]
}
```

### Add Checkout Step
```
POST /api/ecommerce/stores/{storeId}/checkout-steps
Content-Type: application/json

{
  "step_number": 1,
  "step_name": "cart",
  "endpoint_url": "https://mystore.com/cart",
  "expected_load_time_ms": 1000,
  "alert_threshold_ms": 2000
}

Response: 201 Created
{
  "id": "uuid",
  "step_number": 1,
  "step_name": "cart",
  "endpoint_url": "https://mystore.com/cart"
}
```

### Get Real-Time Checkout Metrics
```
GET /api/ecommerce/stores/{storeId}/checkout/realtime
Response: 200 OK
{
  "store_id": "uuid",
  "steps_count": 6,
  "metrics_count": 100,
  "metrics": [
    {
      "step_id": "uuid",
      "load_time_ms": 450,
      "error_occurred": false,
      "http_status_code": 200,
      "timestamp": "2024-01-15T10:30:00+00:00"
    }
  ]
}
```

### Get Checkout Performance Analytics
```
GET /api/ecommerce/stores/{storeId}/checkout/performance
Response: 200 OK
{
  "store_id": "uuid",
  "steps": [
    {
      "id": "uuid",
      "name": "cart",
      "avg_load_time_ms": 450,
      "error_rate_percentage": 2.5,
      "completion_rate_percentage": 98.0
    }
  ],
  "overall_completion_rate": 85.2
}
```

---

## Payment Gateway Integration

### List Payment Gateways
```
GET /api/ecommerce/stores/{storeId}/payment-gateways
Response: 200 OK
{
  "gateways": [
    {
      "id": "uuid",
      "gateway_name": "stripe",
      "is_primary": true,
      "enabled": true,
      "created_at": "2024-01-15T10:30:00+00:00"
    }
  ]
}
```

### Add Payment Gateway
```
POST /api/ecommerce/stores/{storeId}/payment-gateways
Content-Type: application/json

{
  "gateway_name": "stripe",
  "api_key": "sk_test_...",
  "webhook_url": "https://myapp.com/api/ecommerce/webhooks/stripe",
  "is_primary": true
}

Response: 201 Created
{
  "id": "uuid",
  "gateway_name": "stripe",
  "is_primary": true
}
```

### Get Payment Metrics
```
GET /api/ecommerce/stores/{storeId}/payment-metrics
Response: 200 OK
{
  "store_id": "uuid",
  "authorization_success_rate": 98.5,
  "declined_rate": 1.5,
  "total_transactions": 1000,
  "authorized_transactions": 985
}
```

---

## Sales & Revenue Tracking

### Get Real-Time Sales Metrics
```
GET /api/ecommerce/stores/{storeId}/sales/realtime
Response: 200 OK
{
  "status": "normal|degraded|down",
  "revenue_per_minute": 125.50,
  "orders_per_minute": 6,
  "checkout_success_rate": 2.3,
  "avg_order_value": 45.99,
  "timestamp": "2024-01-15T10:30:00+00:00"
}

// During outage:
{
  "status": "down",
  "revenue_per_minute": 0,
  "orders_per_minute": 0,
  "checkout_success_rate": 0,
  "estimated_lost_revenue": 5250.00,
  "timestamp": "2024-01-15T10:35:00+00:00"
}
```

### Calculate Lost Revenue
```
GET /api/ecommerce/stores/{storeId}/sales/lost-revenue?from=2024-01-15T08:00:00Z&to=2024-01-15T10:00:00Z
Response: 200 OK
{
  "store_id": "uuid",
  "period": {
    "from": "2024-01-15T08:00:00+00:00",
    "to": "2024-01-15T10:00:00+00:00"
  },
  "estimated_lost_revenue": 5250.00,
  "currency": "USD"
}
```

### Get Sales Trends
```
GET /api/ecommerce/stores/{storeId}/sales/trends?days=7
Response: 200 OK
{
  "store_id": "uuid",
  "period_days": 7,
  "data_points": 168,
  "metrics": [
    {
      "timestamp": "2024-01-08T10:00:00+00:00",
      "revenue_per_minute": 125.50,
      "orders_per_minute": 6,
      "checkout_success_rate": 2.3,
      "status": "normal"
    }
  ]
}
```

---

## Alert Management

### List Active Alerts
```
GET /api/ecommerce/stores/{storeId}/alerts?resolved=false
Response: 200 OK
{
  "alerts": [
    {
      "id": "uuid",
      "alert_type": "checkout_slow|payment_failed|high_abandonment|traffic_spike",
      "severity": "low|medium|high|critical",
      "triggered_at": "2024-01-15T10:30:00+00:00",
      "metric_value": 2100,
      "threshold_value": 2000,
      "description": "Checkout step taking longer than expected",
      "resolved_at": null
    }
  ]
}
```

### List Alert History
```
GET /api/ecommerce/stores/{storeId}/alerts?resolved=true
Response: 200 OK
{
  "alerts": [
    {
      "id": "uuid",
      "alert_type": "checkout_slow",
      "severity": "high",
      "triggered_at": "2024-01-15T10:30:00+00:00",
      "resolved_at": "2024-01-15T10:35:00+00:00",
      "description": "Checkout step resolved"
    }
  ]
}
```

### Create Custom Alert
```
POST /api/ecommerce/stores/{storeId}/alerts
Content-Type: application/json

{
  "alert_type": "custom_threshold",
  "severity": "high",
  "metric_value": 75.5,
  "threshold_value": 80.0,
  "description": "Custom metric exceeded threshold"
}

Response: 201 Created
{
  "id": "uuid",
  "alert_type": "custom_threshold",
  "severity": "high",
  "triggered_at": "2024-01-15T10:30:00+00:00"
}
```

---

## Webhooks

### Stripe Webhook Endpoint
```
POST /api/ecommerce/webhooks/stripe
Headers:
  Stripe-Signature: t={timestamp},v1={signature}
  Content-Type: application/json

Body: Stripe webhook payload (automatically verified)

Response: 200 OK

Supported Events:
- charge.succeeded       → Record successful payment
- charge.failed         → Record failed payment + alert
- charge.refunded       → Update payment status to refunded
- charge.dispute.created → Alert on chargeback
- payment_intent.succeeded → Record payment intent
```

### PayPal Webhook Endpoint
```
POST /api/ecommerce/webhooks/paypal
(Implementation coming soon)
```

### Square Webhook Endpoint
```
POST /api/ecommerce/webhooks/square
(Implementation coming soon)
```

---

## Error Responses

### 400 Bad Request
```json
{
  "error": "Missing required fields: store_name, store_url"
}
```

### 401 Unauthorized
```json
{
  "error": "Missing or invalid JWT token"
}
```

### 403 Forbidden
```json
{
  "error": "Insufficient permissions for this resource"
}
```

### 404 Not Found
```json
{
  "error": "Store not found"
}
```

### 500 Internal Server Error
```json
{
  "error": "Database connection failed"
}
```

---

## Rate Limiting

- **Tier**: Free (5 requests/sec), Pro (50 requests/sec), Enterprise (unlimited)
- **Headers**: 
  - `X-RateLimit-Limit`: 5
  - `X-RateLimit-Remaining`: 4
  - `X-RateLimit-Reset`: 1705319460

---

## Testing Endpoints

### Quick Smoke Test
```bash
# 1. Create store
curl -X POST http://localhost:8000/api/ecommerce/stores \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "store_name": "Test Store",
    "store_url": "https://test.example.com",
    "platform": "shopify",
    "currency": "USD"
  }'

# 2. List stores
curl -X GET http://localhost:8000/api/ecommerce/stores \
  -H "Authorization: Bearer YOUR_TOKEN"

# 3. Get store health
curl -X GET http://localhost:8000/api/ecommerce/stores/{STORE_ID}/health \
  -H "Authorization: Bearer YOUR_TOKEN"

# 4. Add checkout step
curl -X POST http://localhost:8000/api/ecommerce/stores/{STORE_ID}/checkout-steps \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "step_number": 1,
    "step_name": "cart",
    "endpoint_url": "https://test.example.com/cart"
  }'

# 5. Add payment gateway
curl -X POST http://localhost:8000/api/ecommerce/stores/{STORE_ID}/payment-gateways \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "gateway_name": "stripe",
    "api_key": "sk_test_...",
    "is_primary": true
  }'

# 6. Get sales metrics
curl -X GET http://localhost:8000/api/ecommerce/stores/{STORE_ID}/sales/realtime \
  -H "Authorization: Bearer YOUR_TOKEN"

# 7. List alerts
curl -X GET http://localhost:8000/api/ecommerce/stores/{STORE_ID}/alerts \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Database Schema

All tables use UUID primary keys with indexed foreign keys:

- `ecommerce_stores` - Store configuration
- `ecommerce_checkout_steps` - Checkout flow steps
- `ecommerce_checkout_metrics` - Real-time metrics (indexed by store_id, timestamp)
- `ecommerce_payment_gateways` - Payment processor configs
- `ecommerce_payment_metrics` - Transaction records (indexed by store_id, transaction_id)
- `ecommerce_sales_metrics` - Revenue/order metrics (indexed by store_id, timestamp)
- `ecommerce_abandonment` - Cart abandonment tracking
- `ecommerce_traffic_spikes` - Traffic anomaly detection
- `ecommerce_alerts` - E-commerce specific alerts

---

## Next Steps

1. **Deploy migrations**: `php bin/console doctrine:migrations:migrate`
2. **Test API endpoints** using curl commands above
3. **Set up Stripe webhooks** in Stripe dashboard
4. **Configure environment variables**:
   - `STRIPE_SECRET_KEY=sk_test_...`
   - `STRIPE_WEBHOOK_SECRET=whsec_...`
5. **Build React dashboard** to consume these endpoints

