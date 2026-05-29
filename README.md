# Rubkhar — Node.js (Express + EJS) on Vercel

Migrated from PHP. Server-rendered EJS, MySQL (mysql2), Vercel Blob for image uploads. UI unchanged.

## Stack
- **Express + EJS** — server-rendered pages (1:1 port of the PHP views)
- **mysql2** — managed MySQL (TiDB Serverless / PlanetScale / Railway)
- **@vercel/blob** — product/category image uploads
- **JWT cookies** — stateless sessions (`rk_session` customer, `rk_admin` admin)
- **helmet / express-rate-limit / CSRF** — security

## Local development

1. Install deps:
   ```bash
   npm install
   ```
2. Create `.env` from `.env.example`:
   ```
   DATABASE_URL=mysql://user:pass@host:port/dbname
   JWT_SECRET=<long-random-string>
   BLOB_READ_WRITE_TOKEN=<vercel-blob-token>
   NODE_ENV=development
   ```
3. Provision a managed MySQL DB and run the migration (creates tables + seeds categories, products, guest user, admin):
   ```bash
   npm run migrate
   ```
4. Start dev server:
   ```bash
   npm run dev        # nodemon server.js → http://localhost:3000
   ```

**Default admin:** `admin@rubkhar.com` / `admin123` (change after first login).

## Deploy to Vercel

1. Push repo to GitHub, import into Vercel.
2. Set env vars in Vercel dashboard: `DATABASE_URL`, `JWT_SECRET`, `BLOB_READ_WRITE_TOKEN`, `NODE_ENV=production`.
3. Enable **Vercel Blob** in the project (provides `BLOB_READ_WRITE_TOKEN`).
4. Run `npm run migrate` once locally (or via a one-off job) against the production `DATABASE_URL`.
5. Deploy. `vercel.json` routes static `/assets/**` to the CDN and everything else to the Express function in `api/index.js`.

## Project layout
```
api/index.js          Vercel serverless entry → exports Express app
server.js             local dev (app.listen)
src/app.js            Express factory (helmet/CSP, CSRF, sessions, routes)
src/db.js             mysql2 pool (lazy)
src/config.js         env validation
src/routes/           public.js · api.js · admin.js
src/views/            EJS (public/ + admin/ + partials/)
src/lib/              session.js · helpers.js
src/middleware/       auth.js · csrf.js · errorHandler.js
db/schema.sql         consolidated DDL + seed users
db/seed.sql           categories + products
scripts/migrate.js    runs schema + seed
public/assets/        static CSS (served by CDN)
```

## Notes (behavior preserved from original PHP)
- **Cart & checkout use a mocked cart** (2 sample products) — same as the original PHP. No real cart persistence was implemented (out of scope).
- Some nav/footer links point to pages that never existed in the original (`category.php`, `sale.php`, `wishlist.php`, etc.) — left as-is to keep the UI identical.
- CSP currently allows `'unsafe-inline'` for styles/scripts because the original pages use inline `<style>`/`<script>`. Can be nonce-hardened later.

## Security
- All POST/PUT/DELETE forms carry a CSRF token (`rk_csrf` cookie + `_csrf` field), verified constant-time.
- Admin destructive actions are POST (were GET in PHP).
- Image uploads validated by mimetype **and** magic bytes, 5 MB limit, stored in Vercel Blob.
- Passwords bcrypt (compatible with existing PHP `$2y$` hashes).
- Rate limiting on login + ajax endpoints.
- DB errors never leaked to clients.
- `npm audit`: **0 vulnerabilities**.
