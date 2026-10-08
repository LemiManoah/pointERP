import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFile, mkdir } from 'node:fs/promises';
import { resolve, extname } from 'node:path';
import { chromium } from 'playwright';

// Exercise the production frontend with synthetic Inertia responses; no application database is used.
const root = resolve(import.meta.dirname, '../..');
const build = resolve(root, 'public/build');
const manifest = JSON.parse(await readFile(resolve(build, 'manifest.json'), 'utf8'));
const entry = manifest['resources/js/app.tsx'];
const output = resolve(root, 'storage/app/dashboard-browser');
await mkdir(output, { recursive: true });
let restricted = false;
const requests = [];
function pageData(url) {
    const period = url.searchParams.get('period') ?? 'month';
    const currency = url.searchParams.get('currency') ?? 'UGX';
    const factor = period === 'today' ? 0.1 : 1;
    const cards = [
        ['sales', 'Sales', 24850000, 64, 'POS sales before returns', '/pos'],
        ['expenses', 'Expenses', 9120000, 22, 'Approved expenses in this period', '/expenses'],
        ['receivables', 'Customers owe', 4850000, 12, 'Current outstanding POS balances', '/pos'],
        ['payables', 'We owe', 2350000, 7, 'Current unpaid approved expenses', '/expenses'],
    ].map(([id, title, amount, count, description, href]) => ({ id, title, amount: String(amount * (id === 'sales' || id === 'expenses' ? factor : 1)), count, description, href, snapshot: ['receivables', 'payables'].includes(id) }));
    const cashFlow = {
        received: { amount: String(20000000 * factor), count: 52 }, paid: { amount: String(6770000 * factor), count: 18 }, net: String(13230000 * factor), interval: 'day',
        series: Array.from({ length: 21 }, (_, i) => ({ label: `${i + 1} Sep`, received: String((i % 4 + 1) * 300000 * factor), paid: String((i % 3 + 1) * 140000 * factor) })),
        methods: [
            { method: 'cash', label: 'Cash', received: '8000000', paid: '3770000', receivedCount: 30, paidCount: 12 },
            { method: 'bank', label: 'Bank transfer', received: '10000000', paid: '2000000', receivedCount: 12, paidCount: 3 },
            { method: 'mobile_money', label: 'Mobile money', received: '2000000', paid: '1000000', receivedCount: 10, paidCount: 3 },
        ],
    };
    return {
        component: 'dashboard', url: url.pathname + url.search, version: null, clearHistory: false, encryptHistory: false,
        props: {
            auth: { user: { id: 'owner', name: 'Test Owner', email: 'owner@example.test', permissions: ['pos.view', 'expenses.view'], roles: [] } },
            currentTenant: { name: 'Test Business', is_multibranch: false }, currentBranch: { name: 'Main branch' }, accessibleBranches: [], canViewAllBranches: false,
            sidebarOpen: true, errors: {}, flash: {},
            filters: { period, currency, from: url.searchParams.get('from') ?? '2026-09-01', to: url.searchParams.get('to') ?? '2026-09-21' }, currencies: ['UGX', 'USD'],
            cards: restricted ? cards.slice(0, 1) : cards, ...(restricted ? {} : { cashFlow }),
        },
    };
}
const server = createServer(async (req, res) => {
    try {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname.startsWith('/build/')) {
            const file = resolve(build, url.pathname.slice('/build/'.length));
            if (!file.startsWith(build)) { res.writeHead(403).end(); return; }
            res.setHeader('Content-Type', extname(file) === '.css' ? 'text/css' : 'text/javascript');
            res.end(await readFile(file));
            return;
        }
        if (url.pathname === '/favicon.ico') { res.writeHead(204).end(); return; }
        requests.push(url);
        const data = pageData(url);
        if (req.headers['x-inertia']) {
            res.setHeader('Content-Type', 'application/json'); res.setHeader('X-Inertia', 'true'); res.end(JSON.stringify(data)); return;
        }
        res.setHeader('Content-Type', 'text/html');
        const css = (entry.css ?? []).map((file) => `<link rel="stylesheet" href="/build/${file}">`).join('');
        res.end(`<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">${css}</head><body><script data-page="app" type="application/json">${JSON.stringify(data).replaceAll('<', '\\u003c')}</script><div id="app"></div><script type="module" src="/build/${entry.file}"></script></body></html>`);
    } catch (error) { res.writeHead(500).end(String(error)); }
});
await new Promise((done) => server.listen(0, '127.0.0.1', done));
const origin = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch({ channel: 'msedge', headless: true });
try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    const errors = [];
    page.on('pageerror', (error) => { errors.push(error.message); console.error(error.message); });
    page.on('console', (message) => { if (message.type() === 'error') console.error(message.text()); });
    await page.goto(origin + '/dashboard');
    await page.getByRole('heading', { name: 'Dashboard', exact: true }).waitFor();
    assert.equal(await page.getByRole('tab').count(), 0);
    assert.equal(await page.getByText('Welcome back', { exact: false }).count(), 0);
    assert.equal(await page.getByTestId('metric-sales').textContent(), '24,850,000');
    await page.locator('.recharts-bar-rectangle').first().waitFor();
    await page.locator('.recharts-pie').first().waitFor();
    await page.getByRole('combobox', { name: 'Payment direction' }).click();
    await page.getByRole('option', { name: 'Money out', exact: true }).click();
    await page.getByText('Expense payments · share by amount', { exact: false }).waitFor();
    await page.screenshot({ path: resolve(output, 'desktop.png'), fullPage: true });
    await page.getByRole('combobox', { name: 'Reporting period' }).click();
    await page.getByRole('option', { name: 'Today', exact: true }).click();
    await page.waitForURL('**/dashboard?**period=today**');
    await page.getByTestId('metric-sales').filter({ hasText: '2,485,000' }).waitFor();
    await page.getByRole('combobox', { name: 'Reporting period' }).click();
    await page.getByRole('option', { name: 'Custom range' }).click();
    await page.getByLabel('From', { exact: true }).fill('2026-09-05');
    await page.getByLabel('To', { exact: true }).fill('2026-09-12');
    await page.getByRole('button', { name: 'Apply', exact: true }).click();
    await page.waitForURL('**/dashboard?**period=range**');
    assert.equal(requests.at(-1).searchParams.get('from'), '2026-09-05');
    assert.equal(requests.at(-1).searchParams.get('to'), '2026-09-12');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: resolve(output, 'mobile.png'), fullPage: true });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false, 'Dashboard must not overflow horizontally');
    restricted = true;
    await page.goto(origin + '/dashboard');
    await page.getByTestId('metric-sales').waitFor();
    assert.equal(await page.getByText('Payment methods', { exact: true }).count(), 0);
    assert.equal(await page.getByTestId('metric-expenses').count(), 0);
    assert.deepEqual(errors, []);
    console.log('PASS: desktop/mobile layout, charts, presets, custom range, restricted cards, and browser errors.');
    console.log(output);
} finally {
    await browser.close();
    await new Promise((done) => server.close(done));
}
