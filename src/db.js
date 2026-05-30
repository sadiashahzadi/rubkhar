'use strict';

// Lazy-load config so importing this module doesn't throw if env isn't set in tests
let _pool = null;

function isLocalDatabase(urlString) {
  try {
    const dbUrl = new URL(urlString);
    return dbUrl.hostname === '127.0.0.1' || dbUrl.hostname === 'localhost';
  } catch {
    return false;
  }
}

function getPool() {
  if (_pool) return _pool;

  const mysql = require('mysql2/promise');
  const { DATABASE_URL } = require('./config');

  const poolOptions = {
    uri: DATABASE_URL,
    connectionLimit: 3,
  };

  if (!isLocalDatabase(DATABASE_URL)) {
    poolOptions.ssl = {
      minVersion: 'TLSv1.2',
      rejectUnauthorized: true,
    };
  }

  _pool = mysql.createPool(poolOptions);

  return _pool;
}

// Export a Proxy so callers can do `pool.query(...)` directly;
// the pool is created on first use, not at require-time.
const pool = new Proxy(
  {},
  {
    get(_target, prop) {
      return (...args) => getPool()[prop](...args);
    },
  }
);

module.exports = pool;
