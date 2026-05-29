'use strict';

const crypto = require('crypto');

const COOKIE_NAME = 'rk_csrf';
const EXEMPT_PATHS = new Set(['/ajax/products']);

/**
 * issueCsrf — global middleware.
 * Generates a random token and stores it in an httpOnly cookie if not already
 * present, then exposes it to every EJS view via res.locals.csrfToken.
 */
function issueCsrf(req, res, next) {
  let token = req.cookies[COOKIE_NAME];

  if (!token) {
    token = crypto.randomBytes(32).toString('hex');
    res.cookie(COOKIE_NAME, token, {
      httpOnly: true,
      sameSite: 'lax',
      secure: process.env.NODE_ENV === 'production',
      path: '/',
    });
  }

  res.locals.csrfToken = token;
  next();
}

/**
 * verifyCsrf — state-change middleware.
 * Skips:
 *   - safe HTTP methods (GET, HEAD, OPTIONS)
 *   - the AJAX product-filter endpoint (read-only, no state change)
 *   - multipart/form-data requests (multer hasn't run yet on those routes;
 *     those routes attach verifyCsrf AFTER multer in the handler chain instead)
 *
 * Accepts the token from:
 *   - req.body._csrf  (URL-encoded / JSON form field)
 *   - x-csrf-token    request header (AJAX)
 */
function verifyCsrf(req, res, next) {
  const method = req.method.toUpperCase();

  // Skip safe methods
  if (method === 'GET' || method === 'HEAD' || method === 'OPTIONS') {
    return next();
  }

  // Skip exempted paths
  if (EXEMPT_PATHS.has(req.path)) {
    return next();
  }

  // Skip multipart — the caller must place verifyCsrf AFTER multer per-route
  const contentType = req.headers['content-type'] || '';
  if (contentType.startsWith('multipart/form-data')) {
    return next();
  }

  const expected = req.cookies[COOKIE_NAME];
  const provided  = req.body._csrf || req.headers['x-csrf-token'] || '';

  if (!expected || !provided) {
    return _csrfError(res);
  }

  // Constant-time comparison to prevent timing attacks
  try {
    const a = Buffer.from(expected);
    const b = Buffer.from(provided);
    if (a.length !== b.length || !crypto.timingSafeEqual(a, b)) {
      return _csrfError(res);
    }
  } catch (_) {
    return _csrfError(res);
  }

  next();
}

/**
 * verifyCsrfMultipart — run this AFTER multer on multipart routes.
 * By the time multer finishes, req.body is populated with text fields
 * (including _csrf), so a normal token check works.
 */
function verifyCsrfMultipart(req, res, next) {
  const expected = req.cookies[COOKIE_NAME];
  const provided  = req.body._csrf || req.headers['x-csrf-token'] || '';

  if (!expected || !provided) {
    return _csrfError(res);
  }

  try {
    const a = Buffer.from(expected);
    const b = Buffer.from(provided);
    if (a.length !== b.length || !crypto.timingSafeEqual(a, b)) {
      return _csrfError(res);
    }
  } catch (_) {
    return _csrfError(res);
  }

  next();
}

function _csrfError(res) {
  return res.status(403).send('Invalid or missing CSRF token.');
}

module.exports = { issueCsrf, verifyCsrf, verifyCsrfMultipart };
