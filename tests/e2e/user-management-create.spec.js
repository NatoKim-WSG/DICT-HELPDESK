import { expect, test } from '@playwright/test';

const completeLegalAcceptanceIfNeeded = async (page) => {
    if (!page.url().includes('/legal/acceptance')) {
        return;
    }

    await page.getByLabel('I agree to the Terms of Service.').check();
    await page.getByLabel('I acknowledge and consent to the Privacy Notice.').check();
    await page.getByLabel('I confirm I am authorized to use this platform and provide data needed for support.').check();
    await page.getByRole('button', { name: 'Accept and Continue' }).click();
};

const loginAsSeededSuperUser = async (page) => {
    await page.goto('/login');
    await page.getByLabel('Username or Email').fill('e2e-super@example.com');
    await page.getByLabel('Password').fill('Playwright123!');
    await page.getByRole('button', { name: 'Sign in' }).click();

    await Promise.race([
        page.waitForURL(/\/admin\/dashboard$/, { timeout: 7_000 }),
        page.waitForURL(/\/legal\/acceptance(?:\?.*)?$/, { timeout: 7_000 }),
    ]);

    await completeLegalAcceptanceIfNeeded(page);
    await expect(page).toHaveURL(/\/admin\/dashboard$/);
};

test('super user can create a client account from the browser flow', async ({ page }) => {
    await loginAsSeededSuperUser(page);

    await page.goto('/admin/users/create');
    await expect(page.getByRole('heading', { name: 'Create New User' })).toBeVisible();

    const stamp = Date.now();
    const email = `e2e-client-${stamp}@example.com`;
    const username = `e2e.client.${stamp}`;

    await page.getByLabel('Username').fill(username);
    await page.getByLabel('Display Name').fill(`E2E Client ${stamp}`);
    await page.getByLabel('Email Address').fill(email);
    await page.getByLabel('Phone Number').fill('09175550000');
    await page.getByLabel('Role').selectOption('client');
    await page.getByLabel('Department').selectOption('iOne');
    await page.getByRole('button', { name: 'Create User' }).click();

    await expect(page).toHaveURL(/\/admin\/users$/);
    await expect(page.getByText('User created successfully.')).toBeVisible();

    await page.getByPlaceholder('Search users').fill(email);
    await page.getByRole('button', { name: 'Filter' }).click();

    await expect(page.getByText(email)).toBeVisible();
    await expect(page.getByText(`@${username}`)).toBeVisible();
});

