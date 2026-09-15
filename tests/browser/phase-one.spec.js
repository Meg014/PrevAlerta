const { test, expect } = require('@playwright/test');

async function login(page, role = 'admin') {
  await page.goto('/login');
  await page.getByLabel('E-mail', { exact: true }).fill(`${role}@browser.example.test`);
  await page.getByLabel('Senha', { exact: true }).fill('Browser-test-password-123');
  await page.getByRole('button', { name: 'Entrar na minha conta' }).click();
  await expect(page).toHaveURL(/\/$/);
}

test('cadastro, edição, filtro, logout e proteção de acesso no navegador', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await login(page);
  await expect(page.getByRole('heading', { name: 'Painel de alertas' })).toBeVisible();
  await page.screenshot({ path: 'tmp/screenshots/dashboard-desktop.png', fullPage: true });
  await page.getByRole('link', { name: 'Novo checklist', exact: true }).click();
  const code = `BROWSER-${Date.now()}`;
  await page.getByLabel('Código *', { exact: true }).fill(code);
  await page.getByLabel('Nome *', { exact: true }).fill('Manutenção Preventiva Elétrica');
  await page.getByLabel('Área / setor *', { exact: true }).fill('Recepção');
  await page.getByLabel('Rota', { exact: true }).fill('Rota 01');
  await page.getByLabel('Periodicidade em dias *', { exact: true }).fill('15');
  await page.getByLabel('Data inicial *', { exact: true }).fill('2026-09-10');
  await page.getByLabel('Descrição', { exact: true }).fill('Inspeção preventiva dos componentes elétricos.');
  await page.screenshot({ path: 'tmp/screenshots/cadastro-desktop.png', fullPage: true });
  await page.getByRole('button', { name: 'Cadastrar checklist', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Manutenção Preventiva Elétrica' })).toBeVisible();
  await expect(page.getByText('10/09/2026', { exact: true })).toBeVisible();
  await page.getByRole('link', { name: 'Editar checklist', exact: true }).click();
  await page.getByLabel('Observação', { exact: true }).fill('Cadastro revisado.');
  await page.getByRole('button', { name: 'Salvar alterações' }).click();
  await expect(page.getByText('Cadastro revisado.')).toBeVisible();
  await page.goto('/checklists');
  await page.getByLabel('Buscar checklist').fill(code);
  await page.getByRole('button', { name: 'Filtrar', exact: true }).click();
  await expect(page.locator('tbody tr')).toHaveCount(1);
  await page.screenshot({ path: 'tmp/screenshots/checklists-desktop.png', fullPage: true });
  await page.getByRole('button', { name: 'Alternar menu' }).click();
  await expect(page.locator('body')).toHaveClass(/sidebar-collapsed/);
  await page.getByRole('link', { name: 'Sair', exact: true }).click();
  await expect(page).toHaveURL(/\/login$/);
  await page.goto('/checklists');
  await expect(page).toHaveURL(/\/login/);
  expect(errors).toEqual([]);
});

test('celular: drawer, foco e consulta por usuário sem permissão de edição', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page, 'usuario');
  await expect(page.getByRole('link', { name: 'Novo checklist', exact: true })).toHaveCount(0);
  await page.screenshot({ path: 'tmp/screenshots/dashboard-mobile.png', fullPage: true });
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await page.getByRole('button', { name: 'Alternar menu' }).click();
  await expect(page.locator('body')).toHaveClass(/drawer-open/);
  await page.screenshot({ path: 'tmp/screenshots/drawer-mobile.png', fullPage: true });
  await page.keyboard.press('Escape');
  await expect(page.getByRole('button', { name: 'Alternar menu' })).toBeFocused();
  await page.goto('/checklists');
  await expect(page.getByRole('heading', { name: 'Checklists', exact: true })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  const response = await page.goto('/checklists/novo');
  expect(response.status()).toBe(403);
});
