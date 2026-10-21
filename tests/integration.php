<?php
/** Run ONLY against a disposable, installed PrestaShop 9 database. */
if (getenv('PAYS_TEST_ALLOW_WRITE') !== '1') {
    fwrite(STDERR, "Use a disposable PS9 shop and set PAYS_TEST_ALLOW_WRITE=1.\n");
    exit(2);
}
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require (getenv('PRESTASHOP_ROOT') ?: '/var/www/html') . '/config/config.inc.php';
Context::getContext()->currency = new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'));
Context::getContext()->language = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
Context::getContext()->country = new Country((int) Configuration::get('PS_COUNTRY_DEFAULT'));
$kernel = new FrontKernel(_PS_ENV_, _PS_MODE_DEV_);
$kernel->boot();
Context::getContext()->smarty->force_compile = true;
$module = Module::getInstanceByName('pays_ps');
if (!$module || !Module::isInstalled('pays_ps')) {
    throw new RuntimeException('Install pays_ps in the disposable shop before testing.');
}
require __DIR__ . '/support/integration.php';

$tests = [];
function test($name, $body) { global $tests; $tests[$name] = $body; }
function same($expected, $actual) {
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

test('2.0.0 upgrade preserves payment data and expands diagnostic codes', function () use ($module) {
    $before = [];
    foreach (['pays_ps_payment', 'pays_ps_response', 'pays_ps_message', 'pays_ps_currency'] as $table) { $before[$table] = fixtureCount($table, '1'); }
    require_once dirname(__DIR__) . '/upgrade/upgrade-2.0.0.php';
    same(true, upgrade_module_2_0_0($module));
    foreach ($before as $table => $count) { same($count, fixtureCount($table, '1')); }
    same('varchar(255)', Db::getInstance()->getValue('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=\'' . _DB_PREFIX_ . 'pays_ps_message\' AND COLUMN_NAME=\'code\'', false));
});

test('unsigned browser return cannot create a payment or disclose the order key', function () {
    $order = fixtureOrder();
    $r = requestCallback('validation', ['MerchantOrderNumber' => $order->reference, 'status' => 'online']);
    same(401, $r['status']);
    same(false, strpos($r['headers'] . $r['body'], $order->secure_key) !== false);
    same(0, fixtureCount('pays_ps_payment', 'id_cart=' . (int) $order->id_cart));
});
foreach (['confirm', 'validation'] as $controller) {
    test($controller . ': missing or invalid signature has no database effects', function () use ($controller) {
        $order = fixtureOrder();
        $p = callbackPayload($order);
        unset($p['hash']);
        same(401, requestCallback($controller, $p)['status']);
        $p['hash'] = str_repeat('0', 32);
        same(401, requestCallback($controller, $p)['status']);
        same(0, fixtureCount('pays_ps_payment', 'id_cart=' . (int) $order->id_cart));
        same(0, fixtureCount('pays_ps_response', 'id_cart=' . (int) $order->id_cart));
        same(0, fixtureCount('order_payment', "order_reference='" . pSQL($order->reference) . "'"));
    });
}
test('array-valued request is a client error, not a PHP crash', function () {
    $order = fixtureOrder();
    $p = callbackPayload($order);
    $p['Amount'] = ['10000'];
    same(400, requestCallback('confirm', $p)['status']);
});
test('valid confirmation records one payment and reaches the paid status', function () {
    $order = fixtureOrder();
    same(202, requestCallback('confirm', callbackPayload($order))['status']);
    same(100.0, (float) fixtureFreshOrder($order->id)->getTotalPaid());
    same((int) Configuration::get('PAYS_PS_ORDER_STATUS_PAYMENT_RECEIVED'), (int) fixtureFreshOrder($order->id)->current_state);
});
test('signed browser return uses the PS9 order-confirmation route', function () {
    $order = fixtureOrder();
    $r = requestCallback('validation', callbackPayload($order));
    same(302, $r['status']);
    same(true, strpos($r['headers'], 'key=' . $order->secure_key) !== false);
    same(false, strpos($r['headers'], 'order-confirmation.php') !== false);
    same(100.0, (float) fixtureFreshOrder($order->id)->getTotalPaid());
});
test('duplicate delivery across both controllers adds only one payment', function () {
    $order = fixtureOrder();
    $p = callbackPayload($order);
    same(202, requestCallback('confirm', $p)['status']);
    same(302, requestCallback('validation', $p)['status']);
    same(202, requestCallback('confirm', $p)['status']);
    same(1, fixtureCount('order_payment', "order_reference='" . pSQL($order->reference) . "'"));
});
test('late cancellation and replay cannot downgrade or charge a paid order again', function () {
    $order = fixtureOrder();
    $paid = callbackPayload($order);
    same(202, requestCallback('confirm', $paid)['status']);
    $cancelled = callbackPayload($order, ['PaymentOrderID' => $paid['PaymentOrderID'], 'PaymentOrderStatusID' => '2']);
    same(202, requestCallback('confirm', $cancelled)['status']);
    same((int) Configuration::get('PAYS_PS_ORDER_STATUS_PAYMENT_RECEIVED'), (int) fixtureFreshOrder($order->id)->current_state);
    same(202, requestCallback('confirm', $paid)['status']);
    same(1, fixtureCount('order_payment', "order_reference='" . pSQL($order->reference) . "'"));
});
test('unknown currency is rejected before saving a response', function () {
    $order = fixtureOrder();
    same(422, requestCallback('confirm', callbackPayload($order, ['CurrencyID' => 'ZZZ']))['status']);
    same(0, fixtureCount('pays_ps_response', 'id_cart=' . (int) $order->id_cart));
});
test('a currency with incompatible base units is rejected', function () {
    $order = fixtureOrder();
    same(422, requestCallback('confirm', callbackPayload($order, ['CurrencyBaseUnits' => '1']))['status']);
    same(0.0, (float) fixtureFreshOrder($order->id)->getTotalPaid());
});
test('confirmation cannot pay an order belonging to another module', function () {
    $order = fixtureOrder('ps_checkpayment');
    same(422, requestCallback('confirm', callbackPayload($order))['status']);
    same(0.0, (float) fixtureFreshOrder($order->id)->getTotalPaid());
});
test('a reference shared by split orders is rejected instead of paying the first order', function () {
    $order = fixtureOrder();
    $second = fixtureOrder();
    $second->reference = $order->reference;
    $second->update();
    same(422, requestCallback('confirm', callbackPayload($order))['status']);
    same(0.0, (float) fixtureFreshOrder($order->id)->getTotalPaid());
});
test('partial payments settle only after the outstanding balance is paid', function () {
    $order = fixtureOrder();
    same(202, requestCallback('confirm', callbackPayload($order, ['Amount' => '5000']))['status']);
    same(50.0, (float) fixtureFreshOrder($order->id)->getTotalPaid());
    same((int) Configuration::get('PAYS_PS_ORDER_STATUS_PAYMENT_AWAITING'), (int) fixtureFreshOrder($order->id)->current_state);
    same(202, requestCallback('confirm', callbackPayload($order, ['Amount' => '5000']))['status']);
    same(100.0, (float) fixtureFreshOrder($order->id)->getTotalPaid());
});
test('parallel confirmations are serialized and acknowledged without duplicate payments', function () {
    $order = fixtureOrder();
    $p = callbackPayload($order);
    // Widen the race window; an unlocked check-then-insert can otherwise pass.
    Db::getInstance()->execute('CREATE TRIGGER pays_test_slow_payment BEFORE INSERT ON `' . _DB_PREFIX_ . 'order_payment` FOR EACH ROW DO SLEEP(0.25)');
    try { $responses = requestParallel(array_fill(0, 6, $p)); }
    finally { Db::getInstance()->execute('DROP TRIGGER pays_test_slow_payment'); }
    foreach ($responses as $status) { same(202, $status); }
    same(1, fixtureCount('order_payment', "order_reference='" . pSQL($order->reference) . "'"));
    same(1, fixtureCount('pays_ps_response', 'id_cart=' . (int) $order->id_cart));
});
test('a reused gateway ID with changed amount is rejected', function () {
    $order = fixtureOrder();
    $p = callbackPayload($order, ['Amount' => '5000']);
    same(202, requestCallback('confirm', $p)['status']);
    same(422, requestCallback('confirm', callbackPayload($order, ['PaymentOrderID' => $p['PaymentOrderID'], 'Amount' => '10000']))['status']);
    same(50.0, (float) fixtureFreshOrder($order->id)->getTotalPaid());
});
test('parallel callbacks cannot bind one gateway payment to two order references', function () {
    $first = fixtureOrder();
    $second = fixtureOrder();
    $a = callbackPayload($first);
    $b = callbackPayload($second, ['PaymentOrderID' => $a['PaymentOrderID']]);
    Db::getInstance()->execute('CREATE TRIGGER pays_test_slow_payment BEFORE INSERT ON `' . _DB_PREFIX_ . 'order_payment` FOR EACH ROW DO SLEEP(0.25)');
    try { $statuses = requestParallel([$a, $b]); }
    finally { Db::getInstance()->execute('DROP TRIGGER pays_test_slow_payment'); }
    sort($statuses);
    same([202, 422], $statuses);
    same(1, fixtureCount('order_payment', "transaction_id='" . pSQL($a['PaymentOrderID']) . "'"));
});
test('a late confirmation preserves an explicitly cancelled order state', function () {
    $order = fixtureOrder();
    $order->current_state = (int) Configuration::get('PS_OS_CANCELED');
    $order->update();
    same(202, requestCallback('confirm', callbackPayload($order))['status']);
    same((int) Configuration::get('PS_OS_CANCELED'), (int) fixtureFreshOrder($order->id)->current_state);
});
test('cancelled orders do not offer a new payment', function () use ($module) {
    $order = fixtureOrder();
    $order->current_state = (int) Configuration::get('PS_OS_CANCELED');
    $order->update();
    same(false, $module->isOrderForPayment($order));
});
test('admin panel does not offer a payment for a different module', function () use ($module) {
    $order = fixtureOrder('ps_checkpayment');
    $html = $module->hookDisplayAdminOrder(['id_order' => $order->id]);
    same(false, strpos($html, 'https://www.pays.cz/paymentorder?') !== false);
});
test('admin rendering clears the previous orders payment link', function () use ($module) {
    $first = fixtureOrder();
    same(true, strpos($module->hookDisplayAdminOrder(['id_order' => $first->id]), 'https://www.pays.cz/paymentorder?') !== false);
    $paid = fixtureOrder();
    $paid->addOrderPayment(100, 'Pays', 'admin-test-' . $paid->id);
    $html = $module->hookDisplayAdminOrder(['id_order' => $paid->id]);
    same(false, strpos($html, 'https://www.pays.cz/paymentorder?') !== false);
});
test('the admin payment history renders valid table markup', function () use ($module) {
    $order = fixtureOrder();
    same(202, requestCallback('confirm', callbackPayload($order))['status']);
    $html = $module->hookDisplayAdminOrder(['id_order' => $order->id]);
    $previous = libxml_use_internal_errors(true);
    libxml_clear_errors();
    try {
        $document = new DOMDocument();
        $document->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>', LIBXML_NONET);
        $errors = array_filter(libxml_get_errors(), function ($error) { return $error->level >= LIBXML_ERR_ERROR; });
        same([], array_map(function ($error) { return trim($error->message); }, $errors));
        same(1, (new DOMXPath($document))->query('//tbody/tr')->length);
    } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
});
test('failed payment write rolls back its receipt and can be retried', function () {
    $order = fixtureOrder();
    $p = callbackPayload($order);
    Db::getInstance()->execute('CREATE TRIGGER pays_test_fail_payment BEFORE INSERT ON `' . _DB_PREFIX_ . 'order_payment` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Pays test failure\'');
    try { same(500, requestCallback('confirm', $p)['status']); }
    finally { Db::getInstance()->execute('DROP TRIGGER pays_test_fail_payment'); }
    same(0, fixtureCount('pays_ps_response', 'id_cart=' . (int) $order->id_cart));
    same(0.0, (float) fixtureFreshOrder($order->id)->getTotalPaid());
    same(202, requestCallback('confirm', $p)['status']);
    same(100.0, (float) fixtureFreshOrder($order->id)->getTotalPaid());
});
test('failed receipt write rolls back payment and can be retried', function () {
    $order = fixtureOrder();
    $p = callbackPayload($order);
    Db::getInstance()->execute('CREATE TRIGGER pays_test_fail_receipt BEFORE INSERT ON `' . _DB_PREFIX_ . 'pays_ps_response` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Pays test failure\'');
    try { same(500, requestCallback('confirm', $p)['status']); }
    finally { Db::getInstance()->execute('DROP TRIGGER pays_test_fail_receipt'); }
    same(0, fixtureCount('pays_ps_response', 'id_cart=' . (int) $order->id_cart));
    same(0.0, (float) fixtureFreshOrder($order->id)->getTotalPaid());
    same(202, requestCallback('confirm', $p)['status']);
    same(100.0, (float) fixtureFreshOrder($order->id)->getTotalPaid());
});
test('payment email is sent only after commit and never for a failed receipt', function () {
    fixtureInstallMailProbe();
    file_put_contents('/tmp/pays-test-mail-probe.jsonl', '');
    if ((fileperms('/tmp/pays-test-mail-probe.jsonl') & 0777) !== 0666) { chmod('/tmp/pays-test-mail-probe.jsonl', 0666); }
    $order = fixtureOrder();
    $p = callbackPayload($order);
    Db::getInstance()->execute('CREATE TRIGGER pays_test_fail_receipt BEFORE INSERT ON `' . _DB_PREFIX_ . 'pays_ps_response` FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Pays test failure\'');
    try { same(500, requestCallback('confirm', $p)['status']); }
    finally { Db::getInstance()->execute('DROP TRIGGER pays_test_fail_receipt'); }
    same('', file_get_contents('/tmp/pays-test-mail-probe.jsonl'));
    same(202, requestCallback('confirm', $p)['status']);
    $events = array_map('json_decode', file('/tmp/pays-test-mail-probe.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    same(1, count($events));
    same(0, $events[0]->in_transaction);
});
test('payment descriptions are escaped in the checkout template', function () {
    $smarty = Context::getContext()->smarty;
    $smarty->assign(['paysPsPaymentDescription' => '<img src=x onerror=alert(1)>', 'hookDisplayPaysPsPayOption' => '']);
    $html = $smarty->fetch('module:pays_ps/views/templates/front/option_paymentgateway.tpl');
    same(false, strpos($html, '<img src=x onerror=') !== false);
    same(true, strpos($html, '&lt;img') !== false);
});
test('PS9 checkout creates and pays an order even with a legacy advanced-stock flag', function () use ($module) {
    $cart = fixtureCart();
    $context = Context::getContext();
    $context->cart = $cart;
    $context->customer = new Customer($cart->id_customer);
    $product = (int) Db::getInstance()->getValue('SELECT p.id_product FROM `' . _DB_PREFIX_ . 'product` p INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product=p.id_product AND ps.id_shop=1 INNER JOIN `' . _DB_PREFIX_ . 'stock_available` s ON s.id_product=p.id_product AND s.id_product_attribute=0 WHERE ps.active=1 AND ps.cache_default_attribute=0 AND s.quantity>0');
    if (!$product || $cart->updateQty(1, $product) <= 0) { throw new RuntimeException('No in-stock demo product for checkout'); }
    $options = $cart->getDeliveryOptionList();
    $selected = [];
    foreach ($options as $address => $choices) { $selected[$address] = array_key_first($choices); }
    if (!$selected) { throw new RuntimeException('No demo delivery option'); }
    $cart->setDeliveryOption($selected);
    $cart->update();
    $option = $module->getGatePaymentOption();
    $token = $option->getInputs()['token']['value'];
    same(true, $module->checkOptionHash('PAYMENTGATEWAY', $cart->id, $token));
    same(false, $module->checkOptionHash('PAYMENTGATEWAY', $cart->id + 1, $token));
    $total = $cart->getOrderTotal(true, Cart::BOTH);
    $module->validateOrder($cart->id, (int) Configuration::get('PAYS_PS_ORDER_STATUS_PAYMENT_AWAITING'), $total, 'Pays', null, [], null, false, $context->customer->secure_key);
    $order = fixtureFreshOrder($module->currentOrder);
    same((int) $cart->id, (int) $order->id_cart);
    same('pays_ps', $order->module);
    same((int) Configuration::get('PAYS_PS_ORDER_STATUS_PAYMENT_AWAITING'), (int) $order->current_state);
    $url = $module->createPaymentUrl($order);
    same('www.pays.cz', parse_url($url, PHP_URL_HOST));
    parse_str(parse_url($url, PHP_URL_QUERY), $params);
    same((string) (int) round($total * 100), $params['Amount']);
    same($order->reference, $params['MerchantOrderNumber']);
    same($context->currency->iso_code, $params['Currency']);
    Configuration::updateValue('PS_ADVANCED_STOCK_MANAGEMENT', 1);
    same(202, requestCallback('confirm', callbackPayload($order, ['Amount' => $params['Amount']]))['status']);
    same((float) $total, (float) fixtureFreshOrder($order->id)->getTotalPaid());
});

$keys = ['PAYS_PS_PASSWORD', 'PAYS_PS_MERCHANT', 'PAYS_PS_SHOP', 'PS_MAIL_METHOD', 'PS_ADVANCED_STOCK_MANAGEMENT'];
$previous = [];
foreach ($keys as $key) { $previous[$key] = Configuration::get($key); }
Configuration::updateValue('PAYS_PS_PASSWORD', 'integration-test-only-password');
Configuration::updateValue('PAYS_PS_MERCHANT', 'test-merchant');
Configuration::updateValue('PAYS_PS_SHOP', 'test-shop');
Configuration::updateValue('PS_MAIL_METHOD', 3); // Never send email from tests.
$failed = 0;
try {
    foreach ($tests as $name => $body) {
        try { $body(); echo "PASS $name\n"; }
        catch (Throwable $e) { ++$failed; echo "FAIL $name: {$e->getMessage()}\n"; }
    }
} finally {
    if (!empty($paysMailProbeInstalledByTests)) { Module::getInstanceByName('pays_test_probe')->uninstall(); }
    foreach ($previous as $key => $value) {
        if ($value === false) { Configuration::deleteByName($key); }
        else { Configuration::updateValue($key, $value); }
    }
}
echo count($tests) . ' tests, ' . $failed . " failures\n";
exit($failed ? 1 : 0);
