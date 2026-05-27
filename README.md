# Starlight Express Website

**Nepal's #1 Air Cargo GSSA** — official corporate website for [Starlight Express Pvt. Ltd.](https://www.starlight.com.np), a proud partner of [Kales Airline Services](https://kales.com/).

---

## ✨ Key Features

- **7 fully designed pages** — Home, About, Offices & Network, Services, Track Shipment, Contact, Privacy
- **Live airline portfolio** — 4 Nepal principals + 40+ Kales network airlines with logos from `pics.avs.io`
- **Interactive global map** — 69 Kales office pins with click popups across 36 countries
- **Contact form** — PHP backend with reCAPTCHA v3, XSS sanitization, IP rate-limiting
- **CargoMIS AWB tracking** — embedded shipment lookup
- **SEO + JSON-LD** — Organization, LocalBusiness, FAQPage schemas; AI-crawler friendly
- **Zero JS dependencies** — Pure HTML / CSS / vanilla JS, no build step
- **Fully responsive** — Mobile, tablet, desktop

---

## 📁 Project Structure

```
starlight/
├── index.html              Home — hero, airlines, services, FAQ
├── about.html              Company story & Kales partnership
├── offices.html            Interactive global office map (69 pins)
├── services.html           Full GSSA service catalogue
├── track.html              CargoMIS AWB tracking
├── contact.html            Contact form + Google Maps
├── privacy.html            Privacy & cookie policy
│
├── contact.php             Form handler (reCAPTCHA v3, mail, rate-limit)
├── track-proxy.php         CargoMIS proxy (cURL)
│
├── api/
│   └── config.js.php       Serves RECAPTCHA_SITE_KEY to the browser
│
├── css/style.css           All styles (single file)
├── js/main.js              Navigation, tracking, contact form, cookie consent
├── images/                 Brand assets, airline logos, map tiles
├── company-data.json       Kales network data (69 offices, 36 countries)
│
├── sitemap.xml             SEO sitemap
├── robots.txt              Crawler instructions (incl. AI bots)
│
├── Dockerfile              PHP 8.2 + Apache + msmtp
├── docker-compose.yml      Web + Mailhog services
├── docker/
│   └── entrypoint.sh       Generates msmtprc from env vars at startup
│
├── .env.example            ← copy to .env and fill in keys
├── .dockerignore
└── README.md               This file
```

---

## 🚀 Quick Start (Docker — recommended)

### 1. Prerequisites
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (or Docker Engine + Compose plugin)

### 2. Configure environment

```bash
cp .env.example .env
```

Open `.env` and fill in:

| Variable | Where to get it |
|---|---|
| `RECAPTCHA_SITE_KEY` | [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin) — choose **v3**, add `localhost` + your production domain |
| `RECAPTCHA_SECRET_KEY` | Same page — the secret key |
| `SMTP_*` | Leave as-is for dev (Mailhog); change for production |

### 3. Start

```bash
docker-compose up --build
```

| Service | URL |
|---|---|
| Website | http://localhost:9000 |
| Mailhog (caught emails) | http://localhost:8025 |

> **Live reload:** The project directory is volume-mounted into the container. HTML/CSS/JS/PHP changes appear immediately — no rebuild required.

### 4. Stop

```bash
docker-compose down
```

---

## 📧 Email in Development

All email sent by the contact form is **caught by Mailhog** — nothing reaches real inboxes. Open [http://localhost:8025](http://localhost:8025) to see captured messages.

To test the full flow:
1. Fill in the contact form at http://localhost:9000/contact.html
2. Submit → check Mailhog UI for the formatted email

---

## 🔑 Before Going Live (Production Checklist)

- [ ] Register your domain in [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin) and update `.env` keys
- [ ] Set `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS` in `.env` to your real SMTP relay
- [ ] Verify `MAIL_TO` in `contact.php` (`ktmops@starlight.com.np`) is the correct inbox
- [ ] Confirm the Google Maps embed in `contact.html` points to the right office location
- [ ] Update `sitemap.xml` with the live domain and current dates
- [ ] Enable HTTPS on your server — the `.htaccess` HTTPS redirect is already in place (it skips localhost)

---

## 🎨 Design System

```css
/* Brand colours */
--navy:    #0a1628    /* headings, navbar */
--red:     #c8102e    /* CTA, principal airline cards */
--gold:    #d4a017    /* accents, highlights */
--white:   #ffffff
--off-white: #f8f9fc  /* section backgrounds */

/* Typography */
--font-display: 'Bebas Neue'   /* headings */
--font-body:    'Outfit'       /* body text, UI */
```

---

## 🌐 Airline Data

`company-data.json` contains the full scraped Kales network:
- **69 offices** across **36 countries**
- **49 unique airlines** (after deduplication)
- Nepal principals: Druk Air (KB), Japan Airlines (JL), Maldivian (Q2), Pegasus Airlines (PC)

Airline logos are loaded from `https://pics.avs.io/200/50/{IATA}.png`. Cards degrade gracefully (name only) when a logo isn't available.

---

## 📜 License

© 2025 Starlight Express Pvt. Ltd. All rights reserved.

Third-party services used: Google Fonts, Google Maps, Google reCAPTCHA, CargoMIS, Kales Airline Services, pics.avs.io.
