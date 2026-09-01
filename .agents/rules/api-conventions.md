# Nextcloud API Controller Conventions

## JSON Response Format
All API endpoints MUST return consistent JSON structure:
```json
{
  "Data": { ... },
  "Message": "string or null",
  "ErrorList": []
}
```

This matches nopCommerce's own API response format, providing consistency across the system.

## Controller Base Classes
- **Page views**: Extend `OCP\AppFramework\Controller`, return `TemplateResponse`.
- **Internal API endpoints**: Extend `OCP\AppFramework\Controller`, return `JSONResponse`.
- **Webhook receiver**: Extend `OCP\AppFramework\Controller`, use `#[PublicPage]` + `#[NoCSRFRequired]`.

## Route Registration
Routes are registered via PHP 8 attributes on controller methods:
```php
// Frontpage route (renders HTML template)
#[FrontpageRoute(verb: 'GET', url: '/')]

// Internal API routes (JSON, require Nextcloud login)
#[FrontpageRoute(verb: 'GET', url: '/api/v1/analytics/kpi')]
#[FrontpageRoute(verb: 'POST', url: '/api/v1/sync/run')]

// Webhook route (public, no CSRF, no admin required)
#[PublicPage]
#[NoCSRFRequired]
#[FrontpageRoute(verb: 'POST', url: '/api/v1/webhook')]
```

## App Route File (`appinfo/routes.php`)
If using `routes.php` instead of attributes:
```php
return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'settings#get', 'url' => '/api/v1/settings', 'verb' => 'GET'],
        ['name' => 'settings#save', 'url' => '/api/v1/settings', 'verb' => 'POST'],
        ['name' => 'settings#test', 'url' => '/api/v1/settings/test', 'verb' => 'POST'],
        ['name' => 'analytics#kpi', 'url' => '/api/v1/analytics/kpi', 'verb' => 'GET'],
        ['name' => 'analytics#trends', 'url' => '/api/v1/analytics/trends', 'verb' => 'GET'],
        ['name' => 'analytics#bestsellers', 'url' => '/api/v1/analytics/bestsellers', 'verb' => 'GET'],
        ['name' => 'analytics#customers', 'url' => '/api/v1/analytics/customers', 'verb' => 'GET'],
        ['name' => 'sync#run', 'url' => '/api/v1/sync/run', 'verb' => 'POST'],
        ['name' => 'sync#status', 'url' => '/api/v1/sync/status', 'verb' => 'GET'],
        ['name' => 'webhook#receive', 'url' => '/api/v1/webhook', 'verb' => 'POST'],
    ],
];
```

## Error Handling Pattern
```php
try {
    $result = $this->service->doSomething($params);
    return new JSONResponse(['Data' => $result, 'Message' => null, 'ErrorList' => []]);
} catch (\Exception $e) {
    return new JSONResponse(
        ['Data' => null, 'Message' => $e->getMessage(), 'ErrorList' => [$e->getMessage()]],
        Http::STATUS_INTERNAL_SERVER_ERROR
    );
}
```
