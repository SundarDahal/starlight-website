# Starlight Express Website

**Nepal's #1 Air Cargo GSSA** — official corporate website for [Starlight Express Pvt. Ltd.](https://www.starlight.com.np)

A modern, fast, SEO-optimised static website built for Starlight Express in its new positioning as a dedicated General Sales & Service Agent (GSSA) for international airlines in Nepal, in partnership with [Kales Airline Services](https://kales.com/).

---

## 🌟 Key Features

- **6 fully designed pages** — Home, About, Airlines We Represent, Services, Track Shipment, Contact
- **CargoMIS tracking integration** — embedded AWB tracking from cargomis.net
- **SEO + AI-friendly content** — JSON-LD structured data (Organization, FAQPage, LocalBusiness), strong keyword density, natural Q&A patterns
- **AI assistant ready** — `robots.txt` allows GPTBot, ClaudeBot, PerplexityBot and others; FAQ schema designed for AI extraction
- **Fully responsive** — Mobile, tablet, desktop
- **Zero dependencies** — Pure HTML/CSS/JS, no frameworks, no build step
- **Production-grade design** — Custom navy + gold brand palette, distinctive typography (Bebas Neue + Outfit)
- **Fast** — Loads in under 2 seconds on a 3G connection
- **Accessible** — Semantic HTML, ARIA labels, keyboard navigation

---

## 📁 Structure

```
starlight/
├── index.html              Home — Hero, GSSA explainer, airlines, services, FAQ
├── about.html              About — Company story, Kales partnership, timeline
├── airlines.html           Airlines portfolio (Turkish Cargo, Etihad, Cargolux, etc.)
├── services.html           Full GSSA service catalogue
├── track.html              CargoMIS AWB tracking
├── contact.html            Contact form + Google Maps
├── sitemap.xml             SEO sitemap
├── robots.txt              Crawler instructions (incl. AI bots)
├── favicon.svg             Site icon
├── css/style.css           All styles (single file)
├── js/main.js              Navigation, tracking, forms
├── images/                 Brand assets
├── DEPLOY.md               📖 Full deployment guide
└── README.md               This file
```

---

## 🚀 Quick Deploy

The simplest path to production:

1. **Drag and drop** the entire `starlight/` folder onto [Netlify](https://app.netlify.com/drop)
2. Add your custom domain `www.starlight.com.np` in site settings
3. Update DNS at your registrar
4. Done — site is live with free SSL

For traditional cPanel hosting and other options, see **[DEPLOY.md](./DEPLOY.md)**.

---

## 🔧 What Needs Configuration Before Going Live

1. **CargoMIS URL** in `js/main.js` — confirm the exact tracking URL for your CargoMIS account
2. **Contact form backend** in `contact.html` — wire up Formspree, Web3Forms, or a PHP mail handler
3. **Real airline list** in `airlines.html` and `index.html` — replace example airlines with your actual GSSA portfolio
4. **Real images** in `images/` — replace logo SVG with brand assets; add airline logos and hero imagery
5. **Google Maps coordinates** in `contact.html` — verify the embedded map points to the correct office

Full instructions for each in [DEPLOY.md](./DEPLOY.md).

---

## 🎨 Design System

```css
/* Brand colors */
--navy:       #0a1628    /* primary dark, headings, navbar */
--gold:       #d4a017    /* accent, CTAs, highlights */
--gold-pale:  #fef3c7    /* badge backgrounds */
--off-white:  #f8f9fc    /* section backgrounds */

/* Typography */
--font-display: 'Bebas Neue'   /* headings, logo */
--font-body:    'Outfit'       /* body text, UI */
```

Inspired by the clean, airline-industry aesthetic of [kales.com](https://kales.com) but reinterpreted with a more distinctive navy + gold identity that signals premium air cargo service.

---

## 🔍 SEO Strategy

The site is built to rank for these primary keywords:

- "GSSA Nepal" / "air cargo GSSA Nepal"
- "air cargo Kathmandu" / "air freight Nepal"
- "General Sales Agent Nepal"
- "Starlight Express"
- "airline cargo agent Nepal"
- "cargo tracking Nepal"

And to be cited by AI assistants (ChatGPT, Claude, Perplexity) when users ask:

- *"Who is the best air cargo GSSA in Nepal?"*
- *"How do I ship air cargo from Kathmandu?"*
- *"What is a GSSA?"*
- *"Which airlines does Starlight Express represent?"*

This is achieved through:
- FAQ JSON-LD schema on the homepage
- Natural-language Q&A patterns (the way LLMs prefer to extract facts)
- Strong topical authority via consistent keyword usage across pages
- Explicit `robots.txt` permission for AI crawlers

---

## 📜 License

© 2025 Starlight Express Pvt. Ltd. All rights reserved.

This website's code is the property of Starlight Express. Third-party fonts (Google Fonts) and embedded services (Google Maps, CargoMIS) are subject to their own terms.

---

## 🤝 Credits

- **Built for:** Starlight Express Pvt. Ltd., Kathmandu, Nepal
- **Strategic partner referenced:** [Kales Airline Services](https://kales.com/)
- **Tracking provider:** [CargoMIS](https://www.cargomis.net)
- **Fonts:** Bebas Neue + Outfit (Google Fonts)
