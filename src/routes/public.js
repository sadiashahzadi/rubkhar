'use strict';

const router = require('express').Router();
const pool = require('../db');
const bcrypt = require('bcryptjs');
const { setSession, getSession, clearSession } = require('../lib/session');
const { requireUser } = require('../middleware/auth');
const { handleSubscribe } = require('./api');

// ── Async wrapper ─────────────────────────────────────────────────────────────
const wrap = fn => (req, res, next) => Promise.resolve(fn(req, res, next)).catch(next);

// ── GET / ─────────────────────────────────────────────────────────────────────
router.get('/', wrap(async (req, res) => {
  const [new_arrivals] = await pool.query(
    'SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.created_at DESC LIMIT 8'
  );
  const [best_sellers] = await pool.query(
    'SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.id ASC LIMIT 8'
  );
  res.render('public/index', { new_arrivals, best_sellers });
}));

// ── GET /shop ─────────────────────────────────────────────────────────────────
router.get('/shop', wrap(async (req, res) => {
  const [categories] = await pool.query('SELECT * FROM categories ORDER BY name ASC');
  res.render('public/shop', { categories });
}));

// ── GET /product ──────────────────────────────────────────────────────────────
// PHP used ?id= param
router.get('/product', wrap(async (req, res) => {
  const product_id = Number(req.query.id) || 1;
  if (isNaN(product_id) || product_id < 1) return res.redirect('/shop');

  const [[product]] = await pool.query(
    'SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ?',
    [product_id]
  );

  if (!product) return res.redirect('/shop');

  const [related_products] = await pool.query(
    'SELECT * FROM products WHERE category_id = ? AND id != ? LIMIT 4',
    [product.category_id, product_id]
  );

  // Mock original price (20% higher) — matches PHP behaviour
  const original_price = product.price * 1.20;

  res.render('public/product', { product, related_products, original_price });
}));

// ── GET /order-confirmation ───────────────────────────────────────────────────
router.get('/order-confirmation', wrap(async (req, res) => {
  // Primary: req.session.last_order_id (populated by checkout via JWT session cookie)
  // Fallback: ?order_id= or ?id= query param (PHP used ?id=)
  const order_id =
    (req.session && Number(req.session.last_order_id)) ||
    Number(req.query.order_id) ||
    Number(req.query.id) ||
    0;

  if (!order_id) return res.redirect('/shop');

  const [[order]] = await pool.query('SELECT * FROM orders WHERE id = ?', [order_id]);
  if (!order) return res.redirect('/shop');

  const [order_items] = await pool.query(
    'SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?',
    [order_id]
  );

  // Parse the address field created during checkout
  const address_lines = (order.shipping_address || '').split('\n');
  const parsed = { name: '', phone: '', email: '', address: '', payment: 'COD', notes: '' };

  for (const line of address_lines) {
    if (line.startsWith('Name: '))    parsed.name    = line.slice('Name: '.length);
    else if (line.startsWith('Phone: '))   parsed.phone   = line.slice('Phone: '.length);
    else if (line.startsWith('Email: '))   parsed.email   = line.slice('Email: '.length);
    else if (line.startsWith('Address: ')) parsed.address = line.slice('Address: '.length);
    else if (line.startsWith('Payment: ')) parsed.payment = line.slice('Payment: '.length);
    else if (line.startsWith('Notes: '))   parsed.notes   = line.slice('Notes: '.length);
  }

  // Format payment display
  let payment_display = 'Cash on Delivery (COD)';
  if (parsed.payment === 'jazzcash')  payment_display = 'JazzCash Mobile Account';
  if (parsed.payment === 'easypaisa') payment_display = 'EasyPaisa';

  // Calculate subtotal and delivery fee
  let subtotal = 0;
  for (const item of order_items) {
    subtotal += item.price * item.quantity;
  }
  const delivery_fee = order.total_amount - subtotal;

  res.render('public/order-confirmation', {
    order,
    order_items,
    parsed,
    payment_display,
    subtotal,
    delivery_fee,
  });
}));

// ── GET /cart ─────────────────────────────────────────────────────────────────
router.get('/cart', wrap(async (req, res) => {
  const is_empty = ('empty' in req.query);

  let cart_items = [];

  if (!is_empty) {
    const [mock_products] = await pool.query('SELECT * FROM products LIMIT 2');

    if (mock_products.length > 0) {
      cart_items.push({
        id: mock_products[0].id,
        name: mock_products[0].name,
        price: mock_products[0].price,
        image: 'https://via.placeholder.com/150x150?text=Product+1',
        size: 'M',
        color: 'Maroon',
        quantity: 2,
      });
      if (mock_products[1]) {
        cart_items.push({
          id: mock_products[1].id,
          name: mock_products[1].name,
          price: mock_products[1].price,
          image: 'https://via.placeholder.com/150x150?text=Product+2',
          size: 'L',
          color: 'Gold',
          quantity: 1,
        });
      }
    } else {
      // no products at all — treat as empty
      return res.render('public/cart', { is_empty: true, cart_items: [] });
    }
  }

  res.render('public/cart', { is_empty, cart_items });
}));

// ── GET /checkout ─────────────────────────────────────────────────────────────
router.get('/checkout', wrap(async (req, res) => {
  const [mock_products] = await pool.query('SELECT * FROM products LIMIT 2');
  const cart_items = mock_products;

  let subtotal = 0;
  for (const item of cart_items) {
    subtotal += Number(item.price);
  }

  const standard_delivery_fee = subtotal >= 2000 ? 0 : 200;
  const standard_delivery_text = standard_delivery_fee === 0 ? 'Free' : 'Rs. 200';

  res.render('public/checkout', {
    cart_items,
    subtotal,
    standard_delivery_fee,
    standard_delivery_text,
    error: null,
  });
}));

// ── POST /checkout ────────────────────────────────────────────────────────────
router.post('/checkout', wrap(async (req, res) => {
  const [mock_products] = await pool.query('SELECT * FROM products LIMIT 2');
  const cart_items = mock_products;

  let subtotal = 0;
  for (const item of cart_items) {
    subtotal += Number(item.price);
  }

  const standard_delivery_fee = subtotal >= 2000 ? 0 : 200;
  const standard_delivery_text = standard_delivery_fee === 0 ? 'Free' : 'Rs. 200';

  const name     = (req.body.name     || '').trim();
  const phone    = (req.body.phone    || '').trim();
  const email    = (req.body.email    || '').trim();
  const address  = (req.body.address  || '').trim();
  const city     = (req.body.city     || '').trim();
  const province = (req.body.province || '').trim();
  const delivery = (req.body.delivery || 'standard').trim();
  const payment  = (req.body.payment  || 'cod').trim();
  const notes    = (req.body.notes    || '').trim();

  if (!name || !phone || !address || !city || !province) {
    return res.render('public/checkout', {
      cart_items,
      subtotal,
      standard_delivery_fee,
      standard_delivery_text,
      error: 'Please fill in all required fields.',
    });
  }

  const full_address = `Name: ${name}\nPhone: ${phone}\nEmail: ${email}\nAddress: ${address}, ${city}, ${province}\nPayment: ${payment}\nNotes: ${notes}`;
  const delivery_fee = delivery === 'express' ? 350 : (subtotal >= 2000 ? 0 : 200);
  const total_amount = subtotal + delivery_fee;

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();

    const [orderResult] = await conn.query(
      "INSERT INTO orders (user_id, total_amount, status, shipping_address) VALUES (1, ?, 'pending', ?)",
      [total_amount, full_address]
    );
    const order_id = orderResult.insertId;

    for (const item of cart_items) {
      await conn.query(
        'INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)',
        [order_id, item.id, 1, item.price]
      );
    }

    await conn.commit();

    // Merge last_order_id into existing session (preserve user login state)
    const existingSession = getSession(req, 'rk_session') || {};
    setSession(res, { ...existingSession, last_order_id: order_id }, 'rk_session');

    return res.redirect('/order-confirmation');
  } catch (err) {
    await conn.rollback();
    // Log server-side but never expose raw DB error to client
    console.error('Checkout DB error:', err);
    return res.render('public/checkout', {
      cart_items,
      subtotal,
      standard_delivery_fee,
      standard_delivery_text,
      error: 'Failed to place order. Please try again.',
    });
  } finally {
    conn.release();
  }
}));

// ── GET /login ────────────────────────────────────────────────────────────────
router.get('/login', (req, res) => {
  // Already logged in → redirect home
  if (res.locals.user) return res.redirect('/');
  res.render('public/login', { active_tab: 'login', error: null });
});

// ── POST /login ───────────────────────────────────────────────────────────────
router.post('/login', wrap(async (req, res) => {
  const action = req.body.action || '';

  if (action === 'register') {
    const name     = (req.body.name     || '').trim();
    const email    = (req.body.email    || '').trim();
    const phone    = (req.body.phone    || '').trim();
    const password = req.body.password  || '';
    const confirm  = req.body.confirm_password || '';
    const terms    = !!req.body.terms;

    const renderRegError = (msg) => res.render('public/login', {
      active_tab: 'register',
      error: msg,
      post_name: name,
      post_reg_email: email,
      post_phone: phone,
    });

    if (!name || !email || !phone || !password || !confirm) {
      return renderRegError('All fields are required.');
    }
    if (!terms) {
      return renderRegError('You must accept the Terms and Conditions.');
    }
    if (password !== confirm) {
      return renderRegError('Passwords do not match.');
    }
    if (password.length < 6) {
      return renderRegError('Password must be at least 6 characters long.');
    }

    const [[existing]] = await pool.query('SELECT id FROM users WHERE email = ?', [email]);
    if (existing) {
      return renderRegError('This email is already registered. Please log in.');
    }

    const hash = await bcrypt.hash(password, 10);
    let newId;
    try {
      const [result] = await pool.query(
        "INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'customer')",
        [name, email, phone, hash]
      );
      newId = result.insertId;
    } catch (err) {
      console.error('Register DB error:', err);
      return renderRegError('Registration failed due to a server error. Please try again.');
    }

    setSession(res, { user_id: newId, user_name: name, user_role: 'customer' }, 'rk_session');
    return res.redirect('/');

  } else if (action === 'login') {
    const email    = (req.body.email    || '').trim();
    const password = req.body.password  || '';

    const renderLoginError = (msg) => res.render('public/login', {
      active_tab: 'login',
      error: msg,
      post_email: email,
    });

    if (!email || !password) {
      return renderLoginError('Please enter both email and password.');
    }

    const [[user]] = await pool.query(
      'SELECT id, name, password, role FROM users WHERE email = ?',
      [email]
    );

    if (user && await bcrypt.compare(password, user.password)) {
      setSession(res, { user_id: user.id, user_name: user.name, user_role: user.role }, 'rk_session');
      return res.redirect('/');
    } else {
      return renderLoginError('Invalid email address or password.');
    }
  }

  // Unknown action — redirect back
  return res.redirect('/login');
}));

// ── GET /my-account ───────────────────────────────────────────────────────────
router.get('/my-account', requireUser, wrap(async (req, res) => {
  // Handle logout via GET ?logout=1
  if ('logout' in req.query) {
    clearSession(res, 'rk_session');
    return res.redirect('/login');
  }

  const session = getSession(req, 'rk_session');
  const user_id = session.user_id;

  const [[user]] = await pool.query('SELECT * FROM users WHERE id = ?', [user_id]);

  const [orders] = await pool.query(
    'SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC',
    [user_id]
  );

  // Pre-fetch order items for the modal
  let order_items = {};
  if (orders.length > 0) {
    const order_ids = orders.map(o => o.id);
    const placeholders = order_ids.map(() => '?').join(',');
    const [items] = await pool.query(
      `SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id IN (${placeholders})`,
      order_ids
    );
    for (const item of items) {
      if (!order_items[item.order_id]) order_items[item.order_id] = [];
      order_items[item.order_id].push(item);
    }
  }

  const [wishlist] = await pool.query(
    'SELECT w.id as wish_id, p.* FROM wishlist w JOIN products p ON w.product_id = p.id WHERE w.user_id = ? ORDER BY w.created_at DESC',
    [user_id]
  );

  res.render('public/my-account', {
    user,
    orders,
    order_items,
    wishlist,
    active_tab: 'orders',
    success_msg: null,
    error_msg: null,
  });
}));

// ── POST /my-account ──────────────────────────────────────────────────────────
router.post('/my-account', requireUser, wrap(async (req, res) => {
  const session = getSession(req, 'rk_session');
  const user_id = session.user_id;
  const action  = req.body.action || '';
  const tab     = req.body.tab || 'orders';

  let success_msg = null;
  let error_msg   = null;
  let active_tab  = tab;

  if (action === 'update_profile') {
    active_tab = 'profile';
    const name  = (req.body.name  || '').trim();
    const email = (req.body.email || '').trim();
    const phone = (req.body.phone || '').trim();

    try {
      await pool.query(
        'UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?',
        [name, email, phone, user_id]
      );
      // Update the name in the session cookie
      setSession(res, { ...session, user_name: name }, 'rk_session');
      success_msg = 'Profile updated successfully.';
    } catch (err) {
      console.error('Profile update error:', err);
      error_msg = 'Failed to update profile. Email might be in use.';
    }

  } else if (action === 'change_password') {
    active_tab = 'password';
    const current = req.body.current_password || '';
    const newPwd  = req.body.new_password     || '';
    const confirm = req.body.confirm_password || '';

    if (newPwd !== confirm) {
      error_msg = 'New passwords do not match.';
    } else if (newPwd.length < 6) {
      error_msg = 'Password must be at least 6 characters.';
    } else {
      const [[row]] = await pool.query('SELECT password FROM users WHERE id = ?', [user_id]);
      if (row && await bcrypt.compare(current, row.password)) {
        const new_hash = await bcrypt.hash(newPwd, 10);
        await pool.query('UPDATE users SET password = ? WHERE id = ?', [new_hash, user_id]);
        success_msg = 'Password changed successfully.';
      } else {
        error_msg = 'Current password is incorrect.';
      }
    }

  } else if (action === 'remove_wishlist') {
    active_tab = 'wishlist';
    const wish_id = Number(req.body.wishlist_id) || 0;
    if (wish_id) {
      await pool.query('DELETE FROM wishlist WHERE id = ? AND user_id = ?', [wish_id, user_id]);
    }
    success_msg = 'Item removed from your wishlist.';
  }

  // Re-fetch all data to re-render page
  const [[user]] = await pool.query('SELECT * FROM users WHERE id = ?', [user_id]);

  const [orders] = await pool.query(
    'SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC',
    [user_id]
  );

  let order_items = {};
  if (orders.length > 0) {
    const order_ids = orders.map(o => o.id);
    const placeholders = order_ids.map(() => '?').join(',');
    const [items] = await pool.query(
      `SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id IN (${placeholders})`,
      order_ids
    );
    for (const item of items) {
      if (!order_items[item.order_id]) order_items[item.order_id] = [];
      order_items[item.order_id].push(item);
    }
  }

  const [wishlist] = await pool.query(
    'SELECT w.id as wish_id, p.* FROM wishlist w JOIN products p ON w.product_id = p.id WHERE w.user_id = ? ORDER BY w.created_at DESC',
    [user_id]
  );

  res.render('public/my-account', {
    user,
    orders,
    order_items,
    wishlist,
    active_tab,
    success_msg,
    error_msg,
  });
}));

// ── POST /subscribe ───────────────────────────────────────────────────────────
router.post('/subscribe', wrap(handleSubscribe));

module.exports = router;
