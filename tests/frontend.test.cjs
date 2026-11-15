const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');

const frontScript = readFileSync(__dirname + '/../views/js/front.js', 'utf8');
const adminScript = readFileSync(__dirname + '/../views/js/admin-order-detail.js', 'utf8');

test('checkout script works without jQuery in PS 9.1 Hummingbird and adds no click guard', () => {
    const classes = [];
    const listeners = [];
    const img = {
        classList: { add(name) { classes.push(name); } },
        addEventListener(type, handler) { listeners.push({ type, handler }); },
    };
    const document = {
        readyState: 'complete',
        querySelectorAll(selector) {
            return selector === '.payment-option label img[src*="pays_ps-payment"]' ? [img] : [];
        },
        addEventListener(type, handler) { listeners.push({ type, handler }); },
    };
    vm.runInNewContext(frontScript, { document, window: {} });
    assert.deepEqual(classes, ['pays_ps-option-img']);
    assert.equal(listeners.filter((l) => l.type === 'click').length, 0);
});

test('checkout script defers until DOMContentLoaded when async script loads early', () => {
    const classes = [];
    let domReady = null;
    const img = { classList: { add(name) { classes.push(name); } } };
    const document = {
        readyState: 'loading',
        querySelectorAll() { return [img]; },
        addEventListener(type, handler) {
            if (type === 'DOMContentLoaded') { domReady = handler; }
        },
    };
    vm.runInNewContext(frontScript, { document, window: {} });
    assert.deepEqual(classes, []);
    assert.equal(typeof domReady, 'function');
    domReady();
    assert.deepEqual(classes, ['pays_ps-option-img']);
});

test('admin order script works without jQuery and preserves panel when container is absent', async () => {
    const appended = [];
    const copied = [];
    const alerts = [];
    let clickHandler = null;
    const panel = { id: 'pays_ps-payment' };
    const copyButton = {
        addEventListener(type, handler) {
            if (type === 'click') { clickHandler = handler; }
        },
    };
    const copySource = { textContent: 'https://www.pays.cz/paymentorder?Merchant=1' };
    const makeDocument = (target) => ({
        readyState: 'complete',
        getElementById(id) {
            if (id === 'pays_ps-payment') { return panel; }
            if (id === 'pays_ps-paymentUrlCopyButton') { return copyButton; }
            if (id === 'pays_ps-paymentUrlCopy') { return copySource; }
            return null;
        },
        querySelector(selector) {
            return selector === '#view_order_payments_block .card-body' ? target : null;
        },
        addEventListener() {},
    });

    vm.runInNewContext(adminScript, {
        document: makeDocument(null),
        navigator: { clipboard: { writeText(t) { copied.push(t); return Promise.resolve(); } } },
        alert(msg) { alerts.push(msg); },
    });
    assert.deepEqual(appended, []);
    assert.equal(typeof clickHandler, 'function');

    const target = { appendChild(el) { appended.push(el); } };
    vm.runInNewContext(adminScript, {
        document: makeDocument(target),
        navigator: { clipboard: { writeText(t) { copied.push(t); return Promise.resolve(); } } },
        alert(msg) { alerts.push(msg); },
    });
    assert.deepEqual(appended, [panel]);
    clickHandler();
    await Promise.resolve();
    assert.deepEqual(copied, ['https://www.pays.cz/paymentorder?Merchant=1']);
    assert.deepEqual(alerts, ['Copied to clipboard']);
});
