# Test Cases Suite: nopCommerce Analytics by nopStation

A comprehensive test suite covering all functional modules, REST API endpoints, UI state persistence, data sync, and Nextcloud platform compliance.

---

## Test Cases Summary Matrix

| Test ID | Module | Title | Priority | Verification Method |
| --- | --- | --- | --- | --- |
| **TC-01** | Settings & API Client | nopCommerce REST API Connection & JWT Authentication | Critical | PHP CLI / REST API |
| **TC-02** | Data Sync Engine | Full & Incremental Synchronization with Audit Logging | Critical | `POST /api/v1/sync/run` |
| **TC-03** | Executive Dashboard | Sales KPI Aggregation & Trend Curves | High | `GET /api/v1/analytics/kpi`, `GET /trends` |
| **TC-04** | Fulfillment & Logistics | Shipment Overview Status Counts & Logistics Activity Feed | High | `GET /api/v1/analytics/shipments` |
| **TC-05** | Sales Summary | Date/Period Sales Grouping (Day/Week/Month) | High | `GET /api/v1/analytics/summary` |
| **TC-06** | Period Orders Hierarchy | Expandable Date Row Orders & Level-3 Line Items | High | `GET /api/v1/analytics/summary/orders` |
| **TC-07** | Customer Segmentation | First-Time vs Returning Buyers & Date Range Filters | High | `GET /api/v1/analytics/customers` |
| **TC-08** | Customer Orders & Line Items | Customer Order History with Payment/Shipping/Discount & Items | High | `GET /api/v1/analytics/customers/{id}/orders` |
| **TC-09** | Dual Pagination | Parent Table & Nested Child Orders Slicing | Medium | Frontend Unit / Slice Logic |
| **TC-10** | URL Hash Routing | State Persistence Across Browser Refresh & History | Medium | Hash / `localStorage` State Check |
| **TC-11** | Low Stock Inventory | Real-Time Low Stock Alerts Threshold ($\le 10$ units) | Medium | `GET /api/v1/analytics/lowstock` |
| **TC-12** | Report Exports | Automated CSV Report Generation in Nextcloud Files | Medium | `POST /api/v1/analytics/export` |
| **TC-13** | Webhooks Receiver | Signature Verification & Real-Time Event Ingestion | High | `POST /api/v1/webhook` |
| **TC-14** | Code Signing & Compliance | Nextcloud `info.xsd` Schema Validation & App Store Signatures | Critical | `DOMDocument::schemaValidate` |

---

## Detailed Test Case Specifications

### TC-01: nopCommerce REST API Connection & JWT Authentication
- **Objective**: Verify that the Nextcloud backend can authenticate with nopCommerce REST APIs, handle token expiration, and send appropriate custom headers (`Admin-Token`, `Admin-NST`, `DeviceId`).
- **Steps**:
  1. Call `POST /api/v1/settings/test` with valid credentials.
  2. Inspect HTTP status code and response payload.
- **Expected Outcome**:
  - HTTP `200 OK`.
  - JSON payload: `{ "Data": { "success": true, ... }, "Message": "Connection successful", "ErrorList": [] }`.

---

### TC-02: Full & Incremental Synchronization with Audit Logging
- **Objective**: Verify that the sync engine fetches customers, products, orders, and order items, performs upserts, and writes execution history into `oc_nop_sync_logs`.
- **Steps**:
  1. Call `POST /api/v1/sync/run` with `{ "syncType": "full" }`.
  2. Query `GET /api/v1/sync/status` to check the audit log record.
- **Expected Outcome**:
  - Sync terminates successfully with status `success`.
  - `oc_nop_orders`, `oc_nop_customers`, `oc_nop_products`, and `oc_nop_order_items` tables populated.
  - Audit log recorded with execution duration, records processed, and 0 errors.

---

### TC-03: Executive Dashboard KPI Aggregation
- **Objective**: Verify calculation of total revenue, profit, orders count, and time-series trends from local database tables.
- **Steps**:
  1. Call `GET /api/v1/analytics/kpi`.
  2. Call `GET /api/v1/analytics/trends?interval=day`.
- **Expected Outcome**:
  - `kpi` returns `totalRevenue`, `profit`, `ordersCount`, `averageOrderValue`, `shippingTotal`, `taxTotal`.
  - Values match MariaDB sums ($70,379.30 revenue, 32 orders).
  - Trends return synchronized arrays for `labels`, `revenue`, and `orders`.

---

### TC-04: Shipment & Fulfillment Overview Widget
- **Objective**: Verify aggregation of orders across shipping statuses (Delivered, Shipped, Not Yet Shipped, Shipping Not Required).
- **Steps**:
  1. Call `GET /api/v1/analytics/shipments`.
- **Expected Outcome**:
  - Returns counts for `notYetShipped`, `partiallyShipped`, `shipped`, `delivered`, `shippingNotRequired`.
  - Returns recent shipments feed with `orderId`, `customerName`, `shippingMethod`, and `createdOnUtc`.

---

### TC-05: Sales Summary Grouping (Day, Week, Month)
- **Objective**: Verify grouping of sales metrics by time periods.
- **Steps**:
  1. Call `GET /api/v1/analytics/summary?groupBy=day`.
  2. Call `GET /api/v1/analytics/summary?groupBy=month`.
- **Expected Outcome**:
  - Summary periods formatted as `YYYY-MM-DD` for day and `YYYY-MM` for month.
  - Returns `numberOfOrders`, `profit`, `shipping`, `tax`, and `orderTotal`.

---

### TC-06: Period Orders Hierarchy & Line Items
- **Objective**: Verify that expanding a date row fetches orders placed during that date, including line items.
- **Steps**:
  1. Call `GET /api/v1/analytics/summary/orders?period=2026-05-04&groupBy=day`.
- **Expected Outcome**:
  - Returns all 10 orders placed on `2026-05-04`.
  - Each order includes `paymentMethod`, `shippingMethod`, `orderDiscount`, `orderTotal`, and an array of `items` with `productName`, `sku`, `quantity`, `unitPrice`, and `totalPrice`.

---

### TC-07: Customer Segmentation & Date Filtering
- **Objective**: Verify customer segmentation into first-time vs returning buyers, and verify date bounds filtering.
- **Steps**:
  1. Call `GET /api/v1/analytics/customers`.
  2. Call `GET /api/v1/analytics/customers?startDate=2026-05-01&endDate=2026-05-31`.
- **Expected Outcome**:
  - All-time returns total active customers with counts.
  - Date-bounded request filters orders and customer spent amounts to May 2026.

---

### TC-08: Customer Order History & Nested Hierarchy
- **Objective**: Verify retrieval of customer orders with payment method, shipping method, discounts, and item details.
- **Steps**:
  1. Call `GET /api/v1/analytics/customers/1/orders`.
- **Expected Outcome**:
  - Returns 27 orders for Customer #1.
  - Each order row contains `paymentMethod`, `shippingMethod`, `orderDiscount`, and `items`.

---

### TC-09: Dual Pagination Logic
- **Objective**: Verify parent customer table pagination and independent child orders pagination.
- **Steps**:
  1. Parent pagination slices customer directory by `customerPage` (10 items/page).
  2. Child pagination slices order history by `childOrderPages[customerId]` (5 orders/page).
- **Expected Outcome**:
  - Independent page controls operate without mutual interference.

---

### TC-10: URL Hash Routing & State Persistence
- **Objective**: Verify that refreshing the page or using browser Back/Forward restores the exact active main tab and sub-tab.
- **Steps**:
  1. Navigate to `#/reports/customers`.
  2. Simulate page reload / inspect `window.location.hash` and `localStorage.getItem('nopstation_analytics_route')`.
- **Expected Outcome**:
  - App state initializes with `currentTab = 'reports'` and `activeSubTab = 'customers'`.

---

### TC-11: Low Stock Inventory Alerts
- **Objective**: Flag catalog products with inventory stock $\le 10$ units.
- **Steps**:
  1. Call `GET /api/v1/analytics/lowstock?threshold=10`.
- **Expected Outcome**:
  - Returns product list with `productId`, `name`, `sku`, `stockQuantity` ($\le 10$), and `price`.

---

### TC-12: Report Exports to Nextcloud Files
- **Objective**: Generate CSV sales summary report into user's Nextcloud Files directory.
- **Steps**:
  1. Call `POST /api/v1/analytics/export?groupBy=day`.
- **Expected Outcome**:
  - Returns `Message` confirming file generation.
  - File created under `/Analytics/sales_summary_*.csv` in Nextcloud storage.

---

### TC-13: Webhooks Security & Processing
- **Objective**: Reject invalid signatures and accept valid HMAC-SHA256 signed webhook payloads.
- **Steps**:
  1. Send `POST /api/v1/webhook` with invalid signature $\rightarrow$ expect 401 Unauthorized.
  2. Send `POST /api/v1/webhook` with valid HMAC-SHA256 signature $\rightarrow$ expect 200 OK.
- **Expected Outcome**:
  - Security validation enforced; valid payloads trigger incremental sync.

---

### TC-14: Nextcloud Platform Compliance & Code Signing
- **Objective**: Validate `appinfo/info.xml` against Nextcloud `info.xsd` schema and verify release signature integrity.
- **Steps**:
  1. Execute `schemaValidate('/tmp/info.xsd')` on `appinfo/info.xml`.
  2. Check 4096-bit RSA keypair, CSR Common Name (`CN=nopstation_analytics`), and release signatures.
- **Expected Outcome**:
  - `VALID SCHEMA`.
  - Subject matches `CN = nopstation_analytics`.
  - Tarball and App ID signatures verify with 684-character base64 output.
