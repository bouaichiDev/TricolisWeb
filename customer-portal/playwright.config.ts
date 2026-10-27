import { defineConfig, devices } from '@playwright/test'

/**
 * Parcours de bout en bout du portail client.
 *
 * Même règle que le back-office : **la pile tourne contre la base de test**,
 * jamais celle de développement. Préalable, hors Playwright :
 *
 * ```bash
 * php artisan migrate:fresh --seed --env=testing
 * php artisan db:seed --class=CustomerPortalDemoSeeder --env=testing
 * php artisan db:seed --class=DemoCustomerPortalStockSeeder --env=testing
 * php artisan db:seed --class=DemoCustomerPortalIntegrationsSeeder --env=testing
 * ```
 *
 * Ports distincts de ceux du back-office (8001/5174) : les deux suites
 * peuvent coexister sur la machine, pas s'exécuter en même temps — elles
 * partagent la base de test.
 */
const API_PORT = 8002
// Port réservé aux parcours, `--strictPort` et jamais réutilisé : un serveur
// de développement déjà lancé pointe vers l'API de développement, et les
// écrans écriraient alors dans une autre base que celle que vérifient les tests.
const WEB_PORT = 5182

export default defineConfig({
  testDir: './e2e',
  timeout: 90_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list']],

  use: {
    baseURL: `http://localhost:${WEB_PORT}`,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    locale: 'fr-FR',
  },

  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],

  webServer: [
    {
      command: `php artisan serve --port=${API_PORT}`,
      cwd: '..',
      // `APP_ENV` est transmis au processus fils : il charge `.env.testing`.
      env: { APP_ENV: 'testing', CUSTOMER_PORTAL_URL: `http://localhost:${WEB_PORT}` },
      url: `http://localhost:${API_PORT}/up`,
      reuseExistingServer: false,
      timeout: 60_000,
    },
    {
      command: `npm run dev -- --port ${WEB_PORT} --strictPort`,
      url: `http://localhost:${WEB_PORT}`,
      env: { VITE_API_URL: `http://localhost:${API_PORT}` },
      reuseExistingServer: false,
      timeout: 60_000,
    },
  ],
})
