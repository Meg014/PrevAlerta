const { defineConfig } = require('@playwright/test');
module.exports = defineConfig({
  testDir: './tests/browser', workers: 1, timeout: 30000,
  use: { baseURL: process.env.PREV_TEST_URL || 'http://127.0.0.1:8766', headless: true },
  reporter: 'list',
});
