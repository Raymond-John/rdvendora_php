# Public store URLs (path mode)

Stores use clean URLs on the main domain:

`https://rdvendora.com/{store-slug}`

Example: `https://rdvendora.com/john-fashion`

## Hostinger

1. Deploy / pull the latest code to `public_html` (including `.htaccess`).
2. Set in `.env`:

```
APP_ENV=production
APP_URL=https://rdvendora.com
STORE_URL_MODE=path
```

3. No wildcard DNS or wildcard SSL is required for path URLs.
4. Ensure Apache `mod_rewrite` is enabled (default on Hostinger).

## Optional modes

| `STORE_URL_MODE` | Example |
|------------------|---------|
| `path` (default) | `https://rdvendora.com/my-shop` |
| `subdomain` | `https://my-shop.rdvendora.com` (needs DNS `*` + wildcard SSL) |
| `query` | `https://rdvendora.com/storefront.php?store=3` |

## Legacy links

`storefront.php?store={id}` redirects (301) to `/{store-slug}` when the store is active.

## Database

Uses existing `stores.store_slug` (UNIQUE). No required migration.
