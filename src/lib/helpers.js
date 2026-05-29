'use strict';

/**
 * Format a number matching PHP number_format() default behaviour:
 * thousands separator = comma, no decimal places.
 * e.g. 4500 → "4,500"
 */
function money(n) {
  return Number(n).toLocaleString('en-US', { maximumFractionDigits: 0, minimumFractionDigits: 0 });
}

/**
 * HTML-escape a string (mirrors PHP htmlspecialchars with ENT_QUOTES).
 */
function escape(s) {
  if (s == null) return '';
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/**
 * Convert a string to a URL-friendly slug.
 * e.g. "Women's Clothing" → "womens-clothing"
 */
function slugify(s) {
  return String(s)
    .toLowerCase()
    .replace(/['']/g, '')        // remove apostrophes
    .replace(/[^a-z0-9]+/g, '-') // replace non-alphanumeric with hyphen
    .replace(/^-+|-+$/g, '');    // trim leading/trailing hyphens
}

module.exports = { money, escape, slugify };
