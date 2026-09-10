# Mobile REST API

The application exposes a JSON API under `/api/v1`. This document describes the endpoints currently implemented in `routes/api.php`.

## Conventions

Every request must include:

```http
Accept: application/json
```

Protected routes also require an access token:

```http
Authorization: Bearer <access_token>
```

Dates use `YYYY-MM-DD`. Timestamps use ISO 8601. Numeric money and quantity values in resource responses are strings, so mobile clients should not use binary floating-point values for accounting.

Single resources are wrapped in `data`:

```json
{
  "data": {
    "type": "distribution",
    "id": "123",
    "attributes": {}
  }
}
```

List responses include pagination metadata:

```json
{
  "data": [],
  "meta": {
    "pagination": {
      "page": 1,
      "per_page": 20,
      "total": 0,
      "last_page": 1
    }
  }
}
```

Validation failures return `422` and use an `errors` object keyed by field. Authentication failures return `401`; permission denials return `403`.

## Authentication and sessions

| Method | Route | Description |
| --- | --- | --- |
| POST | `/auth/login` | Authenticate a device. |
| POST | `/auth/refresh` | Rotate a refresh token and issue a new access token. |
| POST | `/auth/logout` | Revoke the current token and its session. |
| POST | `/auth/logout-all` | Revoke every session belonging to the current user. Optional `password` confirms the action. |
| GET | `/auth/sessions` | List active sessions for the current user. |
| DELETE | `/auth/sessions/{session}` | Revoke one owned session. |
| GET | `/me` | Return the user, role codes, and effective permissions. |

`POST /auth/login` body:

```json
{
  "email": "user@example.com",
  "password": "password",
  "device_id": "stable-device-id",
  "device_name": "Amina’s iPhone",
  "platform": "ios",
  "app_version": "1.0.0"
}
```

Successful login and refresh responses contain `access_token`, `expires_in` (currently 900 seconds), `refresh_token`, `session_id`, `user`, and `permissions`. Refresh requests require `refresh_token` and `session_id`. Refresh tokens are single-use: a replay revokes the session.

## Authorization

Each protected business endpoint has a permission such as `clients.view` or `distributions.create`. Access is resolved from assigned roles, then optional endpoint overrides. A deny override takes precedence over an allow override. Inactive users and expired or revoked tokens cannot access the API.

## Supporting data

| Method | Route | Permission | Notes |
| --- | --- | --- | --- |
| GET | `/clients?search=&include=shops&page=&per_page=` | `clients.view` | Returns clients and calculated balance; `include=shops` loads shops. |
| POST | `/clients` | `clients.create` | Body: `name` required, `phone` optional. |
| GET | `/clients/{client}/shops` | `clients.view` | Shops belonging to the client. |
| POST | `/clients/{client}/shops` | `shops.create` | Body: `name` required, `address` optional. |
| GET | `/product-categories` | `catalogs.view` | Ordered by usage priority then name. |
| GET | `/products?product_category_id=` | `catalogs.view` | Category ID is required. Includes default unit/provider and buy price. |
| GET | `/suppliers?search=&page=&per_page=` | `suppliers.view` | Searches car number. |
| POST | `/suppliers` | `suppliers.create` | Body: `car_number` required, `car_color` optional. |
| GET | `/providers?search=&page=&per_page=` | `providers.view` | Searches provider name. |

## Debt ledgers

| Method | Route | Permission |
| --- | --- | --- |
| GET | `/debt-ledgers` | `debt_ledgers.view` |
| POST | `/debt-ledgers` | `debt_ledgers.create` |
| GET | `/debt-ledgers/{debtLedger}` | `debt_ledgers.view` |
| PATCH | `/debt-ledgers/{debtLedger}` | `debt_ledgers.update` |
| DELETE | `/debt-ledgers/{debtLedger}` | `debt_ledgers.delete` |

List filters: `client_id`, `type`, `search`, `date_from`, `date_to`, `page`, and `per_page`.

Create and update body fields are `client_id`, `type` (`charge`, `payment`, or `credit_note`), `amount`, `transaction_date`, `payment_method`, `payment_purpose`, `payer_name`, `reference_id`, and `notes`. On creation, payments also require `currency` (`TJS`, `USD`, `EUR`, `UZS`, or `RUB`) and a positive `exchange_rate`. Foreign payments are converted to TJS and the conversion is appended to notes.

`payment_method` is required for payments. Payment-only fields are cleared for non-payment records; `payer_name` is retained only when `payment_purpose` is `on_behalf_of`.

Create requests require a unique `Idempotency-Key` header. PATCH and DELETE require the ETag returned from GET/POST/PATCH in `If-Match`; a stale ETag returns `412`. Rows with `origin: "distribution"` cannot be edited or deleted through this endpoint (`409`).

## Distributions

| Method | Route | Permission |
| --- | --- | --- |
| GET | `/distributions` | `distributions.view` |
| POST | `/distributions` | `distributions.create` |
| GET | `/distributions/{distribution}` | `distributions.view` |
| PATCH | `/distributions/{distribution}` | `distributions.update` |
| DELETE | `/distributions/{distribution}` | `distributions.delete` |

List filters: `client_id`, `product_id`, `supplier_id`, `quantity_unit`, `date_from`, `date_to`, `page`, and `per_page`.

Create/update body:

```json
{
  "supplier_id": 4,
  "client_id": 12,
  "shop_id": 8,
  "credit_client_id": null,
  "credit_client_price": null,
  "product_category_id": 2,
  "product_id": 17,
  "quantity_unit": "per_ton",
  "quantity": "3.500",
  "price": "1200.00",
  "provider_buy_price": "1000.00",
  "distribution_date": "2026-09-10",
  "provider_received_at": "2026-09-10T14:30:00+05:00"
}
```

The selected product must belong to `product_category_id`; a selected shop must belong to `client_id`. `quantity_unit` is `per_ton`, `per_bag`, or `per_piece`. Quantity and prices must be non-negative. `subtotal` is calculated by the server and cannot be supplied by clients.

Distribution creation needs `Idempotency-Key`. PATCH and DELETE need `If-Match` with the resource ETag. Distribution updates synchronize their generated debt and provider ledger rows; deletion soft-deletes those generated rows in the same transaction.

## Concurrency and idempotency

`POST /debt-ledgers` and `POST /distributions` save a successful response for 24 hours against the combination of user, route, and `Idempotency-Key`. Repeating the same request returns the saved response. Reusing the key with another request body returns `409`.

For mutable resources, first fetch the record and copy its response ETag:

```http
If-Match: "etag-from-response"
```

If another change has occurred, the API responds with `412 Precondition Failed` and the client should reload the resource before retrying.
