# nopCommerce API — Authentication, Data Fetching & Field Mapping

## Authentication Flow

### Step 1: Login to get JWT Token
```
POST {BASE_URL}/api/admincustomer/login
Headers:
  Content-Type: application/json
  User-Agent: com.bs.ecommerce/1.0

Body:
{
  "Data": {
    "CheckoutAsGuest": false,
    "Email": "admin@yourstore.com",
    "UsernamesEnabled": false,
    "RegistrationType": 1,
    "Username": "",
    "Password": "admin",
    "RememberMe": false,
    "DisplayCaptcha": false
  }
}

Response:
{
  "Data": {
    "CustomerInfo": { ... },
    "Token": "eyJ0eXAiOi..."   ← JWT Token (30-day lifetime, refresh at 28 days)
  }
}
```

### Step 2: Use Token for authenticated requests
```
Headers for ALL subsequent requests:
  Content-Type: application/json
  Token: {JWT_TOKEN}
  User-Agent: com.bs.ecommerce/1.0
```

### Token Management Rules
- JWT lifetime: 30 days from nopCommerce.
- Store token + issue timestamp in Nextcloud App Config (`IConfig`).
- Auto-refresh token when age > 28 days.
- Admin email and password are stored encrypted in Nextcloud settings.

---

## Entity Data Fetching Endpoints

### Orders — Paginated List
```
POST {BASE_URL}/api/order/List
Body:
{
  "Data": {
    "StartDate": null,      // or "2026-01-01"
    "EndDate": null,         // or "2026-08-31"
    "OrderStatusIds": null,  // or [10, 20, 30]
    "PaymentStatusIds": null,
    "ShippingStatusIds": null,
    "StoreId": 0,            // 0 = all stores
    "Page": 1,
    "PageSize": 100,
    "Start": 0,
    "Length": 100
  }
}
```

**Response** returns paginated order list. Key fields per order in `Data.Data[]`:
- `Id`, `OrderGuid`, `CustomOrderNumber`
- `StoreName`, `StoreId` (from context, not always in list response)
- `CustomerId`, `CustomerEmail`, `CustomerFullName`
- `OrderTotal` — ⚠️ FORMATTED STRING (e.g. `"$700.00"` or `"19.00৳"`)
- `OrderStatusId`, `PaymentStatusId`, `ShippingStatusId`
- `CreatedOnUtc`

### Orders — Single Edit (for numeric values)
```
GET {BASE_URL}/api/order/Edit/{orderId}
```

**Response** includes NUMERIC fields we need for analytics:
- `OrderSubtotalInclTaxValue` (float)
- `OrderSubtotalExclTaxValue` (float)
- `OrderShippingInclTaxValue` (float)
- `OrderShippingExclTaxValue` (float)
- `TaxRateValue` (float)
- `OrderTotalValue` (float) — ⚠️ Sometimes formatted, cross-check
- `Profit` — May be formatted string like `"$700.00"`
- `OrderStatusId` (10=Pending, 20=Processing, 30=Complete, 40=Cancelled)
- `PaymentStatusId` (10=Pending, 20=Authorized, 30=Paid, 35=PartiallyRefunded, 40=Refunded, 50=Voided)
- `ShippingStatusId` (10=NotYetShipped, 20=PartiallyShipped, 25=Shipped, 30=Delivered, 40=NotRequired)
- `CreatedOnUtc`

### Customers — List
```
POST {BASE_URL}/api/customer/CustomerList
Body:
{
  "Data": {
    "SearchEmail": "",
    "SearchFirstName": "",
    "SearchLastName": "",
    "SelectedCustomerRoleIds": [3],
    "Page": 1,
    "PageSize": 100,
    "Start": 0,
    "Length": 100
  }
}
```

### Products — List
```
POST {BASE_URL}/api/product/ProductList
Body:
{
  "Data": {
    "SearchProductName": "",
    "SearchCategoryId": 0,
    "SearchManufacturerId": 0,
    "SearchStoreId": 0,
    "Page": 1,
    "PageSize": 100,
    "Start": 0,
    "Length": 100
  }
}
```

### Shipments — List
```
POST {BASE_URL}/api/order/ShipmentListSelect
Body:
{
  "Data": {
    "StartDate": null,
    "EndDate": null,
    "Page": 1,
    "PageSize": 100,
    "Start": 0,
    "Length": 100
  }
}
```

### Stores — List
```
POST {BASE_URL}/api/store/StoreList
```

---

## Critical Field Mapping Notes

### Money Value Extraction
nopCommerce returns **formatted currency strings** in list views (e.g. `"$1,855.00"`, `"19.00৳"`). These MUST be parsed to numeric values before storing:

```php
// Strip currency symbols, commas, and non-numeric chars except decimal point
function parseMoneyValue(string $formatted): float {
    $cleaned = preg_replace('/[^0-9.\-]/', '', $formatted);
    return (float)$cleaned;
}
```

The **Order Edit** endpoint returns numeric `*Value` fields (e.g. `OrderSubtotalInclTaxValue: 1855`). Prefer these when available.

### Status ID Mappings
| Status Type | ID | Name |
|---|---|---|
| OrderStatus | 10 | Pending |
| OrderStatus | 20 | Processing |
| OrderStatus | 30 | Complete |
| OrderStatus | 40 | Cancelled |
| PaymentStatus | 10 | Pending |
| PaymentStatus | 20 | Authorized |
| PaymentStatus | 30 | Paid |
| PaymentStatus | 35 | PartiallyRefunded |
| PaymentStatus | 40 | Refunded |
| PaymentStatus | 50 | Voided |
| ShippingStatus | 10 | NotYetShipped |
| ShippingStatus | 20 | PartiallyShipped |
| ShippingStatus | 25 | Shipped |
| ShippingStatus | 30 | Delivered |
| ShippingStatus | 40 | NotRequired |

### GET vs POST Convention
For each entity in nopCommerce:
- `GET /entity/List` → Returns the **form model** with available filter options (dropdowns, defaults).
- `POST /entity/List` → Returns the actual **paginated data** filtered by the posted parameters.
- `GET /entity/Edit/{id}` → Returns the **full detail** for a single entity (includes numeric value fields).
