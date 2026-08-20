# Learn Piano Keys Article Admin

## What was added

The site remains a coded HTML/JavaScript web app. The new PHP admin publisher lets an administrator manage articles without manually editing HTML.

- Admin: `/admin/`
- Public Blog page: `/blog/`
- Article URLs: `/blog/<slug>/`
- Article sitemap: `/sitemap-articles.xml`
- Featured images: `/uploads/articles/`
- Private article records: `lpk_private/articles.json` outside `public_html`

The article admin uses the existing `admin_password` from `lpk_private/secrets.php`, so no second password is required.

## First test on Hostinger

1. Upload the upgraded site over the current `public_html` files.
2. Keep your existing `lpk_private/secrets.php` outside `public_html`.
3. Visit `https://learnpianokeys.com/admin/` and sign in with the same admin password used for the existing leads admin.
4. A sample **Piano Scales for Beginners** article appears as a draft automatically.
5. Open it, edit anything you want, then choose **Save & preview**.
6. When ready, choose **Publish article**.
7. Confirm the article appears at `/blog/piano-scales-for-beginners-guide/` and on `/blog/`.
8. Confirm `/sitemap-articles.xml` contains the published URL.

## Publishing behaviour

Drafts are stored privately and are not crawlable. Preview pages are admin-only and return `noindex, nofollow`.

Publishing generates a static `index.html` page inside the article folder. It also rebuilds the public Blog page and article sitemap. Unpublishing removes the public HTML file and removes the URL from the article sitemap. Deleting removes the record and any public copy.

## Editor formatting

The editor accepts simple Markdown and converts it to safe HTML. Raw HTML is escaped.

- `## Heading` creates an H2
- `### Heading` creates an H3
- `**bold**` creates bold text
- `- item` creates a bullet list
- `1. item` creates a numbered list
- `[Scale finder](/piano-scale-finder.html)` creates an internal link
- Markdown tables are supported

## SEO generated automatically

Published articles include a canonical URL, meta description, Open Graph tags, Twitter card tags, Article structured data, breadcrumb structured data, publish/update dates, related-guide links and a category-specific call to an interactive Learn Piano Keys tool.

`robots.txt` now references both the main sitemap and `sitemap-articles.xml`, and `/admin/` is blocked from crawling.

## Security

The admin uses the existing password stored outside `public_html`, secure session cookies where HTTPS is enabled, session ID regeneration on login, CSRF protection for writes, server-side IP-based failed-login throttling, noindex headers, safe Markdown rendering, and MIME/type/size validation for image uploads.

The admin folder and article uploads also include `.htaccess` protections.
