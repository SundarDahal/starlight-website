<?php
/**
 * api/config.js.php — Serves public runtime configuration as JavaScript
 * ───────────────────────────────────────────────────────────────────────
 * Returns a JS snippet that sets window.RECAPTCHA_SITE_KEY so the
 * front-end can load the reCAPTCHA v3 script dynamically without
 * embedding the key directly in HTML (which would require a rebuild to
 * rotate it).
 *
 * The value is read from the RECAPTCHA_SITE_KEY environment variable
 * set in .env / docker-compose.yml.
 *
 * This file is intentionally public — the reCAPTCHA *site* key is
 * always visible to the browser. Only the *secret* key must stay
 * server-side (in contact.php / environment variable).
 */

// No caching — key may be rotated without a container rebuild.
header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

// Try environment first, then fallback (public key safe to hardcode)
$key = getenv('RECAPTCHA_SITE_KEY') ?: '6Lci5v4sAAAAADv7Xwes35yNhMlixckr1Ho5FAm4';

// json_encode() safely escapes the key for JS string context.
echo 'window.RECAPTCHA_SITE_KEY = ' . json_encode($key) . ';' . PHP_EOL;
