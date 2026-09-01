# nopStation Sales Analytics & Reporting Studio for Nextcloud

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![Nextcloud Version](https://img.shields.io/badge/Nextcloud-31--35-0082c9.svg)](https://nextcloud.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.1--8.3-777bb4.svg)](https://php.net)

A powerful Nextcloud application for synchronizing e-commerce sales, customer segmentation, fulfillment logistics, and inventory data from **nopCommerce** via REST APIs and Webhooks.

---

## 🌟 Key Features

### 📊 1. Executive Sales Dashboard
- **Real-Time KPI Cards**: Total Revenue, Net Profit, Orders Count, Average Order Value (AOV), Total Shipping Fees, and Tax Collected.
- **Interactive Time-Series Charts**: Side-by-side Revenue vs Profit and Order Volume charts with flexible granularity (*Day, Week, Month*).
- **Shipment & Fulfillment Overview**: Logistics widget tracking *Not Yet Shipped*, *Shipped*, *Delivered*, and *Shipping Not Required* orders with an interactive fulfillment efficiency rate (%) progress bar and recent logistics activity feed.

### 📑 2. Sales & Operational Reports
- **Expandable Period Breakdown**: Sales breakdown summary by Day, Week, or Month.
- **Expandable Period Orders Table**: Click any period row (e.g. `2026-05-04`) to view all orders placed during that time frame with *Order #*, *Customer Name & Email*, *Date/Time*, *Payment Method*, *Shipping Method*, *Discount*, *Status Badges*, and *Total*.
- **Level-3 Order Items Sub-Table**: Expand any period order row to inspect its dedicated line items breakdown (*Product Name*, *SKU*, *Quantity*, *Unit Price*, *Subtotal*).
- **Compact Child Pagination**: Slices period orders into clean pages of 5 with compact navigation controls.

### 👥 3. Customer Segmentation & Analytics
- **3-Level Expandable Customer Directory**: Customer $\rightarrow$ Customer Orders $\rightarrow$ Order Line Items.
- **Date Range Filter Toolbar**: Nullable start and end date pickers with quick presets (*All Time*, *Last 30 Days*, *Last 90 Days*, *This Year*, and *Clear Dates*).
- **Customer Directory Filters & Search**: Search directory by Name, Email, or Customer ID; filter by segment (*First-Time Buyers*, *Returning*); sort by *Highest Spend*, *Most Orders*, *Most Recent Order*, or *Customer Name*.
- **Dual Pagination**: Main customer directory pagination (5/10/25/50 per page with page numbers & record summaries) + Compact child orders pagination (5 orders per page).

### ⚠️ 4. Low Stock Inventory Alerts
- Real-time catalog tracking flagging products with stock levels $\le 10$ units to prevent stockouts.

### 📥 5. Automated Report Exports
- One-click CSV report exports saved directly into Nextcloud Files (`/Files/Analytics/`).

### 🧭 6. URL Hash Routing & State Persistence
- Seamless navigation supporting direct links (`#/dashboard`, `#/reports/summary`, `#/reports/customers`, `#/reports/lowstock`, `#/exports`, `#/settings`) with `localStorage` dual fallback to retain active main tab and sub-tab across browser refreshes and Back/Forward history navigation.

---

## ⚙️ Installation & Setup

1. **Install App**:
   Copy the `nopstation_analytics` directory into your Nextcloud server's `custom_apps/` directory:
   ```bash
   cp -r nopstation_analytics /var/www/html/custom_apps/
   chown -R www-data:www-data /var/www/html/custom_apps/nopstation_analytics
   ```

2. **Enable App**:
   ```bash
   php occ app:enable nopstation_analytics
   ```

3. **Configure API Credentials**:
   - Navigate to **Settings & Data Sync** within the app interface.
   - Enter your nopCommerce Store Base URL (e.g., `https://your-nopcommerce-store.com`).
   - Enter Admin Email & Password or JWT API credentials.
   - Click **Save Settings** and test connection using **Test Connection**.

4. **Run Initial Data Sync**:
   - Click **Run Full Sync** to synchronize historical orders, customers, products, and line items into Nextcloud's database tables (`oc_nop_orders`, `oc_nop_customers`, `oc_nop_products`, `oc_nop_order_items`).

---

## 🔌 Webhooks Integration

`nopstation_analytics` supports real-time event updates from nopCommerce. Configure your nopCommerce Webhook plugin to post to:

```
POST https://<your-nextcloud-domain>/index.php/apps/nopstation_analytics/api/v1/webhook
Headers:
  X-Nop-Signature: <hmac-sha256-signature>
```

Supported event types:
- `OrderCreated` / `OrderPaid` / `OrderCancelled`
- `CustomerRegistered` / `CustomerUpdated`
- `ProductCreated` / `ProductStockChanged`

---

## 🌐 API REST Endpoints

All endpoints return JSON responses matching Nextcloud standard API conventions (`{ "Data": ..., "Message": null, "ErrorList": [] }`).

| HTTP Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/analytics/kpi` | Executive sales KPI metrics & growth rates |
| `GET` | `/api/v1/analytics/trends` | Time-series revenue, profit & order volume data |
| `GET` | `/api/v1/analytics/bestsellers` | Top-selling products ranking |
| `GET` | `/api/v1/analytics/customers` | Customer segmentation data with optional date bounds |
| `GET` | `/api/v1/analytics/customers/{customerId}/orders` | Customer order history with line items & date bounds |
| `GET` | `/api/v1/analytics/summary` | Period sales breakdown summary |
| `GET` | `/api/v1/analytics/summary/orders` | Orders placed within a specific date/period |
| `GET` | `/api/v1/analytics/shipments` | Fulfillment overview & logistics feed |
| `GET` | `/api/v1/analytics/lowstock` | Products with inventory threshold $\le 10$ units |
| `POST` | `/api/v1/analytics/export` | Export sales summary to CSV in Nextcloud Files |
| `POST` | `/api/v1/sync/run` | Execute manual data sync (*full* or *incremental*) |

---

## 🛠️ Development & Building

Building the frontend Vue 3 + TypeScript bundle:

```bash
# Install dependencies
npm install

# Run development server
npm run dev

# Build production assets (with inlined CSS resilience)
npm run build
```

---

## 📜 License & Author

- **License**: AGPL-3.0-or-later
- **Author / Publisher**: [nopStation](https://nop-station.com)
- **Support Contact**: `support@nop-station.com`
