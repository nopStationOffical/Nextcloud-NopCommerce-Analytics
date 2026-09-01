# Coding Standards & Architecture

## Backend (PHP / Nextcloud App Framework)

### PHP Version & Compatibility
- PHP 8.1–8.3 (Nextcloud 31+).
- Always use `declare(strict_types=1);` at the top of every PHP file.

### Namespace
- Root namespace: `OCA\NopStationAnalytics\`
- PSR-4 autoloading maps `OCA\NopStationAnalytics\` → `lib/`

### Architecture Layers
```
lib/
├── AppInfo/Application.php        # App bootstrap, DI registration
├── Controller/                    # HTTP controllers (thin, delegate to services)
│   ├── PageController.php         # Renders main Vue template
│   ├── SettingsController.php     # Admin settings API
│   ├── AnalyticsController.php    # Dashboard data API
│   └── WebhookController.php      # External webhook receiver
├── Service/                       # Business logic (fat services)
│   ├── NopApiClient.php           # HTTP client for nopCommerce REST API
│   ├── SyncService.php            # Full/incremental data sync orchestrator
│   ├── AnalyticsCalculatorService.php  # KPI, time-series, segmentation calculations
│   ├── ExportService.php          # CSV/PDF report generation → Nextcloud Files
│   └── WebhookService.php         # Webhook signature verification & event processing
├── Db/                            # Entities & Mappers (QBMapper pattern)
│   ├── OrderEntity.php / OrderMapper.php
│   ├── OrderItemEntity.php / OrderItemMapper.php
│   ├── CustomerEntity.php / CustomerMapper.php
│   ├── ProductEntity.php / ProductMapper.php
│   └── SyncLogEntity.php / SyncLogMapper.php
├── BackgroundJob/                 # Nextcloud cron jobs
│   └── ScheduledSyncJob.php
└── Migration/                     # Database migrations
    └── Version001000Date*.php
```

### Controller Rules
- Controllers MUST be thin: validate parameters → call service → return response.
- Use PHP 8 attributes for routing: `#[ApiRoute]`, `#[FrontpageRoute]`.
- Use `#[NoCSRFRequired]` and `#[NoAdminRequired]` attributes appropriately.
- Webhook controller uses `#[PublicPage]` + `#[NoCSRFRequired]` (no Nextcloud login required).

### Service Rules
- All business logic goes in `lib/Service/` classes.
- Services are injected via Nextcloud DI container (constructor injection).
- Services MUST NOT access `$_GET`, `$_POST`, `$_SERVER` directly.

---

## Frontend (Vue 3 / TypeScript)

### Framework & Build
- Vue 3 Composition API with `<script setup lang="ts">`.
- Build tool: Vite with `@nextcloud/vite-config`.
- Entry point: `src/main.ts` → mounts into `#nopstation_analytics` div.

### UI Components
- Use `@nextcloud/vue` components (NcAppContent, NcAppNavigation, NcButton, NcTextField, etc.).
- Use Nextcloud CSS variables for theming (`var(--color-main-background)`, `var(--color-primary-element)`).

### Charting
- Use `chart.js` + `vue-chartjs` for dashboard visualizations (line charts, bar charts, doughnut).

### File Structure
```
src/
├── main.ts                        # Vue app mount
├── App.vue                        # Root component with tab navigation
├── components/
│   ├── DashboardView.vue          # KPI cards + trend charts
│   ├── ReportsView.vue            # Interactive data tables + filters
│   ├── ExportsView.vue            # Scheduled export configuration
│   └── SettingsView.vue           # nopCommerce API config + sync controls
└── services/
    └── api.ts                     # Axios/fetch wrapper for Nextcloud API calls
```
