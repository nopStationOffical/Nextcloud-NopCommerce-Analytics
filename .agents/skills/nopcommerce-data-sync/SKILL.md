---
name: nopcommerce-data-sync
description: >-
  Skill for implementing the nopCommerce data synchronization engine.
  Covers API client, JWT auth flow, paginated fetching, entity upsert, 
  incremental sync, and sync audit logging.
---

# nopCommerce Data Sync Implementation Skill

## When to Use
Activate when building or modifying the `NopApiClient` or `SyncService`.

## Authentication Implementation

### Login Flow
```php
class NopApiClient {
    private IConfig $config;
    private IClientService $httpClient;
    
    public function authenticate(): string {
        $baseUrl = $this->config->getAppValue('nopstation_analytics', 'nop_api_url');
        $email = $this->config->getAppValue('nopstation_analytics', 'nop_admin_email');
        $password = $this->config->getAppValue('nopstation_analytics', 'nop_admin_password');
        
        $response = $this->httpClient->newClient()->post(
            $baseUrl . '/api/admincustomer/login',
            [
                'json' => [
                    'Data' => [
                        'Email' => $email,
                        'Password' => $password,
                        'CheckoutAsGuest' => false,
                        'UsernamesEnabled' => false,
                        'RegistrationType' => 1,
                        'RememberMe' => false,
                        'DisplayCaptcha' => false,
                    ]
                ],
                'headers' => [
                    'User-Agent' => 'com.bs.ecommerce/1.0',
                ]
            ]
        );
        
        $data = json_decode($response->getBody(), true);
        $token = $data['Data']['Token'] ?? null;
        
        if ($token) {
            $this->config->setAppValue('nopstation_analytics', 'nop_jwt_token', $token);
            $this->config->setAppValue('nopstation_analytics', 'nop_token_issued_at', (string)time());
        }
        
        return $token;
    }
    
    public function getToken(): string {
        $token = $this->config->getAppValue('nopstation_analytics', 'nop_jwt_token', '');
        $issuedAt = (int)$this->config->getAppValue('nopstation_analytics', 'nop_token_issued_at', '0');
        
        // Refresh if older than 28 days
        if (empty($token) || (time() - $issuedAt) > (28 * 24 * 60 * 60)) {
            $token = $this->authenticate();
        }
        
        return $token;
    }
}
```

## Paginated Fetching Pattern
```php
public function fetchAllOrders(?string $startDate = null): array {
    $allOrders = [];
    $page = 1;
    $pageSize = 100;
    
    do {
        $response = $this->makeAuthenticatedRequest('POST', '/api/order/List', [
            'Data' => [
                'StartDate' => $startDate,
                'EndDate' => null,
                'OrderStatusIds' => null,
                'PaymentStatusIds' => null,
                'ShippingStatusIds' => null,
                'StoreId' => 0,
                'Page' => $page,
                'PageSize' => $pageSize,
                'Start' => ($page - 1) * $pageSize,
                'Length' => $pageSize,
            ]
        ]);
        
        $orders = $response['Data']['Data'] ?? [];
        $allOrders = array_merge($allOrders, $orders);
        $page++;
    } while (count($orders) === $pageSize);
    
    return $allOrders;
}
```

## Money Value Parsing
nopCommerce returns formatted strings like `"$1,855.00"`, `"19.00৳"`, `"($10.00)"`.
Always parse to float before storing:
```php
private function parseMoneyValue(string $formatted): float {
    // Handle negative in parentheses: ($10.00) → -10.00
    $negative = str_contains($formatted, '(');
    $cleaned = preg_replace('/[^0-9.]/', '', $formatted);
    $value = (float)$cleaned;
    return $negative ? -$value : $value;
}
```

## Fetching Order Details for Numeric Values
The Order List endpoint returns formatted strings. For accurate numeric data,
fetch individual order details which include `*Value` fields:
```php
public function fetchOrderDetails(int $orderId): array {
    return $this->makeAuthenticatedRequest('GET', "/api/order/Edit/{$orderId}");
}
// Response includes: OrderSubtotalInclTaxValue, OrderTotalValue, Profit, etc.
```

## Sync Audit Logging
After every sync operation, log the result:
```php
$log = new SyncLogEntity();
$log->setSyncType('incremental');   // 'full', 'incremental', 'webhook'
$log->setEntityType('orders');       // 'orders', 'customers', 'products'
$log->setRecordsProcessed(count($processedRecords));
$log->setStatus('success');          // 'success', 'error'
$log->setErrorMessage(null);
$log->setCreatedAt(date('Y-m-d H:i:s'));
$this->syncLogMapper->insert($log);
```
