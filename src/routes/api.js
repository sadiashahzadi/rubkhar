'use strict';

const router = require('express').Router();
const pool = require('../db');
const { escape: htmlEscape, money } = require('../lib/helpers');

// ── Async wrapper ─────────────────────────────────────────────────────────────
const wrap = fn => (req, res, next) => Promise.resolve(fn(req, res, next)).catch(next);

// ── POST /subscribe ───────────────────────────────────────────────────────────
// Mounted at / in app.js (app.use('/', publicRoutes) but subscribe is an API action)
// app.js mounts apiRoutes at /ajax, so this route is reached via POST /subscribe
// handled in public router instead — but per task spec, it lives in api.js.
// Re-export through a named export so public router can use it, OR
// mount subscribe directly here and app.js adds a second use('/') for api routes.
// Per task spec: "Newsletter form posts to /subscribe → add POST /subscribe in api.js"
// app.js mounts apiRoutes at /ajax. To satisfy /subscribe we export the handler
// and also register it at router level so app.js can mount api router at '/' too.
// SOLUTION: Export the subscribe handler so public.js can call it, AND register
// POST /subscribe here. app.js mounts api at /ajax AND also at / for subscribe.
// Simplest: just register POST /subscribe here; caller must mount api at '/' as well.
// We'll update app.js mounting — BUT task says "do not touch app.js" implicitly.
// ACTUAL SOLUTION: app.js already mounts publicRoutes at '/' and apiRoutes at '/ajax'.
// So POST /subscribe must live in public.js OR we add a second mount in app.js.
// Per literal task spec: "add POST /subscribe in api.js" — we add it here, and
// the route will be reachable at POST /ajax/subscribe. The form action in index.ejs
// posts to /subscribe, so we need app.js to also mount api at '/'. We'll add
// a minimal stub in public.js that delegates, keeping the handler here.
// FINAL DECISION: put the full handler here as POST /subscribe (path relative to mount).
// app.js needs one extra line: app.use('/', apiRoutes) — but task says don't commit.
// We cannot modify app.js per constraint "do not touch app.js". Therefore we expose
// the handler and also register it in this file as a named route, AND we will
// register it in public.js forwarding to this logic by requiring the helper.
// ── Cleanest resolution ──────────────────────────────────────────────────────
// Export the subscribe handler so public.js can mount POST /subscribe.
// Keep /products and any future ajax endpoints in this file under /ajax mount.

// ── POST /products (mounted at /ajax in app.js → full path POST /ajax/products) ─
router.post('/products', wrap(async (req, res) => {
  // Get Inputs
  const categories = Array.isArray(req.body['categories[]'])
    ? req.body['categories[]']
    : req.body['categories[]']
      ? [req.body['categories[]']]
      : [];

  const max_price = Number(req.body.max_price) || 50000;
  const sizesRaw  = req.body.sizes  && req.body.sizes  !== '' ? req.body.sizes.split(',')  : [];
  const colorsRaw = req.body.colors && req.body.colors !== '' ? req.body.colors.split(',') : [];
  const sort = req.body.sort || 'newest';
  let page = Number(req.body.page) || 1;
  if (page < 1) page = 1;

  const per_page = 12;
  const offset = (page - 1) * per_page;

  // Build WHERE clause + params (positional ? for mysql2)
  let where = 'p.price <= ?';
  const params = [max_price];

  if (categories.length > 0) {
    const ids = categories.map(id => Number(id)).filter(id => !isNaN(id) && id > 0);
    if (ids.length > 0) {
      const placeholders = ids.map(() => '?').join(',');
      where += ` AND p.category_id IN (${placeholders})`;
      params.push(...ids);
    }
  }

  // Build ORDER BY clause
  let orderBy;
  if (sort === 'price_asc')  orderBy = 'p.price ASC';
  else if (sort === 'price_desc') orderBy = 'p.price DESC';
  else if (sort === 'name_asc')   orderBy = 'p.name ASC';
  else                            orderBy = 'p.created_at DESC'; // newest

  // Count query — separate SELECT COUNT(*) with same WHERE + params (no preg_replace fragility)
  const countSql = `SELECT COUNT(*) as total FROM products p JOIN categories c ON p.category_id = c.id WHERE ${where}`;
  const [[countRow]] = await pool.query(countSql, params);
  const totalItems = countRow.total;
  const totalPages = Math.ceil(totalItems / per_page);

  // Products query
  const productsSql = `SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE ${where} ORDER BY ${orderBy} LIMIT ? OFFSET ?`;
  const [products] = await pool.query(productsSql, [...params, per_page, offset]);

  // Generate HTML for Products Grid — byte-identical to PHP
  let html = '';
  if (products.length > 0) {
    for (const product of products) {
      const price = money(product.price);
      // Mock a sale badge for cheaper items just to show the UI
      const saleBadge = product.price <= 3500
        ? '<div class="sale-badge">SALE</div>'
        : '';

      html += `
        <div class="product-card">
            <div class="product-img">
                ${saleBadge}
                <div class="wishlist-btn"><i class="fas fa-heart"></i></div>
                <i class="fas fa-image" style="font-size: 4rem; color: #ddd;"></i>
            </div>
            <div class="product-info">
                <div class="product-name" title="${htmlEscape(product.name)}">${htmlEscape(product.name)}</div>
                <div class="product-price">Rs. ${price}</div>
                <button class="add-cart-btn"><i class="fas fa-shopping-cart"></i> Add to Cart</button>
            </div>
        </div>`;
    }
  } else {
    html = '<div style="grid-column: 1/-1; text-align: center; padding: 60px; font-size: 1.1rem; color: #777;">No products found matching your criteria. Try adjusting your filters.</div>';
  }

  // Generate HTML for Pagination
  let pagination = '';
  if (totalPages > 1) {
    for (let i = 1; i <= totalPages; i++) {
      const activeClass = i === page ? 'active' : '';
      pagination += `<div class="page-btn ${activeClass}" data-page="${i}">${i}</div>`;
    }
  }

  res.json({ html, pagination, count: totalItems });
}));

// ── Newsletter subscribe handler (exported so public.js can mount POST /subscribe) ─
async function handleSubscribe(req, res) {
  const email = (req.body.email || '').trim();
  if (!email) return res.redirect('/');

  // INSERT IGNORE on duplicate email — matches PHP behaviour
  await pool.query(
    'INSERT IGNORE INTO newsletter (email) VALUES (?)',
    [email]
  );

  // Redirect back (PHP did header("Location: ...") back to referrer or /)
  const referer = req.get('referer') || '/';
  res.redirect(referer);
}

module.exports = router;
module.exports.handleSubscribe = handleSubscribe;
