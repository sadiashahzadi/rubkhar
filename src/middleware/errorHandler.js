'use strict';

/**
 * Central error handler. Logs server-side; never leaks error details to client.
 * Must be registered LAST (after all routes) in app.js.
 */
// eslint-disable-next-line no-unused-vars
function errorHandler(err, req, res, next) {
  console.error('[ERROR]', err);

  const status = err.status || err.statusCode || 500;

  // Never send stack traces or internal messages to the client
  res.status(status);

  if (req.accepts('html')) {
    res.send('<h1>Something went wrong.</h1><p>Please try again later.</p>');
  } else {
    res.json({ error: 'Internal server error' });
  }
}

module.exports = errorHandler;
