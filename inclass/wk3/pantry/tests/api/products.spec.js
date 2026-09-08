const { test, expect } = require('@playwright/test');

// API tests. No browser. Playwright's `request` fixture speaks HTTP to the app
// -- the same JSON the page would fetch -- and we assert on status codes and
// bodies. These test the CONTRACT: what a client is promised.
//
// Every test creates its own product with a unique name. No test depends on
// another one having run, and the database is fresh every run anyway
// (see playwright.config.js).

async function createProduct(request, extra = {}) {
  const name = `Apples ${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
  const res = await request.post('/api/products', { data: { name, ...extra } });
  expect(res.status()).toBe(201);
  return res.json();
}

test('POST /api/products creates a product that GET returns', async ({ request }) => {
  const created = await createProduct(request, { best_before: '2026-12-01' });

  const fetched = await request.get(`/api/products/${created.id}`);

  expect(fetched.status()).toBe(200);
  expect(await fetched.json()).toMatchObject({
    id: created.id,
    name: created.name,
    amount: 0,
    best_before: '2026-12-01',
  });
});

test('GET /api/products lists it, with a shelf status', async ({ request }) => {
  const created = await createProduct(request, { best_before: '2099-01-01' });

  const list = await (await request.get('/api/products')).json();

  const mine = list.find((p) => p.id === created.id);
  expect(mine).toBeDefined();
  expect(mine.status).toBe('fresh');
});

test('a product needs a name', async ({ request }) => {
  const res = await request.post('/api/products', { data: { name: '   ' } });

  expect(res.status()).toBe(422);
  expect((await res.json()).error).toContain('Name is required');
});

test('purchase then consume leaves the difference in stock', async ({ request }) => {
  const { id } = await createProduct(request);

  await request.post(`/api/products/${id}/purchase`, { data: { amount: 6 } });
  const after = await request.post(`/api/products/${id}/consume`, { data: { amount: 2.5 } });

  expect(after.status()).toBe(200);
  expect((await after.json()).amount).toBe(3.5);
});

// The same rule StockTest is missing, seen from outside. Until you finish
// Part 2 this test is red: the API happily returns 200 and a negative amount.
test('consuming more than is in stock is refused with 422', async ({ request }) => {
  const { id } = await createProduct(request);
  await request.post(`/api/products/${id}/purchase`, { data: { amount: 1 } });

  const res = await request.post(`/api/products/${id}/consume`, { data: { amount: 2 } });

  expect(res.status()).toBe(422);
  expect((await res.json()).error).toMatch(/not enough/i);

  // And the shelf is untouched.
  const product = await (await request.get(`/api/products/${id}`)).json();
  expect(product.amount).toBe(1);
});

test('an unknown product is 404', async ({ request }) => {
  const res = await request.get('/api/products/999999');

  expect(res.status()).toBe(404);
});
