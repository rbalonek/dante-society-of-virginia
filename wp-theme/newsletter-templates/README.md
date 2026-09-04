# Newsletter templates

Finished HTML emails. Every `.html` file in this folder shows up in
**Newsletter → Compose → Newsletter type: "Paste finished HTML" → Start from a
saved template**, ready to load, edit and send.

These are theme files, so they travel through Git and the normal theme deploy.
**Adding a design is "commit a file," not "log in and paste."**

## Adding one

1. Drop the `.html` file in this folder. Use a kebab-case filename — it becomes
   the slug (`magic-flute-invitation.html`).
2. Add a label near the top, inside `<head>`, within the first 2KB of the file:

   ```html
   <!-- Template Name: Magic Flute Invitation (Opera on the James) -->
   ```

   Without it the dropdown falls back to the document `<title>`, then the filename.
3. Commit, push, deploy. It appears in the dropdown — no database involved, so it
   behaves identically on Local and live.

## Writing the HTML

- **Send-as-is.** A `custom_html` newsletter is delivered byte-for-byte. The Dante
  header, footer and Unsubscribe button are *not* wrapped around it — whatever the
  file contains is the whole email.
- **Include `{{unsubscribe_url}}`** somewhere. It's replaced per recipient with a
  working one-click unsubscribe link. Leaving it out means sending bulk mail with
  no unsubscribe, which is a CAN-SPAM problem.
- **Images must be hosted `https://` URLs**, not local paths or `data:` URIs.
  Upload to the WordPress Media Library first and paste those URLs in.
- **Email HTML is not web HTML** — tables for layout, inline styles, no flexbox or
  grid, and `<style>` only for media queries. Outlook needs the `mso` conditional
  block and `mso-line-height-rule:exactly`.
- Test with **Send test** before sending to the list. The live preview iframe is
  more forgiving than a real inbox.
