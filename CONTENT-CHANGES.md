# Content / database changes — June 2026

Git is the source of truth for the **theme** only. The changes below live in the
**WordPress database** (and one in the Media Library), so they are **not** carried
by the `wp-theme/` deploy pipeline. Re-apply them in **WP Admin** on any
environment that needs to match (production, a fresh Local, etc.).

The matching theme/code changes (logo header, checkout template, membership
template, name fixes in code) ARE in `wp-theme/` and deploy normally.

---

## 1. Name change — "Dante Society" (legal)

The Society must be referred to as **"Dante Society" / "Dante Society of Virginia"** —
drop **"Alighieri"** from the *organization name* everywhere.

**Keep references to the poet** Dante Alighieri (e.g. "About Dante Alighieri",
"Dante Alighieri (1265–1321)", "Dante Alighieri's influence on visual art"). The
safe rule is a literal replace of `Dante Alighieri Society` → `Dante Society`,
which never touches poet references.

Applied in the DB to page content: **About**, **Italian Culture**, **Membership**.

> ⚠️ **Repo gaps — not yet fixed:** `wordpress-import.xml` (the one-time WXR seed)
> still contains the old "Dante Alighieri Society" name (7 occurrences), as do the
> legacy static `*.html` mockups (`index.html`, `about.html`, … — marked "not used
> by WordPress" in `CLAUDE.md`). A **fresh** WXR import would reintroduce the old
> name. Apply the same `Dante Alighieri Society` → `Dante Society` replace to those
> files before any fresh seed, or ask and it can be done.

## 2. Site title

**Settings → General → Site Title** = `Dante Society of Virginia`
(was the placeholder "dante"). This drives the browser tab, footer, and the
header logo wordmark.

## 3. Logo

Source image: `Desktop/Dante/949cd6e3-…png` (cream-ring "DANTE SOCIETY OF
VIRGINIA" emblem; also archived alongside this repo if you move it in).

- Upload it under **Appearance → Customize → Site Identity → Logo** (this sets the
  `custom_logo` theme_mod and adds the image to the Media Library).
- `header.php` then shows the emblem (64px) **+ the "Dante Society of Virginia"
  wordmark and a "Since 1998" subtitle** to its right, linked home.
- Until a logo is uploaded, the header safely falls back to the "D" monogram —
  so the theme deploys fine with no logo set.

## 4. Membership page content

> **Coupling:** `wp-theme/page-membership.php` no longer hardcodes the
> Dues/Benefits/How-to-Pay sections (they used to render twice). Those now live in
> the **page content**. After the template deploys, the Membership page content on
> that environment MUST contain these sections or the page will look bare.

The current Membership page content (Gutenberg block markup) is in
**Appendix A** below — paste it into the Membership page via the editor's
*Code editor* mode, or rebuild equivalent blocks. It adds:
- Styled dues tiers (Individual $35 / Family $60)
- Member benefits emphasizing the **social/community** angle the owner requested
- A **"Pay Dues Online"** button linking to `/membership-checkout/`

## 5. Membership Checkout page (demo)

The checkout **template** (`wp-theme/page-checkout.php`, "Membership Checkout
(Demo)") deploys with the theme, but the **page** that uses it is DB content and
must be created per environment:

1. **Pages → Add New**, title **"Membership Checkout"**, permalink
   `membership-checkout`.
2. In **Page Attributes → Template**, choose **"Membership Checkout (Demo)"**.
3. Publish. The page body can be empty — the template renders the checkout UI.

This is a **visual mockup only** — no payment is processed (clear "Demo / test
mode" banner). Replace with a real Stripe Checkout integration when ready.

---

## Appendix A — Membership page content (block markup)

```html
<!-- wp:heading --><h2>Become a Member</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Funding for the Dante Society is provided by its membership, which is open to all who have a love of all things Italian. Your membership makes our programs possible — from lectures and musical performances to Carnevale celebrations and film series.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>The Dante Society's fiscal year runs <strong>September through September</strong>. Annual dues are payable in September and are requested by our signature <strong>Viva l'Italia</strong> event on <strong>September 20th</strong>.</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2>Membership Dues</h2><!-- /wp:heading -->
<!-- wp:html -->
<div class="membership-tiers">
    <div class="tier-card"><div class="tier-name">Individual</div><div class="tier-price">$35</div><div class="tier-desc">Annual membership for one person</div></div>
    <div class="tier-card"><div class="tier-name">Family</div><div class="tier-price">$60</div><div class="tier-desc">Annual membership for the whole family</div></div>
</div>
<!-- /wp:html -->
<!-- wp:heading --><h2>Member Benefits</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>More than anything, membership is about the simple pleasure of spending time with others who love Italy. Members enjoy:</p><!-- /wp:paragraph -->
<!-- wp:list -->
<ul>
<!-- wp:list-item --><li>The good company of fellow members who share a passion for Italian language, culture, food, art, and travel</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Conversation and friendship over wonderful meals — practice your Italian, <em>tutti i livelli sono benvenuti</em> (all levels welcome)</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Invitations to all monthly programs, lectures, and events</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Film &amp; dinner series at local Italian restaurants</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Our festive Carnevale celebration</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Advance notice of special events and collaborations with Opera on the James, the Maier Museum, and other cultural organizations</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Travel opportunities with fellow members</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Monthly email updates and program reminders</li><!-- /wp:list-item -->
</ul>
<!-- /wp:list -->
<!-- wp:heading --><h2>How to Pay Dues</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p><strong>We do not accept Zelle.</strong> Please use one of the following methods:</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3>Option 1: Pay Online</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Pay your annual dues quickly and securely by debit or credit card.</p><!-- /wp:paragraph -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/membership-checkout/">Pay Dues Online</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
<!-- wp:heading {"level":3} --><h3>Option 2: Mail a Check</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Make checks payable to <strong>Dante Society of Virginia</strong> and mail to:<br>P.O. Box 131, Forest, VA 24551</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3>Option 3: Bank Bill Pay</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Use your bank's online bill pay feature to send a payment to:<br>Dante Society of Virginia, P.O. Box 131, Forest, VA 24551</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2>Renew for 2026</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>We welcome your 2026 annual dues! It is a new year of exciting programs celebrating all things Italian — lectures, musical events, Carnevale celebration, local restaurant group dinners, wine tastings, and much more!</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Member funding makes it all possible. Renew your membership today: <strong>$35 for individuals · $60 for families.</strong> Pay online above, mail a check to Dante Society of VA, P.O. Box 131, Forest, VA 24551, or use your bank's "bill pay" function.</p><!-- /wp:paragraph -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="http://www.dantesocietyofva.org/uploads/5/0/4/7/50473557/dantemembershipapplication_2026_3_.docx">Download Membership Application</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
```
