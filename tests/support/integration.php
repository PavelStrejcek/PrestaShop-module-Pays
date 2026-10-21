<?php
function fixtureCount($table, $where) {
    return (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . bqSQL($table) . '` WHERE ' . $where, false);
}
function fixtureFreshOrder($id) { Cache::clean('*'); return new Order($id); }
function fixtureInstallMailProbe() {
    global $paysMailProbeInstalledByTests;
    $directory = _PS_MODULE_DIR_ . 'pays_test_probe';
    if (!is_dir($directory)) { mkdir($directory, 0755); }
    $source = dirname(__DIR__) . '/fixtures/pays_test_probe/pays_test_probe.php';
    $target = $directory . '/pays_test_probe.php';
    if (is_file($target) && hash_file('sha256', $target) !== hash_file('sha256', $source)) { throw new RuntimeException('Refusing to overwrite an existing mail probe'); }
    if (!is_file($target)) { copy($source, $target); chmod($target, 0644); }
    $probe = Module::getInstanceByName('pays_test_probe');
    if (!Module::isInstalled('pays_test_probe')) {
        if (!$probe->install()) { throw new RuntimeException('Cannot install email probe'); }
        $paysMailProbeInstalledByTests = true;
    }
}
function fixtureCart() {
    $customer = new Customer((int) Db::getInstance()->getValue('SELECT id_customer FROM `' . _DB_PREFIX_ . 'customer` WHERE deleted=0'));
    $address = (int) Db::getInstance()->getValue('SELECT id_address FROM `' . _DB_PREFIX_ . 'address` WHERE id_customer=' . (int) $customer->id . ' AND deleted=0');
    if (!$customer->id || !$address) { throw new RuntimeException('Integration shop requires demo customer/address fixtures.'); }
    $cart = new Cart();
    $cart->id_customer = $customer->id;
    $cart->id_address_delivery = $cart->id_address_invoice = $address;
    $cart->id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
    $cart->id_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
    $cart->id_shop = $cart->id_shop_group = 1;
    $cart->secure_key = $customer->secure_key;
    if (!$cart->add()) { throw new RuntimeException('Could not create fixture cart'); }
    return $cart;
}
function fixtureOrder($moduleName = 'pays_ps') {
    $cart = fixtureCart();
    $order = new Order();
    foreach (['id_customer', 'id_address_delivery', 'id_address_invoice', 'id_lang', 'id_currency', 'id_shop', 'id_shop_group', 'secure_key'] as $field) { $order->$field = $cart->$field; }
    $order->id_cart = $cart->id;
    $order->id_carrier = (int) Db::getInstance()->getValue('SELECT id_carrier FROM `' . _DB_PREFIX_ . 'carrier` WHERE deleted=0');
    $order->reference = 'T' . strtoupper(bin2hex(random_bytes(4)));
    $order->module = $moduleName;
    $order->payment = 'Pays';
    $order->current_state = (int) Configuration::get('PAYS_PS_ORDER_STATUS_PAYMENT_AWAITING');
    $order->conversion_rate = 1;
    $order->total_paid = $order->total_paid_tax_incl = $order->total_paid_tax_excl = 100;
    $order->total_paid_real = 0;
    $order->total_products = $order->total_products_wt = 100;
    if (!$order->add()) { throw new RuntimeException('Could not create fixture order'); }
    return $order;
}
function callbackPayload($order, $overrides = []) {
    $currency = new Currency($order->id_currency);
    $p = array_merge([
        'PaymentOrderID' => (string) random_int(1000000, 2000000000),
        'MerchantOrderNumber' => $order->reference,
        'PaymentOrderStatusID' => '3',
        'CurrencyID' => $currency->iso_code,
        'Amount' => '10000',
        'CurrencyBaseUnits' => '100',
    ], $overrides);
    $p['hash'] = hash_hmac('md5', implode('', array_values($p)), 'integration-test-only-password');
    return $p;
}
function callbackHandle($controller, $payload) {
    $url = 'http://127.0.0.1/index.php?' . http_build_query(['fc' => 'module', 'module' => 'pays_ps', 'controller' => $controller] + $payload);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 45, CURLOPT_HTTPHEADER => ['Host: localhost:8080']]);
    return $ch;
}
function requestCallback($controller, $payload) {
    $ch = callbackHandle($controller, $payload);
    $result = curl_exec($ch);
    if ($result === false) { throw new RuntimeException(curl_error($ch)); }
    $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $r = ['status' => curl_getinfo($ch, CURLINFO_HTTP_CODE), 'headers' => substr($result, 0, $size), 'body' => substr($result, $size)];
    curl_close($ch);
    return $r;
}
function requestParallel($payloads) {
    $multi = curl_multi_init();
    $handles = [];
    foreach ($payloads as $p) { $handles[] = $ch = callbackHandle('confirm', $p); curl_multi_add_handle($multi, $ch); }
    do {
        $status = curl_multi_exec($multi, $active);
        if ($active) { curl_multi_select($multi, 1); }
    } while ($active && $status === CURLM_OK);
    $statuses = [];
    foreach ($handles as $ch) {
        if (curl_errno($ch)) { throw new RuntimeException(curl_error($ch)); }
        $statuses[] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($multi, $ch); curl_close($ch);
    }
    curl_multi_close($multi);
    return $statuses;
}
