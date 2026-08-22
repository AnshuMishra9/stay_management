import { spawn } from 'node:child_process';
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createServer } from 'node:net';

const appBase = process.env.STAY_TEST_BASE || 'http://localhost/stay_management';
const chromePath = process.env.STAY_CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const profile = mkdtempSync(join(tmpdir(), 'stay-role-management-'));
const screenshotDir = join(tmpdir(), 'stay-role-management-screens');
mkdirSync(screenshotDir, { recursive: true });

const roles = [
    {
        key: 'super',
        mobile: process.env.STAY_SUPER_TEST_MOBILE || '9876543210',
        session: process.env.STAY_SUPER_SESSION || '',
        roleLabel: 'Super Admin',
        managePaths: ['/stay_management/admins', '/stay_management/properties', '/stay_management/users'],
        pages: [
            { path: '/admins', heading: 'Admin accounts', form: false },
            { path: '/admins/add', heading: 'Create admin', form: true },
            { path: '/properties', heading: 'Properties', form: false },
            { path: '/properties/add', heading: 'Create property', form: true, tenantChooser: true },
            { path: '/users?tenant_id=1', heading: 'Property users', form: false },
            { path: '/users/add?tenant_id=1', heading: 'Create user', form: true, tenantChooser: true }
        ]
    },
    {
        key: 'admin',
        mobile: process.env.STAY_ADMIN_TEST_MOBILE || '9988776655',
        session: process.env.STAY_ADMIN_SESSION || '',
        roleLabel: 'Admin',
        managePaths: ['/stay_management/properties', '/stay_management/users'],
        pages: [
            { path: '/properties', heading: 'Properties', form: false },
            { path: '/properties/add', heading: 'Create property', form: true, tenantChooser: false },
            { path: '/users', heading: 'Property users', form: false },
            { path: '/users/add', heading: 'Create user', form: true, tenantChooser: false }
        ]
    },
    {
        key: 'user',
        mobile: process.env.STAY_USER_TEST_MOBILE || '1231231231',
        session: process.env.STAY_USER_SESSION || '',
        roleLabel: 'User',
        managePaths: [],
        pages: []
    }
];

const viewports = [
    { width: 320, height: 568 },
    { width: 390, height: 844 },
    { width: 1280, height: 800 }
];

const delay = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

function assert(condition, message) {
    if (!condition) { throw new Error(message); }
}

function sessionCookie(response) {
    const raw = response.headers.get('set-cookie') || '';
    const values = [...raw.matchAll(/ci_session=([^;,\s]+)/g)]
        .map((match) => match[1])
        .filter((value) => value && value !== 'deleted');
    return values.length ? values[values.length - 1] : '';
}

// Tests may receive pre-authenticated session ids through the environment for
// a strictly GET-only run. The OTP fallback uses the application's existing
// test login contract; after authentication every tested application request
// is read-only.
async function login(role) {
    if (role.session) { return role.session; }

    const otpResponse = await fetch(appBase + '/auth/send_otp', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mobile_no: role.mobile })
    });
    let cookie = sessionCookie(otpResponse);
    const otpPayload = await otpResponse.json();
    assert(otpPayload.status && otpPayload.otp && cookie, `${role.roleLabel} OTP login could not start.`);

    const verifyResponse = await fetch(appBase + '/auth/verify_otp', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Cookie: 'ci_session=' + cookie },
        body: JSON.stringify({ mobile_no: role.mobile, otp: String(otpPayload.otp) })
    });
    cookie = sessionCookie(verifyResponse) || cookie;
    const verifyPayload = await verifyResponse.json();
    assert(verifyPayload.status && cookie, `${role.roleLabel} OTP login failed.`);

    // A Super Admin can legitimately have multiple live properties. Select
    // the first authorized property so operational-page checks do not depend
    // on the old single-Legacy-Property fixture assumption.
    if (/\/properties\/select(?:$|[?#])/.test(String(verifyPayload.redirect || ''))) {
        const selectResponse = await getWithSession(cookie, '/properties/select');
        const selectHtml = await selectResponse.text();
        const token = selectHtml.match(/name="session_write_token"\s+value="([^"]+)"/);
        const property = selectHtml.match(/<option\s+value="(\d+)"/);
        assert(token && property, `${role.roleLabel} property selection page is incomplete.`);
        const switchResponse = await fetch(appBase + '/properties/switch', {
            method: 'POST',
            headers: {
                Cookie: 'ci_session=' + cookie,
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                session_write_token: token[1],
                property_id: property[1]
            }),
            redirect: 'manual'
        });
        cookie = sessionCookie(switchResponse) || cookie;
        const switched = switchResponse.status === 302 || switchResponse.status === 303;
        const switchBody = switched ? '' : (await switchResponse.text()).slice(0, 240);
        assert(switched, `${role.roleLabel} property selection failed (${switchResponse.status}): ${switchBody}`);
    }
    return cookie;
}

async function getWithSession(cookie, path) {
    return fetch(appBase + path, {
        headers: { Cookie: 'ci_session=' + cookie },
        redirect: 'manual'
    });
}

async function freePort() {
    return new Promise((resolve, reject) => {
        const server = createServer();
        server.once('error', reject);
        server.listen(0, '127.0.0.1', () => {
            const port = server.address().port;
            server.close(() => resolve(port));
        });
    });
}

class Cdp {
    constructor(url) {
        this.id = 0;
        this.pending = new Map();
        this.events = new Map();
        this.socket = new WebSocket(url);
    }

    async connect() {
        await new Promise((resolve, reject) => {
            this.socket.addEventListener('open', resolve, { once: true });
            this.socket.addEventListener('error', reject, { once: true });
        });
        this.socket.addEventListener('message', (event) => {
            const message = JSON.parse(event.data);
            if (message.id && this.pending.has(message.id)) {
                const pending = this.pending.get(message.id);
                this.pending.delete(message.id);
                if (message.error) { pending.reject(new Error(message.error.message)); }
                else { pending.resolve(message.result); }
                return;
            }
            const waiters = this.events.get(message.method) || [];
            this.events.delete(message.method);
            waiters.forEach((resolve) => resolve(message.params));
        });
    }

    send(method, params = {}) {
        const id = ++this.id;
        return new Promise((resolve, reject) => {
            this.pending.set(id, { resolve, reject });
            this.socket.send(JSON.stringify({ id, method, params }));
        });
    }

    once(method) {
        return new Promise((resolve) => {
            const waiters = this.events.get(method) || [];
            waiters.push(resolve);
            this.events.set(method, waiters);
        });
    }

    close() { this.socket.close(); }
}

async function waitForChrome(port) {
    for (let attempt = 0; attempt < 100; attempt += 1) {
        try {
            const response = await fetch(`http://127.0.0.1:${port}/json/version`);
            if (response.ok) { return; }
        } catch (_) {}
        await delay(100);
    }
    throw new Error('Headless Chrome did not start.');
}

async function evaluate(cdp, expression) {
    const result = await cdp.send('Runtime.evaluate', {
        expression,
        awaitPromise: true,
        returnByValue: true
    });
    if (result.exceptionDetails) {
        throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
    }
    return result.result.value;
}

async function waitFor(cdp, expression, message, timeout = 10000) {
    const started = Date.now();
    while (Date.now() - started < timeout) {
        if (await evaluate(cdp, `Boolean(${expression})`)) { return; }
        await delay(80);
    }
    throw new Error(message);
}

async function navigate(cdp, url) {
    const loaded = cdp.once('Page.loadEventFired');
    await cdp.send('Page.navigate', { url });
    await Promise.race([loaded, delay(12000)]);
    await waitFor(cdp, 'document.readyState === "complete"', 'Page did not finish loading.');
    await delay(100);
}

async function useSession(cdp, cookie) {
    await cdp.send('Network.clearBrowserCookies');
    const result = await cdp.send('Network.setCookie', {
        name: 'ci_session',
        value: cookie,
        url: appBase + '/',
        path: '/',
        httpOnly: true
    });
    assert(result.success, 'Chrome could not install the authenticated session cookie.');
}

async function verifyRoleNavbar(cdp, role) {
    await cdp.send('Emulation.setDeviceMetricsOverride', {
        width: 1280, height: 800, deviceScaleFactor: 1, mobile: false
    });
    await navigate(cdp, appBase + '/inventory');
    await waitFor(cdp, 'document.querySelector("#erpPropertySelector")', `${role.roleLabel} property selector is missing.`);

    const state = await evaluate(cdp, `(() => {
        const menu = document.querySelector('.erp-manage-menu');
        if (menu) { menu.open = true; }
        const selector = document.getElementById('erpPropertySelector');
        return {
            managePresent: Boolean(menu),
            role: document.querySelector('.erp-manage-user span')?.textContent.trim() || '',
            managePaths: [...document.querySelectorAll('.erp-manage-panel > a')].map((link) => new URL(link.href).pathname),
            options: selector ? [...selector.options].map((option) => ({
                value: option.value,
                text: option.textContent.trim(),
                selected: option.selected
            })) : [],
            propertyId: window.APP_PROPERTY_ID || 0,
            allProperties: selector ? [...selector.options].some((option) => /all properties/i.test(option.textContent)) : false
        };
    })()`);

    if (role.key === 'user') {
        assert(!state.managePresent, 'User navbar still exposes the Manage menu.');
        assert(state.managePaths.length === 0, 'User navbar contains management links.');
    } else {
        assert(state.managePresent, `${role.roleLabel} navbar is missing Manage.`);
        assert(state.role === role.roleLabel, `${role.roleLabel} navbar displayed an incorrect role.`);
        for (const path of role.managePaths) {
            assert(state.managePaths.includes(path), `${role.roleLabel} Manage menu is missing ${path}.`);
        }
        assert(!state.managePaths.includes('/stay_management/room-categories'), `${role.roleLabel} Manage menu still exposes Room Categories.`);
    }
    if (role.key === 'admin') {
        assert(!state.managePaths.includes('/stay_management/admins'), 'Admin Manage menu exposed Admin Accounts.');
    }
    assert(state.options.length >= 1, `${role.roleLabel} has no selectable property.`);
    const selectedOption = state.options.find((option) => option.selected);
    assert(selectedOption && Number(selectedOption.value) === Number(state.propertyId), `${role.roleLabel} active property is not selected.`);
    if (role.key !== 'user') {
        assert(state.options.some((option) => option.text.includes('Legacy Property')), `${role.roleLabel} selector omitted Legacy Property.`);
    }
    assert(!state.allProperties, `${role.roleLabel} selector exposed a forbidden All Properties option.`);
    if (role.key === 'super') {
        assert(state.options.every((option) => option.text.includes('—')), 'Super selector labels did not include owning admins.');
    } else if (role.key === 'admin') {
        assert(state.options.length === 1, 'Legacy Admin should only see its own Legacy Property.');
        assert(!selectedOption.text.includes('Legacy Admin'), 'Admin selector unexpectedly included an owner prefix.');
    } else {
        assert(state.options.length === 1, 'User should see only the assigned property.');
        assert(selectedOption.text.includes('Hotel1'), 'User selector omitted its assigned Hotel1 property.');
    }

    await navigate(cdp, appBase + '/rooms');
    const roomActions = await evaluate(cdp, `(() => [...document.querySelectorAll('.erp-head-actions a')].map((link) => ({
        text: link.textContent.replace(/\\s+/g, ' ').trim(),
        path: new URL(link.href).pathname
    })))()`);
    const addCategoryIndex = roomActions.findIndex((action) => action.path === '/stay_management/room-categories/add');
    const addRoomIndex = roomActions.findIndex((action) => action.path === '/stay_management/rooms/form');
    assert(addCategoryIndex !== -1, `${role.roleLabel} Room Master is missing Add Room Category.`);
    assert(addRoomIndex !== -1 && addCategoryIndex < addRoomIndex, `${role.roleLabel} Add Room Category is not left of Add Room.`);
}

async function verifyManagementPage(cdp, role, page, viewport, screenshots) {
    await cdp.send('Emulation.setDeviceMetricsOverride', {
        width: viewport.width,
        height: viewport.height,
        deviceScaleFactor: 1,
        mobile: viewport.width <= 390
    });
    await navigate(cdp, appBase + page.path);
    await waitFor(cdp, 'document.querySelector("main.mgmt-shell")', `${role.roleLabel} ${page.path} did not render the management layout.`);

    const layout = await evaluate(cdp, `(() => {
        const rect = (element) => {
            if (!element) { return null; }
            const value = element.getBoundingClientRect();
            return { left: value.left, right: value.right, width: value.width };
        };
        const visible = [...document.body.querySelectorAll('*')].filter((element) => {
            const style = getComputedStyle(element);
            const value = element.getBoundingClientRect();
            return style.display !== 'none' && style.visibility !== 'hidden' && value.width > 0 && value.height > 0;
        });
        const offenders = visible.filter((element) => {
            if (element.closest('.erp-nav-right, .erp-subnav, .table-responsive, .erp-table-scroll, .erp-manage-panel')) { return false; }
            const style = getComputedStyle(element);
            if (style.position === 'absolute' || style.position === 'fixed') { return false; }
            const value = element.getBoundingClientRect();
            return value.left < -1 || value.right > innerWidth + 1;
        }).slice(0, 10).map((element) => {
            const cls = typeof element.className === 'string' ? element.className : '';
            return cls || element.tagName;
        });
        const selector = document.getElementById('erpPropertySelector');
        return {
            title: document.title,
            heading: document.querySelector('main.mgmt-shell h1')?.textContent.replace(/\\s+/g, ' ').trim() || '',
            documentWidth: document.documentElement.scrollWidth,
            bodyWidth: document.body.scrollWidth,
            viewportWidth: innerWidth,
            offenders,
            shell: rect(document.querySelector('main.mgmt-shell')),
            card: rect(document.querySelector('.mgmt-card')),
            selector: rect(selector),
            selectorCount: selector ? selector.options.length : 0,
            hasForm: Boolean(document.querySelector('main.mgmt-shell form[method="post"]')),
            hasTenantChooser: Boolean(document.querySelector('main.mgmt-shell #tenant_id')),
            errorPage: Boolean(document.querySelector('.error-container')) || /access denied|error/i.test(document.title)
        };
    })()`);

    const label = `${role.roleLabel} ${page.path} at ${viewport.width}px`;
    assert(!layout.errorPage, `${label} rendered an error page.`);
    assert(layout.heading.includes(page.heading), `${label} heading was “${layout.heading}”.`);
    assert(layout.documentWidth <= layout.viewportWidth + 1, `${label} document width is ${layout.documentWidth}.`);
    assert(layout.bodyWidth <= layout.viewportWidth + 1, `${label} body width is ${layout.bodyWidth}.`);
    assert(layout.offenders.length === 0, `${label} overflowed: ${layout.offenders.join(', ')}.`);
    for (const [name, value] of Object.entries({ shell: layout.shell, card: layout.card, selector: layout.selector })) {
        assert(value && value.left >= -1 && value.right <= layout.viewportWidth + 1, `${label} ${name} leaves the viewport.`);
    }
    assert(layout.selectorCount >= 1, `${label} did not retain the authorized active selector.`);
    if (role.key === 'admin') {
        assert(layout.selectorCount === 1, `${label} exposed a property outside the Admin's tenant.`);
    }
    if (page.form) {
        assert(layout.hasForm, `${label} is missing its POST form.`);
    }
    if (page.tenantChooser !== undefined) {
        assert(layout.hasTenantChooser === page.tenantChooser, `${label} tenant chooser visibility is incorrect.`);
    }

    if (
        viewport.width === 320
        && ((role.key === 'super' && page.path === '/admins')
            || (role.key === 'admin' && page.path === '/users/add'))
    ) {
        const screenshot = await cdp.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
        const path = join(screenshotDir, `${role.key}-${page.path.replace(/[^a-z]+/gi, '-')}-320.png`);
        writeFileSync(path, Buffer.from(screenshot.data, 'base64'));
        screenshots.push(path);
    }
}

let chrome;
let cdp;
let testError;
try {
    const sessions = {};
    for (const role of roles) {
        sessions[role.key] = await login(role);
    }

    // HTTP-level role boundary: normal Admin must never render Super's admin
    // account list/form. These are GET requests and cannot change live data.
    for (const path of ['/admins', '/admins/add']) {
        const denied = await getWithSession(sessions.admin, path);
        assert(denied.status === 403, `Admin ${path} returned ${denied.status}, expected 403.`);
        const deniedHtml = await denied.text();
        assert(!deniedHtml.includes('mgmt-shell'), `Admin ${path} rendered a management page despite denial.`);
    }
    for (const path of ['/admins', '/properties', '/users']) {
        const denied = await getWithSession(sessions.user, path);
        assert(denied.status === 403, `User ${path} returned ${denied.status}, expected 403.`);
    }
    const superAdmins = await getWithSession(sessions.super, '/admins');
    assert(superAdmins.status === 200, `Super Admin /admins returned ${superAdmins.status}.`);

    const port = await freePort();
    chrome = spawn(chromePath, [
        '--headless=new', '--no-sandbox', '--disable-gpu', '--disable-crash-reporter', '--disable-breakpad',
        '--no-first-run', '--no-default-browser-check', '--remote-allow-origins=*',
        '--remote-debugging-port=' + port, '--user-data-dir=' + profile, 'about:blank'
    ], { stdio: 'ignore' });
    await waitForChrome(port);

    const targetResponse = await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' });
    const target = await targetResponse.json();
    cdp = new Cdp(target.webSocketDebuggerUrl);
    await cdp.connect();
    await cdp.send('Page.enable');
    await cdp.send('Network.enable');

    const screenshots = [];
    let checkedLayouts = 0;
    for (const role of roles) {
        await useSession(cdp, sessions[role.key]);
        await verifyRoleNavbar(cdp, role);
        for (const page of role.pages) {
            for (const viewport of viewports) {
                await verifyManagementPage(cdp, role, page, viewport, screenshots);
                checkedLayouts += 1;
            }
        }
    }

    console.log(JSON.stringify({
        passed: true,
        roles: roles.map((role) => role.roleLabel),
        roleDenials: {
            admin: ['/admins', '/admins/add'],
            user: ['/admins', '/properties', '/users']
        },
        checkedLayouts,
        viewports: viewports.map((viewport) => viewport.width),
        screenshots
    }, null, 2));
} catch (error) {
    testError = error;
} finally {
    if (cdp) {
        try { await Promise.race([cdp.send('Browser.close'), delay(1500)]); } catch (_) {}
        cdp.close();
    }
    if (chrome && !chrome.killed) { chrome.kill(); }
    await delay(400);
    try {
        rmSync(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 150 });
    } catch (_) {
        // A temporary Chrome handle must not hide the actual regression result.
    }
}
if (testError) { throw testError; }
