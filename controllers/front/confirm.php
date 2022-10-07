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
 * @copyright 2019 - 2021 Pavel Strejček
 * @license   Licensed under the Open Software License version 3.0  https://opensource.org/licenses/OSL-3.0
 *
 * Payment gateway operator and support: www.Pays.cz
 * Module development: www.BrainWeb.cz
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Pays_PsConfirmModuleFrontController extends ModuleFrontController
{
    public $paysPsMsgs = array();
    public $paysPsResponseFailed = false;

    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        if (!$this->module->active) {
            http_response_code(500);
            die($this->module->l('Pays module is not active', 'confirm'));
        }

        $signedPaymentStatus = Tools::getValue('PaymentOrderStatusID', null);
        $amount = (int) Tools::getValue('CurrencyBaseUnits', null) ? (int) Tools::getValue('Amount', null) / (int) Tools::getValue('CurrencyBaseUnits', null) : (int) Tools::getValue('Amount', null);

        $signed = $this->module->validateResponseHash(Tools::getValue('PaymentOrderID', null), Tools::getValue('MerchantOrderNumber', null), Tools::getValue('PaymentOrderStatusID', null), Tools::getValue('CurrencyID', null), Tools::getValue('Amount', null), Tools::getValue('CurrencyBaseUnits', null), Tools::getValue('hash', null));
        if (Tools::getValue('hash', null) && !$signed) {
            $this->paysPsMsgs['MODULE_confirmationSignatureNotValid'] = array($_SERVER['QUERY_STRING']);
            $this->paysPsResponseFailed = true;
        }

        $payment = PaysPsModelPayment::getByOrderReference(Tools::getValue('MerchantOrderNumber', null));
        $order = $customer = null;

        if ($payment instanceof PaysPsModelPayment) {
            $order = $payment->getPrestashopOrder();
            if (Validate::isLoadedObject($order)) {
                $customer = new Customer((int) $order->id_customer);
            }
        } else {
            $orders = Order::getByReference(Tools::getValue('MerchantOrderNumber', null));
            if ($orders instanceof PrestaShopCollection && $orders->count()) {
                $order = $orders->getFirst();
                $customer = new Customer((int) $order->id_customer);
                $payment = new PaysPsModelPayment($order->id_cart, null, null, true);
                $payment->id_customer = $customer->id;
                $payment->merchant_order_number = $order->reference;
                $payment->update(true);
            }
        }

        if (!Validate::isLoadedObject($order)) {
            $this->paysPsMsgs['MODULE_confirmationOrderNotExists'] = array(Tools::getValue('MerchantOrderNumber', null));
            $this->paysPsResponseFailed = true;
        } elseif ($signed && !array_key_exists($signedPaymentStatus, $this->module->paysPsSignedStatusMessage)) {
            $this->paysPsMsgs['MODULE_confirmationSignedStatusMissing'] = true;
            $this->paysPsResponseFailed = true;
        } elseif ($signed && $signedPaymentStatus == 3) {
            $this->paysPsMsgs['MODULE_confirmationSignedStatus_' . $signedPaymentStatus] = array($amount . ' ' . Tools::getValue('CurrencyID', null));
        } elseif ($signed) {
            $this->paysPsMsgs['MODULE_confirmationSignedStatus_' . $signedPaymentStatus] = true;
        }

        if ($payment instanceof PaysPsModelPayment) {
            if (!empty($this->paysPsMsgs)) {
                PaysPsModelMessage::addMessages($this->paysPsMsgs, $payment->id);
            }

            if ($signed && Validate::isLoadedObject($order) && !PaysPsModelResponse::isLastResponse(Tools::getValue('PaymentOrderID', null), Tools::getValue('hash', null))) {
                $response = new PaysPsModelResponse();
                $response->payment_order_id = Tools::getValue('PaymentOrderID', null);
                $response->id_cart = $order->id_cart;
                $response->merchant_order_number = Tools::getValue('MerchantOrderNumber', null);
                $response->payment_order_status_id = Tools::getValue('PaymentOrderStatusID', null);
                $response->currency_id = Tools::getValue('CurrencyID', null);
                $response->amount = Tools::getValue('Amount', null);
                $response->currency_base_units = Tools::getValue('CurrencyBaseUnits', null);
                $response->payment_order_status_description = Tools::getValue('PaymentOrderStatusDescription', null);
                $response->hash = Tools::getValue('hash', null);
                $response->add(true, true);
                if ($response->payment_order_status_id == 3) {
                    $id_currency = Currency::getIdByIsoCode(Tools::getValue('CurrencyID', null));
                    if (empty($id_currency)) {
                        $this->paysPsMsgs['MODULE_confirmationUnknownCurrency'] = true;
                        $this->paysPsResponseFailed = true;
                    } else {
                        $currency = new Currency($id_currency);
                        $hookResult = Hook::exec('actionPaysPsAddOrderPayment', [
                                    'amount' => $amount,
                                    'order' => $order
                                        ], null, true);
                        $hookResult = is_array($hookResult) ? end($hookResult) : $hookResult;
                        if (! empty($hookResult['amount']) && ! empty($hookResult['currency'])) {
                            $amount = $hookResult['amount'];
                            $currency = $hookResult['currency'];
                        }
                        $order->addOrderPayment($amount, 'Pays', $response->payment_order_id, $currency);
                    }
                    if (PaysPsModelUtils::floatcmp($order->getTotalPaid(), $order->total_paid_tax_incl) >= 0) {
                        $this->module->changeOrderToReceivedStatus($order);
                    }
                } elseif ($response->payment_order_status_id == 2) {
                    $this->module->changeOrderStatus($order, Configuration::get('PAYS_PS_ORDER_STATUS_PAYMENT_UNREALIZED'));
                }
            }
        }

        if ($this->paysPsResponseFailed) {
            $endmsg = sprintf($this->module->l('Pays: Background confimation from payment gateway was unsuccessful. Messages: %s', 'confirm'), implode(' ', PaysPsModelMessage::getCombinedMessages($this->paysPsMsgs)));
            PrestaShopLogger::addLog($endmsg, 3, null, 'Order', Validate::isLoadedObject($order) ? $order->id : null, true);
            http_response_code(500);
            die($endmsg);
        }

        http_response_code(202);
        die('Accepted');
    }
}
