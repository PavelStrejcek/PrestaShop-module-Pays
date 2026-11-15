<?php
/** Dependency-free regression tests; run with PHP 8.1–8.5. */
error_reporting(E_ALL);
define('_PS_VERSION_', '9.1.0');
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

test('PS 9.0 and PS 9.1+ are declared compatible and front controllers exist', function () {
    $m = module();
    foreach (['9.0.0', '9.1.0', '9.1.5'] as $psVersion) {
        same(true, version_compare($psVersion, $m->ps_versions_compliancy['min'], '>='));
        same(true, version_compare($psVersion, $m->ps_versions_compliancy['max'], '<='));
    }
    foreach ($m->controllers as $controller) {
        same(true, is_file(dirname(__DIR__) . '/controllers/front/' . $controller . '.php'));
    }
});
test('every installed hook subscription implements its PS9 listener method', function () {
    $m = module();
    preg_match_all('/->registerHook\(\x27([^\x27]+)\x27\)/', file_get_contents(dirname(__DIR__) . '/pays_ps.php'), $matches);
    same(true, count($matches[1]) > 0);
    $missing = [];
    foreach ($matches[1] as $hook) {
        if (!is_callable([$m, 'hook' . $hook])) { $missing[] = $hook; }
    }
    same([], $missing);
});
test('module construction on PHP 8.2+ does not create dynamic properties', function () {
    module();
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
    same(false, module()->validateResponseHash([], 'ABCDEFGHI', '3', 'CZK', '10000', '100', str_repeat('a', 32)));
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
    same(false, $m->checkOptionHash('PAYMENTGATEWAY', 1, 'token'));
    same(false, $m->checkOptionHash('PAYMENTGATEWAY', 1, []));
});
test('checkout uses a random token bound to the cart without deprecated Reflection calls', function () {
    $m = module();
    $method = new ReflectionMethod($m, 'getOptionHash');
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
test('null return status, null message code and missing language iso emit no PHP 8.5 deprecations', function () {
    Configuration::$values['PAYS_PS_PASSWORD'] = 'test-only-secret';
    Configuration::$values['PAYS_PS_MERCHANT'] = 'M1';
    Configuration::$values['PAYS_PS_SHOP'] = 'S1';
    Configuration::$values['PAYS_PS_ORDER_STATUS_PAYMENT_AWAITING'] = 10;
    Configuration::$values['PAYS_PS_ORDER_STATUS_PAYMENT_UNREALIZED'] = 11;
    Db::$rows = [];
    $m = module();
    $order = new class {
        public $id = 5, $id_cart = 9, $id_customer = 1, $id_currency = 1, $id_lang = 99;
        public $current_state = 10, $module = 'pays_ps', $reference = 'REF123', $total_paid_tax_incl = 100.0;
        public function getTotalPaid() { return 0.0; }
    };
    Language::$isoById = [2 => 'CS'];
    same('rendered:module:pays_ps/views/templates/hook/payment_return.tpl', $m->hookPaymentReturn(['order' => $order]));
    same('Unknown response status of payment.', $m->context->smarty->assigned['paysPsStatusMsg']);
    parse_str((string) parse_url($m->createPaymentUrl($order), PHP_URL_QUERY), $withoutLang);
    same(false, array_key_exists('Lang', $withoutLang));

    $order->id_lang = 2;
    parse_str((string) parse_url($m->createPaymentUrl($order), PHP_URL_QUERY), $withLang);
    same('CS-CZ', $withLang['Lang']);

    Db::$rows = [['id_message' => 1]];
    $translated = PaysPsModelMessage::getTranslatedMessages(9);
    same(1, count($translated));
    same(null, $translated[0]['translation']);
    Db::$rows = [];
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
    set_error_handler(static function ($level, $message, $file, $line) {
        if (error_reporting() & $level) {
            throw new ErrorException($message, 0, $level, $file, $line);
        }
        return false;
    });
    try { $body(); echo "PASS $name\n"; }
    catch (Throwable $e) { ++$failed; echo "FAIL $name: {$e->getMessage()}\n"; }
    finally { restore_error_handler(); }
}
echo count($tests) . ' tests, ' . $failed . " failures\n";
exit($failed ? 1 : 0);
