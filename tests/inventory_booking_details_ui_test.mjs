import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createServer } from 'node:net';

const appBase = process.env.STAY_TEST_BASE || 'http://localhost/stay_management';
const mobile = process.env.STAY_TEST_MOBILE || '9988776655';
const chromePath = process.env.STAY_CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const profile = mkdtempSync(join(tmpdir(), 'stay-inventory-details-'));
const screenshotPath = join(tmpdir(), 'stay-inventory-popup-390.png');

const fixtures = [
    { id: 61, date: '2026-08-18', room: '102', status: 'room_booked', action: 'Check-in', path: '/stay_management/customers/bookings/checkin/61', page: 'Check-in' },
    { id: 10, date: '2026-07-22', room: '101', status: 'checked_in', action: 'Check-out', path: '/stay_management/customers/checkins/checkout/10', page: 'Check-out' },
    { id: 27, date: '2026-07-27', room: '101', status: 'checked_out', action: 'View record', path: '/stay_management/customers/checkedouts/details/27', page: 'Check-out Record' }
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

async function login() {
    const otpResponse = await fetch(appBase + '/auth/send_otp', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mobile_no: mobile })
    });
    let cookie = sessionCookie(otpResponse);
    const otpPayload = await otpResponse.json();
    assert(otpPayload.status && cookie, 'OTP test login could not start.');

    const verifyResponse = await fetch(appBase + '/auth/verify_otp', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Cookie: 'ci_session=' + cookie },
        body: JSON.stringify({ mobile_no: mobile, otp: String(otpPayload.otp) })
    });
    cookie = sessionCookie(verifyResponse) || cookie;
    const verifyPayload = await verifyResponse.json();
    assert(verifyPayload.status, 'OTP test login failed.');
    const inventoryResponse = await fetch(appBase + '/inventory', {
        headers: { Cookie: 'ci_session=' + cookie }
    });
    const inventoryHtml = await inventoryResponse.text();
    const tokenMatch = inventoryHtml.match(/window\.APP_PROPERTY_CONTEXT_TOKEN\s*=\s*("(?:\\.|[^"\\])*")\s*;/);
    assert(tokenMatch, 'Property context token was not rendered after login.');
    return { cookie, contextToken: JSON.parse(tokenMatch[1]) };
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
    for (let attempt = 0; attempt < 80; attempt += 1) {
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

async function waitFor(cdp, expression, message, timeout = 8000) {
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
    await Promise.race([loaded, delay(10000)]);
    await waitFor(cdp, 'document.readyState === "complete"', 'Page did not finish loading.');
    await delay(150);
}

async function endpointChecks(cookie, contextToken) {
    const unauthenticated = await fetch(appBase + '/inventory/booking_detail/61', { redirect: 'manual' });
    assert([301, 302, 303, 307, 308].includes(unauthenticated.status), 'Unauthenticated detail request was not redirected.');

    const details = new Map();
    for (const fixture of fixtures) {
        const response = await fetch(appBase + '/inventory/booking_detail/' + fixture.id, {
            headers: {
                Cookie: 'ci_session=' + cookie,
                'X-Requested-With': 'XMLHttpRequest',
                'X-Property-Context-Token': contextToken
            },
            redirect: 'manual'
        });
        assert(response.status === 200, `Detail endpoint failed for booking ${fixture.id}.`);
        assert((response.headers.get('cache-control') || '').includes('no-store'), 'Sensitive detail response is cacheable.');
        assert((response.headers.get('x-content-type-options') || '').toLowerCase() === 'nosniff', 'nosniff header is missing.');
        const payload = await response.json();
        assert(payload.status && payload.data, `Detail payload missing for booking ${fixture.id}.`);
        assert(payload.data.status_code === fixture.status, `Unexpected current status for booking ${fixture.id}.`);
        assert(new URL(payload.data.workflow.url).pathname === fixture.path, `Wrong server workflow for booking ${fixture.id}.`);
        assert(!/identit|document_path|document_url/i.test(JSON.stringify(payload.data)), 'Detail endpoint exposed identity-document data.');
        details.set(fixture.id, payload.data);
    }

    const missing = await fetch(appBase + '/inventory/booking_detail/999999999', {
        headers: {
            Cookie: 'ci_session=' + cookie,
            'X-Requested-With': 'XMLHttpRequest',
            'X-Property-Context-Token': contextToken
        }
    });
    assert(missing.status === 404, 'Unknown booking ID did not return 404.');
    return details;
}

function cellSelector(fixture) {
    return `.inv-room-slot[data-booking-id="${fixture.id}"][data-date="${fixture.date}"]`;
}

async function openFixture(cdp, fixture) {
    const selector = cellSelector(fixture);
    await navigate(cdp, appBase + '/inventory?start=' + fixture.date + '&room_no=' + encodeURIComponent(fixture.room));
    await waitFor(cdp, `document.querySelector(${JSON.stringify(selector)})`, `Inventory cell missing for booking ${fixture.id}.`);

    const tamperCheck = await evaluate(cdp, `(() => {
        const button = document.querySelector(${JSON.stringify(selector)});
        const originalId = button.dataset.bookingId;
        button.dataset.bookingId = 'abc';
        button.focus();
        button.click();
        const stayedClosed = document.getElementById('invGuestBackdrop').hidden;
        button.dataset.bookingId = originalId;
        button.dataset.bookingStatus = 'checked_out';
        button.focus();
        button.click();
        return stayedClosed;
    })()`);
    assert(tamperCheck, `Malformed booking ID opened the popup for ${fixture.id}.`);
    await waitFor(
        cdp,
        '!document.getElementById("invGuestBackdrop").hidden && document.querySelector("#invGuestModalBody .inv-detail-summary")',
        `Guest popup did not load for booking ${fixture.id}.`
    );
}

async function popupChecks(cdp, details) {
    for (const fixture of fixtures) {
        await cdp.send('Emulation.setDeviceMetricsOverride', { width: 1100, height: 760, deviceScaleFactor: 1, mobile: false });
        await openFixture(cdp, fixture);
        const expected = details.get(fixture.id);
        const popup = await evaluate(cdp, `(() => {
            const body = document.getElementById('invGuestModalBody');
            const action = document.getElementById('invGuestAction');
            return {
                text: body.textContent.replace(/\\s+/g, ' ').trim(),
                badge: body.querySelector('.erp-badge')?.textContent.trim(),
                actionText: action.textContent.trim(),
                actionPath: new URL(action.href).pathname,
                actionOrigin: new URL(action.href).origin,
                modalVisible: !document.getElementById('invGuestBackdrop').hidden
            };
        })()`);
        assert(popup.modalVisible, `Popup is hidden for booking ${fixture.id}.`);
        assert(popup.badge === expected.status_name, `Popup status mismatch for booking ${fixture.id}.`);
        assert(popup.actionText === fixture.action, `Popup action label mismatch for booking ${fixture.id}.`);
        assert(popup.actionPath === fixture.path, `Popup action route mismatch for booking ${fixture.id}.`);
        assert(popup.actionOrigin === new URL(appBase).origin, `Cross-origin action generated for booking ${fixture.id}.`);
        for (const value of [expected.booking_number, expected.customer_name, expected.phone, expected.allotted_room_no]) {
            assert(!value || popup.text.includes(String(value)), `Popup omitted identifying detail "${value}" for booking ${fixture.id}: ${popup.text}`);
        }

        const loaded = cdp.once('Page.loadEventFired');
        await evaluate(cdp, 'document.getElementById("invGuestAction").click()');
        await Promise.race([loaded, delay(10000)]);
        await waitFor(cdp, 'document.readyState === "complete"', `Workflow page did not load for booking ${fixture.id}.`);
        const workflowPage = await evaluate(cdp, `({ path: location.pathname, heading: document.querySelector('h1')?.textContent.replace(/\\s+/g, ' ').trim() || '', title: document.title })`);
        assert(workflowPage.path === fixture.path, `Action navigated to the wrong path for booking ${fixture.id}.`);
        assert((workflowPage.heading + ' ' + workflowPage.title).includes(fixture.page), `Expected ${fixture.page} page did not open for booking ${fixture.id}.`);
    }
}

async function existingFlowGuard(cdp) {
    await cdp.send('Emulation.setDeviceMetricsOverride', { width: 1100, height: 760, deviceScaleFactor: 1, mobile: false });
    await navigate(cdp, appBase + '/inventory?start=2026-08-18');
    await waitFor(cdp, 'document.querySelector("td.inv-cell-bookable .inv-bk")', 'No available room cell found for regression check.');
    const result = await evaluate(cdp, `(() => {
        document.querySelector('td.inv-cell-bookable .inv-bk').click();
        return {
            selectionOpen: !document.getElementById('invSelectionPopup').hidden,
            detailClosed: document.getElementById('invGuestBackdrop').hidden
        };
    })()`);
    assert(result.selectionOpen && result.detailClosed, 'Existing available-room selection flow changed.');

    await evaluate(cdp, 'document.getElementById("invCreateBooking").click()');
    await waitFor(
        cdp,
        '!document.getElementById("invBookingBackdrop").hidden && document.querySelector("form[data-booking-context=\\"inventory\\"]")',
        'Existing New Booking modal no longer loads from an available room.'
    );
    const bookingModalState = await evaluate(cdp, `({
        formLoaded: Boolean(document.querySelector('form[data-booking-context="inventory"]')),
        detailClosed: document.getElementById('invGuestBackdrop').hidden
    })`);
    assert(bookingModalState.formLoaded && bookingModalState.detailClosed, 'Occupied-room popup interfered with New Booking.');
    await evaluate(cdp, 'document.getElementById("invBookingClose").click()');
}

async function xssEscapingGuard(cdp, fixture) {
    await cdp.send('Emulation.setDeviceMetricsOverride', { width: 1100, height: 760, deviceScaleFactor: 1, mobile: false });
    await navigate(cdp, appBase + '/inventory?start=' + fixture.date + '&room_no=' + encodeURIComponent(fixture.room));
    await waitFor(cdp, `document.querySelector(${JSON.stringify(cellSelector(fixture))})`, 'XSS test fixture cell is missing.');
    const result = await evaluate(cdp, `new Promise((resolve, reject) => {
        const originalFetch = window.fetch;
        const maliciousName = '<img src=x onerror="window.__inventoryXss=1"> Test';
        window.__inventoryXss = 0;
        window.fetch = () => Promise.resolve(new Response(JSON.stringify({
            status: true,
            data: {
                id: ${fixture.id}, booking_number: '<script>window.__inventoryXss=1</script>',
                customer_name: maliciousName, customer_code: 'SAFE-1', phone: '9999999999', country: 'India',
                allotted_room_no: '${fixture.room}', room_category: 'Test', total_guest: 1, channel_name: 'Test',
                scheduled_check_in_date: '${fixture.date}', scheduled_check_out_date: '${fixture.date}',
                checked_in_at: null, checked_out_at: null, status_code: '${fixture.status}',
                status_name: '<b>Room Booked</b>',
                workflow: { url: location.origin + ${JSON.stringify(fixture.path)} }
            }
        }), { status: 200, headers: { 'Content-Type': 'application/json' } }));
        const button = document.querySelector(${JSON.stringify(cellSelector(fixture))});
        button.click();
        const started = Date.now();
        const timer = setInterval(() => {
            const body = document.getElementById('invGuestModalBody');
            if (body.querySelector('.inv-detail-summary')) {
                clearInterval(timer);
                window.fetch = originalFetch;
                const value = {
                    executed: window.__inventoryXss,
                    injectedNodes: body.querySelectorAll('img, script, b').length,
                    literalName: body.textContent.includes(maliciousName)
                };
                document.getElementById('invGuestClose').click();
                resolve(value);
            } else if (Date.now() - started > 5000) {
                clearInterval(timer);
                window.fetch = originalFetch;
                reject(new Error('Escaping test popup timed out.'));
            }
        }, 50);
    })`);
    assert(result.executed === 0 && result.injectedNodes === 0 && result.literalName, 'Popup rendered untrusted booking data as HTML.');
}

async function mobileChecks(cdp, fixture) {
    for (const viewport of [{ width: 320, height: 568 }, { width: 390, height: 844 }]) {
        await cdp.send('Emulation.setDeviceMetricsOverride', {
            width: viewport.width,
            height: viewport.height,
            deviceScaleFactor: 1,
            mobile: true
        });
        await openFixture(cdp, fixture);
        const layout = await evaluate(cdp, `(() => {
            const modal = document.getElementById('invGuestModal');
            const body = document.getElementById('invGuestModalBody');
            const action = document.getElementById('invGuestAction');
            const close = document.getElementById('invGuestClose');
            const rect = (element) => {
                const value = element.getBoundingClientRect();
                return { left: value.left, right: value.right, top: value.top, bottom: value.bottom, width: value.width, height: value.height };
            };
            return {
                modal: rect(modal), body: rect(body), action: rect(action), close: rect(close),
                viewport: { width: innerWidth, height: innerHeight },
                documentWidth: document.documentElement.scrollWidth,
                bodyScrollable: body.scrollHeight > body.clientHeight,
                actionVisible: getComputedStyle(action).display !== 'none'
            };
        })()`);
        assert(layout.modal.left >= -1 && layout.modal.right <= layout.viewport.width + 1, `Modal overflows ${viewport.width}px viewport horizontally.`);
        assert(layout.modal.top >= -1 && layout.modal.bottom <= layout.viewport.height + 1, `Modal overflows ${viewport.height}px viewport vertically.`);
        assert(layout.documentWidth <= layout.viewport.width + 1, `Document overflows at ${viewport.width}px.`);
        assert(layout.actionVisible, `Workflow action is hidden at ${viewport.width}px.`);
        assert(layout.action.width >= 40 && layout.action.height >= 40, `Workflow action touch target is too small at ${viewport.width}px.`);
        assert(layout.close.width >= 40 && layout.close.height >= 40, `Close touch target is too small at ${viewport.width}px.`);
        assert(layout.bodyScrollable || layout.body.bottom <= layout.modal.bottom + 1, `Detail body cannot be reached at ${viewport.width}px.`);

        if (viewport.width === 390) {
            const screenshot = await cdp.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
            writeFileSync(screenshotPath, Buffer.from(screenshot.data, 'base64'));
        }

        await cdp.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape' });
        await cdp.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape' });
        const focusRestored = await evaluate(cdp, `document.getElementById('invGuestBackdrop').hidden && document.activeElement === document.querySelector(${JSON.stringify(cellSelector(fixture))})`);
        assert(focusRestored, `Escape did not close and restore focus at ${viewport.width}px.`);
    }
}

let chrome;
let cdp;
let testError;
try {
    const loginContext = await login();
    const cookie = loginContext.cookie;
    const details = await endpointChecks(cookie, loginContext.contextToken);
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
    await cdp.send('Network.setCookie', {
        name: 'ci_session', value: cookie, url: appBase + '/', path: '/', httpOnly: true
    });

    await popupChecks(cdp, details);
    await existingFlowGuard(cdp);
    await xssEscapingGuard(cdp, fixtures[0]);
    await mobileChecks(cdp, fixtures[2]);
    console.log('PASS: Inventory occupied-booking popup, workflow routing, privacy, and responsive checks passed.');
    console.log('Screenshot: ' + screenshotPath);
} catch (error) {
    testError = error;
} finally {
    if (cdp) {
        try { await Promise.race([cdp.send('Browser.close'), delay(1500)]); } catch (_) {}
        cdp.close();
    }
    if (chrome && !chrome.killed) { chrome.kill(); }
    await delay(500);
    try {
        rmSync(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 150 });
    } catch (_) {
        // Chrome may briefly retain a Windows profile handle after Browser.close.
    }
}
if (testError) { throw testError; }
