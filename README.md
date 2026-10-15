# Inventory API lab (PHP + SQLite)

Small assistant-assisted learning project using fictional stock. Not production software, prior employment or affiliated with any retailer. It provides a concrete way to practice PHP, relational data, idempotent requests and basic monitoring locally.

## Run locally

Requires PHP 8.1+ with PDO SQLite, plus Python 3 for HTTP smoke tests.

```sh
php bin/seed.php
php -S 127.0.0.1:8080 public/router.php
```

In a second terminal:

```sh
curl http://127.0.0.1:8080/stock
curl -X POST http://127.0.0.1:8080/reservations \
  -H 'Content-Type: application/json' \
  -d '{"request_id":"demo-1","sku":"DEMO-SHOE","quantity":8}'
curl http://127.0.0.1:8080/metrics > /tmp/inventory-metrics.txt
php bin/check-alerts.php /tmp/inventory-metrics.txt
```

The last command returns 1 and a `LOW_STOCK` message when any item has fewer than three units, 0 when healthy and 2 for invalid input. This is an on-demand local check, not a deployed monitoring service or notification integration.

## Test

```sh
php tests/store.php
python3 tests/smoke_http.py
python3 tests/concurrency.py
```

The tests use temporary databases. The concurrency check starts independent PHP processes sharing SQLite; exactly 10 of 40 reservations succeed against 10 units. They cover validation, retries, payload conflicts, SQL parameter binding, rollback, persistence after reconnecting, HTTP status codes, invalid JSON, oversized bodies and alert exits. An optional GitHub Actions example is in `docs/ci-example.yml`, not enabled because the publishing credential lacks workflow permission. No remote CI result is claimed.

## API

| Method | Path | Meaning |
| --- | --- | --- |
| GET | /health | Database can be opened and schema checked |
| GET | /stock | Current stock |
| POST | /reservations | request_id, sku and positive integer quantity |
| GET | /metrics | Cumulative API request/conflict counters and current stock gauges |

Successful creates return 201, successful replays 200, invalid inputs 400, inventory or key conflicts 409, missing routes 404 and unexpected backend failures 503. Health/metrics requests are excluded from the request counter. Unknown SKU and insufficient stock deliberately share a conflict response. Metrics are simple text in Prometheus exposition style; no Prometheus server has been deployed. Backend errors are logged rather than writing to a potentially broken database.

## Transaction design

`BEGIN IMMEDIATE` serializes SQLite writers. Inside the transaction the service checks the unique request ID, conditionally decrements stock only when enough remains, and records the reservation. Any failure rolls everything back. Prepared statements keep input separate from SQL. A replay with different data is rejected. Idempotency survives restarting because records are in SQLite; failed attempts do not consume their key.

## Limits and safe use

Use only on loopback with fake data. PHP's built-in server is for development, not public deployment. There is no authentication, authorization, rate limiting, TLS, cancellation, stock import, retention policy or external alert delivery. SQLite locking and a single development server do not demonstrate distributed scale. Cloud, AWS, Kotlin and PostgreSQL are not implemented. Database files must stay outside a public document root. Do not put real customer/order data in this lab.

See [STUDY_GUIDE.md](STUDY_GUIDE.md) before discussing it in an interview.
