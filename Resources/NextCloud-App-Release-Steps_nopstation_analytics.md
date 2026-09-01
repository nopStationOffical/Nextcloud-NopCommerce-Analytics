# Releasing `nopstation_analytics` to the Nextcloud App Store

Internal runbook for publishing the **nopStation Nextcloud Sales Analytics Studio** app. Covers the initial release (`1.0.0`) and all subsequent release updates.

| Property | Value |
| --- | --- |
| **App ID** | `nopstation_analytics` |
| **Namespace** | `OCA\NopStationAnalytics\` |
| **Public Repo** | <https://github.com/nopStation/nopstation_analytics> |
| **Store Listing** | <https://apps.nextcloud.com/apps/nopstation_analytics> *(after first upload)* |
| **Licence** | AGPL-3.0-or-later |
| **Compatibility** | Nextcloud 31–35 (PHP 8.1–8.3) |
| **Maintainer Contact** | `support@nop-station.com` |

---

## 1. How Code Signing & App Store Publishing Works

The Nextcloud App Store **never receives raw uncompiled code**. It receives:
1. A **URL to a downloadable release tarball** (`nopstation_analytics.tar.gz` attached to a GitHub release).
2. A **SHA-512 base64 signature** proving the tarball came from your keypair.

### Three Core Concepts
1. **Your identity is an RSA private key (`nopstation_analytics.key`).** You generate a 4096-bit RSA keypair. Nextcloud's core CA signs your Certificate Signing Request (`.csr`) and issues a `.crt` certificate stating: *"This key belongs to app `nopstation_analytics`"*.
2. **Signing happens on the app folder *before* packaging.** `occ integrity:sign-app` writes `appinfo/signature.json` directly into the app folder. Only after `signature.json` is generated do you package the `.tar.gz`.
3. **The tarball is hosted remotely, not uploaded directly.** Nextcloud App Store fetches the `.tar.gz` asset from your GitHub release URL.

```
   keypair  ──►  CSR  ──►  Nextcloud signs  ──►  certificate
      │                                              │
      │                                              ▼
      └────────►  sign app dir  ──►  tar.gz  ──►  upload URL + signature
```

> [!CAUTION]
> **Golden Rule**: Signing is the absolute LAST step that modifies app files. Any file modification after running `occ integrity:sign-app` (even a 1-character typo fix in a comment) invalidates the signature and requires repeating Phase 4.

---

## 2. Prerequisites

| Component | Required Environment & Tools |
| --- | --- |
| Workstation | PowerShell / Git Bash, `tar`, `openssl`, Docker Desktop |
| Nextcloud Server | Running Nextcloud instance with `occ` CLI access (Docker container `nextcloud_server-app-1`) |
| GitHub Account | Admin access to `nopStation` organization repository with a public email address on profile |

---

## 3. Release Checklist

- [ ] **Phase 1** — Verify source layout, `info.xml`, and compile Vite assets
- [ ] **Phase 2** — Generate RSA 4096 keypair & submit CSR ⏳ *(days to weeks for initial release)*
- [ ] **Phase 3** — Pre-release schema & PHPUnit validation
- [ ] **Phase 4** — Build clean release tarball & sign via `occ`
- [ ] **Phase 5** — Publish GitHub release & submit to Nextcloud App Store
- [ ] **Phase 6** — Verify installation on clean Nextcloud instance

---

## Phase 1 — Verify Source Layout & Assets

1. App files must sit directly at the repository root:
   ```
   ✅ Repo Root: appinfo/  lib/  templates/  js/  css/  README.md  appinfo/info.xml
   ❌ Nested:    nopstation_analytics/appinfo/...
   ```

2. Compile production frontend bundle:
   ```bash
   cd /c/001.Data/BrainStation/NextCloud/nopstation_analytics
   npm run build
   ```
   *Ensures `js/nopstation_analytics-main.mjs` and `css/nopstation_analytics-main.css` are up to date.*

---

## Phase 2 — Generate Keypair & Request Signing Certificate ⏳

### 2.1 Generate the RSA Keypair and CSR

Create directory `~/.nextcloud/certificates` and generate a 4096-bit RSA keypair.

> [!WARNING]
> **Git Bash / MSYS User Note**: The `MSYS_NO_PATHCONV=1` prefix is **mandatory** for `openssl req` on Windows. Without it, Git Bash rewrites `-subj "/CN=nopstation_analytics"` into `C:/Program Files/Git/CN=...`, causing certificate rejection.

```bash
mkdir -p ~/.nextcloud/certificates && cd ~/.nextcloud/certificates

MSYS_NO_PATHCONV=1 openssl req -nodes -newkey rsa:4096 \
  -keyout nopstation_analytics.key -out nopstation_analytics.csr \
  -subj "/CN=nopstation_analytics"
```

### 2.2 Verify Common Name (`/CN=nopstation_analytics`)

```bash
openssl req -in nopstation_analytics.csr -noout -subject
# MUST OUTPUT EXACTLY: subject=CN=nopstation_analytics
```

### 2.3 Submit CSR to Nextcloud

Open a Pull Request at <https://github.com/nextcloud/app-certificate-requests> adding `nopstation_analytics.csr`. Link your public repository URL.

> [!CAUTION]
> **NEVER** commit, push, or share `nopstation_analytics.key`. It is your private signing key.

### 2.4 Securely Store Private Key

Save `nopstation_analytics.key` immediately into your organization's secure password manager / secrets vault. If lost, you can never publish updates under `nopstation_analytics` again.

---

## Phase 3 — Pre-Release Schema Validation

### 3.1 Validate `info.xml` Against Nextcloud XML Schema

Run XML schema validation using Nextcloud's official `info.xsd`:

```bash
curl -sL https://apps.nextcloud.com/schema/apps/info.xsd -o /tmp/info.xsd
php -r '$d=new DOMDocument(); $d->load("appinfo/info.xml");
        echo $d->schemaValidate("/tmp/info.xsd") ? "VALID\n" : "INVALID\n";'
```

---

## Phase 4 — Build and Code Sign

Once Nextcloud approves your `.csr` and returns `nopstation_analytics.crt`, place it in `~/.nextcloud/certificates/nopstation_analytics.crt`.

### Option A — Automated Staging & Signing Script (Recommended)

Run the Python build script:

```bash
python scratch/build_and_sign_release.py
```

### Option B — Manual Step-by-Step Signing Commands

```bash
cd /c/001.Data/BrainStation/NextCloud/nopstation_analytics
CERT=~/.nextcloud/certificates
NC=nextcloud_server-app-1

# 1. Clean build directory
rm -rf build && mkdir -p build/sign/nopstation_analytics

# 2. Copy production release files (excluding dev files)
tar --exclude='.git' --exclude='.github' --exclude='.gitignore' \
    --exclude='build' --exclude='Makefile' --exclude='tests' \
    --exclude='phpunit.xml' --exclude='node_modules' \
    --exclude='src' --exclude='Resources' \
    -cf - -C . . | tar -xf - -C build/sign/nopstation_analytics

# 3. Copy to container & sign with occ
docker cp build/sign/nopstation_analytics $NC:/tmp/
docker cp $CERT/nopstation_analytics.key  $NC:/tmp/
docker cp $CERT/nopstation_analytics.crt  $NC:/tmp/
docker exec $NC php /var/www/html/occ integrity:sign-app \
  --privateKey=/tmp/nopstation_analytics.key \
  --certificate=/tmp/nopstation_analytics.crt \
  --path=/tmp/nopstation_analytics

# 4. Copy generated signature back to build folder
docker cp $NC:/tmp/nopstation_analytics/appinfo/signature.json \
          build/sign/nopstation_analytics/appinfo/

# 5. Compress release archive & calculate SHA-512 base64 signature
tar -czf build/nopstation_analytics.tar.gz -C build/sign nopstation_analytics
openssl dgst -sha512 -sign $CERT/nopstation_analytics.key \
  build/nopstation_analytics.tar.gz | openssl base64 -A > build/signature.txt

# 6. Cleanup temporary container keys
docker exec $NC rm -rf /tmp/nopstation_analytics*
```

### 4.1 Copy Upload Signature
The upload signature in `build/signature.txt` is 684 characters on a single line. Copy it using:

```bash
clip < build/signature.txt
```

### 4.2 Verify Tarball Structure
```bash
tar -tzf build/nopstation_analytics.tar.gz | grep signature.json
#   → MUST output: nopstation_analytics/appinfo/signature.json
```

---

## Phase 5 — Register & Publish to App Store

### 5.1 First Release Only — Claim App ID

Go to <https://apps.nextcloud.com/developer/apps/new>:
1. **Certificate**: Paste full contents of `nopstation_analytics.crt`.
2. **App ID Signature**: Generate signature over the app ID string:
   ```bash
   echo -n "nopstation_analytics" | \
     openssl dgst -sha512 -sign ~/.nextcloud/certificates/nopstation_analytics.key | \
     openssl base64 > appid-signature.txt
   clip < appid-signature.txt
   ```
   *Paste contents into App ID Signature field.*

### 5.2 Create GitHub Release

1. Tag commit as `v1.0.0` (or `v1.0.3`).
2. Attach `build/nopstation_analytics.tar.gz` as a release asset.
3. Copy direct download URL e.g.:
   `https://github.com/nopStation/nopstation_analytics/releases/download/v1.0.0/nopstation_analytics.tar.gz`

### 5.3 Submit Release to App Store

Go to <https://apps.nextcloud.com/developer/apps/releases/new>:
- **Download URL**: Paste GitHub asset download URL from Step 5.2.
- **Signature**: Paste 684-character string from `build/signature.txt` (`clip < build/signature.txt`).

---

## Phase 6 — Verification on Production Instances

1. Verify app listing at <https://apps.nextcloud.com/apps/nopstation_analytics>.
2. Install app on a target Nextcloud server via **Apps $\rightarrow$ Analytics / Integration $\rightarrow$ nopCommerce Sales Analytics**.
3. Perform REST settings test and execute test sync run.

---

## Troubleshooting Matrix

| Symptom | Cause | Solution |
| --- | --- | --- |
| `This name is not in that format: 'C:/Program Files/Git/CN=...'` | Git Bash path conversion altered `-subj` | Use `MSYS_NO_PATHCONV=1` prefix (Phase 2.1) |
| `Could not open file or uri for loading private key` | `MSYS_NO_PATHCONV=1` used on command taking real file paths | Omit prefix when referencing real file paths |
| Store rejects tarball structure | Incorrect root folder name | Archive root folder must be `nopstation_analytics` |
| Server flags app as "INVALID Signature / Tampered" | Code modified after signing | Repeat Phase 4 completely in exact sequence |
