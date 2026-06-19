# Dante Alighieri Society of Virginia — Website

A complete static website and WordPress theme for the Dante Alighieri Society of Virginia, a non-profit organization promoting Italian culture in the Commonwealth of Virginia.

## Quick Start (Static Site)

```bash
# Install dependencies (none required for static site)
npm install

# Start the development server
npm start
```

Then open **http://localhost:3000/** in your browser.

## Project Structure

```
dante-website/
├── index.html              # Homepage — Calendar of Events 2026
├── about.html              # About the Society — mission, history
├── board.html              # Board of Directors — officers & members
├── programs.html           # 2026–2027 Season Programs
├── membership.html         # Membership — dues, benefits, payment
├── italian-culture.html    # Italian Culture — arts, literature
├── contact.html            # Contact form & mailing address
├── newsletter.html         # Newsletter subscription & archive
├── 404.html                # 404 error page
├── css/
│   └── style.css           # Main stylesheet
├── images/                 # Event photos and backgrounds
├── server.js               # Node.js HTTP development server
├── package.json            # Node.js package config
├── README.md               # This file
└── wp-theme/               # WordPress theme (see below)
```

## Colors & Typography

| Element          | Value   |
|------------------|---------|
| Primary Green    | #1B4332 |
| Dark Green       | #0D2B1F |
| Gold Accent      | #C8963E |
| Cream Background | #FAF3E0 |
| Headings         | Playfair Display (serif) |
| Body Text        | Lato (sans-serif) |

## WordPress Theme

The `wp-theme/` directory contains a complete WordPress theme ready for deployment:

```
wp-theme/
├── style.css                # Theme header (WordPress required)
├── functions.php            # Theme setup, enqueues, widgets, customizer
├── header.php               # Site header with navigation + hero
├── footer.php               # Site footer with widget areas
├── index.php                # Post archive template
├── page.php                 # Default page template
├── single.php               # Single post template
├── 404.php                  # 404 template
├── page-events.php          # Events page template (custom)
├── page-membership.php      # Membership page template (custom)
├── screenshot.svg           # Theme screenshot (1200x900)
└── js/
    └── navigation.js        # Mobile menu toggle
```

### How to Install the Theme in WordPress

1. Copy the `wp-theme/` folder to your WordPress installation at `wp-content/themes/dante-society/`
2. Log into WordPress admin → **Appearance → Themes**
3. Activate the **Dante Society of Virginia** theme
4. Go to **Appearance → Menus** and assign your pages to the Primary Menu
5. Edit the homepage via **Pages → Home** and assign the "Events Page" template
6. Create a "Membership" page and assign the "Membership Page" template

### Required WordPress Plugins

| Plugin | Purpose | Recommended |
|--------|---------|-------------|
| **Contact Form 7** | Contact form | Required |
| **Classic Editor** | WYSIWYG for non-technical board members | Strongly Recommended |
| **MailPoet** or **MailChimp for WP** | Newsletter generation & email reminders | Recommended |
| **All-in-One WP Migration** | Backup & migration | Recommended |
| **Akismet** or **Anti-Spam** | Spam protection | Recommended |
| **Wordfence** or **iThemes Security** | Website security | Recommended |
| **Page Builder by SiteOrigin** or **Elementor** | Visual page builder (Elementor is more user-friendly for older users) | Recommended |
| **Tidio** or **ChatBot** | AI chatbot plugin for site updates | Nice to have |
| **UpdraftPlus** | Automated backups | Recommended |
| **Really Simple SSL** | SSL certificate handling | Required (if using HTTPS) |

### Recommended Configuration

1. **Create Pages** in WordPress matching the static HTML files:
   - Home (assign template: Events Page)
   - About
   - Board
   - Programs
   - Membership (assign template: Membership Page)
   - Italian Culture
   - Contact
   - Newsletter

2. **Set up Contact Form 7** for the Contact page

3. **Set up MailPoet** for the newsletter subscription form

4. **Set up Permalinks**: Settings → Permalinks → "Post name"

### Customizer Options

The theme includes WordPress Customizer options accessible at **Appearance → Customize**:
- **Hero Section**: Edit the homepage hero title and tagline
- **Custom Logo**: Upload the Dante Society logo
- **Widgets**: Three footer widget columns

## Membership Information

Per client requirements (Gail Morrison memo):

- **Individual Membership**: $35/year
- **Family Membership**: $60/year
- **Fiscal Year**: September through September
- **Dues Due**: September, requested by Viva l'Italia event on September 20
- **Payment Methods**: Mail check to P.O. Box 131, Forest, VA 24551, or use bank "bill pay"
- **No Zelle**: Do not connect actual payment systems — shell/instructions only

## AI Assistant

An AI chatbot plugin (e.g., Tidio, ChatBot, or similar) can be installed to allow board members to ask questions about updating the site. The plugin can be configured as an "AI editor" that understands the site content and provides guidance on WordPress updates.

## Design Reference

The design is inspired by Italian elegance — think Renaissance Florence meets modern Virginia. Color palette draws from:
- The deep green of Italian cypress trees
- The warm gold of Venetian mosaics
- The cream of aged parchment and Tuscan limestone

## License

This project is licensed under GPL v2 or later — the same license as WordPress itself.
