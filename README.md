# Book Keeper

## Docker Compose

The project now has separate Compose files for development and production so both can run on the same machine at the same time.

Development stack:

```bash
make up-dev
```

Production stack:

```bash
make up-prod
```

Run both:

```bash
make up-dev
make up-prod
```

Default ports:

- Dev app: `http://localhost:8080`
- Dev Vite: `http://localhost:5173`
- Prod app: `https://taqsimot.test`

You can override them in `.env`:

```dotenv
DEV_APP_PORT=8080
DEV_VITE_PORT=5173
PROD_DOMAIN=taqsimot.test
PROD_HTTP_PORT=80
PROD_HTTPS_PORT=443
PROD_SSL_CERT_FILE=
PROD_SSL_KEY_FILE=
```

The two stacks use different Compose project names, networks, containers, and runtime storage. The production container also keeps its SQLite database in its own Docker volume-backed storage directory, so it does not clash with the development environment.

For local domain resolution, add this line to your hosts file:

```text
127.0.0.1 taqsimot.test
```

For trusted local HTTPS, install `mkcert`, trust its local CA once, and generate a certificate for your domain:

```bash
mkcert -install
make cert-prod
make up-prod
```

By default, the proxy will use `docker/certs/taqsimot.test.pem` and `docker/certs/taqsimot.test-key.pem` if they exist. You can also point `PROD_SSL_CERT_FILE` and `PROD_SSL_KEY_FILE` at alternate certificate files inside the mounted cert directory.

If no trusted certificate is available, the production proxy falls back to a self-signed certificate automatically, and the browser will continue to show a warning.

Useful commands:

- `make down-dev`
- `make build-dev`
- `make rebuild-dev`
- `make down-prod`
- `make build-prod`
- `make rebuild-prod`
- `make logs-dev`
- `make logs-prod`
- `make cert-prod`

`make build-dev` only ensures the generic Sail runtime image is available. If `sail-8.5/app` already exists locally, it will not force a rebuild. Use `make rebuild-dev` when you intentionally want to rebuild that runtime from `vendor/laravel/sail/runtimes/8.5/Dockerfile`, which requires Docker Hub access to `ubuntu:24.04`.

## WhatsApp Webhook

The app exposes an official Meta webhook endpoint at `GET|POST /webhooks/whatsapp`.

Add these values to `.env`:

```dotenv
WHATSAPP_APP_SECRET=your_meta_app_secret
WHATSAPP_WEBHOOK_VERIFY_TOKEN=choose_a_random_verify_token
WHATSAPP_LOG_CHANNEL=stack
```

Setup in Meta:

1. Set the callback URL to `https://your-domain/webhooks/whatsapp`.
2. Set the verify token to the same value as `WHATSAPP_WEBHOOK_VERIFY_TOKEN`.
3. Subscribe your app to the WhatsApp Business Account.

The webhook verifies Meta's challenge request, validates the `X-Hub-Signature-256` signature with your app secret, and stores each accepted payload in the `whatsapp_webhook_events` table.
