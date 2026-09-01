# nopStation Nextcloud Sales Analytics — Agent Guidelines

> **This file is the index.** All detailed instructions are in referenced files below.

## Project Identity
- **App ID**: `nopstation_analytics`
- **Namespace**: `OCA\NopStationAnalytics\`
- **Target**: Nextcloud 31+ (PHP 8.1–8.3), Vue 3 + TypeScript frontend
- **Purpose**: Sync raw e-commerce data from nopCommerce REST APIs → Store in Nextcloud DB → Calculate analytics locally → Display interactive dashboards

## Referenced Guideline Files

| File | Purpose |
|------|---------|
| [rules/coding-standards.md](rules/coding-standards.md) | PHP/Vue coding style, naming, architecture layers |
| [rules/database-patterns.md](rules/database-patterns.md) | Entity/Mapper pattern, migration conventions, table naming |
| [rules/api-conventions.md](rules/api-conventions.md) | Nextcloud controller conventions, JSON response format |
| [rules/nopcommerce-api.md](rules/nopcommerce-api.md) | nopCommerce API authentication, data fetching patterns, field mapping |
| [rules/sync-and-webhooks.md](rules/sync-and-webhooks.md) | Data sync strategy, webhook security, event processing |
| [rules/testing-and-deployment.md](rules/testing-and-deployment.md) | PHPUnit tests, Vite build, SSH deployment to staging |
| [rules/development-log.md](rules/development-log.md) | Mandatory real-time logging requirements |

## Quick Reference
- **Server**: `10.112.165.132` / `https://nextcloud.nop-station.site`
- **nopCommerce API**: See [Resources/credentials.md](../Resources/credentials.md)
- **Postman Collection**: [Resources/NopStation Cart Admin API with Example.postman_collection.json](../Resources/NopStation%20Cart%20Admin%20API%20with%20Example.postman_collection.json)
- **Idea Spec**: [Resources/NextCloud_App_Ideas_NopStation.md](../Resources/NextCloud_App_Ideas_NopStation.md) — IDEA 3 only
- **Webhook Spec**: [WEBHOOKS_AND_EVENTS.md](../WEBHOOKS_AND_EVENTS.md)
- **Dev Log**: [DEVELOPMENT_LOG.md](../DEVELOPMENT_LOG.md)
