# Part 3 — API tests

`StockTest` proves `Stock` refuses to go negative. It proves nothing about what a program
*calling this app over the network* gets back. Does the API say no? With what status
code? With a message a client could show? Does the shelf really stay the same?

Those are questions about a **contract**, and the tests for them speak HTTP.

## A — What the API is

You saw `/api/products` in Part 0. The full contract:

| | |
|---|---|
| `GET  /api/products` | every product, with its shelf `status` |
| `POST /api/products` `{"name": "...", "best_before": "2026-12-01"}` | create, `201` |
| `GET  /api/products/{id}` | one product, or `404` |
| `POST /api/products/{id}/purchase` `{"amount": 6}` | buy some |
| `POST /api/products/{id}/consume` `{"amount": 2}` | use some |

Bad input is `422` with `{"error": "..."}`. Everything the page can do, the API can do,
because both call the same `Pantry` class.

## B — Read `tests/api/products.spec.js`

This is Playwright, but there is **no browser in this file**. The `request` fixture is an
HTTP client, and that is all it is:

```js
test('purchase then consume leaves the difference in stock', async ({ request }) => {
  const { id } = await createProduct(request);

  await request.post(`/api/products/${id}/purchase`, { data: { amount: 6 } });
  const after = await request.post(`/api/products/${id}/consume`, { data: { amount: 2.5 } });

  expect(after.status()).toBe(200);
  expect((await after.json()).amount).toBe(3.5);
});
```

Same three beats as PHPUnit — arrange, act, assert — in a different language. The
assertions are on the *response*: status code, JSON body. Nothing here imports `Stock`.
Nothing here knows the app is PHP.

Two habits to notice:

- **Every test makes its own product**, with a name nobody else will use. `createProduct()` at the top does it. No test relies on another having run first, so any one of them can run alone, and in any order.
- **The database is thrown away every run.** `playwright.config.js` starts Apache on a fresh SQLite file before the tests and stops it after. Look at the `webServer` block. The app under test lives in the same container as the tests, for a few seconds — served by the same Apache and the same config as `docker compose watch`.

## C — Run them

```
docker compose run --rm api
```

If you finished Part 2:

```
Running 6 tests using 1 worker
  ✓  POST /api/products creates a product that GET returns (20ms)
  ✓  GET /api/products lists it, with a shelf status (12ms)
  ✓  a product needs a name (5ms)
  ✓  purchase then consume leaves the difference in stock (19ms)
  ✓  consuming more than is in stock is refused with 422 (12ms)
  ✓  an unknown product is 404 (6ms)

  6 passed (6.1s)
```

If you did **not**, the fifth one is red — and read the failure, because it is a lesson on
its own:

```
Expected: 422
Received: 200
```

Your unit test from Part 2 and this test are checking **the same rule at two levels**. The
unit test says the class refuses. This one says the *service* refuses, and says how: a
`422`, an error message, and the stock left alone. Fixing `Stock::consume()` made this go
green without touching `index.php` — because `index.php` was already prepared to turn that
exception into a `422`. That is what "the rules live in one place" buys you.

Compare the time to Part 1. Ten to twenty milliseconds a test — the *whole* PHPUnit suite
ran in five. Each of these is a real HTTP round trip to a real server writing to a real
database. (The six seconds on the last line is mostly Playwright starting up and bringing
the app up on a fresh database, not the tests.)

## D — Write one

Add a test to `products.spec.js`:

> **purchasing a negative amount is refused with 422**, and the amount does not change.

You have everything you need in the file. Run it. Watch it go green — and then ask
yourself whether you *trust* it. Break the rule in `Stock::add()` (remove the guard) and
run again. Red? Then it is a test. Restore the guard.

---

## Key learnings

- **An API test checks the contract, not the code.** It sends HTTP and asserts on the status and the body. It never imports `Stock` and does not know the server is PHP.
- **Same rule, one level up.** The unit test says the class refuses; the API test says the service refuses, how (`422`), with what message, and that the shelf is untouched.
- **A fix in one class flips the API test green** without touching `index.php`, because the web layer was already prepared to turn that exception into a `422`. Rules in one place.
- **Every test makes its own data.** Unique names, no shared product, no dependence on order — any test can run alone.
- **Throw the database away every run.** The runner starts the app on a fresh file and stops it after; nothing leaks between runs.
- **Ten to twenty milliseconds a test** against five for the whole unit suite. Real server, real database: that is what the extra time buys.
