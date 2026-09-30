# Datenight Surprise

A private date-night idea shuffler for PHP 8+. The ideas come from the `idea` post type on [midnightduet.com](https://midnightduet.com/couple-ideas/) via the WordPress REST API; edit them there.

They are fetched server-side and cached in `data/ideas-cache.json` for 10 minutes, so `data/` must be writable by PHP. If WordPress is unreachable, the last cached copy keeps being served. Add `?refresh` to the URL to fetch changes right away.

Pushes to `main` deploy via GitHub Actions (`.github/workflows/deploy.yml`).
