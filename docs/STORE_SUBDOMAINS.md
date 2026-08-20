# Store subdomains (Hostinger)

## DNS

In Hostinger DNS for `rdvendora.com`, add a wildcard A record:

| Type | Name | Points to |
|------|------|-----------|
| A | `*` | Same IP as `rdvendora.com` (your Hostinger web IP) |

Keep existing records for `@` (apex) and `www`.

## SSL

In Hostinger SSL / Cloudflare / Let's Encrypt, enable a **wildcard** certificate for:

- `rdvendora.com`
- `*.rdvendora.com`

PHP cannot issue SSL. Without the wildcard cert, browsers will warn on `https://slug.rdvendora.com`.

## Application

Set in `.env` on production:

```
APP_ENV=production
APP_URL=https://rdvendora.com
STORE_SUBDOMAINS=true
STORE_BASE_DOMAIN=rdvendora.com
```

Local XAMPP (no wildcards needed):

```
APP_ENV=local
STORE_SUBDOMAINS=false
```

With `STORE_SUBDOMAINS=false`, store links stay as `storefront.php?store={id}`.

## Behaviour

| URL | Result |
|-----|--------|
| `https://pamtech.rdvendora.com` | Storefront for slug `pamtech` |
| `https://does-not-exist.rdvendora.com` | Store Not Found page |
| `https://rdvendora.com` | Main site |
| `https://rdvendora.com/storefront.php?store=3` | 301 → `https://{slug}.rdvendora.com` (when subdomains enabled) |
| `https://admin.rdvendora.com` | Not treated as a store (reserved) |
