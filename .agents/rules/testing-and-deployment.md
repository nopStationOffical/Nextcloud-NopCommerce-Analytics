# Testing & Deployment Procedure

## Backend Testing

### PHP Syntax Check
```bash
find lib/ -name "*.php" | xargs -n1 php -l
```

### PHPUnit Tests
- Location: `tests/Unit/` and `tests/Integration/`
- Config: `tests/phpunit.xml`
- Run: `composer test:unit` or `vendor/bin/phpunit tests -c tests/phpunit.xml`
- Bootstrap: `tests/bootstrap.php`

### Test Structure
```
tests/
├── bootstrap.php
├── phpunit.xml
├── Unit/
│   ├── Service/
│   │   ├── NopApiClientTest.php
│   │   ├── SyncServiceTest.php
│   │   └── AnalyticsCalculatorServiceTest.php
│   └── Db/
│       ├── OrderMapperTest.php
│       └── CustomerMapperTest.php
└── Integration/
    └── Controller/
        ├── AnalyticsControllerTest.php
        └── WebhookControllerTest.php
```

## Frontend Verification

### Build Check
```bash
npm run build      # Production build
npm run dev         # Development build
npm run lint        # ESLint
npm run stylelint   # Style lint
```

### Vite Config Note
- Entry point must match: `src/main.ts` (not `main.js`).
- Update `vite.config.ts` if entry point filename changes.

---

## Deployment Procedure

### Server Details
- **IP**: `10.112.165.132`
- **SSH User**: `noptraining`
- **SSH Password**: See `Resources/credentials.md`
- **Nextcloud URL**: `https://nextcloud.nop-station.site` (Cloudflare tunnel)
- **Direct URL**: `http://10.112.165.132:5674`
- **Admin**: `admin` / `adminpassword`

### Deployment Steps
1. **Build frontend assets locally**:
   ```bash
   npm run build
   ```

2. **Verify PHP syntax**:
   ```bash
   find lib/ -name "*.php" | xargs -n1 php -l
   ```

3. **SSH to server**:
   ```bash
   ssh noptraining@10.112.165.132
   ```

4. **Navigate to apps directory**:
   ```bash
   cd /path/to/nextcloud/apps/
   ```

5. **Sync app source** (rsync or scp):
   ```bash
   rsync -avz --exclude='.git' --exclude='node_modules' --exclude='vendor-bin' \
     ./nopstation_analytics/ noptraining@10.112.165.132:/path/to/nextcloud/apps/nopstation_analytics/
   ```

6. **Run migrations** (on server):
   ```bash
   sudo -u www-data php occ migrations:migrate nopstation_analytics
   ```

7. **Enable app** (on server):
   ```bash
   sudo -u www-data php occ app:enable nopstation_analytics
   ```

8. **Clear cache** (on server):
   ```bash
   sudo -u www-data php occ maintenance:repair
   ```

### Lessons from Previous Deployment (Talk Bridge App)
- **Settings save not working**: Ensure CSRF token is passed in frontend API calls via `@nextcloud/axios` which handles this automatically.
- **Page not found on app route**: Ensure `appinfo/info.xml` has correct `<id>` matching the PHP namespace and route names.
- **Navigation icon not showing**: Ensure `<navigation>` block in `info.xml` references the correct route and icon SVG file.
- **JWT token management**: Store email/password in Nextcloud config, call login API to get JWT, cache token with timestamp, auto-refresh before expiry.
