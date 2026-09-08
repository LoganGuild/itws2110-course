# Part 4 — Browser tests with Playwright

The API says `422`. A person does not see status codes. A person sees a red message — or
does not, if someone forgot to render it. That is a different fact, and it needs a
different kind of test: one that *is* a person, sitting at a real browser.

## A — Read `tests/e2e/pantry.spec.js`

```js
test('a user can add a product and see it in the table', async ({ page }) => {
  const name = `Milk ${Date.now()}`;

  await page.goto('/');
  await page.fill('#new-product input[name="name"]', name);
  await page.fill('#new-product input[name="best_before"]', '2026-12-24');
  await page.click('#new-product button[type="submit"]');

  const row = page.locator('#products tr', { hasText: name });
  await expect(row).toBeVisible();
  await expect(row.locator('.amount')).toHaveText('0');
  await expect(row.locator('.badge')).toHaveText('fresh');
});
```

Open the page. Type in two boxes. Click. Find the row with your product in it. Check three
things a person would check. It is a *script of a user*, and it knows nothing else — not
PHP, not SQLite, not `/api/products`.

`page` is the browser tab. `locator()` is how you point at things on the page, the way you
would point with a finger: *the row in the products table that has "Milk" in it*. Then
*the amount cell in that row*.

The second test buys twelve and uses three, and expects to see nine. Read how it finds the
right *Buy* button: `row.locator('form[action$="/purchase"] button')` — the form inside
that row whose action ends in `/purchase`. Selectors are a skill; the [Writing tests
reading](https://playwright.dev/docs/writing-tests) is about little else.

## B — Run them

```
docker compose run --rm e2e
```

```
Running 2 tests using 1 worker
  ✓  a user can add a product and see it in the table (217ms)
  ✓  buying and using change the amount on the shelf (317ms)

  2 passed (6.7s)
```

Look at the time. A quarter of a second *each*. The PHPUnit suite ran twenty-one tests in
five milliseconds. A browser test starts Chromium, renders HTML, submits forms, follows
redirects, and waits for paint. It is fifteen times slower than an API test and a thousand
times slower than a unit test — and it is the only one of the three that would notice a
missing `<button>`.

## C — Break the page, not the logic

Open `public/index.php`. Find the `.amount` cell and change the CSS class to `.qty`. Run
the e2e suite: both tests fail — the robot cannot find the amount any more. (Each
failure takes five seconds to report: Playwright keeps looking for the element until its
timeout runs out, because on a real page things sometimes take a moment to appear.) Run the unit
suite: all green. Run the API suite: all green. **The page is broken and only the browser
tests know.**

Put it back. Now break the other direction: in `Stock::consume()`, remove your fix from
Part 2. Unit: red. API: red. E2E: still green — because neither browser test tries to
over-consume. Which is the gap you are about to close. Restore the fix.

## D — Write the third test

Where the file ends there is a comment. Write the test it describes:

> **using more than is on the shelf shows an error and leaves the amount alone.**

You know the moves: add a product, buy 1, try to use 2. Then two assertions. The page
puts errors in `<p class="err" role="alert">`; the API test told you what the message
says. Run it green, then take out the fix in `consume()` and run it red, then put the fix
back.

Now the one rule has three tests. Same sentence, three languages: a class throws, a
service says `422`, a person sees red text.

## E — See the robot

Playwright can record what it did. Once, run with a trace:

```
docker compose run --rm e2e npx playwright test tests/e2e --trace on
```

Then, on your own machine, open `test-results/<test name>/trace.zip` at
<https://trace.playwright.dev> — drag the file in. Every step, with a screenshot of the
page at that moment. On Friday I will run the same test with a visible browser so you can
watch it type.

## Clean up

```
docker compose down
```

---

## Key learnings

- **A browser test is a script of a person:** open, type, click, look. It knows nothing about PHP, SQLite or the API.
- **Locators are how you point at things** — *the row with "Milk" in it*, *the Buy button inside that row*. Choosing them well is most of the skill.
- **Only the browser sees the page.** Rename one CSS class and the e2e suite is the only one that notices; unit and API stay green. That is not fragility — it is the one job these tests have.
- **A quarter of a second each**, a thousand times a unit test. Write few, aim them at what only a browser can see, and cover the rest lower down.
- **One rule, three tests.** A class throws, a service says `422`, a person sees red text — the same sentence at three altitudes, and one three-line fix turns all three green.
- **Traces show you what the robot did**, step by step with screenshots. Use them before adding `console.log`.
