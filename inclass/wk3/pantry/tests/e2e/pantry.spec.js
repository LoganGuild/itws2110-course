const { test, expect } = require('@playwright/test');

// End-to-end tests. A real Chromium opens the page, types, clicks, and reads
// what came back -- exactly what a person would do, and nothing else. These
// tests know nothing about PHP, SQLite, or the API.

test('a user can add a product and see it in the table', async ({ page }) => {
  const name = `Milk ${Date.now()}`;

  await page.goto('/');
  await expect(page.locator('h1')).toHaveText('Pantry');

  await page.fill('#new-product input[name="name"]', name);
  await page.fill('#new-product input[name="best_before"]', '2026-12-24');
  await page.click('#new-product button[type="submit"]');

  const row = page.locator('#products tr', { hasText: name });
  await expect(row).toBeVisible();
  await expect(row.locator('.amount')).toHaveText('0');
  await expect(row.locator('.badge')).toHaveText('fresh');
});

test('buying and using change the amount on the shelf', async ({ page }) => {
  const name = `Eggs ${Date.now()}`;

  await page.goto('/');
  await page.fill('#new-product input[name="name"]', name);
  await page.click('#new-product button[type="submit"]');

  const row = page.locator('#products tr', { hasText: name });

  await row.locator('form[action$="/purchase"] input[name="amount"]').fill('12');
  await row.locator('form[action$="/purchase"] button').click();
  await expect(row.locator('.amount')).toHaveText('12');

  await row.locator('form[action$="/consume"] input[name="amount"]').fill('3');
  await row.locator('form[action$="/consume"] button').click();
  await expect(row.locator('.amount')).toHaveText('9');
});

// Part 4 asks you to write the third test here: a user who tries to use more
// than is on the shelf sees an error message, and the amount does not change.
