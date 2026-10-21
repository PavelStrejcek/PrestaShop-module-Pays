<?php

/**
 * Module Pays
 *
 * This source file is subject to the Open Software License v. 3.0 (OSL-3.0)
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to application@brainweb.cz so we can send you a copy..
 *
 * @author    Pavel Strejček <aplikace@brainweb.cz>
 * @copyright 2019 - 2023 Pavel Strejček
 * @license   Licensed under the Open Software License version 3.0  https://opensource.org/licenses/OSL-3.0
 *
 * Payment gateway operator and support: www.Pays.cz
 * Module development: www.BrainWeb.cz
 */
if (! defined('_PS_VERSION_')) {
    exit;
}

class Pays_PsValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        try {
            if (!$this->module->active) {
                throw new PaysPsCallbackException('Payment module is unavailable.', 503);
            }
            $handler = new PaysPsCallback($this->module);
            $data = PaysPsCallback::readRequest();
            if ($data['hash'] !== null) {
                $order = $handler->process($data, 'validation');
            } else {
                // An unsigned browser return is informational only. The customer
                // session (also for guests) must own the order before any access.
                $customer = $this->context->customer;
                if (!Validate::isLoadedObject($customer)) {
                    throw new PaysPsCallbackException('Customer authentication is required.', 401);
                }
                if (!is_string($data['MerchantOrderNumber']) || !preg_match('/^[a-zA-Z0-9_-]{1,15}$/D', $data['MerchantOrderNumber'])) {
                    throw new PaysPsCallbackException('Malformed order reference.', 400);
                }
                try {
                    $order = $handler->findOrder($data['MerchantOrderNumber']);
                } catch (PaysPsCallbackException $error) {
                    throw new PaysPsCallbackException('Order is unavailable to this customer.', 401);
                }
                if ((int) $order->id_customer !== (int) $customer->id) {
                    throw new PaysPsCallbackException('Order is unavailable to this customer.', 401);
                }
                $status = Tools::getValue('status');
                if (is_string($status) && array_key_exists($status, $this->module->paysPsUnsignedStatusMessage)) {
                    $payment = new PaysPsModelPayment($order->id_cart, null, null, true);
                    $payment->id_customer = $order->id_customer;
                    $payment->merchant_order_number = $order->reference;
                    $payment->gate_response_status = $status;
                    if (!$payment->update()) { throw new RuntimeException('Could not save return status.'); }
                }
            }
            $customer = new Customer((int) $order->id_customer);
            $url = $this->context->link->getPageLink('order-confirmation', null, (int) $order->id_lang, [
                'key' => $customer->secure_key,
                'id_cart' => (int) $order->id_cart,
                'id_module' => (int) $this->module->id,
                'id_order' => (int) $order->id,
            ]);
        } catch (Throwable $error) {
            PaysPsCallback::fail($error);
        }
        header('Cache-Control: no-store');
        Tools::redirect($url);
    }
}
