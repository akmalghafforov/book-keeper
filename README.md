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
```

The two stacks use different Compose project names, networks, containers, and runtime storage. The production container also keeps its SQLite database in its own Docker volume-backed storage directory, so it does not clash with the development environment.

For local domain resolution, add this line to your hosts file:

```text
127.0.0.1 taqsimot.test
```

The production proxy generates a self-signed certificate for `taqsimot.test` automatically on first start. Your browser will show a warning until you trust that certificate locally.

Useful commands:

- `make down-dev`
- `make down-prod`
- `make logs-dev`
- `make logs-prod`
