# API WAF / Password Protection Bypass (iOS & Mobile)

## Problem

The iOS app (and any mobile client) hits the API at `https://mgastaging.medguarda.com/api/*` but receives:

- **HTML** instead of JSON (e.g. "You are being redirected… Javascript is required…")
- **HTTP 307** redirects
- Response **host** changing to a random domain

This indicates a **WAF / bot-protection / password-protection** layer is intercepting requests **before** they reach Laravel. Mobile apps cannot run JavaScript challenges, so they cannot authenticate.

---

## 1) Root cause – what is intercepting `/api/*`

The intercept happens **in front of** your Laravel app. Possible layers (in order of likelihood on Cloudways):

| Layer | What it does | How it shows up |
|-------|------------------|------------------|
| **Cloudways Application Password (Staging)** | htpasswd / HTTP Basic Auth on the **entire application** (often enabled by default on staging). | 401 + HTML login page or redirect to a “password required” page. Browser may get a JS challenge page if the host uses one for “suspicious” clients. |
| **Sucuri / CloudProxy / Cloudways add-on WAF** | Bot protection and “browser integrity check” (JavaScript challenge). | Exactly what you see: HTML “You are being redirected”, “Javascript is required”, 307, host change to a challenge domain. |
| **Cloudflare** (if you use it in front of Cloudways) | Similar: “Under Attack” mode or Bot Fight Mode can show JS challenges. | Same: HTML challenge, 307, different host. |
| **Nginx/Apache** (server-level redirects) | e.g. http→https, www→non-www. | Usually 301/302 to same host; less likely to change host or show “JS required”. |

**Most likely cause:** **Sucuri/CloudProxy-style WAF** or **Cloudways staging password protection** (or both). The “random domain” and “Javascript is required” text are strong signs of a **WAF JS challenge**, not only Basic Auth.

---

## 2) Fix – bypass protection for `/api/*` (server-side)

Goal: **All requests to `/api/*` must reach Laravel and return JSON**. No JS challenge, no HTML, no redirect to another domain.

### A) Cloudways dashboard (do first)

1. **Application Password / Staging URL Protection**
   - **Cloudways** → Your Server → **Application** → **Staging / Application** settings.
   - Find **“Application Password”** or **“Staging URL Password Protection”** or **“htpasswd”**.
   - **Option 1:** Disable it for the staging app if you can secure the app another way (e.g. IP allowlist, or use API subdomain below).
   - **Option 2:** If there is a **“Whitelist paths”** or **“Bypass for paths”** option, add: `/api` or `/api/*` so that **no password is required** for `/api/*`.

2. **Sucuri / CloudProxy / Security add-on (if present)**
   - In Cloudways or the security add-on panel, look for:
     - **“Whitelist URL”** / **“Allowlist”** / **“Bypass”**: add `/api` or `/api/*`.
     - **“JavaScript challenge”** / **“Browser integrity check”**: **disable for** `/api/*` or **enable “API / JSON” bypass** for paths starting with `/api`.
   - If the WAF is managed by a **third-party (e.g. Sucuri dashboard)**, add the same rule there: **exempt `/api/*`** from JS challenge and from blocking.

3. **Cloudflare (if you use it)**
   - **Rules** → **Page Rules** or **Configuration Rules** / **WAF**:
     - Create a rule: **URL** `*mgastaging.medguarda.com/api*` (or your staging domain + `/api*`).
     - **Setting:** Security Level = Essentially Off, or disable “JavaScript challenge” / “Browser integrity check” for that rule.
   - **WAF** → **Custom rules**: add an allow/skip rule for URI path containing `/api`.

After this, **retest with curl** (see section 4). If you still get HTML/307, the protection is applied at another layer (e.g. Nginx/Apache or another proxy).

### B) Nginx (if you have access to server config)

If your app is behind Nginx and you can edit the server/vhost config, you can **avoid passing `/api` through a WAF** or **exempt Basic Auth** for `/api` by handling `/api` in a dedicated location **before** any auth or WAF include.

**File (typical on Cloudways):** `/home/master/applications/<appname>/conf/nginx/app.conf` or similar. **Back up before editing.**

```nginx
server {
    # ... existing server_name, root, etc. ...

    # Optional: skip auth for /api (if auth is set in this server block)
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
        # If you use FastCGI for PHP:
        location ~ \.php$ {
            fastcgi_pass unix:/path/to/php-fpm.sock;  # use your actual socket
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        }
    }

    # Rest of your Laravel location block (e.g. location / { ... })
}
```

If Cloudways injects WAF/proxy via an **include**, you may need to **exclude `/api`** from that include (e.g. a separate `location /api` that does not include the WAF config). Exact paths depend on your host; Cloudways support can confirm the correct file and include structure.

### C) Apache (if you use Apache and have access)

**File (typical):** application’s `.htaccess` in the **document root** or the **vhost** config. If **password protection is applied in the vhost** (e.g. `AuthType Basic`), add a **directive that exempts `/api`** so those requests are not subject to auth.

Example idea (exact syntax depends on where auth is set – vhost vs .htaccess):

```apache
# In vhost or in .htaccess BEFORE Laravel rewrite rules:
SetEnvIf Request_URI "^/api" noauth=1
# Then in the same block where you have Require valid-user, use:
Require env noauth
# or Satisfy any + Allow from env=noauth (Apache 2.2 style)
```

If you don’t manage the vhost (Cloudways does), then **A) Cloudways dashboard** is the place to disable or whitelist `/api` for Application Password and WAF.

---

## 3) Safe alternative: dedicated API subdomain (no WAF)

If the main staging domain **cannot** exempt `/api/*` from the JS challenge (e.g. no path-based whitelist), use a **separate host** that is **not** behind the WAF/challenge:

1. **Create subdomain:** e.g. `api.mgastaging.medguarda.com` (or `mgastaging-api.medguarda.com`).
2. **Point it to the same app** (same document root / same Laravel app) on Cloudways.
3. **Do not** enable Application Password or WAF/JS challenge for this subdomain (or disable them for this host).
4. **SSL:** Use Cloudways’ SSL for the subdomain (e.g. Let’s Encrypt).
5. **iOS app:** Change base URL to `https://api.mgastaging.medguarda.com` (paths stay the same: `/api/login`, `/api/user`, `/api/files/*`, etc.).

Laravel does not need code changes: it already serves `/api/*` by path. Only the **host** and **server/DNS/WAF configuration** change.

---

## 4) Verify with curl (must see JSON, never HTML)

Run these **from your machine or from the server (SSH)**. Replace `TEST_EMAIL`, `TEST_PASS`, and `TOKEN` with real values.

**Base URL:** `https://mgastaging.medguarda.com` (or `https://api.mgastaging.medguarda.com` if you use the subdomain).

### 4.1) POST /api/login

```bash
curl -i -X POST https://mgastaging.medguarda.com/api/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"TEST_EMAIL","password":"TEST_PASS","device_name":"ios"}'
```

- **Expected (success):** `HTTP/1.1 200` and body like `{"token":"...","user":{...}}`.
- **Expected (invalid creds):** `HTTP/1.1 422` and body `{"message":"Invalid credentials"}`.
- **Failure:** Any `HTTP/1.1 307`, or body containing “You are being redirected”, “Javascript is required”, or HTML. Then the bypass for `/api/*` is not in effect yet.

### 4.2) GET /api/user (with token)

```bash
curl -i https://mgastaging.medguarda.com/api/user \
  -H "Accept: application/json" \
  -H "Authorization: Bearer TOKEN"
```

- **Expected:** `HTTP/1.1 200` and JSON user object.
- **Failure:** 307, or HTML body.

### 4.3) POST /api/logout (with token)

```bash
curl -i -X POST https://mgastaging.medguarda.com/api/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer TOKEN"
```

- **Expected:** `HTTP/1.1 200` and `{"message":"Logged out"}`.

### 4.4) GET /api/files (or any protected route)

```bash
curl -i https://mgastaging.medguarda.com/api/leads \
  -H "Accept: application/json" \
  -H "Authorization: Bearer TOKEN"
```

- **Expected:** `HTTP/1.1 200` and JSON (e.g. list or 401 if token invalid). No HTML, no 307.

---

## 5) Laravel changes made (this repo)

- **`bootstrap/app.php`**  
  Exception handler was updated so that **any** request under `api/*` (or with `Accept: application/json`) gets a **JSON** response for errors (404, 500, etc.). This does **not** fix the WAF intercept (which happens before Laravel), but it ensures that **once the request reaches the app**, you never return an HTML error page for the API.

---

## 6) Summary checklist

- [ ] **Cloudways:** Disable or path-whitelist Application Password / Staging URL Protection for `/api/*`.
- [ ] **Cloudways / Add-on:** Disable JS challenge / browser integrity check for `/api/*` (Sucuri/CloudProxy or similar).
- [ ] **Cloudflare (if used):** Add rule to skip challenge for `*yourdomain*/api*`.
- [ ] **Nginx/Apache (if you have access):** Exempt `/api` from auth/WAF in server or vhost config.
- [ ] **Optional:** Use subdomain `api.mgastaging.medguarda.com` without WAF/password and point iOS to it.
- [ ] **Verify:** All curl commands above return **JSON** and **no 307** for `/api/login`, `/api/user`, `/api/logout`, and `/api/*` protected routes.

Once the above is done, **/api/* is no longer challenged or redirected** and the iOS app can authenticate and call the API normally.
