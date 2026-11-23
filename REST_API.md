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

## Provider ledgers

| Method | Route | Permission | Success |
| --- | --- | --- | --- |
| GET | `/provider-ledgers` | `provider_ledgers.view` | `200` with a paginated list |
| GET | `/provider-ledgers/{id}` | `provider_ledgers.view` | `200` with the entry and its `ETag` |
| POST | `/provider-ledgers` | `provider_ledgers.create` | `201` with the entry and its `ETag` |
| PATCH | `/provider-ledgers/{id}` | `provider_ledgers.update` | `200` with the updated entry and its `ETag` |
| DELETE | `/provider-ledgers/{id}` | `provider_ledgers.delete` | `204` with an empty body |

The list uses the admin ledger order, starting with the latest operation timestamp. It defaults to 20 entries per page and caps `per_page` at 100. A filtered list request can combine filters.

```http
GET /api/v1/provider-ledgers?provider_id=7&type=charge&search=cement&provider_date_from=2026-09-01&provider_date_to=2026-09-30&tonnage=3.500&vehicle_number=TJ-1234&page=1&per_page=20
```

`search` matches notes, vehicle number, or distribution ID. The received date bounds are inclusive. `tonnage` matches the stored quantity, `vehicle_number` matches part of the vehicle number, and `paid_amount` matches the stored TJS amount of a payment.

Entries have resource type `provider-ledger`. The attributes include `provider_id`, `product_id`, `distribution_id`, and corresponding references when available, plus `type`, `origin`, `amount`, `car_number`, `quantity`, `buy_price`, `payment_method`, `payment_purpose`, `payer_name`, `transaction_date`, `provider_received_at`, `notes`, and `running_balance`. IDs and decimal values are strings. Each running balance includes charges minus payments through that entry in the provider’s full active ledger, even when filters or pagination hide some of those entries.

```http
POST /api/v1/provider-ledgers
Accept: application/json
Content-Type: application/json
Authorization: Bearer <access_token>
Idempotency-Key: provider-payment-001

{
  "provider_id": "7",
  "type": "payment",
  "amount": "100.00",
  "currency": "USD",
  "exchange_rate": "9.50",
  "payment_method": "cash",
  "payment_purpose": "on_behalf_of",
  "payer_name": "Farid",
  "provider_received_at": "2026-09-10T14:30:00+05:00",
  "notes": "September payment"
}
```

POST and PATCH require an active `provider_id`, `type` (`charge` or `payment`), `amount` of at least `0.01`, and an ISO 8601 `provider_received_at` timestamp with an offset or `Z`. The server stores the timestamp to second precision and derives `transaction_date` in the application timezone. `car_number` applies to charges. Payments require `payment_method` (`cash`, `card`, `ds`, `eo`, or `alif`). Optional `payment_purpose` accepts `on_behalf_of`, `vehicle_and_labor`, `vehicle`, or `labor`. Supply `payer_name` for `on_behalf_of`.

POST payments require `currency` (`TJS`, `USD`, `EUR`, `UZS`, or `RUB`) and a positive `exchange_rate`. The server converts foreign payments to TJS and appends the calculation to notes. PATCH takes the complete editable form with the stored TJS amount. Include optional values you want to retain.

```http
PATCH /api/v1/provider-ledgers/123
Accept: application/json
Content-Type: application/json
Authorization: Bearer <access_token>
If-Match: "etag-from-response"

{
  "provider_id": "7",
  "type": "payment",
  "amount": "950.0000",
  "payment_method": "cash",
  "payment_purpose": "on_behalf_of",
  "payer_name": "Farid",
  "provider_received_at": "2026-09-10T14:30:00+05:00",
  "notes": "September payment | 100 USD × 9.50 = 950 TJS"
}
```

DELETE also requires `If-Match` and soft-deletes the entry. POST retries with the same key and payload replay the original `201` body and `ETag` for 24 hours.

| Status | When it applies |
| --- | --- |
| `401` | The access token is missing or invalid. |
| `403` | The user lacks endpoint access. |
| `404` | The entry is unknown or soft-deleted. |
| `409` | A POST key is reused with a different payload within 24 hours, or an entry with `origin: "distribution"` or a `distribution_id` is targeted for a direct edit or delete. Use `/api/v1/distributions/{id}` to change distribution-derived entries. |
| `412` | A manual PATCH or DELETE has a missing or stale `If-Match`. |
| `422` | Validation fails or `Idempotency-Key` is missing, blank, or longer than 255 bytes. |

Apply the provider-ledger permission migration before enabling client use. It copies existing role grants from the corresponding `debt_ledgers.*` permissions. Provider-ledger endpoint overrides are independent. On staging, verify an authorized list and detail read, create and replay a manual payment, then update and delete it with the returned ETags.

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

`POST /debt-ledgers`, `POST /provider-ledgers`, and `POST /distributions` save a successful response for 24 hours against the combination of user, route, and `Idempotency-Key`. Repeating the same request returns the saved response. Reusing the key with another request body returns `409`.

For mutable resources, first fetch the record and copy its response ETag:

```http
If-Match: "etag-from-response"
```

If another change has occurred, the API responds with `412 Precondition Failed` and the client should reload the resource before retrying.

## Transaction drafts

Use the draft queue for transactions derived from WhatsApp exports. Submission can record an incomplete suggestion. Only approval by an active, authorized web reviewer creates posted transactions.

| Method | Route | Permission |
| --- | --- | --- |
| POST | `/transaction-drafts` | `transaction_drafts.submit` |
| GET | `/transaction-drafts?kind=&status=&corrected=&page=&per_page=` | `transaction_drafts.view` |
| GET | `/transaction-drafts/{id}` | `transaction_drafts.view` |

Send `Content-Type: application/json` and `Idempotency-Key` on submission. The response is `201` for a new draft. The same request key replays the saved response for 24 hours. A fresh or expired key returns `200` and the existing draft for the same source and original suggestion. A different interpretation of the same source returns `409` with `existing_draft_id`.

The durable identity is `source.chat_key`, `source.message_key`, and `source.event_slot`. Use a stable keyed fingerprint for `message_key` and a distinct slot for each transaction in a multi-event message. Include `sent_at` with an offset, an opaque `export_id`, and a positive `line` or `position`. Optional `additional_locators` list related messages, each with `message_key`, `sent_at`, `export_id`, and a `line` or `position`. Export identifiers and positions can change across exports. Keep raw export text, filenames, and attachments in the source workspace.

```json
{
  "kind": "debt_ledger",
  "source": {
    "chat_key": "taqsimot",
    "message_key": "example-fingerprint",
    "sent_at": "2026-11-19T10:02:00+05:00",
    "export_id": "export-2026-11-22",
    "line": 261,
    "event_slot": "customer-receipt-1"
  },
  "fields": {
    "client_id": "42",
    "type": "payment",
    "amount": "100.00",
    "currency": "USD",
    "exchange_rate": "10.25",
    "payment_method": "card",
    "transaction_date": "2026-11-19"
  }
}
```

| Kind | Fields |
| --- | --- |
| `distribution` | `client_id`, `product_category_id`, `product_id`, `quantity_unit` (`per_ton`, `per_bag`, `per_piece`), `quantity`, `price`, `distribution_date`. Optional `shop_id`, `supplier_id`, `credit_client_id` with `credit_client_price`, `provider_buy_price`, and `provider_received_at`. |
| `provider_ledger` | `provider_id`, `type` (`charge`, `payment`), and `amount`. An offset-bearing `provider_received_at` is required for charges and optional for payments. Payments without it use the source message date in the application timezone as `transaction_date`. Optional `car_number` and `notes`. |
| `debt_ledger` | `client_id`, `type` (`charge`, `payment`, `credit_note`), `amount`, and `transaction_date`. Optional `notes` and a payment-only `reference_id`. |

Payments require a `payment_method`, `currency`, and positive `exchange_rate`. Use rate `1` for TJS. The converter supports TJS, USD, EUR, UZS, and RUB. Set `payer_name` for `payment_purpose: "on_behalf_of"`. The `other` purpose is available on debt payments. Date-only values use `YYYY-MM-DD`. Omit unknown fields or use explicit `null` when a field was cleared. Unknown field names and malformed supplied values return `422`. Pending drafts display posting issues for incomplete fields and unresolved catalog references.

Draft details return an ETag such as `"1"`, the original and current fields, field-keyed `validation_issues`, source locators, and `events`. Audit changes distinguish missing fields with `present: false` and explicit nulls with `present: true, value: null`. Approved details include `approval.reviewed_fields`, `approval.posted_values`, `approval.generated_records`, and `posted_record` with current availability. Use `status=approved&corrected=true` for edited examples or `corrected=false` for unchanged approvals. The list orders approved drafts by recent approval.

Open **Drafts Records** at `/admin/transaction-drafts` to review all three transaction kinds. Review uses the regular admin session, CSRF token, and `transaction_drafts.review`. The inline editor can preview validation and duplicate matches with `POST /admin/transaction-drafts/{id}/preview`. Send the current `If-Match` on `PATCH /admin/transaction-drafts/{id}`, `POST /admin/transaction-drafts/{id}/approve`, and `POST /admin/transaction-drafts/{id}/dismiss`. A `412` includes the current draft and ETag. Approval also takes `Idempotency-Key`, optional `fields`, and the current `duplicate_confirmation` from the preview or a `409`. The same reviewer can replay a successful approval using the same key and request, including the original ETag. To dismiss as already recorded, send `reason: "already_recorded"` and a currently matched `posted_record_id`.
