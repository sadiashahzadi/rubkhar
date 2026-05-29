'use strict';

const { getSession } = require('../lib/session');

/**
 * Global middleware: attach user from rk_session JWT to res.locals.user.
 * Must run on every request so partials can show login state.
 */
function attachUser(req, res, next) {
  const session = getSession(req, 'rk_session');
  res.locals.user = session
    ? { id: session.user_id, name: session.user_name, role: session.user_role }
    : null;
  next();
}

/**
 * Require a logged-in customer. Redirects to /login if not authenticated.
 */
function requireUser(req, res, next) {
  if (!res.locals.user) {
    return res.redirect('/login');
  }
  next();
}

/**
 * Require a logged-in admin (rk_admin cookie). Redirects to /admin/login if not.
 */
function requireAdmin(req, res, next) {
  const session = getSession(req, 'rk_admin');
  if (!session || !session.admin_logged_in) {
    return res.redirect('/admin/login');
  }
  res.locals.admin = { id: session.admin_id, name: session.admin_name };
  next();
}

module.exports = { attachUser, requireUser, requireAdmin };
