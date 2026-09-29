# 🚀 nopCommerce Analytics by nopStation — v1.0.1 Release Notes

> **Tag**: `v1.0.1`  
> **Previous Release**: `v1.0.0`  
> **License**: AGPL-3.0-or-later  
> **Nextcloud Compatibility**: 28 – 35 (PHP 8.1 – 8.3)  
> **Publisher**: [nopStation](https://www.nop-station.com) (Brain Station 23 PLC)

---

## 🌟 What's New in v1.0.1

This release focuses on **App Store presentation enhancements**, **visual banner assets**, **comprehensive documentation**, and **CI/CD workflow stability**.

---

### 🎨 Visual Assets & App Store Experience
- **High-Resolution Banner & Screenshot**:
  - Added official UI showcase banner (`screenshots/nopCommerceAnalytics_by_nopStation_screenshot.png`) highlighting the executive sales dashboard, KPI widgets, and fulfillment tracker.
  - Linked official screenshot carousel in `appinfo/info.xml` for rich visual presentation on the [Nextcloud App Store](https://apps.nextcloud.com/apps/nopstation_analytics).
- **Multi-Category Classification**:
  - Expanded app store categories to include **`dashboard`** and **`integration`** alongside **`office`**, significantly improving discoverability in the Nextcloud ecosystem.

---

### 📑 Comprehensive App Store & Documentation Overhaul
- **Enriched App Store Description**:
  - Replaced the initial single-sentence summary with a detailed, structured overview of core features:
    - **Executive KPI Dashboard**: Gross Revenue, Net Profit, Order Volume, AOV, Tax & Shipping fee metrics with multi-granularity time-series charts (Day, Week, Month).
    - **Fulfillment & Logistics Intelligence**: Order dispatch tracker with interactive efficiency rates and live activity stream.
    - **3-Level Operational Drilldown Reports**: Sales summaries with nested expandable hierarchies (Period $\rightarrow$ Orders with payment/shipping badges $\rightarrow$ Line items with SKU and unit prices).
    - **Customer Segmentation**: Directory with date range filters (*All Time*, *Last 30 Days*, *Last 90 Days*, *This Year*), buyer segment tagging (*First-Time* vs *Returning*), and dual independent pagination.
    - **Inventory Alerts**: Proactive catalog monitoring highlighting stock levels $\le 10$ units to prevent stockouts.
    - **Automated CSV Exports**: Direct export to Nextcloud Files (`/Files/Analytics/`).
    - **Data Sovereignty & Privacy**: 100% on-premise local calculations from synced `oc_nop_*` database tables with zero third-party SaaS tracking.
- **Enterprise Partner & ERP 23 Profile**:
  - Integrated publisher credentials as the **#1 nopCommerce Gold Solution Partner** by Brain Station 23 PLC, including enterprise ERP integration ([ERP 23](https://www.linkedin.com/showcase/erp-23bs)) references and official support channels.
- **Official Documentation Links**:
  - Added direct links in `info.xml` for User Documentation, Admin Setup Guides, Developer Webhook Specifications, Discussions, and Issue Tracker.

---

### 🛠️ Developer & CI/CD Enhancements
- **GitHub Actions Runner Fix**:
  - Updated runner labels from Nextcloud internal `ubuntu-latest-low` to standard `ubuntu-latest` across all 9 workflow files, preventing CI jobs from hanging queued indefinitely.
- **OpenAPI Specification Workflow Fix**:
  - Resolved `generate-spec` binary execution paths and ensured ephemeral composer modifications are discarded prior to git cleanliness checks.
  - Added explicit HTTP 200 return annotations to `ApiController.php` for strict OpenAPI compliance.
  - All 6 CI workflows verified green (`info.xml lint`, `openapi`, `php-cs`, `stylelint`, `block unconventional commits`, `block fixup`).

---

### 🔒 Package Integrity & Code Signing
- **Tarball**: `build/nopstation_analytics.tar.gz` (1,162,587 bytes)
- **Signature Algorithm**: RSA SHA-512 (4096-bit key verified against Nextcloud CA certificate)
- **Validation**: Schema-validated 100% compliant with official Nextcloud [`info.xsd`](https://apps.nextcloud.com/schema/apps/info.xsd).

---

## 📦 Assets to Attach to GitHub Release

1. **`nopstation_analytics.tar.gz`** (located at `build/nopstation_analytics.tar.gz`)
2. **`signature.txt`** (located at `build/signature.txt`)

---

## 🔗 Useful Links
- **Nextcloud App Store**: [apps.nextcloud.com/apps/nopstation_analytics](https://apps.nextcloud.com/apps/nopstation_analytics)
- **Source Repository**: [GitHub - Nextcloud-NopCommerce-Analytics](https://github.com/nopStationOffical/Nextcloud-NopCommerce-Analytics)
- **nopStation Website**: [https://www.nop-station.com](https://www.nop-station.com)
- **nopCommerce Partner Profile**: [nopcommerce.com/en/nopstation](https://www.nopcommerce.com/en/nopstation)
- **ERP 23**: [linkedin.com/showcase/erp-23bs](https://www.linkedin.com/showcase/erp-23bs)
