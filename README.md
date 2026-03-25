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
- `make down-prod`
- `make logs-dev`
- `make logs-prod`
- `make cert-prod`
