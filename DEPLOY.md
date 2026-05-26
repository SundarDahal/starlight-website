# Starlight Express — Deployment Guide

Complete instructions for deploying the new Starlight Express GSSA website to **www.starlight.com.np**.

This site is built as **static HTML/CSS/JS** — no database, no server-side code required. It will run on virtually any hosting provider.

---

## 📁 What's in the Package

```
starlight/
├── index.html              # Homepage
├── about.html              # About Us
├── airlines.html           # Airlines We Represent
├── services.html           # GSSA Services
├── track.html              # Shipment Tracking (CargoMIS integration)
├── contact.html            # Contact Us
├── sitemap.xml             # SEO sitemap
├── robots.txt              # SEO crawler instructions
├── favicon.svg             # Site favicon
├── css/
│   └── style.css           # All site styles
├── js/
│   └── main.js             # Site JavaScript (navigation, tracking, forms)
├── images/
│   └── logo.svg            # Brand logo
├── DEPLOY.md               # This file
└── README.md               # Project overview
```

---

## 🚀 Deployment Options

### Option 1 — cPanel / Standard Web Hosting (Recommended)

This is the most common setup. The existing site at starlight.com.np runs on WordPress, so you likely have cPanel access from your current host.

**Steps:**

1. **Backup the existing WordPress site first**
   - In cPanel → **Backup Wizard** → Download a Full Backup. Keep this safe in case you need to roll back.

2. **Login to cPanel** for your hosting account.

3. **Open File Manager** and navigate to `public_html/` (this is the web root for starlight.com.np).

4. **Remove or archive the old WordPress files**
   - Select all files inside `public_html/` → Right-click → **Compress** → save as `wp-old-backup-2025.zip`. Then download the zip and delete the original files.
   - ⚠️ Don't delete the zip until you've confirmed the new site works.

5. **Upload the new site**
   - In File Manager → click **Upload**.
   - Zip the contents of the `starlight/` folder on your computer (the files **inside**, not the folder itself).
   - Upload the zip to `public_html/` and extract it.
   - After extraction, the file structure should be: `public_html/index.html`, `public_html/css/style.css`, etc.

6. **Set file permissions** (if needed)
   - Folders: `755`
   - Files: `644`

7. **Test the site**
   - Visit `https://www.starlight.com.np` — the new homepage should load.
   - Click through all navigation links to verify they work.
   - Test the tracking page with a sample AWB.

8. **SEO post-deploy**
   - Visit `https://www.starlight.com.np/sitemap.xml` — confirm it loads.
   - Visit `https://www.starlight.com.np/robots.txt` — confirm it loads.
   - Submit sitemap to Google: see SEO section below.

---

### Option 2 — Netlify (Easiest, Free, Modern)

Best if you want CI/CD, free SSL, global CDN, and zero server maintenance.

**Steps:**

1. Sign up at [netlify.com](https://www.netlify.com).
2. Click **Add new site** → **Deploy manually**.
3. Drag the entire `starlight/` folder onto the Netlify upload area.
4. Netlify gives you a temporary URL like `starlight-xyz.netlify.app` — verify the site works.
5. **Connect your custom domain:**
   - In site settings → **Domain management** → **Add custom domain** → enter `www.starlight.com.np`.
   - Netlify will give you DNS records (usually a CNAME).
   - Login to your domain registrar (where you bought starlight.com.np) → update DNS:
     - `www` → CNAME → `starlight-xyz.netlify.app`
     - `@` (root) → A record → Netlify's load-balancer IP (Netlify shows you the value)
6. Wait 5–30 minutes for DNS to propagate.
7. Netlify auto-provisions a free SSL certificate. Site goes live on HTTPS.

---

### Option 3 — Vercel (Same as Netlify, alternative)

1. Sign up at [vercel.com](https://vercel.com).
2. Click **Add New** → **Project** → import or upload the folder.
3. Vercel deploys automatically.
4. Add custom domain `www.starlight.com.np` in Project Settings → Domains.
5. Update DNS records as Vercel instructs.

---

### Option 4 — GitHub Pages (Free, requires GitHub)

1. Create a GitHub repository: `starlight-website`.
2. Push the contents of `starlight/` into the repo:
   ```bash
   cd starlight
   git init
   git add .
   git commit -m "Initial deploy"
   git branch -M main
   git remote add origin https://github.com/YOUR_USER/starlight-website.git
   git push -u origin main
   ```
3. In the repo → **Settings** → **Pages** → Source: `main` branch, `/` folder → Save.
4. Add custom domain `www.starlight.com.np` in Pages settings.
5. In your domain DNS, add a CNAME record: `www` → `YOUR_USER.github.io`.

---

## 🔗 CargoMIS Tracking Integration

The tracking page (`track.html`) uses an iframe to embed the CargoMIS tracker.

**Current behavior:** When a user enters an AWB and clicks "Track", the iframe loads:
```
https://www.cargomis.net/track?awb=XXX-XXXXXXXX
```

**Important — you may need to adjust this URL.** CargoMIS provides different integration options depending on your account. Common patterns:

| Integration Type | URL Pattern |
|---|---|
| Public tracking page | `https://www.cargomis.net/track?awb=XXX` |
| Branded subdomain | `https://starlight.cargomis.net/track?awb=XXX` |
| Tenant-specific URL | `https://www.cargomis.net/{tenant_id}/track?awb=XXX` |

**To update:** open `js/main.js` and edit the `loadCargoMIS` function:

```javascript
function loadCargoMIS(awb) {
  const frame = document.getElementById('cargomisFrame');
  if (!frame) return;
  // ⬇ Update this URL to match your CargoMIS account
  const url = `https://www.cargomis.net/track?awb=${encodeURIComponent(awb)}`;
  frame.src = url;
  ...
}
```

**Contact CargoMIS support** ([info@cargomis.net](mailto:info@cargomis.net)) to confirm:
1. The exact tracking URL for your Starlight Express account.
2. Whether iframe embedding is permitted (some configurations block iframes via `X-Frame-Options` — if so, the page falls back to opening CargoMIS in a new tab).
3. Whether they offer a JavaScript SDK or API for deeper integration.

---

## 📧 Contact Form Setup

The contact form currently shows a success message but **does not actually send emails**. To make it functional, pick one of:

### Option A — Formspree (Easiest, free tier available)

1. Sign up at [formspree.io](https://formspree.io).
2. Create a new form, get your form ID (e.g. `xyzabc`).
3. Open `contact.html`, find:
   ```html
   <form id="contactForm" autocomplete="on">
   ```
   Change to:
   ```html
   <form id="contactForm" action="https://formspree.io/f/xyzabc" method="POST">
   ```
4. Open `js/main.js`, find the `initContactForm` function and **remove** the `e.preventDefault()` line so the form submits normally. Or keep AJAX submission — Formspree supports both.

### Option B — Web3Forms (Free, no signup needed for basic)

1. Visit [web3forms.com](https://web3forms.com) → Get your access key.
2. In `contact.html`, change the form action:
   ```html
   <form id="contactForm" action="https://api.web3forms.com/submit" method="POST">
     <input type="hidden" name="access_key" value="YOUR_KEY_HERE">
     ...
   ```

### Option C — Your own PHP script (if on cPanel)

Create `contact-handler.php` in `public_html/`:

```php
<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $name = strip_tags($_POST['name']);
  $email = strip_tags($_POST['email']);
  $message = strip_tags($_POST['message']);
  $to = "nepal@starlightexp.com";
  $subject = "New Contact from Starlight Website";
  $body = "Name: $name\nEmail: $email\nMessage: $message";
  mail($to, $subject, $body, "From: $email");
  header("Location: contact.html?success=1");
}
?>
```

Then in `contact.html`:
```html
<form id="contactForm" action="contact-handler.php" method="POST">
```

---

## 🔍 SEO Setup (Critical Post-Deploy)

The site is already optimised with:
- ✅ Semantic HTML5 structure
- ✅ Open Graph & Twitter Card meta tags
- ✅ JSON-LD structured data (Organization + FAQPage + LocalBusiness)
- ✅ AI-crawler-friendly content (definitions, FAQ blocks, natural Q&A patterns)
- ✅ Mobile-responsive
- ✅ Fast loading (no jQuery, no heavy frameworks)
- ✅ `robots.txt` allowing major search engines AND AI crawlers (Googlebot, GPTBot, ClaudeBot, PerplexityBot)
- ✅ `sitemap.xml` for crawlers

### Submit to Google Search Console

1. Go to [search.google.com/search-console](https://search.google.com/search-console).
2. Add your property — choose **URL prefix** → enter `https://www.starlight.com.np`.
3. Verify ownership (the easiest method is to upload a small HTML verification file to `public_html/`).
4. Once verified → **Sitemaps** → submit `https://www.starlight.com.np/sitemap.xml`.
5. **Request indexing** for each main page via the URL Inspection tool.

### Submit to Bing Webmaster Tools

1. Go to [bing.com/webmasters](https://www.bing.com/webmasters).
2. Add `https://www.starlight.com.np` → verify → submit sitemap.

### AI Search Engine Visibility (ChatGPT, Claude, Perplexity, etc.)

Modern AI assistants crawl and index websites just like search engines. To maximise discovery:

- The site already has FAQ schema (JSON-LD) on the homepage — this is what AI models pick up when answering questions like *"Who is the best air cargo GSSA in Nepal?"*
- `robots.txt` already explicitly allows `GPTBot`, `ClaudeBot`, `anthropic-ai`, `PerplexityBot`, `Google-Extended`.
- The content uses natural-language Q&A patterns ("**A GSSA is a company that…**") which AI models extract more reliably than marketing copy.
- Repeating key phrases — *"Nepal's #1 air cargo GSSA"*, *"GSSA Nepal"*, *"Kales Airline Services partner"* — across multiple pages reinforces the brand association for AI ranking.

No special action needed beyond going live and being patient (typically 2–6 weeks for AI assistants to start citing the site).

### Update Google Business Profile

If Starlight Express has a Google Business listing, update it to reflect the new positioning:
- Category: **Cargo Service** / **Logistics Service**
- Description: Use the homepage hero copy.
- Add the website link.
- Upload new photos if available.

---

## 🎨 Adding Real Images (Recommended)

The site currently uses emoji icons (✈ 🛫 📦) for a clean, modern look. To add real imagery:

1. **Hero background:** Add an aerial/cargo photo to `images/hero.jpg`. In `css/style.css`, in the `.hero` rule, add:
   ```css
   background: linear-gradient(rgba(10,22,40,0.85), rgba(10,22,40,0.85)), url('../images/hero.jpg');
   background-size: cover;
   background-position: center;
   ```

2. **Airline logos:** Replace the 🛩 emoji in `airlines.html` and `index.html` with actual logo images:
   ```html
   <img src="images/airlines/turkish-cargo.png" alt="Turkish Cargo" />
   ```
   ⚠️ Always get permission from the airline before using their logo. Most GSSA contracts allow this.

3. **Team photos:** Add a "Team" or "Leadership" section to `about.html` with photos of key staff.

4. **Office photos:** Add office, warehouse, or KTM airport photos to the About and Services pages.

**Image best practices:**
- Format: WebP (best) or JPEG (fallback).
- Compress: use [tinypng.com](https://tinypng.com) or [squoosh.app](https://squoosh.app).
- Add `loading="lazy"` to images below the fold.
- Always include descriptive `alt` text — important for SEO and accessibility.

---

## 🛡️ Security & SSL

- Make sure your hosting has an **SSL certificate** installed. Most modern hosts (cPanel, Netlify, Vercel) provide free Let's Encrypt SSL automatically.
- Force HTTPS — in cPanel, look for **"Force HTTPS Redirect"** in the Domains section, or add this to a `.htaccess` file in `public_html/`:
  ```
  RewriteEngine On
  RewriteCond %{HTTPS} off
  RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
  ```

---

## ✅ Pre-Launch Checklist

- [ ] All pages load without errors (test every navigation link)
- [ ] Site looks correct on mobile, tablet, desktop
- [ ] Tracking page CargoMIS URL is correct for your account
- [ ] Contact form submits successfully and you receive the test email
- [ ] Google Maps embed on contact page shows the correct location
- [ ] Phone numbers and email addresses are correct
- [ ] Airlines list reflects your current GSSA portfolio (edit `airlines.html` and `index.html`)
- [ ] SSL certificate active (URL shows `https://` with padlock)
- [ ] `robots.txt` and `sitemap.xml` accessible
- [ ] Google Search Console verified and sitemap submitted
- [ ] All "lorem ipsum" or placeholder text replaced with real content
- [ ] Old WordPress site backed up safely before going live

---

## 🛠️ How to Edit Content Later

Everything is plain HTML — open any `.html` file in a code editor (VS Code, Sublime, Notepad++) and edit the text directly.

**Common edits:**

- **Phone/email:** Search/replace `+977-1-4155451` and `nepal@starlightexp.com` across all files.
- **Add an airline:** Open `airlines.html` and `index.html`, copy an existing `<div class="airline-card">` block, change the airline name and details.
- **Update services:** Open `services.html`, edit the `<div class="service-card">` blocks.
- **Change colors:** Open `css/style.css`, the top `:root` block has all brand colors. Change `--gold` and `--navy` to update site-wide.

After editing, re-upload changed files via cPanel File Manager, FTP, or `git push` (if using Netlify/Vercel/GitHub Pages, deployment is automatic on commit).

---

## 📞 Need Help?

If you run into issues during deployment, the most likely problems are:

| Problem | Solution |
|---|---|
| Pages show 404 | Check files are in `public_html/` (not in a subfolder) |
| CSS not loading | Confirm `css/style.css` path is correct, check file permissions (644) |
| Tracking iframe blank | CargoMIS may block iframes — contact them for the correct embed URL |
| Form not sending | Set up Formspree/Web3Forms/PHP handler (see Contact Form section) |
| Site not on HTTPS | Enable SSL in cPanel or wait 24h for auto-provisioning |
| DNS not resolving | DNS changes can take up to 48h to propagate globally |

---

**Built with care for Starlight Express Pvt. Ltd. — Nepal's #1 Air Cargo GSSA**
