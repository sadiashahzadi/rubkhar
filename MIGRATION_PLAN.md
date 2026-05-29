# Rubkhar — PHP → Node.js (Vercel) Migration Plan

**Author:** Opus 4.8 (plan) · **Implementer:** Sonnet 4.6
**Decisions (locked):** Express + EJS · Managed MySQL (TiDB Serverless / PlanetScale / Railway) · Vercel Blob for images
**Hard requirements:** UI pixel-identical · zero runtime errors · zero known vulnerabilities · deploys to Vercel

---

## 0. Guiding principles

1. **UI is frozen.** Every byte of HTML/CSS/JS the browser receives must match the PHP output. We port the *server*, not the *front end*. Do not "improve" markup, classes, inline styles, or scripts.
2. **Mechanical translation first, fixes second.** Port each page 1:1, verify it renders identically, *then* apply security/bug fixes in a separate pass with the diff visible.
3. **No runtime DDL.** All `ALTER TABLE`/`CREATE TABLE` in request code is removed; schema lives in one migration file.
4. **Stateless server.** No filesystem sessions, no filesystem uploads — Vercel serverless fs is read-only except `/tmp` (ephemeral).
5. **Every query parameterized.** Keep prepared-statement discipline (current code already uses PDO placeholders — preserve that).

---

## 1. Target architecture

```
rubkhar/
├── api/
│   └── index.js              # Vercel serverless entry → exports Express app
├── src/
│   ├── app.js                # Express app factory (middleware, routes, view engine)
│   ├── db.js                 # mysql2/promise pool (replaces includes/db.php)
│   ├── config.js             # env loading + validation
│   ├── middleware/
│   │   ├── auth.js           # requireUser, requireAdmin (replaces session checks)
│   │   ├── csrf.js           # CSRF token issue + verify
│   │   └── errorHandler.js   # central error handler → no stack traces leaked
│   ├── lib/
│   │   ├── session.js        # cookie/JWT session helpers (replaces $_SESSION)
│   │   ├── blob.js           # Vercel Blob upload helper (replaces move_uploaded_file)
│   │   └── helpers.js        # escape(), money(), slugify() — EJS view helpers
│   ├── routes/
│   │   ├── public.js         # /, /shop, /product, /cart, /checkout, /order-confirmation, /login, /my-account
│   │   ├── api.js            # /ajax/products (replaces ajax_products.php), /subscribe
│   │   └── admin.js          # /admin/* (login, dashboard, products, categories, orders, customers, coupons)
│   └── views/
│       ├── partials/
│       │   ├── header.ejs    # from includes/header.php
│       │   └── footer.ejs    # from includes/footer.php
│       ├── public/           # index.ejs, shop.ejs, product.ejs, cart.ejs, checkout.ejs,
│       │                     #   order-confirmation.ejs, login.ejs, my-account.ejs
│       └── admin/
│           ├── partials/     # header.ejs, footer.ejs, auth handled by middleware
│           └── *.ejs         # index, login, products, categories, orders, customers, coupons
├── public/                   # static assets served by Vercel CDN
│   └── assets/css/style.css  # unchanged
├── db/
│   ├── schema.sql            # consolidated DDL (all tables + runtime-ALTER columns folded in)
│   └── seed.sql              # categories + products sample data (from rubkhar_database.sql)
├── scripts/
│   └── migrate.js            # runs schema.sql + seed.sql against managed MySQL
├── .env.example
├── vercel.json
├── package.json
└── MIGRATION_PLAN.md
```

### Request flow
`vercel.json` routes **all** non-static requests to `api/index.js`, which exports the Express `app`. Express handles routing, EJS rendering, DB, sessions. Static files (`/assets/**`) served directly by Vercel CDN, never touching the function.

### Dependencies
- `express` — server
- `ejs` — templating (PHP-echo → EJS mechanical map)
- `mysql2` — promise pool, MySQL dialect preserved
- `cookie-parser` — read cookies
- `jsonwebtoken` — signed stateless session token
- `bcryptjs` — verify existing `password_hash` bcrypt hashes (compatible) + hash new ones
- `@vercel/blob` — image uploads
- `multer` (memoryStorage) — parse multipart, buffer → Blob (never touches disk)
- `helmet` — security headers
- `express-rate-limit` — throttle login/register/ajax
- `dotenv` — local env
- (dev) `nodemon`

---

## 2. PHP → Node concept mapping

| PHP construct | Node/Express equivalent |
|---|---|
| `include 'includes/header.php'` | `<%- include('partials/header') %>` |
| `<?= htmlspecialchars($x) ?>` | `<%= x %>` (EJS auto-escapes) |
| `<?= $rawHtml ?>` (intentional HTML) | `<%- rawHtml %>` |
| `<?php ... ?>` logic block | `<% ... %>` |
| `$pdo->prepare()->execute([$a])` | `await pool.query('… ?', [a])` |
| `$_GET['x']` / `$_POST['x']` | `req.query.x` / `req.body.x` |
| `$_SESSION['user_id']` | `req.session.user_id` (from signed JWT cookie) |
| `session_start()` | session middleware (global) |
| `header("Location: x"); exit;` | `return res.redirect('x')` |
| `password_hash($p, PASSWORD_BCRYPT)` | `await bcrypt.hash(p, 10)` |
| `password_verify($p, $h)` | `await bcrypt.compare(p, h)` |
| `move_uploaded_file()` | `await put(name, buffer, {access:'public'})` (Blob) |
| `number_format($n)` | `helpers.money(n)` (must match PHP output exactly) |
| `json_encode([...]); exit;` | `res.json({...})` |

**`number_format` parity note:** PHP `number_format(4500)` → `"4,500"`. Implement `money()` as `Number(n).toLocaleString('en-US')` and verify against sample products (no decimals shown in current UI). Mismatch here = visible UI diff.

---

## 3. Database migration

### 3.1 Consolidate schema
Build `db/schema.sql` from `rubkhar_database.sql` **plus** every column added at runtime:
- `users.phone VARCHAR(50) NULL` (from `login.php`)
- `products.sizes VARCHAR(255) NULL`, `products.colors VARCHAR(255) NULL`, `products.featured TINYINT(1) DEFAULT 0` (from `admin/products.php`)
- `products.sale_price DECIMAL(10,2) NULL` (referenced in `admin/products.php` INSERT/UPDATE but **never in original DDL** — confirm and add)

Keep MySQL dialect (ENUM, AUTO_INCREMENT, FK CASCADE) — managed MySQL accepts it as-is.

### 3.2 Provider
TiDB Serverless (free, MySQL 8 wire-compatible) recommended. Connection string → `DATABASE_URL` env. `mysql2` pool with TLS (`ssl: { rejectUnauthorized: true }` for TiDB).

### 3.3 Migrate script
`scripts/migrate.js`: connect, run `schema.sql`, then `seed.sql` (categories + 15 products). Idempotent (`CREATE TABLE IF NOT EXISTS`, `INSERT … ON DUPLICATE KEY`/`INSERT IGNORE`). Run once locally against the cloud DB before first deploy.

### 3.4 Pool, not connection-per-request
Single module-level `mysql2.createPool` reused across warm invocations. Small pool size (`connectionLimit: 2-5`) — serverless fans out; managed MySQL has connection caps.

---

## 4. Sessions & auth (stateless)

Replace `$_SESSION` with a **signed JWT in an httpOnly cookie** (`Secure`, `SameSite=Lax`).

- `lib/session.js`: `setSession(res, payload)` signs JWT (`JWT_SECRET`, e.g. 7-day exp) → cookie `rk_session`. `getSession(req)` verifies → `req.session` (or `{}`).
- Customer payload: `{ user_id, user_name, user_role }`. Admin payload: `{ admin_logged_in:true, admin_id }` in a separate cookie `rk_admin` (mirrors the separate PHP admin session).
- `middleware/auth.js`:
  - `requireAdmin` → no valid `rk_admin` → redirect `/admin/login`.
  - `attachUser` (global) → populate `res.locals.user` so header partial shows login state identically.
- **Transient flash data** currently in session (`redirect_to`, `last_order_id`): carry `last_order_id` via signed short-lived cookie or query param to `order-confirmation`. `redirect_to` → derive from `req.get('referer')` same as PHP, store in a short-lived cookie.

---

## 5. File uploads → Vercel Blob

Admin product/category image upload:
- `multer({ storage: memoryStorage(), limits:{ fileSize: 5*1024*1024 } })`.
- **Validate** (fixes current upload-RCE-class vuln): allow only `image/jpeg|png|webp`; check magic bytes, not just mimetype; reject otherwise.
- `await put(\`products/${Date.now()}_${safeName}\`, file.buffer, { access:'public' })` → store returned `url` in `products.image_url`.
- Existing seed images: commit any present static images under `public/assets/images/...`; for missing ones the UI already falls back to a Font Awesome `<i class="fas fa-image">` placeholder (see `ajax_products.php`) — preserve that.

---

## 6. Page-by-page port checklist

Order = simplest → most complex. Each: port markup to EJS, wire route, verify identical render, commit.

| # | Source PHP | Route | Notes / gotchas |
|---|---|---|---|
| 1 | `includes/header.php` | partial | Has inline `<style>` + nav with dead links — **keep dead links as-is** (UI frozen); optionally create stub routes returning a styled "coming soon" only if user later asks. |
| 2 | `includes/footer.php` | partial | Likely closing tags + newsletter JS. Port verbatim. |
| 3 | `index.php` | `GET /` | Home + featured products query + newsletter form (`POST /subscribe`). |
| 4 | `shop.php` | `GET /shop` | Renders shell; products loaded via AJAX. The inline `fetch('ajax_products.php', …)` JS → change URL to `/ajax/products`. **Only allowed JS edit** (endpoint path). |
| 5 | `ajax_products.php` | `POST /ajax/products` | Returns `{html, pagination, count}` JSON. **Reimplement count query properly** — drop the fragile `preg_replace` regex; run a parallel `SELECT COUNT(*)` with same WHERE/params. HTML string must match byte-for-byte (same classes, same `number_format`). |
| 6 | `product.php` | `GET /product` (by `?slug=` or `?id=`) | Reviews list + add-review form. Confirm param style from source. |
| 7 | `order-confirmation.php` | `GET /order-confirmation` | Reads `last_order_id` (now cookie/param). |
| 8 | `cart.php` | `GET /cart` | **Currently mocked** (2 random products). Port mock 1:1 to keep UI identical; do NOT build real cart unless user requests (out of scope, would change behavior). Note in PR. |
| 9 | `checkout.php` | `GET/POST /checkout` | Mocked cart + order insert in a **transaction** (`pool.getConnection` → `beginTransaction`/`commit`/`rollback`). Guest `user_id=1` `INSERT IGNORE` → move to migrate/seed, not request code. |
| 10 | `login.php` | `GET/POST /login` | Login + register tabs. bcrypt compatible via `bcryptjs`. Remove runtime `ALTER TABLE phone`. Set session cookie. Inline tab/password-toggle JS unchanged. |
| 11 | `my-account.php` | `GET/POST /my-account` | Profile + orders + address forms. Gate with `requireUser`. Multiple POST forms — branch on a hidden action field as PHP does. |

### Admin (all behind `requireAdmin`)
| # | Source | Route | Notes |
|---|---|---|---|
| 12 | `admin/login.php` | `GET/POST /admin/login` | Has runtime check/insert of default admin — move seeding to migrate script. Set `rk_admin` cookie. |
| 13 | `admin/logout.php` | `GET /admin/logout` | Clear `rk_admin` cookie → redirect. |
| 14 | `admin/includes/header.php`+`footer.php`+`auth.php` | admin partials + middleware | `auth.php` session gate → `requireAdmin` middleware. |
| 15 | `admin/index.php` | `GET /admin` | Dashboard stat queries. |
| 16 | `admin/products.php` | `/admin/products` | CRUD + Blob upload. **Move delete to `POST` + CSRF** (currently GET — CSRF vuln). Remove runtime `ALTER TABLE`. |
| 17 | `admin/categories.php` | `/admin/categories` | CRUD (+ optional image upload). Same delete-via-GET fix. |
| 18 | `admin/orders.php` | `/admin/orders` | List + status update form. |
| 19 | `admin/customers.php` | `/admin/customers` | List + detail. |
| 20 | `admin/coupons.php` | `/admin/coupons` | CRUD. Same delete-via-GET fix. |

---

## 7. Security hardening pass (the "no vulnerability" requirement)

Apply **after** functional parity verified, each as a reviewable change:

1. **CSRF** — issue per-session token (`csrf.js`), embed hidden `<input name="_csrf">` in every state-changing form, verify on POST. *UI impact: one hidden input per form — invisible.*
2. **Destructive GET → POST** — admin delete actions become POST forms (the `onclick=confirm()` link becomes a tiny inline form/button styled identically). *Verify visual parity.*
3. **Upload validation** — mime + magic-byte + size limit + extension allowlist (§5).
4. **Headers** — `helmet` with a CSP that allows current external origins: Google Fonts (`fonts.googleapis.com`, `fonts.gstatic.com`), Font Awesome CDN (`cdnjs.cloudflare.com`), inline `<style>`/`<script>` (the pages use inline JS/CSS → CSP needs `'unsafe-inline'` for style/script, or nonce them; start with `'unsafe-inline'` to guarantee UI works, document as known relaxation).
5. **Rate limiting** — login/register/ajax endpoints.
6. **Error handling** — central handler returns generic message; never echo `$e->getMessage()` to client (current checkout leaks it). Log server-side.
7. **Cookies** — `httpOnly`, `Secure`, `SameSite=Lax`; strong `JWT_SECRET`.
8. **Input validation** — keep server-side checks from PHP (required fields, password length, email uniqueness); cast numeric IDs (`Number()`), reject NaN.
9. **No secrets in repo** — `db.php` hardcoded creds gone; everything via env. `.env` gitignored.
10. **SQL** — confirm 100% parameterized; the dynamic `IN (...)` in ajax already uses placeholder generation — preserve that pattern.

---

## 8. Vercel config

**`vercel.json`**
```json
{
  "version": 2,
  "builds": [{ "src": "api/index.js", "use": "@vercel/node" }],
  "routes": [
    { "src": "/assets/(.*)", "dest": "/public/assets/$1" },
    { "src": "/(.*)", "dest": "/api/index.js" }
  ]
}
```

**`api/index.js`**
```js
const app = require('../src/app');
module.exports = app;            // Vercel @vercel/node wraps Express app
```

**Env vars** (Vercel dashboard + `.env.example`):
`DATABASE_URL`, `JWT_SECRET`, `BLOB_READ_WRITE_TOKEN`, `NODE_ENV`.

**Local dev:** `vercel dev` (mirrors prod routing) or `nodemon` against a tiny `server.js` that `app.listen(3000)`.

---

## 9. Verification strategy

For each page, **diff rendered HTML** PHP-vs-Node:
1. Stand up old PHP (XAMPP/local) and new Node side by side against the *same* seeded DB.
2. For each route, curl both, normalize volatile bits (CSRF token, timestamps, blob URLs), `diff`. Goal: empty diff except intended security additions.
3. Manual smoke in browser: home, shop filter (AJAX), product, login/register, checkout flow, my-account, every admin CRUD + image upload.
4. Confirm static assets (`style.css`, fonts, FA icons) load (check CSP not blocking).
5. `npm audit` clean; no secrets committed; deployed preview on Vercel works end-to-end.

**Definition of done:** all routes render identically, full flows work on a Vercel preview deploy, `npm audit` shows no high/critical, no `$e->getMessage()`-style leaks, all forms CSRF-protected, uploads validated.

---

## 10. Implementation order (for Sonnet)

1. **Scaffold** — `package.json`, deps, `vercel.json`, `api/index.js`, `src/app.js` (EJS engine, static, cookie-parser, helmet, session middleware), `src/db.js`, `src/config.js`, `.env.example`, `.gitignore`.
2. **DB** — `db/schema.sql` (consolidated), `db/seed.sql`, `scripts/migrate.js`. Provision TiDB, run migrate, confirm tables+data.
3. **Helpers + partials** — `helpers.js` (`money`, `escape`), `views/partials/header.ejs`+`footer.ejs`, verify a blank page renders header/footer identical to PHP.
4. **Public pages** in checklist order (3→11), verifying each.
5. **AJAX endpoint** (5) — byte-identical JSON HTML.
6. **Admin** (12→20) incl. Blob upload + `requireAdmin`.
7. **Security pass** (§7) — CSRF, GET→POST deletes, upload validation, rate limit, error handler, CSP.
8. **Verify** (§9) + deploy preview.
9. Commit in logical chunks; open PR with parity notes (mocked cart, dead nav links, CSP `unsafe-inline` relaxation documented).

---

## 11. Explicit out-of-scope (do NOT change without new instruction)

- Real cart/wishlist logic (currently mocked — porting the mock keeps UI identical; building real cart changes behavior).
- Missing pages behind dead nav links (`category.php`, `sale.php`, `new-arrivals.php`, `wishlist.php`) — they 404 today; leave as-is.
- Any visual/markup/CSS/JS change beyond the single `ajax_products.php` → `/ajax/products` endpoint URL and invisible CSRF hidden inputs.
- Switching DB dialect, adding ORMs, or restructuring tables beyond consolidating runtime ALTERs.

---

## 12. Risk register

| Risk | Mitigation |
|---|---|
| EJS auto-escape differs from PHP `htmlspecialchars` on edge chars | Spot-check product names with quotes/apostrophes (e.g. "Women's Clothing"); use `<%-%>` only for known-safe HTML. |
| `number_format` vs `toLocaleString` mismatch | Unit-test `money()` against sample prices; verify rendered prices. |
| Serverless cold start + DB connection storms | Small pool, reuse across warm invocations, TiDB connection limits respected. |
| CSP breaks inline styles/scripts | Start with `'unsafe-inline'`, confirm UI, document; nonce-harden later if requested. |
| bcrypt hash compatibility | `bcryptjs` reads PHP `$2y$`/`$2b$` hashes — verify with a known seeded hash. |
| Blob URL vs old relative `image_url` paths in DB | Seed uses bare filenames (e.g. `maroon-silk-dress.jpg`); template must build correct `/assets/images/products/<file>` path exactly as PHP did. |
