# Book Keeper

## Docker Compose and Traefik

Taqsimot uses the shared MrStairs `traefik-net` network for all HTTP(S) ingress. Create the shared network once and start local Traefik from the `mrstairs-backend` project before starting this development stack:

```bash
docker network create traefik-net  # once; omit if it already exists
cd ../mrstairs-backend
docker compose up -d traefik
```

Development stack:

```bash
make up-dev
```

Production stack (on the server):

```bash
make up-prod
```

Endpoints:

- Dev app: `https://taqsimot.fannjourney.test`
- Dev Vite: `http://localhost:5173`
- Production app: `https://taqsimot.fannjourney.com`

The development app is available to Traefik as `taqsimot-app-dev:80`. The production app is available as `taqsimot-app-prod:80`; it does not publish host ports. Both stacks retain a private network for application and queue traffic. Production runtime configuration is loaded from a private `.env.production` file; create it before deployment:

```bash
cp .env.production.example .env.production
```

Set `APP_KEY` and the required integration secrets in that private file. The shared production Traefik resolver currently uses Let’s Encrypt staging; move that resolver to the production ACME endpoint before a public cutover.

For local domain resolution, add this line to your hosts file:

```text
127.0.0.1 taqsimot.fannjourney.test
```

For trusted local HTTPS, install `mkcert`, trust its local CA once, and generate the certificate that the shared Traefik configuration reads:

```bash
mkcert -install
make cert-local
```

The generated certificate and key are deliberately ignored by the MrStairs repository. Restart or reload local Traefik after changing them.

Useful commands:

- `make down-dev`
- `make build-dev`
- `make rebuild-dev`
- `make down-prod`
- `make build-prod`
- `make rebuild-prod`
- `make logs-dev`
- `make logs-prod`
- `make cert-local`

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
