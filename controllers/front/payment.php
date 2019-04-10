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
 * @author    Pavel Strejček <pavel.strejcek@brainweb.cz>
 * @copyright 2019 Pavel Strejček
 * @license   Licensed under the Open Software License version 3.0  https://opensource.org/licenses/OSL-3.0
 *
 * Payment gateway operator and support: www.Pays.cz
 * Module development: www.BrainWeb.cz
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Pays_PsPaymentModuleFrontController extends ModuleFrontController {

    public $paysPsMsgs = array();

    /**
     * @see FrontController::postProcess()
     */
    public function postProcess() {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == $this->module->name) {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not authorized.', 'payment'));
        }

        $optionHash = Tools::getValue('optionHash');
        if (empty($optionHash) || !$this->module->checkOptionHash('PAYMENTGATEWAY', $cart->id, $optionHash)) {
            die($this->module->l('Data does not match this option.', 'payment'));
        }

        $customer = new Customer($this->context->cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirectLink(__PS_BASE_URI__ . 'order.php?step=1');
        }

        // confirm
        $total = $this->context->cart->getOrderTotal(true, Cart::BOTH);
        $this->module->validateOrder((int) $cart->id, Configuration::get('PAYS_PS_ORDER_STATUS_PAYMENT_AWAITING'), $total, $this->module->displayName, null, array(), null, false, $customer->secure_key);

        $this->paysPsMsgs['MODULE_paymentMethodChosen'] = true;

        $order = new Order($this->module->currentOrder);

        $newPayment = new PaysPsModelPayment($cart->id, null, null, true);
        $newPayment->id_customer = $cart->id_customer;
        $newPayment->merchant_order_number = $order->reference;
        $newPayment->update(true);

        PaysPsModelMessage::addMessages($this->paysPsMsgs, $newPayment->id);

        // redirect to gate
        $url = $this->module->createPaymentUrl($order);
        Tools::redirect($url);
    }

}
