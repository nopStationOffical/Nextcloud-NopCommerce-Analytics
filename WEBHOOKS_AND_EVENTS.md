# Webhook Engine & Event-Driven Data Synchronization Architecture

## Overview
This document specifies the event-driven data synchronization architecture between **nopCommerce** and the **Nextcloud nopStation Sales Analytics App**.

Since nopCommerce raw REST APIs provide entity-level endpoints (`/order/List`, `/customer/CustomerList`, `/product/ProductList`, `/order/ShipmentList`), the Nextcloud App ingests raw entity data, stores it in its local database, and processes analytics calculations locally. 

To keep analytics data up-to-date in real time without continuous heavy polling, the app supports **Event-Driven Webhooks** alongside scheduled cron sync.

---

## 1. Security & Authentication Architecture

### 1.1 Signature Verification (HMAC-SHA256)
Every incoming webhook request from nopCommerce MUST include an authentication signature in the HTTP header:
- Header Name: `X-NopStation-Signature`
- Payload: Base64-encoded HMAC-SHA256 signature calculated over the raw HTTP request body using a shared **Webhook Secret**.

#### Verification Algorithm (Nextcloud Receiver):
```php
$signature = $request->getHeader('X-NopStation-Signature');
$computedSignature = base64_encode(hash_hmac('sha256', $requestBody, $webhookSecret, true));

if (!hash_equals($signature, $computedSignature)) {
    return new JSONResponse(['error' => 'Invalid signature'], Http::STATUS_UNAUTHORIZED);
}
```

### 1.2 Webhook Secret Management
- Configured in Nextcloud App Settings (`Settings > nopCommerce Sales Analytics`).
- Shared key stored encrypted in Nextcloud App System Config (`OCP\IConfig`).

### 1.3 Replay Protection
Requests MUST include a timestamp header (`X-NopStation-Timestamp`). Any request with a timestamp older than 300 seconds (5 minutes) will be rejected to prevent replay attacks.

---

## 2. Webhook Receiver Endpoint

- **Endpoint URL**: `POST /apps/nopstation_analytics/api/v1/webhook`
- **Authentication**: HMAC-SHA256 (`X-NopStation-Signature`)
- **Response Format**:
```json
{
  "status": "success",
  "message": "Event processed successfully",
  "event": "order.created",
  "entity_id": 1042,
  "processed_at": "2026-08-31T19:45:00Z"
}
```

---

## 3. Supported Webhook Events & Payloads

### 3.1 `order.created` / `order.updated`
Triggered when a new order is placed or an order status/payment status changes in nopCommerce.

```json
{
  "event": "order.created",
  "timestamp": 1788205500,
  "data": {
    "Id": 1042,
    "OrderGuid": "8f3b2a1c-4d5e-6f7a-8b9c-0d1e2f3a4b5c",
    "StoreId": 1,
    "CustomerId": 45,
    "OrderStatusId": 30,
    "OrderStatus": "Processing",
    "PaymentStatusId": 30,
    "PaymentStatus": "Paid",
    "ShippingStatusId": 20,
    "OrderSubtotalInclTax": 250.00,
    "OrderSubtotalExclTax": 220.00,
    "OrderSubTotalDiscountInclTax": 0.00,
    "OrderShippingInclTax": 15.00,
    "TaxTotal": 15.00,
    "OrderTotal": 265.00,
    "Profit": 85.00,
    "CreatedOnUtc": "2026-08-31T19:44:00Z",
    "Items": [
      {
        "Id": 205,
        "ProductId": 78,
        "ProductName": "Build your own computer",
        "Quantity": 1,
        "UnitPriceInclTax": 250.00,
        "PriceInclTax": 250.00,
        "AttributeXml": ""
      }
    ]
  }
}
```

### 3.2 `customer.created`
Triggered when a new customer registers on nopCommerce.

```json
{
  "event": "customer.created",
  "timestamp": 1788205500,
  "data": {
    "Id": 45,
    "CustomerGuid": "a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d",
    "Email": "john.doe@example.com",
    "Username": "johndoe",
    "Active": true,
    "CreatedOnUtc": "2026-08-31T19:40:00Z"
  }
}
```

### 3.3 `shipment.created`
Triggered when a shipment is created for an order.

```json
{
  "event": "shipment.created",
  "timestamp": 1788205500,
  "data": {
    "Id": 12,
    "OrderId": 1042,
    "TrackingNumber": "TRACK123456789",
    "ShippedDateUtc": "2026-08-31T19:42:00Z",
    "DeliveryDateUtc": null
  }
}
```

---

## 4. Error Handling & Audit Logging

- **Audit Log Table**: All incoming webhooks are logged in `*prefix*_nop_sync_logs` table with fields (`id`, `event_type`, `payload`, `status`, `error_message`, `received_at`).
- **Retry Mechanism**: If Nextcloud returns a 5xx error, nopCommerce retries delivery with exponential backoff (1 min, 5 min, 15 min, 1 hr).
