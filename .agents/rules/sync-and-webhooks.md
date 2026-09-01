# Data Synchronization Strategy & Webhook Security

## Sync Modes

### Full Sync (Initial Import)
- Triggered manually from Settings UI or on first-time app setup.
- Fetches ALL entities from nopCommerce via paginated API calls.
- Pagination: `PageSize: 100`, loop until `Data.Data[]` returns empty.
- Order of sync: Stores → Customers → Products → Orders → Order Items → Shipments.
- Writes audit log entry per entity type in `*prefix*_nop_sync_logs`.

### Incremental Sync (Scheduled)
- Runs via Nextcloud background job (`ScheduledSyncJob`), every 15 minutes by default.
- Fetches only records created/modified since `last_sync_timestamp`.
- Uses nopCommerce `StartDate` filter on Order List and Customer List endpoints.
- For Products: fetches full list (smaller dataset) and upserts based on `nop_product_id`.
- Updates `last_sync_timestamp` in Nextcloud App Config after successful sync.

### Webhook-Triggered Sync (Real-Time)
- Processes individual entity events as they arrive.
- Inserts/updates a single record in the appropriate table.
- Does NOT replace full/incremental sync — only supplements it for immediate updates.

## Upsert Strategy
For each entity, the sync uses "find or create" pattern:
```php
$existing = $this->orderMapper->findByNopOrderId($nopOrderId);
if ($existing !== null) {
    // Update existing fields
    $existing->setOrderTotal($data['OrderTotal']);
    $this->orderMapper->update($existing);
} else {
    // Insert new
    $entity = new OrderEntity();
    $entity->setNopOrderId($nopOrderId);
    // ... set all fields
    $this->orderMapper->insert($entity);
}
```

## Sync Logging
Every sync operation writes to `*prefix*_nop_sync_logs`:
```
| id | sync_type   | entity_type | records_processed | status  | error_message | created_at          |
|----|-------------|-------------|-------------------|---------|---------------|---------------------|
| 1  | full        | orders      | 214               | success | null          | 2026-08-31 19:50:00 |
| 2  | incremental | orders      | 3                 | success | null          | 2026-08-31 20:05:00 |
| 3  | webhook     | order       | 1                 | success | null          | 2026-08-31 20:08:00 |
```

---

## Webhook Security Architecture

### Endpoint
`POST /apps/nopstation_analytics/api/v1/webhook`

### Authentication: HMAC-SHA256 Signature
1. nopCommerce plugin signs request body using shared secret.
2. Signature sent in `X-NopStation-Signature` header (base64-encoded HMAC-SHA256).
3. Nextcloud verifies signature before processing.

### Replay Protection
- `X-NopStation-Timestamp` header contains Unix timestamp.
- Reject requests where `abs(time() - timestamp) > 300` (5 minutes).

### Verification Flow
```php
public function verifyWebhook(IRequest $request, string $rawBody): bool {
    $signature = $request->getHeader('X-NopStation-Signature');
    $timestamp = $request->getHeader('X-NopStation-Timestamp');
    $secret = $this->config->getAppValue('nopstation_analytics', 'webhook_secret');

    // 1. Check timestamp freshness
    if (abs(time() - (int)$timestamp) > 300) {
        return false;  // Replay attack
    }

    // 2. Verify HMAC signature
    $computed = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
    return hash_equals($computed, $signature);
}
```

### Supported Events
| Event | Trigger | Action |
|---|---|---|
| `order.created` | New order placed | Insert order + items into DB |
| `order.updated` | Order status/payment changes | Update existing order record |
| `customer.created` | New customer registration | Insert customer record |
| `product.updated` | Product info changed | Update product record |
| `shipment.created` | Shipment dispatched | Insert shipment, update order shipping status |

### Security: IP Whitelisting (Optional)
Admin can configure allowed source IPs in Settings. If configured, webhook requests from non-whitelisted IPs are rejected with HTTP 403.
