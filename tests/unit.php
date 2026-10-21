<?php
/** Dependency-free regression tests; run with PHP 8.1 or later. */
error_reporting(E_ALL);
define('_PS_VERSION_', '9.0.0');
define('_MODULE_DIR_', '/modules/');
require __DIR__ . '/support/platform.php';
require dirname(__DIR__) . '/pays_ps.php';
require dirname(__DIR__) . '/controllers/front/payment.php';

$tests = [];
function test($name, $body) { global $tests; $tests[$name] = $body; }
function same($expected, $actual) {
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
function module() { return new Pays_PS(); }
function signature($values, $password) { return hash_hmac('md5', implode('', array_slice($values, 0, 6)), $password); }

test('PS9 is declared compatible and the validation controller exists', function () {
    $m = module();
    same(true, version_compare('9.0.0', $m->ps_versions_compliancy['min'], '>='));
    same(true, version_compare('9.0.0', $m->ps_versions_compliancy['max'], '<='));
    foreach ($m->controllers as $controller) {
        same(true, is_file(dirname(__DIR__) . '/controllers/front/' . $controller . '.php'));
    }
});
test('module construction on PHP 8.2+ does not create dynamic properties', function () {
    set_error_handler(function ($level, $message) { throw new RuntimeException($message); });
    try { module(); } finally { restore_error_handler(); }
});
test('callback with an empty configured password is rejected', function () {
    Configuration::$values['PAYS_PS_PASSWORD'] = '';
    $p = ['123', 'ABCDEFGHI', '3', 'CZK', '10000', '100'];
    same(false, module()->validateResponseHash(...array_merge($p, [signature($p, '')])));
});
test('valid Pays HMAC-MD5 is accepted', function () {
    Configuration::$values['PAYS_PS_PASSWORD'] = 'test-only-secret';
    $p = ['123', 'ABCDEFGHI', '3', 'CZK', '10000', '100'];
    same(true, module()->validateResponseHash(...array_merge($p, [signature($p, 'test-only-secret')])));
});
foreach (['PaymentOrderID', 'MerchantOrderNumber', 'PaymentOrderStatusID', 'CurrencyID', 'Amount', 'CurrencyBaseUnits'] as $index => $field) {
    test('tampered signed ' . $field . ' is rejected', function () use ($index) {
        Configuration::$values['PAYS_PS_PASSWORD'] = 'test-only-secret';
        $p = ['123', 'ABCDEFGHI', '3', 'CZK', '10000', '100'];
        $hash = signature($p, 'test-only-secret');
        $p[$index] .= '1';
        same(false, module()->validateResponseHash(...array_merge($p, [$hash])));
    });
}
foreach ([['123', 'ABCDEFGHI', '3', 'CZK', '10000', '0'], ['123', 'ABCDEFGHI', '9', 'CZK', '10000', '100'], ['123', 'ABCDEFGHI', '3', 'CZK', '-1', '100'], ['123', 'ABCDEFGHI', '3', 'CZK', '1e4', '100'], ['123', 'ABCDEFGHI', '3', 'CZK', '10000', '3'], ['123', '', '3', 'CZK', '10000', '100']] as $i => $values) {
    test('malformed but correctly signed callback #' . $i . ' is rejected', function () use ($values) {
        Configuration::$values['PAYS_PS_PASSWORD'] = 'test-only-secret';
        same(false, module()->validateResponseHash(...array_merge($values, [signature($values, 'test-only-secret')])));
    });
}
test('array request parameters are rejected without warnings', function () {
    set_error_handler(function ($level, $message) { throw new RuntimeException($message); });
    try { same(false, module()->validateResponseHash([], 'ABCDEFGHI', '3', 'CZK', '10000', '100', str_repeat('a', 32))); }
    finally { restore_error_handler(); }
});
test('caller-supplied monetary epsilon is respected', function () {
    same(0, PaysPsModelUtils::floatcmp(12.3400001, 12.34, 0.00001));
});
test('money comparison is stable after arithmetic', function () {
    same(0, PaysPsModelUtils::floatcmp(100.1 + 0.2, 100.3));
    same(-1, PaysPsModelUtils::floatcmp(100.29, 100.3));
    same(1, PaysPsModelUtils::floatcmp(100.31, 100.3));
});
test('missing checkout cookie and array token are rejected without warnings', function () {
    $m = module();
    set_error_handler(function ($level, $message) { throw new RuntimeException($message); });
    try {
        same(false, $m->checkOptionHash('PAYMENTGATEWAY', 1, 'token'));
        same(false, $m->checkOptionHash('PAYMENTGATEWAY', 1, []));
    } finally { restore_error_handler(); }
});
test('checkout uses a random token bound to the cart', function () {
    $m = module();
    $method = new ReflectionMethod($m, 'getOptionHash');
    $method->setAccessible(true);
    $first = $method->invoke($m, 'PAYMENTGATEWAY');
    same(64, strlen($first));
    same(true, ctype_xdigit($first));
    same(true, $m->checkOptionHash('PAYMENTGATEWAY', 1, $first));
    same(false, $m->checkOptionHash('PAYMENTGATEWAY', 2, $first));
    same(false, $first === $method->invoke($m, 'PAYMENTGATEWAY'));
});
test('a placeholder at the beginning of a message is interpolated', function () {
    PaysPsModelMessage::setMessageList(['ERROR' => ['test' => '%s failed'], 'STATUS' => []]);
    same(['test' => 'Payment failed'], PaysPsModelMessage::getCombinedMessages(['test' => ['Payment']]));
});
test('fresh-install message column can store all diagnostic codes', function () {
    module();
    preg_match('/`code` varchar\((\d+)\)/i', file_get_contents(dirname(__DIR__) . '/install/db_structure.sql'), $matches);
    $limit = (int) $matches[1];
    foreach (PaysPsModelMessage::$messageList as $messages) {
        foreach (array_keys($messages) as $code) { same(true, strlen($code) <= $limit); }
    }
});
test('checkout with a deleted customer redirects instead of calling a removed PS9 API', function () {
    $m = module();
    $m->context->cart = (object) ['id' => 1, 'id_customer' => 999, 'id_address_delivery' => 1, 'id_address_invoice' => 1];
    $m->context->cookie->pays_ps_option_PAYMENTGATEWAY_hash = '1_token';
    Tools::$request = ['optionHash' => 'token'];
    $controller = new Pays_PsPaymentModuleFrontController();
    $controller->module = $m;
    $controller->context = $m->context;
    try { $controller->postProcess(); }
    catch (TestRedirect $redirect) {
        same(true, strpos($redirect->getMessage(), 'controller=order') !== false);
        return;
    }
    throw new RuntimeException('Expected a checkout redirect');
});

$failed = 0;
foreach ($tests as $name => $body) {
    try { $body(); echo "PASS $name\n"; }
    catch (Throwable $e) { ++$failed; echo "FAIL $name: {$e->getMessage()}\n"; }
}
echo count($tests) . ' tests, ' . $failed . " failures\n";
exit($failed ? 1 : 0);
