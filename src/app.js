'use strict';

const path = require('path');
const express = require('express');
const cookieParser = require('cookie-parser');
const helmet = require('helmet');
const rateLimit = require('express-rate-limit');

const { attachUser } = require('./middleware/auth');
const { issueCsrf, verifyCsrf } = require('./middleware/csrf');
const errorHandler = require('./middleware/errorHandler');
const helpers = require('./lib/helpers');

const publicRoutes = require('./routes/public');
const apiRoutes = require('./routes/api');
const adminRoutes = require('./routes/admin');

const app = express();

// ── View engine ─────────────────────────────────────────────────────────────
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));

// ── Static assets ────────────────────────────────────────────────────────────
app.use(express.static(path.join(__dirname, '..', 'public')));

// ── Security headers (helmet + custom CSP) ───────────────────────────────────
app.use(
  helmet({
    contentSecurityPolicy: {
      directives: {
        defaultSrc: ["'self'"],
        styleSrc: [
          "'self'",
          "'unsafe-inline'",
          'https://fonts.googleapis.com',
          'https://cdnjs.cloudflare.com',
        ],
        scriptSrc: [
          "'self'",
          "'unsafe-inline'",
          'https://cdn.jsdelivr.net',
        ],
        fontSrc: [
          "'self'",
          'https://fonts.gstatic.com',
          'https://cdnjs.cloudflare.com',
        ],
        imgSrc: ["'self'", 'data:', 'https:'],
        connectSrc: ["'self'"],
      },
    },
  })
);

// ── Body parsing ─────────────────────────────────────────────────────────────
app.use(express.urlencoded({ extended: true }));
app.use(express.json());

// ── Cookie parsing ────────────────────────────────────────────────────────────
app.use(cookieParser());

// ── Global session / user attach ─────────────────────────────────────────────
app.use(attachUser);

// ── CSRF token issuance (global — sets rk_csrf cookie + res.locals.csrfToken) ─
app.use(issueCsrf);

// ── EJS view helpers via app.locals ──────────────────────────────────────────
app.locals.money = helpers.money;
app.locals.escape = helpers.escape;
app.locals.slugify = helpers.slugify;

// ── Rate limiting ─────────────────────────────────────────────────────────────
const loginLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 10,
  standardHeaders: true,
  legacyHeaders: false,
  message: 'Too many login attempts. Please try again later.',
});

const ajaxProductsLimiter = rateLimit({
  windowMs: 60 * 1000, // 1 minute
  max: 60,
  standardHeaders: true,
  legacyHeaders: false,
  message: 'Too many requests. Please slow down.',
});

// Apply rate limiters to specific paths before routers
app.use('/login', loginLimiter);
app.use('/admin/login', loginLimiter);
app.use('/ajax/products', ajaxProductsLimiter);

// ── CSRF verification for state-changing routes (before routers, after body+cookie parse)
// Multipart routes handle CSRF per-route via verifyCsrfMultipart after multer.
app.use(verifyCsrf);

// ── Routes ────────────────────────────────────────────────────────────────────
app.use('/', publicRoutes);
app.use('/ajax', apiRoutes);
app.use('/admin', adminRoutes);

// ── Central error handler (must be last) ─────────────────────────────────────
app.use(errorHandler);

module.exports = app;
