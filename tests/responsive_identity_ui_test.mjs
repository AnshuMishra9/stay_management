import { spawn } from 'node:child_process';
import { writeFileSync, mkdtempSync, mkdirSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createServer } from 'node:net';

const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const appBase = 'http://localhost/stay_management';
const mobile = process.env.STAY_TEST_MOBILE || '9988776655';
const existingBookingId = Number.parseInt(process.env.STAY_EXISTING_BOOKING_ID || '', 10) || 0;
const emptyBookingId = Number.parseInt(process.env.STAY_EMPTY_BOOKING_ID || '', 10) || 0;
const mobileUserAgent = 'Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0 Mobile Safari/537.36';
const desktopUserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0 Safari/537.36';
const profile = mkdtempSync(join(tmpdir(), 'stay-responsive-chrome-'));
const screenshotDir = join(tmpdir(), 'stay-responsive-screens');
mkdirSync(screenshotDir, { recursive: true });

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
    if (!otpPayload.status || !cookie) { throw new Error('OTP test login could not start.'); }

    const verifyResponse = await fetch(appBase + '/auth/verify_otp', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Cookie': 'ci_session=' + cookie
        },
        body: JSON.stringify({ mobile_no: mobile, otp: String(otpPayload.otp) })
    });
    cookie = sessionCookie(verifyResponse) || cookie;
    const verifyPayload = await verifyResponse.json();
    if (!verifyPayload.status) { throw new Error('OTP test login failed.'); }
    return cookie;
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

const delay = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

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

const layoutExpression = `(label) => {
    const get = (selector) => {
        const element = document.querySelector(selector);
        if (!element) return null;
        const rect = element.getBoundingClientRect();
        return { left: rect.left, right: rect.right, top: rect.top, bottom: rect.bottom, width: rect.width, height: rect.height };
    };
    const visible = [...document.body.querySelectorAll('*')].filter((element) => {
        const style = getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return style.position !== 'absolute' && style.position !== 'fixed' && style.display !== 'none' && rect.width > 0;
    });
    const offenders = visible.filter((element) => {
        if (element.closest('.erp-subnav, .erp-nav-right, .erp-table-scroll')) return false;
        const rect = element.getBoundingClientRect();
        return rect.left < -1 || rect.right > innerWidth + 1;
    }).slice(0, 8).map((element) => {
        const className = typeof element.className === 'string'
            ? element.className
            : (element.className?.baseVal || '');
        return className || element.tagName;
    });
    const touchTargets = [...document.querySelectorAll(
        '.idn-remove, .idn-upload-actions .erp-btn, .idn-slot-actions .idn-mini-btn, ' +
        '.idn-preview-remove, ' +
        '.idn-ratio-btn, .idn-crop-close, .idn-crop-foot .erp-btn, ' +
        '.idn-camera-close, .idn-camera-foot button'
    )].filter((element) => {
        const style = getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
    }).map((element) => {
        const rect = element.getBoundingClientRect();
        return {
            name: element.getAttribute('data-idn-action') || element.getAttribute('data-camera-action') ||
                element.getAttribute('data-crop-action') || element.className || element.tagName,
            width: rect.width,
            height: rect.height
        };
    });
    return {
        label,
        viewport: { width: innerWidth, height: innerHeight },
        documentWidth: document.documentElement.scrollWidth,
        bodyWidth: document.body.scrollWidth,
        offenders,
        touchTargets,
        form: get('form[data-booking-form]'),
        identityRow: get('.idn-row'),
        uploadWidget: get('[data-idn-upload]'),
        cropDialog: get('.idn-crop-dialog:not([hidden])'),
        cropToolbar: get('.idn-crop-toolbar'),
        cropCanvas: get('.idn-crop-stage canvas'),
        cropFooter: get('.idn-crop-foot'),
        cameraDialog: get('.idn-camera-dialog:not([hidden])'),
        cameraStage: get('.idn-camera-stage'),
        cameraFooter: get('.idn-camera-foot')
    };
}`;

function assertLayout(layout) {
    const errors = [];
    if (layout.documentWidth > layout.viewport.width + 1 || layout.bodyWidth > layout.viewport.width + 1) {
        errors.push('horizontal document overflow');
    }
    if (layout.offenders.length) { errors.push('overflowing elements: ' + layout.offenders.join(', ')); }
    ['form', 'identityRow', 'uploadWidget', 'cropDialog', 'cameraDialog'].forEach((key) => {
        const rect = layout[key];
        if (rect && (rect.left < -1 || rect.right > layout.viewport.width + 1)) {
            errors.push(key + ' leaves viewport');
        }
    });
    ['cropDialog', 'cropToolbar', 'cropCanvas', 'cropFooter', 'cameraDialog', 'cameraStage', 'cameraFooter'].forEach((key) => {
        const rect = layout[key];
        if (rect && rect.height > 0 && (rect.top < -1 || rect.bottom > layout.viewport.height + 1)) {
            errors.push(key + ' leaves viewport vertically');
        }
    });
    const smallTargets = layout.viewport.width <= 720
        ? layout.touchTargets.filter((target) => {
            const minimum = target.name === 'clear' ? 32 : 40;
            return target.width < minimum || target.height < minimum;
        })
        : [];
    if (smallTargets.length) {
        errors.push('touch targets below 40px: ' + smallTargets.map((target) => target.name).join(', '));
    }
    if (layout.label === 'base-320' && layout.uploadWidget && layout.uploadWidget.height > 600) {
        errors.push('compact uploader exceeds 600px height');
    }
    if (errors.length) { throw new Error(layout.label + ': ' + errors.join('; ')); }
}

async function screenshot(cdp, name) {
    const result = await cdp.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
    const path = join(screenshotDir, name + '.png');
    writeFileSync(path, Buffer.from(result.data, 'base64'));
    return path;
}

let chrome;
let cdp;
try {
    const cookie = await login();
    const port = await freePort();
    chrome = spawn(chromePath, [
        '--headless=new', '--no-sandbox', '--disable-gpu', '--disable-crash-reporter', '--disable-breakpad',
        '--no-first-run', '--no-default-browser-check', '--remote-allow-origins=*',
        '--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream',
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
    await cdp.send('Network.setUserAgentOverride', { userAgent: mobileUserAgent });

    await cdp.send('Emulation.setDeviceMetricsOverride', {
        width: 390, height: 844, deviceScaleFactor: 1, mobile: true
    });
    const loaded = cdp.once('Page.loadEventFired');
    await cdp.send('Page.navigate', { url: appBase + '/customers/bookings/checkin/7' });
    await Promise.race([loaded, delay(10000)]);
    await delay(500);
    const title = await evaluate(cdp, 'document.title');
    if (!title.includes('Check-in') || !await evaluate(cdp, 'Boolean(document.querySelector("[data-idn-upload]"))')) {
        throw new Error('Authenticated Check-in page did not load.');
    }

    await evaluate(cdp, `new Promise((resolve, reject) => {
        const canvas = document.createElement('canvas');
        canvas.width = 900; canvas.height = 600;
        const context = canvas.getContext('2d');
        context.fillStyle = '#ece8d4'; context.fillRect(0, 0, 900, 600);
        context.fillStyle = '#423b2f'; context.fillRect(55, 55, 790, 490);
        context.fillStyle = '#fff'; context.font = '46px sans-serif'; context.fillText('RESPONSIVE TEST DOCUMENT', 100, 300);
        canvas.toBlob((blob) => {
            try {
                const file = new File([blob], 'responsive-test.jpg', { type: 'image/jpeg' });
                const transfer = new DataTransfer(); transfer.items.add(file);
                const input = document.querySelector('[data-idn-input]');
                input.files = transfer.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                const started = Date.now();
                const timer = setInterval(() => {
                    const crop = document.querySelector('[data-idn-action="crop"]');
                    if (crop && !crop.disabled) { clearInterval(timer); resolve(true); }
                    else if (Date.now() - started > 6000) { clearInterval(timer); reject(new Error('Image preparation timed out')); }
                }, 50);
            } catch (error) { reject(error); }
        }, 'image/jpeg', .9);
    })`);

    const mobileSecondImageFlow = await evaluate(cdp, `new Promise((resolve, reject) => {
        const field = document.querySelector('.idn-document-field');
        const addSecond = field.querySelector('[data-idn-add-second]');
        const secondSlot = field.querySelector('[data-idn-slot="2"]');
        const before = {
            label: addSecond.textContent.trim().replace(/\s+/g, ' '),
            actionVisible: getComputedStyle(addSecond).display !== 'none',
            slotHidden: getComputedStyle(secondSlot).display === 'none',
            expanded: addSecond.getAttribute('aria-expanded')
        };
        addSecond.click();
        const revealed = {
            actionHidden: getComputedStyle(addSecond).display === 'none',
            slotVisible: getComputedStyle(secondSlot).display !== 'none',
            chooseFocused: document.activeElement === secondSlot.querySelector('[data-idn-action="choose-slot"]')
        };

        const canvas = document.createElement('canvas');
        canvas.width = 640; canvas.height = 400;
        const context = canvas.getContext('2d');
        context.fillStyle = '#e8e5ff'; context.fillRect(0, 0, canvas.width, canvas.height);
        context.fillStyle = '#5646e0'; context.font = '32px sans-serif'; context.fillText('IMAGE 2 TEST', 190, 210);
        canvas.toBlob((blob) => {
            try {
                const file = new File([blob], 'image-2-test.jpg', { type: 'image/jpeg' });
                const transfer = new DataTransfer(); transfer.items.add(file);
                const input = secondSlot.querySelector('[data-idn-input]');
                input.files = transfer.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                const started = Date.now();
                const timer = setInterval(() => {
                    const crop = secondSlot.querySelector('[data-idn-action="crop"]');
                    const preview = secondSlot.querySelector('[data-idn-preview]');
                    if (!crop.disabled && !preview.hidden) {
                        clearInterval(timer);
                        resolve({ before, revealed, uploadReady: true, cropEnabled: true, previewVisible: true });
                    } else if (Date.now() - started > 6000) {
                        clearInterval(timer);
                        reject(new Error('Image 2 preparation timed out'));
                    }
                }, 50);
            } catch (error) { reject(error); }
        }, 'image/jpeg', .88);
    })`);
    if (
        mobileSecondImageFlow.before.label !== 'Image 2' ||
        !mobileSecondImageFlow.before.actionVisible || !mobileSecondImageFlow.before.slotHidden ||
        mobileSecondImageFlow.before.expanded !== 'false' ||
        !mobileSecondImageFlow.revealed.actionHidden || !mobileSecondImageFlow.revealed.slotVisible ||
        !mobileSecondImageFlow.revealed.chooseFocused || !mobileSecondImageFlow.uploadReady ||
        !mobileSecondImageFlow.cropEnabled || !mobileSecondImageFlow.previewVisible
    ) {
        throw new Error('Mobile Image 2 reveal/upload flow failed: ' + JSON.stringify(mobileSecondImageFlow));
    }

    const compactUploader = await evaluate(cdp, `(() => {
        const slots = [...document.querySelectorAll('[data-idn-slot]')];
        return {
            labels: slots.map((slot) => slot.querySelector('.idn-slot-head strong')?.textContent.trim()),
            helperParagraphs: document.querySelectorAll('.idn-upload-hint').length,
            viewLinks: document.querySelectorAll('.idn-current-link').length,
            actionRows: slots.map((slot) => {
                const buttons = [...slot.querySelectorAll('.idn-slot-actions button')];
                const tops = buttons.map((button) => Math.round(button.getBoundingClientRect().top));
                return { count: buttons.length, rows: new Set(tops).size };
            }),
            removeButtons: slots.map((slot) => {
                const button = slot.querySelector('[data-idn-action="clear"]');
                const style = button ? getComputedStyle(button) : null;
                return {
                    parentIsHeader: Boolean(button && button.parentElement.classList.contains('idn-slot-head')),
                    text: button ? button.textContent.trim() : null,
                    background: style ? style.backgroundColor : null,
                    borderWidth: style ? style.borderTopWidth : null,
                    boxShadow: style ? style.boxShadow : null
                };
            })
        };
    })()`);
    if (
        JSON.stringify(compactUploader.labels) !== JSON.stringify(['Image 1', 'Image 2']) ||
        compactUploader.helperParagraphs !== 0 ||
        compactUploader.viewLinks !== 0 ||
        compactUploader.actionRows.some((row) => row.count !== 3 || row.rows !== 1) ||
        compactUploader.removeButtons.some((button) =>
            !button.parentIsHeader || button.text !== '\u00d7' ||
            button.background !== 'rgba(0, 0, 0, 0)' || button.borderWidth !== '0px' || button.boxShadow !== 'none'
        )
    ) {
        throw new Error('Compact Image 1/Image 2 uploader contract failed: ' + JSON.stringify(compactUploader));
    }

    const results = [];
    const screenshots = [];
    const spacingResults = [];
    let ratioCycle = null;
    let existingSavedUi = null;
    let sameCustomerEmptyUi = null;
    let mobileCollapsedUi = null;
    let mobileNativeCameraUi = null;
    let laptopCameraUi = null;
    for (const viewport of [
        { width: 320, height: 568 },
        { width: 360, height: 640 },
        { width: 390, height: 844 },
        { width: 568, height: 320 },
        { width: 640, height: 360 },
        { width: 768, height: 1024 },
        { width: 1024, height: 768 }
    ]) {
        await cdp.send('Emulation.setDeviceMetricsOverride', {
            width: viewport.width, height: viewport.height, deviceScaleFactor: 1, mobile: viewport.width <= 720
        });
        await delay(150);
        const base = await evaluate(cdp, `(${layoutExpression})('base-${viewport.width}')`);
        assertLayout(base);
        results.push(base);
        const spacing = await evaluate(cdp, `(() => {
            const documentField = document.querySelector('.idn-document-field');
            const documentHeading = documentField && documentField.querySelector('.idn-document-heading');
            const uploadWidget = documentField && documentField.querySelector('.idn-upload-widget');
            const lastIdentityRow = document.querySelector('#identityRows .idn-row:last-child');
            const statusSection = document.querySelector('.erp-checkin-status-section');
            const statusTitle = statusSection && statusSection.querySelector('.erp-section-title');
            const statusGrid = statusSection && statusSection.querySelector('.erp-checkin-grid');
            const gap = (after, before) => after && before
                ? Math.round((after.getBoundingClientRect().top - before.getBoundingClientRect().bottom) * 10) / 10
                : null;
            return {
                viewport: ${JSON.stringify(viewport)},
                documentHeadingGap: gap(uploadWidget, documentHeading),
                identityToStatusTitleGap: gap(statusTitle, lastIdentityRow),
                statusTitleGap: gap(statusGrid, statusTitle)
            };
        })()`);
        if (
            spacing.documentHeadingGap < 3 || spacing.documentHeadingGap > 6 ||
            spacing.identityToStatusTitleGap < 18 || spacing.identityToStatusTitleGap > 28 ||
            spacing.statusTitleGap < 10 || spacing.statusTitleGap > 14
        ) {
            throw new Error('Check-in spacing contract failed: ' + JSON.stringify(spacing));
        }
        spacingResults.push(spacing);
        if (viewport.width === 320) {
            screenshots.push(await screenshot(cdp, 'base-320x568'));
            await evaluate(cdp, `document.querySelector('.idn-row').scrollIntoView({ block: 'start' })`);
            await delay(100);
            screenshots.push(await screenshot(cdp, 'identity-card-320x568'));
            await evaluate(cdp, `document.querySelector('.erp-checkin-status-section').scrollIntoView({ block: 'start' })`);
            await delay(100);
            screenshots.push(await screenshot(cdp, 'status-spacing-320x568'));
            await evaluate(cdp, `scrollTo(0, 0)`);
        }

        await evaluate(cdp, `new Promise((resolve) => {
            document.querySelector('[data-idn-action="crop"]').click();
            const timer = setInterval(() => {
                const modal = document.querySelector('.idn-crop-modal');
                if (modal && !modal.hidden) { clearInterval(timer); resolve(true); }
            }, 25);
        })`);
        await delay(100);
        if (viewport.width === 320) {
            ratioCycle = await evaluate(cdp, `(() => {
                const select = (ratio) => {
                    document.querySelector('[data-crop-ratio="' + ratio + '"]').click();
                    return document.querySelector('[data-crop-size]').textContent;
                };
                return {
                    sixteenFirst: select('16:9'),
                    sixteenRepeat: select('16:9'),
                    nineFirst: select('9:16'),
                    sixteenSecond: select('16:9'),
                    nineSecond: select('9:16'),
                    sixteenThird: select('16:9')
                };
            })()`);
            if (
                ratioCycle.sixteenFirst !== ratioCycle.sixteenRepeat ||
                ratioCycle.sixteenFirst !== ratioCycle.sixteenSecond ||
                ratioCycle.sixteenFirst !== ratioCycle.sixteenThird ||
                ratioCycle.nineFirst !== ratioCycle.nineSecond
            ) {
                throw new Error('Crop coverage changed while repeatedly switching 16:9 and 9:16: ' + JSON.stringify(ratioCycle));
            }
            await evaluate(cdp, `document.querySelector('[data-crop-ratio="free"]').click()`);
        }
        const crop = await evaluate(cdp, `(${layoutExpression})('crop-${viewport.width}')`);
        assertLayout(crop);
        results.push(crop);
        if (viewport.width === 320 || viewport.height === 320) {
            screenshots.push(await screenshot(cdp, `crop-${viewport.width}x${viewport.height}`));
        }
        await evaluate(cdp, `document.querySelector('.idn-crop-close').click()`);

        await evaluate(cdp, `document.querySelector('[data-idn-action="camera-next"]').click()`);
        await delay(350);
        if (viewport.width === 320) {
            mobileNativeCameraUi = await evaluate(cdp, `(() => {
                const native = document.querySelector('[data-camera-action="native"]');
                return {
                    hiddenAttribute: Boolean(native && native.hidden),
                    visible: Boolean(native && !native.hidden && getComputedStyle(native).display !== 'none'),
                    label: native ? native.textContent.trim().replace(/\s+/g, ' ') : null
                };
            })()`);
            if (mobileNativeCameraUi.hiddenAttribute || !mobileNativeCameraUi.visible || mobileNativeCameraUi.label !== 'Device camera') {
                throw new Error('Mobile native camera fallback is unavailable: ' + JSON.stringify(mobileNativeCameraUi));
            }
        }
        const camera = await evaluate(cdp, `(${layoutExpression})('camera-${viewport.width}')`);
        assertLayout(camera);
        results.push(camera);
        if (viewport.width === 320 || viewport.height === 320) {
            screenshots.push(await screenshot(cdp, `camera-${viewport.width}x${viewport.height}`));
        }
        if (viewport.width === 320) {
            mobileNativeCameraUi.clickResult = await evaluate(cdp, `(() => {
                const native = document.querySelector('[data-camera-action="native"]');
                const fallback = document.querySelector('[data-idn-source="camera"]');
                let fallbackClicks = 0;
                fallback.click = () => { fallbackClicks += 1; };
                native.click();
                return {
                    fallbackClicks,
                    modalClosed: document.querySelector('.idn-camera-modal').hidden
                };
            })()`);
            if (mobileNativeCameraUi.clickResult.fallbackClicks !== 1 || !mobileNativeCameraUi.clickResult.modalClosed) {
                throw new Error('Mobile Device camera did not open its native capture input: ' + JSON.stringify(mobileNativeCameraUi));
            }
        }
        await evaluate(cdp, `document.querySelector('.idn-camera-close').click()`);
    }

    if (existingBookingId) {
        await cdp.send('Emulation.setDeviceMetricsOverride', {
            width: 320, height: 568, deviceScaleFactor: 1, mobile: true
        });
        const existingLoaded = cdp.once('Page.loadEventFired');
        await cdp.send('Page.navigate', { url: appBase + '/customers/bookings/checkin/' + existingBookingId });
        await Promise.race([existingLoaded, delay(10000)]);
        await delay(400);
        existingSavedUi = await evaluate(cdp, `new Promise((resolve) => {
            const slot = [...document.querySelectorAll('[data-idn-slot]')].find((item) =>
                item.getAttribute('data-existing') === '1' && item.getAttribute('data-existing-kind') === 'image'
            );
            if (!slot) { resolve({ found: false }); return; }
            const preview = slot.querySelector('[data-idn-preview]');
            const finish = () => {
                const actions = [...slot.querySelectorAll('.idn-slot-actions button')];
                resolve({
                    found: true,
                    previewLoaded: !preview.hidden && preview.complete && preview.naturalWidth > 0,
                    crossVisible: !slot.querySelector('[data-idn-action="clear"]').hidden,
                    cropEnabled: !slot.querySelector('[data-idn-action="crop"]').disabled,
                    viewLinks: slot.querySelectorAll('.idn-current-link').length,
                    actionRows: new Set(actions.map((button) => Math.round(button.getBoundingClientRect().top))).size
                });
            };
            if (preview.complete) { finish(); }
            else {
                preview.addEventListener('load', finish, { once: true });
                preview.addEventListener('error', finish, { once: true });
                setTimeout(finish, 5000);
            }
        })`);
        if (
            !existingSavedUi.found || !existingSavedUi.previewLoaded || !existingSavedUi.crossVisible ||
            !existingSavedUi.cropEnabled || existingSavedUi.viewLinks !== 0 || existingSavedUi.actionRows !== 1
        ) {
            throw new Error('Saved-image thumbnail/action contract failed: ' + JSON.stringify(existingSavedUi));
        }
        await evaluate(cdp, `document.querySelector('[data-existing="1"][data-existing-kind="image"]').scrollIntoView({ block: 'center' })`);
        await delay(150);
        screenshots.push(await screenshot(cdp, 'saved-image-320x568'));

        await evaluate(cdp, `document.querySelector('[data-existing="1"][data-existing-kind="image"] [data-idn-action="crop"]').click()`);
        await evaluate(cdp, `new Promise((resolve, reject) => {
            const started = Date.now();
            const timer = setInterval(() => {
                const modal = document.querySelector('.idn-crop-modal');
                if (modal && !modal.hidden) { clearInterval(timer); resolve(true); }
                else if (Date.now() - started > 7000) { clearInterval(timer); reject(new Error('Saved-image crop did not open')); }
            }, 40);
        })`);
        existingSavedUi.cropOpened = true;
        await evaluate(cdp, `document.querySelector('.idn-crop-close').click()`);
        existingSavedUi.removalState = await evaluate(cdp, `(() => {
            const slot = document.querySelector('[data-existing="1"][data-existing-kind="image"]');
            slot.querySelector('[data-idn-action="clear"]').click();
            return {
                flag: slot.querySelector('[data-idn-remove-input]').value,
                previewHidden: slot.querySelector('[data-idn-preview]').hidden,
                crossHidden: slot.querySelector('[data-idn-action="clear"]').hidden,
                cropDisabled: slot.querySelector('[data-idn-action="crop"]').disabled
            };
        })()`);
        if (
            existingSavedUi.removalState.flag !== '1' || !existingSavedUi.removalState.previewHidden ||
            !existingSavedUi.removalState.crossHidden || !existingSavedUi.removalState.cropDisabled
        ) {
            throw new Error('Saved-image removal state failed: ' + JSON.stringify(existingSavedUi.removalState));
        }
    }

    if (emptyBookingId) {
        const emptyLoaded = cdp.once('Page.loadEventFired');
        await cdp.send('Page.navigate', { url: appBase + '/customers/bookings/checkin/' + emptyBookingId });
        await Promise.race([emptyLoaded, delay(10000)]);
        await delay(300);
        sameCustomerEmptyUi = await evaluate(cdp, `(() => {
            const slots = [...document.querySelectorAll('[data-idn-slot]')];
            return {
                existingSlots: slots.filter((slot) => slot.getAttribute('data-existing') === '1').length,
                loadedPreviews: slots.filter((slot) => {
                    const preview = slot.querySelector('[data-idn-preview]');
                    return preview && !preview.hidden && preview.getAttribute('src');
                }).length,
                visibleCrosses: slots.filter((slot) => !slot.querySelector('[data-idn-action="clear"]').hidden).length,
                enabledCrops: slots.filter((slot) => !slot.querySelector('[data-idn-action="crop"]').disabled).length
            };
        })()`);
        if (
            sameCustomerEmptyUi.existingSlots !== 0 || sameCustomerEmptyUi.loadedPreviews !== 0 ||
            sameCustomerEmptyUi.visibleCrosses !== 0 || sameCustomerEmptyUi.enabledCrops !== 0
        ) {
            throw new Error('Documents leaked into an empty booking: ' + JSON.stringify(sameCustomerEmptyUi));
        }

        await cdp.send('Emulation.setDeviceMetricsOverride', {
            width: 390, height: 844, deviceScaleFactor: 1, mobile: true
        });
        await delay(150);
        mobileCollapsedUi = await evaluate(cdp, `(() => {
            const field = document.querySelector('.idn-document-field');
            const action = field.querySelector('[data-idn-add-second]');
            const second = field.querySelector('[data-idn-slot="2"]');
            return {
                actionVisible: getComputedStyle(action).display !== 'none',
                secondHidden: getComputedStyle(second).display === 'none',
                label: action.textContent.trim().replace(/\s+/g, ' ')
            };
        })()`);
        if (!mobileCollapsedUi.actionVisible || !mobileCollapsedUi.secondHidden || mobileCollapsedUi.label !== 'Image 2') {
            throw new Error('Empty booking mobile Image 2 state failed: ' + JSON.stringify(mobileCollapsedUi));
        }
        await evaluate(cdp, `document.querySelector('.idn-document-heading').scrollIntoView({ block: 'center' })`);
        await delay(100);
        screenshots.push(await screenshot(cdp, 'image-2-action-390x844'));
    }

    await cdp.send('Network.setUserAgentOverride', { userAgent: desktopUserAgent });
    await cdp.send('Emulation.setDeviceMetricsOverride', {
        width: 1024, height: 768, deviceScaleFactor: 1, mobile: false
    });
    const laptopLoaded = cdp.once('Page.loadEventFired');
    await cdp.send('Page.navigate', { url: appBase + '/customers/bookings/checkin/7' });
    await Promise.race([laptopLoaded, delay(10000)]);
    await delay(300);
    await evaluate(cdp, `document.querySelector('[data-idn-action="camera-next"]').click()`);
    await delay(350);
    screenshots.push(await screenshot(cdp, 'laptop-camera-1024x768'));
    laptopCameraUi = await evaluate(cdp, `(() => {
        const native = document.querySelector('[data-camera-action="native"]');
        const capture = document.querySelector('[data-camera-action="capture"]');
        const switcher = document.querySelector('[data-camera-action="switch"]');
        const fallback = document.querySelector('[data-idn-source="camera"]');
        let fallbackClicks = 0;
        fallback.click = () => { fallbackClicks += 1; };
        const nativeHiddenAttribute = Boolean(native && native.hidden);
        const nativeDisplayNone = Boolean(native && getComputedStyle(native).display === 'none');
        native.hidden = false;
        native.click();
        return {
            nativeHiddenAttribute,
            nativeDisplayNone,
            captureVisible: Boolean(capture && getComputedStyle(capture).display !== 'none'),
            switchVisible: Boolean(switcher && getComputedStyle(switcher).display !== 'none'),
            fallbackClicks,
            modalStillOpen: !document.querySelector('.idn-camera-modal').hidden
        };
    })()`);
    if (
        !laptopCameraUi.nativeHiddenAttribute || !laptopCameraUi.nativeDisplayNone ||
        !laptopCameraUi.captureVisible || !laptopCameraUi.switchVisible ||
        laptopCameraUi.fallbackClicks !== 0 || !laptopCameraUi.modalStillOpen
    ) {
        throw new Error('Laptop camera opened a file picker: ' + JSON.stringify(laptopCameraUi));
    }
    await evaluate(cdp, `document.querySelector('.idn-camera-close').click()`);

    console.log(JSON.stringify({
        passed: true, mobileSecondImageFlow, compactUploader, spacingResults, ratioCycle, existingSavedUi, sameCustomerEmptyUi,
        mobileCollapsedUi, mobileNativeCameraUi, laptopCameraUi, results, screenshots
    }, null, 2));
} finally {
    if (cdp) { cdp.close(); }
    if (chrome) { chrome.kill(); }
    await delay(200);
    if (profile.startsWith(tmpdir())) {
        try {
            rmSync(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 150 });
        } catch (error) {
            // Windows can briefly retain Chrome profile locks after the
            // browser exits. A temp cleanup race must not mask test results.
            if (!error || (error.code !== 'EPERM' && error.code !== 'EBUSY')) { throw error; }
        }
    }
}
