# Lineforge Website

Standalone PHP product site for Lineforge. It has public product pages, local account auth, SEO/security foundations, and a protected `/app` workspace for the sports intelligence dashboard.

## Run Locally

```powershell
cd "C:\Users\gabri\Desktop\Aegis\Tools\04 Runtime & Engine Tools\Aegis Sports Betting AI\Website"
php -S 127.0.0.1:8088 router.php
```

Then open:

```text
http://127.0.0.1:8088/
```

Useful local routes:

- `/`: product home page
- `/pricing`: placeholder pricing and tier positioning
- `/methodology`: SEO-facing explanation of how Lineforge builds a pick
- `/register`: create a local account
- `/login`: sign in
- `/account`: account summary and logout link
- `/app`: protected sports betting research workspace
- `/responsible-use`, `/terms`, `/privacy`: product safety and legal pages

## Configuration

Copy `.env.example` to `.env` and adjust values as needed.

- `AEGIS_SPORTS_TIER`: `free`, `pro`, or `elite`
- `AEGIS_SPORTS_NEW_USER_TIER`: default tier assigned to newly registered local accounts
- `AEGIS_SPORTS_TRACKED_GAMES`: event cap for the live board
- `AEGIS_SPORTS_MODELS`: prediction model depth used by the UI state builder
- `AEGIS_SPORTS_REFRESH_SECONDS`: frontend refresh cadence
- `AEGIS_ODDS_API_KEY`: optional The Odds API key for live sportsbook matching

SEO and security:

- `AEGIS_SITE_URL`: canonical site origin used for canonical links, `robots.txt`, and `sitemap.xml`
- Public pages include descriptions, canonical URLs, Open Graph metadata, and JSON-LD on the home page
- Auth, account, app, and API surfaces are marked noindex
- Runtime responses include CSP, frame, MIME, referrer, permissions, and HTTPS-only HSTS headers when served over HTTPS

Provider setup:

- The protected app Settings view includes a provider setup form for The Odds API, injury feed URL, lineup feed URL, news feed URL, player props feed URL, bankroll unit, and max stake units.
- The protected JSON endpoint is `/api/provider-settings.php`.
- Local provider settings are stored in `storage/provider-settings.json`, ignored by git, and injected into the sports engine at runtime.
- This is suitable for local product development. Use a production secret manager or database-backed encrypted storage before hosting real user secrets.

Local auth stores development accounts in `storage/users.json` with PHP password hashes. Keep this file private and replace the JSON store with a database-backed auth service before a real hosted launch.

The site is informational only. It shows analytics, provider links, and paper slip tools, but it does not place bets or execute exchange orders.
