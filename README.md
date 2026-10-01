# Datenight Surprise

A date-night idea shuffler for PHP 8+. The ideas come from [midnightduet.com](https://midnightduet.com/couple-ideas/) through its custom endpoint `/wp-json/midnightduet/v1/datenight-ideas`; edit them in WordPress.

They are fetched server-side and cached in `data/ideas-cache.json` for 12 hours, so `data/` must be writable by PHP. If WordPress is unreachable, the last cached copy keeps being served and a new fetch is tried every 5 minutes.

To pull changes from WordPress right away, open the site with `?refresh` (e.g. `https://datenight-surprise.midnightduet.com/?refresh`). It refetches whenever the cache is older than 30 seconds.

Pushes to `main` deploy via GitHub Actions (`.github/workflows/deploy.yml`).
