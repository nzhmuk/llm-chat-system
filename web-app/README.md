# Web App (Laravel)

The front end for the LLM chat system — a Laravel application that serves the
**MU-TH-UR/6000** chat terminal and proxies requests to the
[LLM service](../llm-service/README.md).

It never talks to Ollama directly: the browser calls this app, and this app
calls the FastAPI LLM service over HTTP.

---

## Architecture

```
Browser → Web App (Laravel) → LLM Service (FastAPI) → Ollama
```

- Authenticated users access a chat terminal that **streams** responses
  token-by-token.
- The app forwards messages to the LLM service's `/chat/stream` endpoint and
  pipes the stream straight back to the browser.

---

## Requirements

- PHP 8.4+
- Composer
- Node.js + npm (for building front-end assets with Vite)
- A database (MySQL by default)
- A reachable LLM service (see [llm-service](../llm-service/README.md))

---

## Layout

```
web-app/
├── app/            # the Laravel project
└── deployment/
    └── deploy.sh   # install deps, build assets, run migrations, cache config
```

The Laravel application lives in `web-app/app/`. Run all `php artisan`,
`composer`, and `npm` commands from there.

---

## Configuration

Copy `app/.env.example` to `app/.env` and set the values below.

| Variable                | Example                          | Description                                              |
| ----------------------- | -------------------------------- | -------------------------------------------------------- |
| `APP_URL`               | `https://chat.example.com`       | Public URL; must match the real (HTTPS) domain           |
| `APP_ENV` / `APP_DEBUG` | `production` / `false`           | Use production settings on a hosted server               |
| `LLM_API_URL`           | `http://10.0.0.5:8000`           | Base URL of the FastAPI LLM service (no trailing slash)  |
| `LLM_TIMEOUT`           | `300`                            | Max seconds to wait for the LLM (≥ the reverse-proxy timeout) |
| `LLM_MODEL`             | `qwen2.5:3b`                     | Optional model override; empty = use the LLM service default (model must be pulled on the Ollama host) |
| `LLM_API_KEY`           | _(from installer)_               | API key for the LLM service (sent as a Bearer token); must match the service's `LLM_API_KEY` |
| `SESSION_SECURE_COOKIE` | `true`                           | Required when served over HTTPS                          |
| `SESSION_DOMAIN`        | `chat.example.com`               | Pins the session cookie to the domain                    |
| `DB_*`                  | —                                | Standard Laravel database settings                       |

Behind a reverse proxy that terminates TLS (e.g. Plesk), the app trusts proxy
headers (configured in `app/bootstrap/app.php`) so it detects the original
HTTPS scheme — important for correct cookies and CSRF handling.

---

## Local setup

```bash
cd web-app/app

cp .env.example .env
composer install
npm install

php artisan key:generate
php artisan migrate

# Build assets (or `npm run dev` for hot reload during development)
npm run build

php artisan serve
```

Make sure `LLM_API_URL` points at a running LLM service.

---

## Deployment

`deployment/deploy.sh` is location-independent (it resolves the Laravel app
relative to its own path), so it can be run from the repository root — e.g. as
a Plesk Git "Additional deployment action":

```bash
bash web-app/deployment/deploy.sh
```

It runs `composer install`, `npm ci && npm run build`, database migrations, and
rebuilds the config/route/view caches.

When serving over HTTPS behind a reverse proxy, set `proxy_buffering off` and a
generous `proxy_read_timeout` (≥ `LLM_TIMEOUT`) so streamed responses pass
through immediately and long generations aren't cut off.
