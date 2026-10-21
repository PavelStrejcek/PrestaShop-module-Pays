const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');

test('module does not block checkout retries or other payment methods', () => {
    const handlers = [];
    const classes = [];
    const $ = (selector) => {
        if (typeof selector === 'function') { selector(); return; }
        return {
            click(handler) { handlers.push(handler); return this; },
            addClass(name) { classes.push(name); return this; },
        };
    };
    vm.runInNewContext(readFileSync(__dirname + '/../views/js/front.js', 'utf8'), { $, window: {} });
    for (const handler of handlers) {
        handler({});
        assert.notEqual(handler({}), false, 'a second checkout click must remain possible');
    }
    assert.ok(classes.includes('pays_ps-option-img'));
});
