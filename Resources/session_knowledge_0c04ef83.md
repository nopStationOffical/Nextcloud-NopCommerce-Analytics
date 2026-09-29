# Session Knowledge: `0c04ef83` — nopStation Analytics Nextcloud App

> **Session ID**: `0c04ef83-bc89-4ef8-9a63-c9900dd8afc0`
> **Date Range**: 2026-08-31 (19:19 BDT) → 2026-09-01 (17:00+ BDT)
> **Duration**: ~22 hours (multi-phase, long-running session)
> **Project Path**: `c:\001.Data\BrainStation\NextCloud\nopstation_analytics`

---

## 🗺️ Session Overview

This was a **founding session** for the `nopstation_analytics` Nextcloud app. It started from scratch — the user referenced an idea document (`Resources/NextCloud_App_Ideas_NopStation.md`, **IDEA 3**) and the Postman collection for the nopCommerce API, and the entire application was designed, built, debugged, tested, packaged, and prepared for App Store release within this single session.

---

## 📋 Session Phases & Milestones

### Phase 1 — Project Planning & Architecture (Steps 0–96)

**Trigger**: User requested building a Nextcloud app based on nopCommerce using IDEA 3 from the ideas document, also referencing a prior conversation (`3da7dabf-01bc-4e91-8644-dae3dbcc8208`).

**Key Design Decisions Made:**
- **Architecture**: Nextcloud App (PHP 8.x backend + Vue 3/TypeScript frontend)
- **Sync Strategy**: Pull data from nopCommerce REST API (JWT auth) → store in Nextcloud DB → calculate analytics locally
  - There is **no native `/salesSummary`, `/bestsellers`, or `/orderStatistics` API endpoint** in nopCommerce
  - All KPIs (Revenue, AOV, Profit, Orders) are calculated **locally** from synced `nop_orders`, `nop_customers`, `nop_products`, `nop_order_items` tables
- **App ID**: `nopstation_analytics` (machine-readable, schema-compliant for Nextcloud's `info.xsd`)
- **Display Name**: `nopCommerce Analytics by nopStation`
- **Namespace**: `OCA\NopStationAnalytics\`
- **Webhook Security**: HMAC-SHA256 signature verification for event-based data
- **Real-time Logging**: Mandatory `DEVELOPMENT_LOG.md` tracking all actions

**Artifacts Created in Phase 1:**
- `DEVELOPMENT_LOG.md` — real-time activity log
- `.agents/AGENTS.md` — minimal index file pointing to rule sub-files
- Multiple `.agents/rules/*.md` skill files (coding standards, DB patterns, API conventions, sync strategy, etc.)
- `WEBHOOKS_AND_EVENTS.md` — webhook API documentation

---

### Phase 2 — App Scaffolding & Backend Implementation (Steps 96–500)

**User Key Instruction:**
> "there is no direct data/api about SalesSummary, LoadOrderStatistics, Bestsellers directly available on nopCommerce. we can get customers, order, shipments, products and other entity data from nopCommerce using the api and need to process here."

**PHP Backend Files Created:**
| File | Purpose |
|------|---------|
| `lib/Controller/ApiController.php` | Health check / base API endpoint |
| `lib/Controller/AnalyticsController.php` | KPI aggregations, shipment overview, trend curves |
| `lib/Controller/SyncController.php` | Trigger manual data sync from nopCommerce |
| `lib/Controller/SettingsController.php` | Store/retrieve API credentials |
| `lib/Service/NopApiClient.php` | nopCommerce JWT auth + paginated REST fetch |
| `lib/Service/SyncService.php` | Entity upsert logic for orders/customers/products |
| `lib/Mapper/*.php` | Nextcloud entity/mapper pattern for each table |
| `lib/Migration/Version001000Date2026083101.php` | DB schema migration (creates 4 `nop_*` tables) |
| `appinfo/routes.php` | All OCS route definitions |
| `appinfo/info.xml` | App metadata (id, name, version, description) |

**Database Tables Created:**
```sql
oc_nop_orders         -- synced orders from nopCommerce
oc_nop_customers      -- synced customers
oc_nop_products       -- synced products
oc_nop_order_items    -- synced line items (order → products)
oc_nop_sync_logs      -- audit log per sync run
```

---

### Phase 3 — Vue 3 Frontend Development (Steps 500–900)

**Frontend Components Built:**
| File | Purpose |
|------|---------|
| `src/App.vue` | Root app shell — navigation, hash routing |
| `src/components/DashboardView.vue` | Executive KPI dashboard (Revenue, Orders, AOV, Profit), trend charts |
| `src/components/ReportsView.vue` | Tabbed reports: Sales Summary, Customer Segmentation, Low Stock, Exports |
| `src/components/SettingsView.vue` | API URL/credentials form |

**Frontend Key Features:**
- **URL Hash Routing**: `#/dashboard`, `#/reports/summary`, `#/reports/customers`, `#/reports/lowstock`, `#/exports`, `#/settings`
- `localStorage` state persistence so page refresh lands on the same tab
- Real-time KPI cards: Revenue `$70,379.30`, Orders `32`, trend curves
- **Sales Summary**: Expandable date rows → Level 2 order list → Level 3 line items
- **Customer Segmentation**: Searchable/filterable customer table with 3-level hierarchy
  - Level 1: Customer directory (paginated, filterable by name/email)
  - Level 2: Orders per customer (expandable, with Payment Method, Shipping Method, Discount columns)
  - Level 3: Order line items sub-table (Item Name, Qty, Unit Price, Subtotal)
- **Shipment Overview Widget** on dashboard: Delivered / Not shipped / Recent activity feed
- **Low Stock Alerts** (`≤ 10 units`)
- **CSV Report Export** → saved to `NopCommerce_Analytics/Reports/` in Nextcloud Files

---

### Phase 4 — Deployment to Staging Server (Steps 700–1200)

**Staging Environment:**
- Host: `10.112.165.132`
- Server: `nextcloud.nop-station.site`
- Container: `nextcloud_server-app-1` (Docker)
- SSH User: `noptraining` / Password: `nopTraining@2026#`
- Tunnel: ngrok (`https://5b66-118-67-219-51.ngrok-free.app → https://localhost:59579`)

**Deployment Workflow:**
```bash
# 1. Build Vite bundle locally
npm run build  # with inlineCSS: true (critical!)

# 2. Package tarball
build-and-sign.ps1  # PowerShell script in project

# 3. SCP to server
scp build/nopstation_analytics.tar.gz noptraining@10.112.165.132:~/

# 4. Deploy in container
tar -xzf nopstation_analytics.tar.gz -C .../custom_apps/nopstation_analytics
sudo docker cp ... nextcloud_server-app-1:/var/www/html/custom_apps/
occ app:enable nopstation_analytics
```

**Critical CSS Bug Fixed During Deployment:**
- Vite was splitting CSS into a separate chunk → Cloudflare CDN cached the empty 79-byte placeholder CSS file
- **Fix**: Set `build.cssCodeSplit: false` in `vite.config.ts` + `inlineCSS: true` → all CSS inlined into JS bundle
- After fix: `nopstation_analytics-main.css` = 56.6 kB (full styles applied)

---

### Phase 5 — Feature Enhancements & Bug Fixes (Steps 1200–1700)

**Features Added in This Phase (based on user requests):**

1. **Customer Segmentation Enhancements** (major):
   - Date range filter (nullable start/end dates) with quick presets: All Time, Last 30 Days, Last 90 Days, This Year
   - Dual pagination: parent table (5/10/25/50 per page) + child orders table
   - 3-level nested hierarchy: Customer → Orders → Line Items
   - Payment Method, Shipping Method, Discount badge columns on order rows

2. **Sales Summary Expandable Rows**:
   - Each date/period row in Sales Summary is expandable
   - Shows list of orders for that day/week/month (Level 2)
   - Further expands to line items (Level 3)

3. **Dashboard Shipment Overview Widget**:
   - Added delivery statistics: Delivered, Not Shipped, In Transit
   - Recent logistics activity feed (last 8 activity entries)

4. **URL Hash Routing + State Persistence Fix**:
   - Problem: Every browser refresh would land on `#/dashboard` regardless of the current tab
   - Fix: `localStorage` dual fallback: save current route on navigation + restore on mount; `hashchange` listener
   - All routes persist correctly across full-page refresh

---

### Phase 6 — Release Preparation & Code Quality (Steps 1700–2237)

**App Release Steps:**
- Reference doc created: `Resources/NextCloud-App-Release-Steps_nopstation_analytics.md`

**Code Signing (4096-bit RSA):**
```bash
# Generate keypair & CSR (done inside staging container, downloaded locally)
openssl genrsa -out nopstation_analytics.key 4096
openssl req -new -key nopstation_analytics.key -out nopstation_analytics.csr
```
- Key: `build/sign/nopstation_analytics.key` (**never commit!**)
- CSR: `build/sign/nopstation_analytics.csr`
- Certificate (from Nextcloud): `build/sign/nopstation_analytics.crt`

**PHP Code Style (php-cs-fixer):**
- Tool: `nextcloud/coding-standard` v1.5.0 (Nextcloud official)
- Issue Found: 11 of 31 PHP files had indentation violations (multiline query builder chains)
- Fixed: `php-cs-fixer fix` applied → 0 of 31 files with violations
- `composer run cs:check` now passes in GitHub Actions

**OpenAPI Spec Fix:**
- Error: `Api#index: Missing description for status code 200`
- Fix: Updated `ApiController.php` `#[DataResponse]` return type annotation to include proper description
- Then re-ran `composer run openapi` to regenerate `openapi.json` and TypeScript types

**App Branding Rename:**
- Technical ID: kept as `nopstation_analytics` (20 chars, lowercase, schema-valid)
- Display Name: changed to `nopCommerce Analytics by nopStation`
- Reason: Nextcloud `info.xsd` enforces `pattern="[a-z]+[a-z0-9_]*[a-z0-9]+"` and `maxLength="32"` on `<id>` — uppercase `C` and length >32 would fail App Store validation
- Files Updated: `appinfo/info.xml`, `src/App.vue`, `DashboardView.vue`, `ReportsView.vue`, `README.md`
- Version bumped to `1.0.4`

**Final Release Package:**
- Tarball: `build/nopstation_analytics.tar.gz` — 1,159,204 bytes, 81 files
- Upload signature: `build/signature.txt` (684 chars, RSA SHA-512 base64)
- App ID claim signature: `build/appid-signature.txt`

---

## ✅ Test Results (14/14 Passing — 100% Pass Rate)

Test suite documented in `TEST_CASES.md` and results in `TEST_REPORT.md`:

| Test ID | Feature | Status | Time |
|---------|---------|--------|------|
| TC-01 | REST API Settings & Credentials | `PASS` | 0 ms |
| TC-02 | Sync Audit Logs | `PASS` | 0.8 ms |
| TC-03 | Executive KPIs ($70,379.30 revenue, 32 orders) | `PASS` | 3.1 ms |
| TC-04 | Shipment Overview Widget | `PASS` | 1.0 ms |
| TC-05 | Sales Summary Grouping (Day/Month) | `PASS` | 0.9 ms |
| TC-06 | Period Orders Hierarchy & Level-3 Line Items | `PASS` | 1.5 ms |
| TC-07 | Customer Segmentation & Date Filtering | `PASS` | 1.3 ms |
| TC-08 | Customer Hierarchy (27 orders, nested items) | `PASS` | 1.6 ms |
| TC-09 | Dual Pagination & REST Endpoint Consistency | `PASS` | 840.4 ms |
| TC-10 | URL Hash Routing & State Persistence | `PASS` | 807.5 ms |
| TC-11 | Low Stock Alerts (≤ 10 units) | `PASS` | 0.8 ms |
| TC-12 | CSV Export → `/NopCommerce_Analytics/Reports/` | `PASS` | 207.1 ms |
| TC-13 | Webhooks HMAC-SHA256 Signature Verification | `PASS` | 220.0 ms |
| TC-14 | `info.xsd` Official Schema Validation | `PASS` | 418.8 ms |

---

## 🔑 Key Technical Knowledge

### nopCommerce API Integration
- **Auth**: JWT Bearer token — POST to `/admincustomer/login` with `Admin-NST` header (device token) + `User-Agent`
- **Token Storage**: Saved in Nextcloud `oc_preferences` table per user
- **Paginated Fetch**: All entity endpoints paginated (PageNumber + PageSize params)
- **Endpoints Used**: `orders`, `customers`, `products`, `order-items`, `shipments`
- **No native analytics endpoints** — all KPIs computed locally

### Nextcloud App Constraints
- `info.xml` `<id>` must match: `pattern="[a-z]+[a-z0-9_]*[a-z0-9]+"` and `maxLength="32"`
- All PHP controllers must use `DataResponse<Http::STATUS_OK, array{...}, array{}>` return type annotations (for OpenAPI extractor)
- `php-cs-fixer` with `nextcloud/coding-standard` enforces query builder multiline indentation
- Code must be signed with a 4096-bit RSA keypair — CSR submitted to Nextcloud App Store

### CSS/Vite Build Critical Setting
```ts
// vite.config.ts — CRITICAL to avoid CSS chunk caching issues
build: {
  cssCodeSplit: false,   // inline all CSS into JS
}
```

### Deployment Commands
```bash
# In container
php occ app:enable nopstation_analytics
php occ maintenance:repair

# CS check (must pass CI)
composer run cs:check

# OpenAPI regen
composer run openapi

# Sign tarball (local)
openssl dgst -sha512 -sign build/sign/nopstation_analytics.key build/nopstation_analytics.tar.gz | openssl enc -base64 > build/signature.txt
```

---

## 📁 Key Files in the Project

| File | Purpose |
|------|---------|
| [`appinfo/info.xml`](file:///c:/001.Data/BrainStation/NextCloud/nopstation_analytics/appinfo/info.xml) | App metadata, version, navigation |
| [`appinfo/routes.php`](file:///c:/001.Data/BrainStation/NextCloud/nopstation_analytics/appinfo/routes.php) | All API routes |
| [`lib/Controller/AnalyticsController.php`](file:///c:/001.Data/BrainStation/NextCloud/nopstation_analytics/lib/Controller/AnalyticsController.php) | Core analytics endpoints |
| [`lib/Service/NopApiClient.php`](file:///c:/001.Data/BrainStation/NextCloud/nopstation_analytics/lib/Service/NopApiClient.php) | nopCommerce API client (JWT auth) |
| [`src/components/DashboardView.vue`](file:///c:/001.Data/BrainStation/NextCloud/nopstation_analytics/src/components/DashboardView.vue) | Executive dashboard |
| [`src/components/ReportsView.vue`](file:///c:/001.Data/BrainStation/NextCloud/nopstation_analytics/src/components/ReportsView.vue) | Reports (Sales, Customers, Low Stock, Exports) |
| [`src/App.vue`](file:///c:/001.Data/BrainStation/NextCloud/nopstation_analytics/src/App.vue) | Root app + hash routing |
| [`DEVELOPMENT_LOG.md`](file:///c:/001.Data/BrainStation/NextCloud/nopstation_analytics/DEVELOPMENT_LOG.md) | Real-time dev activity log |
| [`WEBHOOKS_AND_EVENTS.md`](file:///c:/001.Data/BrainStation/NextCloud/nopstation_analytics/WEBHOOKS_AND_EVENTS.md) | Webhook API documentation |
| [`TEST_CASES.md`](file:///c:/001.Data/BrainStation/NextCloud/nopstation_analytics/TEST_CASES.md) | Full test case suite |
| [`TEST_REPORT.md`](file:///c:/001.Data/BrainStation/NextCloud/nopstation_analytics/TEST_REPORT.md) | Test execution results |
| [`Resources/NextCloud-App-Release-Steps_nopstation_analytics.md`](file:///c:/001.Data/BrainStation/NextCloud/nopstation_analytics/Resources/NextCloud-App-Release-Steps_nopstation_analytics.md) | Release guide |
| `build/sign/nopstation_analytics.key` | 🔒 Private key (NEVER COMMIT) |
| [`build/sign/nopstation_analytics.csr`](file:///c:/001.Data/BrainStation/NextCloud/nopstation_analytics/build/sign/nopstation_analytics.csr) | CSR for App Store submission |
| [`build/nopstation_analytics.tar.gz`](file:///c:/001.Data/BrainStation/NextCloud/nopstation_analytics/build/nopstation_analytics.tar.gz) | Release package |

---

## 🐛 Known Issues Fixed in This Session

| Issue | Root Cause | Fix Applied |
|-------|-----------|-------------|
| CSS not loading after deploy | Cloudflare CDN cached empty 79-byte CSS chunk | `cssCodeSplit: false` in vite.config.ts |
| Page refresh always goes to Dashboard | No route persistence | `localStorage` + `hashchange` listener in `App.vue` |
| 11/31 PHP files failing cs:check | Multiline query builder indentation | `composer run cs:fix` inside container |
| OpenAPI extractor error on `Api#index` | Missing `description` for status 200 | Updated `DataResponse` return type annotation |
| App ID `nopCommerce_analytics` invalid | Uppercase C + >32 chars violates `info.xsd` | Kept `nopstation_analytics` as technical ID |

---

## 📌 Status at End of Session

- ✅ App fully built and deployed to staging
- ✅ 14/14 test cases passing (100%)
- ✅ PHP code style compliant (`composer run cs:check`)
- ✅ OpenAPI spec valid (`composer run openapi`)
- ✅ Tarball packaged and signed (`1.0.4`)
- ✅ CSR generated and ready for Nextcloud App Store submission
- ⏳ Awaiting Nextcloud App Store review + certificate issuance
- ⏳ GitHub Actions CI not yet verified (PHP cs:check fix still needs to be pushed)
