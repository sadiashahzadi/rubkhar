'use strict';

const jwt = require('jsonwebtoken');

const SEVEN_DAYS_MS = 7 * 24 * 60 * 60 * 1000;
const SEVEN_DAYS_S = 7 * 24 * 60 * 60;

function cookieOptions(isProduction) {
  return {
    httpOnly: true,
    secure: isProduction,
    sameSite: 'lax',
    maxAge: SEVEN_DAYS_MS,
  };
}

/**
 * Sign a JWT and set it as an httpOnly cookie.
 * @param {import('express').Response} res
 * @param {object} payload
 * @param {string} cookieName
 */
function setSession(res, payload, cookieName) {
  const { JWT_SECRET, NODE_ENV } = require('../config');
  const token = jwt.sign(payload, JWT_SECRET, { expiresIn: SEVEN_DAYS_S });
  res.cookie(cookieName, token, cookieOptions(NODE_ENV === 'production'));
}

/**
 * Verify the JWT cookie and return the decoded payload, or null.
 * @param {import('express').Request} req
 * @param {string} cookieName
 * @returns {object|null}
 */
function getSession(req, cookieName) {
  const { JWT_SECRET } = require('../config');
  const token = req.cookies && req.cookies[cookieName];
  if (!token) return null;
  try {
    return jwt.verify(token, JWT_SECRET);
  } catch {
    return null;
  }
}

/**
 * Clear the session cookie.
 * @param {import('express').Response} res
 * @param {string} cookieName
 */
function clearSession(res, cookieName) {
  const { NODE_ENV } = require('../config');
  res.clearCookie(cookieName, {
    httpOnly: true,
    secure: NODE_ENV === 'production',
    sameSite: 'lax',
  });
}

module.exports = { setSession, getSession, clearSession };
