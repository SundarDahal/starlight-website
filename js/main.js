// =====================================================
// Starlight Express — Main Site Script
// =====================================================

const navbar = document.getElementById('navbar');
if (navbar) {
  window.addEventListener('scroll', () => {
    navbar.classList.toggle('scrolled', window.scrollY > 50);
  });
}

const hamburger = document.getElementById('hamburger');
const navLinks = document.getElementById('navLinks');
if (hamburger && navLinks) {
  hamburger.addEventListener('click', () => {
    navLinks.classList.toggle('open');
    hamburger.classList.toggle('open');
  });
  navLinks.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => navLinks.classList.remove('open'));
  });
}

function initReveal() {
  const revealEls = document.querySelectorAll('.reveal:not(.visible)');
  if (!revealEls.length) return;
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });
  revealEls.forEach(el => observer.observe(el));
}

function handleTrack(e) {
  const input = document.getElementById('trackInput');
  if (input && input.value.trim()) {
    e.preventDefault();
    const awb = encodeURIComponent(input.value.trim());
    window.location.href = `track.html?awb=${awb}`;
  }
}

// =====================================================
// CARGOMIS TRACKING
// =====================================================
// In WordPress: STARLIGHT_CFG.trackProxy points at admin-ajax.php
// In static deployments: falls back to local track-proxy.php
const CARGOMIS_PROXY = (typeof STARLIGHT_CFG !== 'undefined' && STARLIGHT_CFG.trackProxy)
  ? STARLIGHT_CFG.trackProxy
  : 'https://staging.starlight.com.np/track-proxy.php';
const CARGOMIS_BASE = 'https://cargomis.net/tracking';

function parseAWB(input) {
  const cleaned = input.trim().toUpperCase();
  // Match IATA format: 3 digits + 3 letters + hyphen + 4 digits + space + 4 digits
  // e.g., 131KTM-3571 9725
  const m = cleaned.match(/^(\d{3})([A-Z]{3})-(\d{4})\s(\d{4})$/);
  if (m) {
    return { raw: `${m[1]}${m[2]}-${m[3]} ${m[4]}`, prefix: m[1] };
  }
  return null;
}

async function trackShipment(awbInput, resultEl, frameEl) {
  if (!awbInput) return;
  const parsed = parseAWB(awbInput);
  if (!parsed) {
    showTrackingError(resultEl, 'Invalid AWB format. Use: 131KTM-3571 9725');
    return;
  }
  showTrackingLoader(resultEl);
  const trackId = parsed.raw;
  const tz = 'Asia/Katmandu';

  try {
    const sep = CARGOMIS_PROXY.includes('?') ? '&' : '?';
    const proxyUrl = `${CARGOMIS_PROXY}${sep}awb=${encodeURIComponent(trackId)}&timezone=${encodeURIComponent(tz)}`;
    console.log('Fetching:', proxyUrl);
    const res = await fetch(proxyUrl, { 
      headers: { 
        'Accept': 'application/json',
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
      },
      credentials: 'same-origin'
    });
    console.log('Response status:', res.status, res.ok);
    
    if (res.ok) {
      const fullResponse = await res.json();
      console.log('Full response:', fullResponse);
      const trackingData = fullResponse?.data || fullResponse;
      console.log('Extracted data:', trackingData);
      console.log('Current status:', trackingData?.current_status);
      resultEl.innerHTML = ''; // Clear old HTML
      renderTrackingResult(resultEl, trackingData, trackId);
      return;
    }
  } catch (err) {
    console.error('Tracking error:', err);
  }

  // If API failed, show error with link to CargoMIS
  const cargomisUrl = `https://cargomis.net/#/tracking/${encodeURIComponent(trackId)}`;
  showTrackingError(resultEl, `Unable to fetch tracking. <a href="${cargomisUrl}" target="_blank" rel="noopener">View on CargoMIS →</a>`);
}

function showTrackingLoader(el) {
  if (!el) return;
  el.innerHTML = `<div class="track-loading"><div class="track-spinner"></div><p>Looking up your shipment…</p></div>`;
}
function showTrackingError(el, msg) {
  if (!el) return;
  el.innerHTML = `<div class="track-error"><strong>⚠</strong> ${msg}</div>`;
}

function renderTrackingResult(el, data, awb) {
  if (!el) return;
  
  // Handle CargoMIS API response format
  const currentStatus = data?.current_status || {};
  const statusName = (currentStatus?.name || data?.last_status || 'In Transit').replace(/_/g, ' ');
  const statusDesc = currentStatus?.description || '';
  
  const origin = data?.origin || data?.from || '—';
  const destination = data?.destination || data?.to || '—';
  const pieces = data?.pieces || data?.no_of_pieces || '—';
  const weight = data?.weight || data?.gross_weight || '—';
  const history = data?.history || data?.events || data?.movements || [];

  let eventsHtml = '';
  if (history.length) {
    eventsHtml = `
      <div class="track-timeline">
        <h4>Shipment History</h4>
        <ol>
          ${history.map(ev => {
            // Handle nested status object
            const statusObj = ev.status || {};
            const eventName = statusObj.name || ev.status || ev.event || ev.code || ev.remarks || 'Update';
            const eventTime = ev.changed_at_local || ev.changed_at_utc || ev.date || ev.timestamp || ev.time || '';
            const eventDesc = statusObj.description || ev.description || ev.remarks || '';
            return `
            <li>
              <strong>${eventName}</strong>
              <span class="track-event-meta">${ev.location || ev.station || ''} ${eventTime}</span>
              ${eventDesc && eventDesc !== eventName ? `<p>${eventDesc}</p>` : ''}
            </li>`;
          }).join('')}
        </ol>
      </div>`;
  }

  el.innerHTML = `
    <div class="track-result">
      <div class="track-result-header">
        <div><span class="track-label">AWB</span><strong>${awb}</strong></div>
        <div class="track-status">${statusName}</div>
      </div>
      ${statusDesc ? `<div class="track-status-desc">${statusDesc}</div>` : ''}
      ${eventsHtml}
      <div class="track-footer"><a href="https://cargomis.net/#/tracking/${encodeURIComponent(awb)}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">View on CargoMIS ↗</a></div>
    </div>`;
}

function initTrackPage() {
  const pageInput = document.getElementById('pageTrackInput');
  if (!pageInput) return;
  const params = new URLSearchParams(window.location.search);
  const awb = params.get('awb');
  if (awb) {
    pageInput.value = decodeURIComponent(awb);
    runTrackingFromPage();
  }
}

function trackPageSubmit(e) {
  e.preventDefault();
  runTrackingFromPage();
}

function runTrackingFromPage() {
  const input = document.getElementById('pageTrackInput');
  const resultEl = document.getElementById('trackResult');
  const frameEl = document.getElementById('cargomisFrame');
  if (!input || !input.value.trim()) return;
  trackShipment(input.value.trim(), resultEl, frameEl);
  const url = new URL(window.location.href);
  url.searchParams.set('awb', input.value.trim());
  window.history.pushState({}, '', url.toString());
}

/**
 * showPopover(type, message)
 * ──────────────────────────
 * Shows a temporary popover notification (success or error).
 * Auto-dismisses after 5 seconds or on click.
 */
function showPopover(type, message) {
  const popover = document.createElement('div');
  popover.className = `popover popover-${type}`;
  popover.setAttribute('role', 'alert');
  popover.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    max-width: 400px;
    padding: 16px 20px;
    border-radius: 8px;
    font-size: 0.95rem;
    line-height: 1.5;
    z-index: 9999;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    animation: slideIn 0.3s ease-out;
    cursor: pointer;
    word-break: break-word;
  `;
  
  if (type === 'success') {
    popover.style.backgroundColor = '#dcfce7';
    popover.style.color = '#166534';
    popover.style.border = '1px solid #bbf7d0';
    popover.innerHTML = '✔ ' + message;
  } else if (type === 'error') {
    popover.style.backgroundColor = '#fee2e2';
    popover.style.color = '#991b1b';
    popover.style.border = '1px solid #fecaca';
    popover.innerHTML = '✖ ' + message;
  }
  
  document.body.appendChild(popover);
  
  const dismiss = () => {
    popover.style.animation = 'slideOut 0.3s ease-in forwards';
    setTimeout(() => popover.remove(), 300);
  };
  
  popover.addEventListener('click', dismiss);
  const timeout = setTimeout(dismiss, 5000);
  
  popover.addEventListener('mouseenter', () => clearTimeout(timeout));
}

// Add CSS animations for popovers
if (!document.getElementById('popover-styles')) {
  const style = document.createElement('style');
  style.id = 'popover-styles';
  style.textContent = `
    @keyframes slideIn {
      from {
        transform: translateX(400px);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }
    @keyframes slideOut {
      from {
        transform: translateX(0);
        opacity: 1;
      }
      to {
        transform: translateX(400px);
        opacity: 0;
      }
    }
  `;
  document.head.appendChild(style);
}

/**
 * initContactForm()
 * ──────────────────────────────────────────────────────────────
 * Handles the contact form submission flow:
 *
 *  1. Intercept the native submit event
 *  2. Run basic client-side validation (required fields, email fmt)
 *  3. Disable the entire form during submission
 *  4. Execute reCAPTCHA v3 to get a fresh token
 *  5. POST JSON to contact.php via fetch()
 *  6. Show success or error feedback as popovers
 *  7. Clear form on success, re-enable on finally
 *
 * The reCAPTCHA SITE_KEY below must match the key loaded in the
 * <script src="…?render=…"> tag in contact.html.
 */
function initContactForm() {
  // Key is injected by api/config.js.php (reads RECAPTCHA_SITE_KEY env var).
  // Falls back to empty string — server will handle the missing token gracefully.
  const RECAPTCHA_SITE_KEY = window.RECAPTCHA_SITE_KEY || '6Lci5v4sAAAAADv7Xwes35yNhMlixckr1Ho5FAm4';

  const form = document.getElementById('contactForm');
  if (!form) return;

  const btn = document.getElementById('submitBtn');

  // ── Helper: disable/enable all form inputs ────────────────────
  function disableForm() {
    form.querySelectorAll('input, textarea, select, button').forEach(el => {
      el.disabled = true;
    });
  }

  function enableForm() {
    form.querySelectorAll('input, textarea, select, button').forEach(el => {
      el.disabled = false;
    });
  }

  // ── Helper: simple client-side email format check ────────────
  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
  }

  // ── Submit handler ───────────────────────────────────────────
  form.addEventListener('submit', async function (e) {
    e.preventDefault();

    // -- 1. Client-side validation (fast feedback before network round-trip)
    const name    = form.querySelector('[name="name"]')?.value.trim()    || '';
    const email   = form.querySelector('[name="email"]')?.value.trim()   || '';
    const enquiry = form.querySelector('[name="enquiry"]')?.value.trim() || '';
    const message = form.querySelector('[name="message"]')?.value.trim() || '';

    if (!name) {
      return showPopover('error', 'Please enter your full name.');
    }
    if (!isValidEmail(email)) {
      return showPopover('error', 'Please enter a valid email address.');
    }
    if (!enquiry) {
      return showPopover('error', 'Please select an enquiry type.');
    }
    if (!message) {
      return showPopover('error', 'Please enter a message.');
    }

    // -- 2. Disable form and show loading state
    disableForm();
    btn.textContent = 'Sending…';

    try {
      // -- 3. Get reCAPTCHA v3 token
      let recaptchaToken = '';
      try {
        // grecaptcha is loaded async — wait for it to be ready
        recaptchaToken = await new Promise((resolve, reject) => {
          if (typeof grecaptcha === 'undefined') {
            // reCAPTCHA script may not have loaded (ad-blocker, slow network)
            // Allow submission to proceed; the server will handle the missing token gracefully
            resolve('');
            return;
          }
          grecaptcha.ready(() => {
            grecaptcha
              .execute(RECAPTCHA_SITE_KEY, { action: 'contact_submit' })
              .then(resolve)
              .catch(reject);
          });
        });
      } catch (err) {
        console.warn('reCAPTCHA execute failed:', err);
        // Don't block submission — server will reject if token is truly required
      }

      // -- 4. Build payload and POST to contact.php
      const payload = {
        name:            name,
        company:         form.querySelector('[name="company"]')?.value.trim() || '',
        email:           email,
        phone:           form.querySelector('[name="phone"]')?.value.trim()   || '',
        enquiry:         enquiry,
        message:         message,
        recaptcha_token: recaptchaToken,
      };

      const response = await fetch('contact.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload),
      });

      // Parse JSON response from PHP
      let data;
      try {
        data = await response.json();
      } catch {
        throw new Error('Server returned an unexpected response.');
      }

      if (data.success) {
        // -- 5a. Success: show popover, clear form
        showPopover('success', data.message || 'Your message has been sent. Our team will respond within one business day.');
        form.reset();
      } else {
        // -- 5b. Server-side validation / reCAPTCHA failure
        showPopover('error', data.message || 'Submission failed. Please try again.');
      }

    } catch (err) {
      // Network error or JSON parse failure
      console.error('Contact form error:', err);
      showPopover('error', 'Could not reach the server. Please check your connection or email us directly at ktmops@starlight.com.np');
    } finally {
      // Always re-enable the form and restore button
      enableForm();
      btn.textContent = 'Send Message';
    }
  });
}

function animateCounters() {
  const counters = document.querySelectorAll('.stat-num[data-target]');
  counters.forEach(el => {
    const target = parseInt(el.dataset.target);
    let count = 0;
    const step = target / 60;
    const interval = setInterval(() => {
      count = Math.min(count + step, target);
      el.textContent = Math.round(count) + (el.dataset.suffix || '');
      if (count >= target) clearInterval(interval);
    }, 20);
  });
}

// =====================================================
// COOKIE CONSENT — GDPR / EU ePrivacy compliant
// =====================================================
const COOKIE_KEY = 'starlight_cookie_consent_v1';

function getCookieConsent() {
  try { const s = localStorage.getItem(COOKIE_KEY); return s ? JSON.parse(s) : null; } catch (e) { return null; }
}

function setCookieConsent(prefs) {
  prefs.timestamp = new Date().toISOString();
  try { localStorage.setItem(COOKIE_KEY, JSON.stringify(prefs)); } catch (e) {}
  applyConsent(prefs);
  hideCookieBanner();
}

function applyConsent(prefs) {
  window.dispatchEvent(new CustomEvent('starlight:consent', { detail: prefs }));
  if (prefs.analytics && typeof window.gtag === 'function') {
    window.gtag('consent', 'update', { analytics_storage: 'granted', ad_storage: prefs.marketing ? 'granted' : 'denied' });
  }
}

function showCookieBanner() {
  const b = document.getElementById('cookieBanner');
  if (!b) return;
  b.classList.add('show');
  requestAnimationFrame(() => requestAnimationFrame(() => b.classList.add('visible')));
}

function hideCookieBanner() {
  const b = document.getElementById('cookieBanner');
  if (!b) return;
  b.classList.remove('visible');
  setTimeout(() => b.classList.remove('show'), 400);
}

function initCookieConsent() {
  const banner = document.getElementById('cookieBanner');
  if (!banner) return;
  const existing = getCookieConsent();
  if (!existing) {
    applyConsent({ necessary: true, analytics: false, marketing: false });
    setTimeout(showCookieBanner, 800);
  } else {
    applyConsent(existing);
  }

  const acceptAll = document.getElementById('cookieAcceptAll');
  const rejectAll = document.getElementById('cookieRejectAll');
  const customize = document.getElementById('cookieCustomize');
  const savePrefs = document.getElementById('cookieSavePrefs');
  const prefsPanel = document.getElementById('cookiePreferences');

  if (acceptAll) acceptAll.addEventListener('click', () => setCookieConsent({ necessary: true, analytics: true, marketing: true }));
  if (rejectAll) rejectAll.addEventListener('click', () => setCookieConsent({ necessary: true, analytics: false, marketing: false }));
  if (customize) customize.addEventListener('click', () => {
    prefsPanel.classList.toggle('show');
    customize.textContent = prefsPanel.classList.contains('show') ? 'Hide options' : 'Customize';
  });

  banner.querySelectorAll('.cookie-switch:not(.disabled)').forEach(sw => {
    sw.addEventListener('click', () => sw.classList.toggle('on'));
  });

  if (savePrefs) savePrefs.addEventListener('click', () => {
    setCookieConsent({
      necessary: true,
      analytics: banner.querySelector('[data-cookie="analytics"]')?.classList.contains('on') || false,
      marketing: banner.querySelector('[data-cookie="marketing"]')?.classList.contains('on') || false
    });
  });

  document.querySelectorAll('[data-reopen-cookies]').forEach(el => {
    el.addEventListener('click', (e) => { e.preventDefault(); showCookieBanner(); });
  });
}

// ===== INIT =====
document.addEventListener('DOMContentLoaded', () => {
  initTrackPage();
  initContactForm();
  initCookieConsent();
  const statsEl = document.querySelector('.hero-stats');
  if (statsEl) {
    const obs = new IntersectionObserver(([e]) => {
      if (e.isIntersecting) { animateCounters(); obs.disconnect(); }
    });
    obs.observe(statsEl);
  }
  document.querySelectorAll('.gssa-card, .service-card, .airline-card, .why-point, .airline-full-card, .office-card').forEach(el => el.classList.add('reveal'));
  initReveal();
});
