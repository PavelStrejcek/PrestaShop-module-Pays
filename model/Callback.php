<?php
/** Authenticated, serialized processing shared by the two Pays endpoints. */
if (!defined('_PS_VERSION_')) {
    exit;
}

class PaysPsCallbackException extends RuntimeException
{
}

class PaysPsCallback
{
    private $module;
    private const FIELDS = ['PaymentOrderID', 'MerchantOrderNumber', 'PaymentOrderStatusID', 'CurrencyID', 'Amount', 'CurrencyBaseUnits', 'hash'];

    public function __construct($module)
    {
        $this->module = $module;
    }

    public static function readRequest()
    {
        $data = [];
        foreach (self::FIELDS as $field) {
            $value = Tools::getValue($field, null);
            if ($value !== null && !is_string($value) && !is_int($value)) {
                throw new PaysPsCallbackException('Malformed payment response.', 400);
            }
            $data[$field] = $value;
        }
        // The free-text description is not signed. Do not trust or persist it.
        return $data;
    }

    public function process(array $data, $source = 'confirm')
    {
        if (!$this->module->active) {
            throw new PaysPsCallbackException('Payment module is unavailable.', 503);
        }
        $values = [];
        foreach (self::FIELDS as $field) { $values[] = $data[$field] ?? null; }
        // Authentication must precede order lookups, logging and all writes.
        if (!$this->module->validateResponseHash(...$values)) {
            throw new PaysPsCallbackException('Invalid payment signature.', 401);
        }
        $data = array_map('strval', $data);
        $data['hash'] = strtolower($data['hash']);
        $db = Db::getInstance();
        $locks = [
            'pays_ps_id_' . $data['PaymentOrderID'],
            'pays_ps_ref_' . substr(hash('sha256', $data['MerchantOrderNumber']), 0, 48),
        ];
        $acquired = [];
        $transaction = false;
        $notifications = [];
        try {
            // Always acquire payment ID before order reference. The first lock
            // prevents concurrent binding of one gateway ID to different orders;
            // the second serializes different installments of the same order.
            foreach ($locks as $lock) {
                if ((int) $db->getValue("SELECT GET_LOCK('" . pSQL($lock) . "', 10)", false) !== 1) {
                    throw new PaysPsCallbackException('Payment processing is busy. Please retry.', 503);
                }
                $acquired[] = $lock;
            }
            if (!$db->execute('START TRANSACTION')) {
                throw new RuntimeException('Could not start payment transaction.');
            }
            $transaction = true;
            $order = $this->findOrder($data['MerchantOrderNumber']);
            $currency = new Currency((int) Currency::getIdByIsoCode($data['CurrencyID'], (int) $order->id_shop));
            if (!Validate::isLoadedObject($currency) || !$currency->active || $currency->deleted
                || (int) $data['CurrencyBaseUnits'] !== (int) pow(10, (int) $currency->precision)) {
                throw new PaysPsCallbackException('Invalid payment currency or base units.', 422);
            }
            $amount = (int) $data['Amount'] / (int) $data['CurrencyBaseUnits'];
            $converted = Hook::exec('actionPaysPsAddOrderPayment', ['amount' => $amount, 'order' => $order], null, true);
            $converted = is_array($converted) ? end($converted) : null;
            if (is_array($converted) && isset($converted['amount'], $converted['currency'])) {
                if (!is_numeric($converted['amount']) || !is_finite((float) $converted['amount']) || (float) $converted['amount'] <= 0
                    || !$converted['currency'] instanceof Currency || !Validate::isLoadedObject($converted['currency'])) {
                    throw new PaysPsCallbackException('Invalid converted payment.', 422);
                }
                $amount = (float) $converted['amount'];
                $currency = $converted['currency'];
            }
            if ((int) $currency->id !== (int) $order->id_currency) {
                throw new PaysPsCallbackException('Payment currency does not match the order.', 422);
            }

            // Search the full history, not just the most recent notification.
            $previous = $db->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'pays_ps_response` WHERE payment_order_id=' . (int) $data['PaymentOrderID'], true, false);
            if ($previous === false) { throw new RuntimeException('Could not read payment history.'); }
            $seen = false;
            foreach ($previous as $response) {
                if ((int) $response['id_cart'] !== (int) $order->id_cart
                    || $response['merchant_order_number'] !== $order->reference
                    || $response['currency_id'] !== $data['CurrencyID']
                    || (int) $response['amount'] !== (int) $data['Amount']
                    || (int) $response['currency_base_units'] !== (int) $data['CurrencyBaseUnits']) {
                    throw new PaysPsCallbackException('Conflicting payment response.', 422);
                }
                $seen = $seen || hash_equals(strtolower($response['hash']), $data['hash']);
            }
            if (!$seen) {
                $payment = new PaysPsModelPayment($order->id_cart, null, null, true);
                $payment->id_customer = $order->id_customer;
                $payment->merchant_order_number = $order->reference;
                if (!Validate::isLoadedObject($payment) || !$payment->update()) {
                    throw new RuntimeException('Could not save payment record.');
                }
                $paid = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'order_payment` WHERE order_reference=\'' . pSQL($order->reference) . '\' AND transaction_id=\'' . pSQL($data['PaymentOrderID']) . '\'', false);
                if ($paid && ((int) $paid['id_currency'] !== (int) $currency->id || PaysPsModelUtils::floatcmp($paid['amount'], $amount) !== 0)) {
                    throw new PaysPsCallbackException('Conflicting recorded payment.', 422);
                }
                if ($data['PaymentOrderStatusID'] === '3' && !$paid) {
                    if (!$order->addOrderPayment($amount, 'Pays', $data['PaymentOrderID'], $currency)) {
                        throw new RuntimeException('Could not record order payment.');
                    }
                }
                $this->updateOrderStatus($order, $data['PaymentOrderStatusID'], (bool) $paid, $notifications);

                // Commit the receipt only together with the payment/status changes.
                $response = new PaysPsModelResponse();
                $response->payment_order_id = $data['PaymentOrderID'];
                $response->id_cart = $order->id_cart;
                $response->merchant_order_number = $order->reference;
                $response->payment_order_status_id = $data['PaymentOrderStatusID'];
                $response->currency_id = $data['CurrencyID'];
                $response->amount = $data['Amount'];
                $response->currency_base_units = $data['CurrencyBaseUnits'];
                $response->payment_order_status_description = '';
                $response->hash = $data['hash'];
                if (!$response->add()) { throw new RuntimeException('Could not save payment response.'); }
                $prefix = $source === 'validation' ? 'response' : 'confirmation';
                $message = $data['PaymentOrderStatusID'] === '3' ? [$amount . ' ' . $currency->iso_code] : true;
                if (!PaysPsModelMessage::addMessages(['MODULE_' . $prefix . 'SignedStatus_' . $data['PaymentOrderStatusID'] => $message], $payment->id)) {
                    throw new RuntimeException('Could not save payment audit message.');
                }
            }
            if (!$db->execute('COMMIT')) { throw new RuntimeException('Could not commit payment transaction.'); }
            $transaction = false;
            foreach ($notifications as $notification) {
                try {
                    if (!$notification['history']->sendEmail($notification['order'], $notification['vars'])) {
                        throw new RuntimeException('Could not send payment email.');
                    }
                } catch (Throwable $error) {
                    // Payment is committed. A mail outage must not make the
                    // gateway retry a successful charge or roll back its receipt.
                    PrestaShopLogger::addLog('Pays: payment recorded, but the order status email could not be sent.', 2, null, 'Order', (int) $order->id);
                }
            }
            return $order;
        } finally {
            try {
                if ($transaction) { $db->execute('ROLLBACK'); }
            } finally {
                foreach (array_reverse($acquired) as $lock) {
                    $db->getValue("SELECT RELEASE_LOCK('" . pSQL($lock) . "')", false);
                }
            }
        }
    }

    public function findOrder($reference)
    {
        $orders = Order::getByReference($reference);
        // Split orders need allocation across orders; never silently pay the first.
        if (!$orders instanceof PrestaShopCollection || $orders->count() !== 1) {
            throw new PaysPsCallbackException('Order reference is missing or ambiguous.', 422);
        }
        $order = $orders->getFirst();
        if (!Validate::isLoadedObject($order) || $order->module !== $this->module->name
            || (int) $order->id_shop !== (int) Context::getContext()->shop->id) {
            throw new PaysPsCallbackException('Order is not payable by this module and shop.', 422);
        }
        return $order;
    }

    private function updateOrderStatus($order, $status, $alreadyPaid, array &$notifications)
    {
        $pending = [(int) Configuration::get('PAYS_PS_ORDER_STATUS_PAYMENT_AWAITING'), (int) Configuration::get('PAYS_PS_ORDER_STATUS_PAYMENT_UNREALIZED')];
        // Preserve paid, shipped, cancelled, refunded and manually assigned states.
        if (!in_array((int) $order->current_state, $pending, true)) { return; }
        $fullyPaid = PaysPsModelUtils::floatcmp($order->getTotalPaid(), $order->total_paid_tax_incl) >= 0;
        if ($status === '3' && $fullyPaid) {
            if (!$this->module->changeOrderToReceivedStatus($order, $notifications)) {
                throw new RuntimeException('Could not set the paid order status.');
            }
        } elseif ($status === '2' && !$alreadyPaid && !$fullyPaid) {
            if (!$this->module->changeOrderStatus($order, Configuration::get('PAYS_PS_ORDER_STATUS_PAYMENT_UNREALIZED'), $notifications)) {
                throw new RuntimeException('Could not set the unrealized order status.');
            }
        }
    }

    public static function fail(Throwable $error)
    {
        $status = $error instanceof PaysPsCallbackException ? $error->getCode() : 500;
        if ($status >= 500) {
            PrestaShopLogger::addLog('Pays: payment confirmation failed; the gateway may retry.', 3);
        }
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        // Never return SQL errors, raw parameters, signatures or customer keys.
        exit($status >= 500 ? 'Payment processing failed. Please retry.' : 'Payment response rejected.');
    }
}
