---
name: nextcloud-app-scaffold
description: >-
  Skill for scaffolding a new Nextcloud app from the template codebase.
  Covers renaming app IDs, namespaces, routes, templates, and build config.
  Use when setting up the initial app identity from the app_template scaffold.
---

# Nextcloud App Scaffolding Skill

## When to Use
Activate this skill when transforming the `app_template` scaffold into the `nopstation_analytics` app.

## Checklist

### 1. `appinfo/info.xml`
- `<id>`: `nopstation_analytics`
- `<name>`: `nopCommerce Sales Analytics`
- `<summary>`: Sales analytics dashboard powered by nopCommerce data
- `<namespace>`: `NopStationAnalytics`
- `<category>`: `analytics`
- `<author>`: NopStation
- Navigation: Route to `nopstation_analytics.page.index`, icon `app.svg`

### 2. PHP Namespace
- Change all `OCA\AppTemplate\` → `OCA\NopStationAnalytics\`
- Files: `lib/AppInfo/Application.php`, `lib/Controller/*.php`, all new files
- Update `Application::APP_ID` → `'nopstation_analytics'`

### 3. `composer.json`
- `"name"`: `"nopstation/nopstation_analytics"`
- PSR-4 autoload: `"OCA\\NopStationAnalytics\\"` → `"lib/"`

### 4. `package.json`
- `"name"`: `"nopstation_analytics"`

### 5. Frontend Mount Point
- `templates/index.php`: Change div id from `app_template` to `nopstation_analytics`
- `src/main.ts`: Change mount target to `#nopstation_analytics`
- `src/App.vue`: Change `app-name` to `nopstation_analytics`

### 6. `vite.config.ts`
- Entry: `resolve(join('src', 'main.ts'))` (note: `.ts` not `.js`)

### 7. Verify
- `php -l lib/AppInfo/Application.php` — no syntax errors
- `npm run build` — builds without errors
