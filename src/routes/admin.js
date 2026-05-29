'use strict';

const router = require('express').Router();
const bcrypt = require('bcryptjs');
const multer = require('multer');

const pool = require('../db');
const { setSession, clearSession } = require('../lib/session');
const { requireAdmin } = require('../middleware/auth');
const { verifyCsrfMultipart } = require('../middleware/csrf');
const { slugify } = require('../lib/helpers');

// ── Multer – memory storage only (never touches disk) ─────────────────────────
const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: 5 * 1024 * 1024 }, // 5 MB
});

// ── Magic-byte image validator ────────────────────────────────────────────────
/**
 * Validate an uploaded file is truly JPEG, PNG, or WebP by checking both
 * the declared mimetype AND the file's magic bytes.
 * Returns an error string on failure, or null on success.
 */
function validateImageFile(file) {
  const allowed = ['image/jpeg', 'image/png', 'image/webp'];
  if (!allowed.includes(file.mimetype)) {
    return 'Only JPEG, PNG, and WebP images are allowed.';
  }
  const buf = file.buffer;
  // JPEG: FF D8 FF
  const isJpeg = buf[0] === 0xff && buf[1] === 0xd8 && buf[2] === 0xff;
  // PNG: 89 50 4E 47
  const isPng =
    buf[0] === 0x89 && buf[1] === 0x50 && buf[2] === 0x4e && buf[3] === 0x47;
  // WebP: 'RIFF' at 0-3, 'WEBP' at 8-11
  const isWebp =
    buf[0] === 0x52 &&
    buf[1] === 0x49 &&
    buf[2] === 0x46 &&
    buf[3] === 0x46 &&
    buf[8] === 0x57 &&
    buf[9] === 0x45 &&
    buf[10] === 0x42 &&
    buf[11] === 0x50;
  if (!isJpeg && !isPng && !isWebp) {
    return 'File content does not match a valid image format.';
  }
  return null;
}

// ── Upload a file buffer to Vercel Blob ───────────────────────────────────────
async function uploadToBlob(file, folder) {
  const { put } = require('@vercel/blob');
  const safeName = file.originalname.replace(/[^a-zA-Z0-9._-]/g, '_');
  const blobPath = `${folder}/${Date.now()}_${safeName}`;
  const blob = await put(blobPath, file.buffer, { access: 'public' });
  return blob.url;
}

// ── Helper: format date like PHP date('d M Y, h:i A') ─────────────────────────
// Used in route logic (not views) if needed; views handle it inline.

// ═════════════════════════════════════════════════════════════════════════════
// LOGIN (public — no requireAdmin)
// ═════════════════════════════════════════════════════════════════════════════

// GET /admin/login
router.get('/login', (req, res) => {
  // Already logged in → redirect dashboard
  const { getSession } = require('../lib/session');
  const session = getSession(req, 'rk_admin');
  if (session && session.admin_logged_in) {
    return res.redirect('/admin');
  }
  res.render('admin/login', { error: '', emailVal: '' });
});

// POST /admin/login
router.post('/login', async (req, res, next) => {
  try {
    const email = (req.body.email || '').trim();
    const password = req.body.password || '';

    if (!email || !password) {
      return res.render('admin/login', {
        error: 'Please enter email and password.',
        emailVal: email,
      });
    }

    const [rows] = await pool.query(
      "SELECT id, name, password, role FROM users WHERE email = ? AND role = 'admin'",
      [email]
    );
    const admin = rows[0];

    if (admin && (await bcrypt.compare(password, admin.password))) {
      setSession(
        res,
        { admin_logged_in: true, admin_id: admin.id, admin_name: admin.name },
        'rk_admin'
      );
      return res.redirect('/admin');
    }

    res.render('admin/login', {
      error: 'Invalid admin credentials.',
      emailVal: email,
    });
  } catch (err) {
    next(err);
  }
});

// ═════════════════════════════════════════════════════════════════════════════
// LOGOUT (public — clears cookie)
// ═════════════════════════════════════════════════════════════════════════════

// GET /admin/logout
router.get('/logout', (req, res) => {
  clearSession(res, 'rk_admin');
  res.redirect('/admin/login');
});

// ═════════════════════════════════════════════════════════════════════════════
// ALL ROUTES BELOW REQUIRE ADMIN
// ═════════════════════════════════════════════════════════════════════════════
router.use(requireAdmin);

// ── Shared view locals helper ─────────────────────────────────────────────────
function adminLocals(currentPage, pageTitle, extra) {
  return Object.assign({ currentPage, pageTitle }, extra);
}

// ═════════════════════════════════════════════════════════════════════════════
// DASHBOARD  GET /admin
// ═════════════════════════════════════════════════════════════════════════════
router.get('/', async (req, res, next) => {
  try {
    const [[{ total_orders }]] = await pool.query('SELECT COUNT(*) as total_orders FROM orders');
    const [[{ total_revenue }]] = await pool.query('SELECT COALESCE(SUM(total_amount),0) as total_revenue FROM orders');
    const [[{ total_customers }]] = await pool.query("SELECT COUNT(*) as total_customers FROM users WHERE role = 'customer'");
    const [[{ low_stock }]] = await pool.query('SELECT COUNT(*) as low_stock FROM products WHERE stock < 5');

    const [recent_orders] = await pool.query(`
      SELECT o.*, u.name as customer_name
      FROM orders o
      JOIN users u ON o.user_id = u.id
      ORDER BY o.created_at DESC
      LIMIT 5
    `);

    // Sales chart: last 7 days
    const chart_labels = [];
    const chart_data = [];
    for (let i = 6; i >= 0; i--) {
      const d = new Date();
      d.setDate(d.getDate() - i);
      const dateStr = d.toISOString().slice(0, 10);
      const label = d.toLocaleDateString('en-US', { month: 'short', day: '2-digit' });
      chart_labels.push(label);

      const [[{ day_revenue }]] = await pool.query(
        "SELECT COALESCE(SUM(total_amount),0) as day_revenue FROM orders WHERE DATE(created_at) = ? AND status != 'cancelled'",
        [dateStr]
      );
      chart_data.push(Number(day_revenue));
    }

    res.render(
      'admin/index',
      adminLocals('index', 'Dashboard', {
        total_orders,
        total_revenue,
        total_customers,
        low_stock,
        recent_orders,
        chart_labels,
        chart_data,
      })
    );
  } catch (err) {
    next(err);
  }
});

// ═════════════════════════════════════════════════════════════════════════════
// PRODUCTS
// ═════════════════════════════════════════════════════════════════════════════

// GET /admin/products
router.get('/products', async (req, res, next) => {
  try {
    const action = req.query.action || 'list';
    const success =
      req.query.success === 'deleted'
        ? 'Product deleted successfully.'
        : req.query.success === 'added'
        ? 'Product added successfully.'
        : req.query.success === 'updated'
        ? 'Product updated successfully.'
        : '';

    const [categories] = await pool.query('SELECT * FROM categories ORDER BY name ASC');

    if (action === 'list') {
      const [products] = await pool.query(
        'SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC'
      );
      return res.render(
        'admin/products',
        adminLocals('products', 'Products', { action, success, products, categories, p: null, error: '' })
      );
    }

    if (action === 'add') {
      return res.render(
        'admin/products',
        adminLocals('products', 'Products', { action, success: '', products: [], categories, p: null, error: '' })
      );
    }

    if (action === 'edit') {
      const id = Number(req.query.id);
      if (!id || isNaN(id)) return res.redirect('/admin/products');

      const [[p]] = await pool.query('SELECT * FROM products WHERE id = ?', [id]);
      if (!p) return res.redirect('/admin/products');

      return res.render(
        'admin/products',
        adminLocals('products', 'Products', { action, success: '', products: [], categories, p, error: '' })
      );
    }

    res.redirect('/admin/products');
  } catch (err) {
    next(err);
  }
});

// POST /admin/products/add
router.post('/products/add', upload.single('image'), verifyCsrfMultipart, async (req, res, next) => {
  try {
    const { name, category_id, description, price, sale_price, stock, sizes, colors } = req.body;
    const featured = req.body.featured ? 1 : 0;
    let image_url = '';

    if (req.file) {
      const validErr = validateImageFile(req.file);
      if (validErr) {
        const [categories] = await pool.query('SELECT * FROM categories ORDER BY name ASC');
        return res.render(
          'admin/products',
          adminLocals('products', 'Products', {
            action: 'add',
            success: '',
            products: [],
            categories,
            p: null,
            error: validErr,
          })
        );
      }
      image_url = await uploadToBlob(req.file, 'products');
    }

    // Generate slug from name; ensure uniqueness by appending timestamp on collision
    let slug = slugify(name || '');
    const [existing] = await pool.query('SELECT id FROM products WHERE slug = ?', [slug]);
    if (existing.length > 0) {
      slug = `${slug}-${Date.now()}`;
    }

    await pool.query(
      'INSERT INTO products (category_id, name, slug, description, price, sale_price, image_url, stock, sizes, colors, featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
      [
        Number(category_id) || 0,
        name,
        slug,
        description,
        Number(price) || 0,
        sale_price ? Number(sale_price) : null,
        image_url,
        Number(stock) || 0,
        sizes || '',
        colors || '',
        featured,
      ]
    );

    res.redirect('/admin/products?success=added');
  } catch (err) {
    next(err);
  }
});

// POST /admin/products/edit
router.post('/products/edit', upload.single('image'), verifyCsrfMultipart, async (req, res, next) => {
  try {
    const id = Number(req.body.product_id);
    if (!id || isNaN(id)) return res.redirect('/admin/products');

    const { name, category_id, description, price, sale_price, stock, sizes, colors, current_image } = req.body;
    const featured = req.body.featured ? 1 : 0;
    let image_url = current_image || '';

    if (req.file) {
      const validErr = validateImageFile(req.file);
      if (validErr) {
        const [categories] = await pool.query('SELECT * FROM categories ORDER BY name ASC');
        const [[p]] = await pool.query('SELECT * FROM products WHERE id = ?', [id]);
        return res.render(
          'admin/products',
          adminLocals('products', 'Products', {
            action: 'edit',
            success: '',
            products: [],
            categories,
            p,
            error: validErr,
          })
        );
      }
      image_url = await uploadToBlob(req.file, 'products');
    }

    // Re-generate slug from (possibly updated) name
    let slug = slugify(name || '');
    // Check uniqueness excluding the current product
    const [existing] = await pool.query('SELECT id FROM products WHERE slug = ? AND id != ?', [slug, id]);
    if (existing.length > 0) {
      slug = `${slug}-${Date.now()}`;
    }

    await pool.query(
      'UPDATE products SET category_id=?, name=?, slug=?, description=?, price=?, sale_price=?, image_url=?, stock=?, sizes=?, colors=?, featured=? WHERE id=?',
      [
        Number(category_id) || 0,
        name,
        slug,
        description,
        Number(price) || 0,
        sale_price ? Number(sale_price) : null,
        image_url,
        Number(stock) || 0,
        sizes || '',
        colors || '',
        featured,
        id,
      ]
    );

    res.redirect('/admin/products?success=updated');
  } catch (err) {
    next(err);
  }
});

// POST /admin/products/delete  (was GET ?action=delete — now POST for security)
router.post('/products/delete', async (req, res, next) => {
  try {
    const id = Number(req.body.id);
    if (!id || isNaN(id)) return res.redirect('/admin/products');

    await pool.query('DELETE FROM products WHERE id = ?', [id]);
    res.redirect('/admin/products?success=deleted');
  } catch (err) {
    next(err);
  }
});

// ═════════════════════════════════════════════════════════════════════════════
// CATEGORIES
// ═════════════════════════════════════════════════════════════════════════════

// GET /admin/categories
router.get('/categories', async (req, res, next) => {
  try {
    const action = req.query.action || 'list';
    const success =
      req.query.success === 'deleted'
        ? 'Category deleted successfully.'
        : req.query.success === 'added'
        ? 'Category added successfully.'
        : req.query.success === 'updated'
        ? 'Category updated successfully.'
        : '';

    if (action === 'list') {
      const [categories] = await pool.query('SELECT * FROM categories ORDER BY name ASC');
      return res.render(
        'admin/categories',
        adminLocals('categories', 'Categories', { action, success, categories, cat: null, error: '' })
      );
    }

    if (action === 'add') {
      return res.render(
        'admin/categories',
        adminLocals('categories', 'Categories', { action, success: '', categories: [], cat: null, error: '' })
      );
    }

    if (action === 'edit') {
      const id = Number(req.query.id);
      if (!id || isNaN(id)) return res.redirect('/admin/categories');

      const [[cat]] = await pool.query('SELECT * FROM categories WHERE id = ?', [id]);
      if (!cat) return res.redirect('/admin/categories');

      return res.render(
        'admin/categories',
        adminLocals('categories', 'Categories', { action, success: '', categories: [], cat, error: '' })
      );
    }

    res.redirect('/admin/categories');
  } catch (err) {
    next(err);
  }
});

// POST /admin/categories/add
router.post('/categories/add', upload.single('image'), verifyCsrfMultipart, async (req, res, next) => {
  try {
    const { name } = req.body;
    let image_url = '';

    if (req.file) {
      const validErr = validateImageFile(req.file);
      if (validErr) {
        return res.render(
          'admin/categories',
          adminLocals('categories', 'Categories', {
            action: 'add',
            success: '',
            categories: [],
            cat: null,
            error: validErr,
          })
        );
      }
      image_url = await uploadToBlob(req.file, 'categories');
    }

    await pool.query('INSERT INTO categories (name, image_url) VALUES (?, ?)', [name, image_url]);
    res.redirect('/admin/categories?success=added');
  } catch (err) {
    next(err);
  }
});

// POST /admin/categories/edit
router.post('/categories/edit', upload.single('image'), verifyCsrfMultipart, async (req, res, next) => {
  try {
    const id = Number(req.body.category_id);
    if (!id || isNaN(id)) return res.redirect('/admin/categories');

    const { name, current_image } = req.body;
    let image_url = current_image || '';

    if (req.file) {
      const validErr = validateImageFile(req.file);
      if (validErr) {
        const [[cat]] = await pool.query('SELECT * FROM categories WHERE id = ?', [id]);
        return res.render(
          'admin/categories',
          adminLocals('categories', 'Categories', {
            action: 'edit',
            success: '',
            categories: [],
            cat,
            error: validErr,
          })
        );
      }
      image_url = await uploadToBlob(req.file, 'categories');
    }

    await pool.query('UPDATE categories SET name=?, image_url=? WHERE id=?', [name, image_url, id]);
    res.redirect('/admin/categories?success=updated');
  } catch (err) {
    next(err);
  }
});

// POST /admin/categories/delete  (was GET ?action=delete)
router.post('/categories/delete', async (req, res, next) => {
  try {
    const id = Number(req.body.id);
    if (!id || isNaN(id)) return res.redirect('/admin/categories');

    await pool.query('DELETE FROM categories WHERE id = ?', [id]);
    res.redirect('/admin/categories?success=deleted');
  } catch (err) {
    next(err);
  }
});

// ═════════════════════════════════════════════════════════════════════════════
// ORDERS
// ═════════════════════════════════════════════════════════════════════════════

// GET /admin/orders
router.get('/orders', async (req, res, next) => {
  try {
    const status_filter = req.query.status || 'all';
    const view_id = Number(req.query.view) || 0;

    let orders = [];
    let order = null;
    let order_items = [];

    if (view_id) {
      const [[row]] = await pool.query(
        'SELECT o.*, u.name, u.email, u.phone as uphone FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?',
        [view_id]
      );
      order = row || null;

      if (order) {
        [order_items] = await pool.query(
          'SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?',
          [view_id]
        );
      }
    } else {
      if (status_filter !== 'all') {
        [orders] = await pool.query(
          'SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.status = ? ORDER BY o.created_at DESC',
          [status_filter]
        );
      } else {
        [orders] = await pool.query(
          'SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC'
        );
      }
    }

    res.render(
      'admin/orders',
      adminLocals('orders', 'Orders', {
        orders,
        order,
        order_items,
        view_id,
        status_filter,
        success: '',
      })
    );
  } catch (err) {
    next(err);
  }
});

// POST /admin/orders/status
router.post('/orders/status', async (req, res, next) => {
  try {
    const order_id = Number(req.body.order_id);
    const status = req.body.status || '';

    const allowed_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (!order_id || isNaN(order_id) || !allowed_statuses.includes(status)) {
      return res.redirect('/admin/orders');
    }

    await pool.query('UPDATE orders SET status = ? WHERE id = ?', [status, order_id]);

    // If updating from detail view, redirect back to it
    if (req.get('Referer') && req.get('Referer').includes('view=')) {
      return res.redirect(`/admin/orders?view=${order_id}`);
    }
    res.redirect('/admin/orders');
  } catch (err) {
    next(err);
  }
});

// ═════════════════════════════════════════════════════════════════════════════
// CUSTOMERS
// ═════════════════════════════════════════════════════════════════════════════

// GET /admin/customers
router.get('/customers', async (req, res, next) => {
  try {
    const view_id = Number(req.query.view) || 0;
    let customer = null;
    let orders = [];
    let customers = [];

    if (view_id) {
      const [[row]] = await pool.query(
        "SELECT * FROM users WHERE id = ? AND role = 'customer'",
        [view_id]
      );
      customer = row || null;

      if (customer) {
        [orders] = await pool.query(
          'SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC',
          [view_id]
        );
      }
    } else {
      [customers] = await pool.query(`
        SELECT u.*,
               COUNT(o.id) as total_orders,
               SUM(o.total_amount) as total_spent
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id AND o.status != 'cancelled'
        WHERE u.role = 'customer'
        GROUP BY u.id
        ORDER BY u.created_at DESC
      `);
    }

    res.render(
      'admin/customers',
      adminLocals('customers', 'Customers', { view_id, customer, orders, customers })
    );
  } catch (err) {
    next(err);
  }
});

// ═════════════════════════════════════════════════════════════════════════════
// COUPONS
// ═════════════════════════════════════════════════════════════════════════════

// GET /admin/coupons
router.get('/coupons', async (req, res, next) => {
  try {
    const action = req.query.action || 'list';
    const success =
      req.query.success === 'deleted'
        ? 'Coupon deleted successfully.'
        : req.query.success === 'added'
        ? 'Coupon created successfully.'
        : req.query.success === 'updated'
        ? 'Coupon updated successfully.'
        : '';

    if (action === 'list') {
      const [coupons] = await pool.query('SELECT * FROM coupons ORDER BY id DESC');
      return res.render(
        'admin/coupons',
        adminLocals('coupons', 'Coupons', { action, success, coupons, coupon: null, error: '' })
      );
    }

    if (action === 'add') {
      return res.render(
        'admin/coupons',
        adminLocals('coupons', 'Coupons', { action, success: '', coupons: [], coupon: null, error: '' })
      );
    }

    if (action === 'edit') {
      const id = Number(req.query.id);
      if (!id || isNaN(id)) return res.redirect('/admin/coupons');

      const [[coupon]] = await pool.query('SELECT * FROM coupons WHERE id = ?', [id]);
      if (!coupon) return res.redirect('/admin/coupons');

      return res.render(
        'admin/coupons',
        adminLocals('coupons', 'Coupons', { action, success: '', coupons: [], coupon, error: '' })
      );
    }

    res.redirect('/admin/coupons');
  } catch (err) {
    next(err);
  }
});

// POST /admin/coupons/add
router.post('/coupons/add', async (req, res, next) => {
  try {
    const code = (req.body.code || '').trim().toUpperCase();
    const discount_type = req.body.discount_type || 'fixed';
    const discount_value = Number(req.body.discount_value) || 0;
    const is_active = req.body.is_active ? 1 : 0;

    try {
      await pool.query(
        'INSERT INTO coupons (code, discount_type, discount_value, is_active) VALUES (?, ?, ?, ?)',
        [code, discount_type, discount_value, is_active]
      );
      res.redirect('/admin/coupons?success=added');
    } catch (dbErr) {
      res.render(
        'admin/coupons',
        adminLocals('coupons', 'Coupons', {
          action: 'add',
          success: '',
          coupons: [],
          coupon: null,
          error: 'Error saving coupon. The code might already exist.',
        })
      );
    }
  } catch (err) {
    next(err);
  }
});

// POST /admin/coupons/edit
router.post('/coupons/edit', async (req, res, next) => {
  try {
    const id = Number(req.body.coupon_id);
    if (!id || isNaN(id)) return res.redirect('/admin/coupons');

    const code = (req.body.code || '').trim().toUpperCase();
    const discount_type = req.body.discount_type || 'fixed';
    const discount_value = Number(req.body.discount_value) || 0;
    const is_active = req.body.is_active ? 1 : 0;

    try {
      await pool.query(
        'UPDATE coupons SET code=?, discount_type=?, discount_value=?, is_active=? WHERE id=?',
        [code, discount_type, discount_value, is_active, id]
      );
      res.redirect('/admin/coupons?success=updated');
    } catch (dbErr) {
      const [[coupon]] = await pool.query('SELECT * FROM coupons WHERE id = ?', [id]);
      res.render(
        'admin/coupons',
        adminLocals('coupons', 'Coupons', {
          action: 'edit',
          success: '',
          coupons: [],
          coupon,
          error: 'Error saving coupon. The code might already exist.',
        })
      );
    }
  } catch (err) {
    next(err);
  }
});

// POST /admin/coupons/delete  (was GET ?action=delete)
router.post('/coupons/delete', async (req, res, next) => {
  try {
    const id = Number(req.body.id);
    if (!id || isNaN(id)) return res.redirect('/admin/coupons');

    await pool.query('DELETE FROM coupons WHERE id = ?', [id]);
    res.redirect('/admin/coupons?success=deleted');
  } catch (err) {
    next(err);
  }
});

module.exports = router;
