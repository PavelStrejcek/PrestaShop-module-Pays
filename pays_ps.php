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
 * @copyright 2019 Pavel Strejček
 * @license   Licensed under the Open Software License version 3.0  https://opensource.org/licenses/OSL-3.0
 *
 * Payment gateway operator and support: www.Pays.cz
 * Module development: www.BrainWeb.cz
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

defined('PAYS_PS_DIR') or define('PAYS_PS_DIR', dirname(__FILE__));


require_once PAYS_PS_DIR . '/model/Db.php';
require_once PAYS_PS_DIR . '/model/Payment.php';
require_once PAYS_PS_DIR . '/model/Response.php';
require_once PAYS_PS_DIR . '/model/Currency.php';
require_once PAYS_PS_DIR . '/model/Message.php';
require_once PAYS_PS_DIR . '/model/Utils.php';
require_once PAYS_PS_DIR . '/model/OrderState.php';

class Pays_PS extends PaymentModule {

    public function __construct() {
        $this->name = 'pays_ps';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.1';
        $this->author = 'Pavel Strejček @ BrainWeb.cz';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '1.6');
        $this->bootstrap = true;
        $this->controllers = array('payment', 'validate', 'confirm');

        parent::__construct();

        $this->displayName = $this->l('Pays');
        $this->description = $this->l('Payment gateway. Simple and easy to set up. No flat-rate and unreasonable charges. With features you will not find anywhere else.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        $this->modelDb = new PaysPsModelDb;
        $this->modelCurrency = new PaysPsModelCurrency;

        PaysPsModelMessage::setMessageList(array(
            'STATUS' =>
            array(
                'MODULE_paymentMethodChosen' => $this->l('Payment method Pays been chosen by the customer when the order is sent.'),
                'MODULE_responseSignedStatus_3' => $this->l('Response status: Customer successfully completed payment. Amount: %s'),
                'MODULE_confirmationSignedStatus_3' => $this->l('Background confirmation status: Customer successfully completed payment. Amount: %s')
            ),
            'ERROR' =>
            array(
                'MODULE_responseOrderNotExists' => $this->l('Error occurred when receiving response. That order does not exists.'),
                'MODULE_confirmationOrderNotExists' => $this->l('Error occurred when receiving background confirmation. That order does not exists.'),
                'MODULE_paymentNotExists' => $this->l('That payment record does not exists.'),
                'MODULE_responseSignedStatusMissing' => $this->l('Response status from Pays gateway is missing or unknown.'),
                'MODULE_responseSignedStatus_2' => $this->l('Response status: Payment not realized.'),
                'MODULE_confirmationSignedStatusMissing' => $this->l('Background confirmation status from Pays gateway is missing or unknown.'),
                'MODULE_confirmationSignedStatus_2' => $this->l('Background confirmation status: Payment not realized.'),
                'MODULE_responseSignatureNotValid' => $this->l('Response signature is not valid. Either the incorrect password in the module or the wrong response signature.'),
                'MODULE_confirmationSignatureNotValid' => $this->l('Background confirmation signature is not valid. Either the incorrect password in the module or the wrong response signature.'),
                'MODULE_responseUnknownCurrency' => $this->l('Unknown currency in response. The payment was not added to the order.'),
                'MODULE_confirmationUnknownCurrency' => $this->l('Unknown currency in background confirmation. The payment was not added to the order.'),
        )));

        $this->paysPsUnsignedStatusMessage = array(
            'online' => $this->l('Payment completed online, but this message is unsigned.'),
            'offline' => $this->l('Payment will be sent offline.'),
            'error' => $this->l('Failed to complete payment.')
        );

        $this->paysPsSignedStatusMessage = array(
            '2' => 'MODULE_responseSignedStatus_2',
            '3' => 'MODULE_responseSignedStatus_3',
        );

        $this->paysPsAllowedLanguages = array(
            'cs' => 'CS-CZ',
            'sk' => 'SK-SK',
            'en' => 'EN-US',
            'ru' => 'RU-RU',
            'ja' => 'JA-JP'
        );
    }

    public function install() {
        // default value of editable field
        $paymentDescriptions = array();
        foreach (Language::getLanguages(false) as $language) {
            if ($language['iso_code'] == 'cs') {
                $paymentDescriptions[$language['id_lang']] = "Na výběr je platba kartou, bankovním převodem, QR platba, přes PayPal nebo mobilní telefon.";
            } else {
                $paymentDescriptions[$language['id_lang']] = "You can choose from a card, bank transfer, QR payment, PayPal or mobile phone.";
            }
        }

        return $this->processSQL('install/db_structure.sql') &&
                parent::install() &&
                $this->installOrderStatusAwaiting() &&
                $this->installOrderStatusReceived() &&
                $this->installOrderStatusUnrealized() &&
                $this->registerHook('paymentReturn') &&
                $this->registerHook('payment') &&
                $this->registerHook('displayAdminOrder') &&
                $this->registerHook('displayBackOfficeHeader') &&
                $this->registerHook('actionAdminControllerSetMedia') &&
                $this->registerHook('displayHeader') &&
                $this->registerHook('displayOrderDetail') &&
                $this->registerHook('actionEmailAddAfterContent') &&
                Configuration::updateValue('PAYS_PS_PAYMENT_DESCRIPTION', $paymentDescriptions);
    }

    public function uninstall() {
        return parent::uninstall() &&
                $this->uninstallOrderState(Configuration::get('PAYS_PS_ORDER_STATUS_PAYMENT_AWAITING')) &&
                $this->uninstallOrderState(Configuration::get('PAYS_PS_ORDER_STATUS_PAYMENT_RECEIVED')) &&
                $this->uninstallOrderState(Configuration::get('PAYS_PS_ORDER_STATUS_PAYMENT_UNREALIZED')) &&
                $this->processSQL('uninstall/uninstall.sql') &&
                Configuration::deleteByName('PAYS_PS_MERCHANT') &&
                Configuration::deleteByName('PAYS_PS_SHOP') &&
                Configuration::deleteByName('PAYS_PS_PASSWORD') &&
                Configuration::deleteByName('PAYS_PS_PAYMENT_DESCRIPTION') &&
                Configuration::deleteByName('PAYS_PS_ORDER_STATUS_PAYMENT_AWAITING') &&
                Configuration::deleteByName('PAYS_PS_ORDER_STATUS_PAYMENT_RECEIVED') &&
                Configuration::deleteByName('PAYS_PS_ORDER_STATUS_PAYMENT_UNREALIZED');
    }

    public function processSQL($file) {
        $allowed_collation = array('utf8_general_ci', 'utf8_unicode_ci');
        $collation_database = Db::getInstance()->getValue('SELECT @@collation_database');


        if (!file_exists(PAYS_PS_DIR . '/' . $file)) {
            $this->_errors[] = Tools::displayError('SQL file not found.');
            return false;
        }
        $content = Tools::file_get_contents(PAYS_PS_DIR . '/' . $file);

        $metaData = array(
            'PREFIX_' => _DB_PREFIX_,
            'COLLATION' => (empty($collation_database) || !in_array($collation_database, $allowed_collation)) ? '' : 'COLLATE ' . $collation_database,
        );

        try {
            $content = str_replace(array_keys($metaData), array_values($metaData), $content);
            $queries = preg_split('#;\s*[\r\n]+#', $content);
            foreach ($queries as $query) {
                $query = trim($query);
                if (!$query) {
                    continue;
                }
                if (!Db::getInstance()->execute($query)) {
                    throw new Exception(Db::getInstance()->getMsgError());
                }
            }
        } catch (Exception $e) {
            $this->_errors[] = Tools::displayError($this->l('SQL error on query <i>%s</i>') . ' ' . $e->getMessage());
            return false;
        }

        return true;
    }

    private function orderStatusExistsByNames($settings) {

        $oldState = PaysPsModelOrderState::findOneByNames($settings['names']);
        if (Validate::isLoadedObject($oldState)) {
            $oldState->deleted = false;
            $oldState->unremovable = true;
            $oldState->save();
            return Configuration::updateValue($settings['confkey'], $oldState->id);
        }
        return false;
    }

    private function newOrderStatus($settings) {
        $orderState = new OrderState();
        $orderState->name = array();
        foreach (Language::getLanguages(false) as $language) {
            $orderState->name[$language['id_lang']] = empty($settings['names'][strtolower($language['iso_code'])]) ? $settings['names']['en'] : $settings['names'][strtolower($language['iso_code'])];
        }
        return $orderState;
    }

    private function addOrderStatus($orderState, $settings) {
        $orderState->add();
        if ($orderState->id) {
            copy(PAYS_PS_DIR . '/views/img/' . $settings['gif'] . '.gif', _PS_ORDER_STATE_IMG_DIR_ . $orderState->id . '.gif');
            return Configuration::updateValue($settings['confkey'], $orderState->id);
        }
        return false;
    }

    private function installOrderStatusAwaiting() {
        $settings = array(
            'names' => array(
                'en' => 'Awaiting Pays payment',
                'cs' => 'Čeká se na platbu Pays'
            ),
            'confkey' => 'PAYS_PS_ORDER_STATUS_PAYMENT_AWAITING',
            'gif' => 'pays_ps-status-awaiting'
        );

        if ($this->orderStatusExistsByNames($settings)) {
            return true;
        }

        $orderState = $this->newOrderStatus($settings);
        $orderState->send_email = false;
        $orderState->color = '#4169E1';
        $orderState->hidden = false;
        $orderState->delivery = false;
        $orderState->logable = false;
        $orderState->invoice = false;
        $orderState->shipped = false;
        $orderState->paid = false;
        $orderState->unremovable = true;
        $orderState->module_name = $this->name;

        if ($this->addOrderStatus($orderState, $settings)) {
            return true;
        }
        $this->_errors[] = Tools::displayError('Creation of awaiting payment status failed.');
    }

    private function installOrderStatusReceived() {
        $settings = array(
            'names' => array(
                'en' => 'Pays payment RECEIVED',
                'cs' => 'Platba Pays PŘIJATA'
            ),
            'confkey' => 'PAYS_PS_ORDER_STATUS_PAYMENT_RECEIVED',
            'gif' => 'pays_ps-status-received'
        );

        if ($this->orderStatusExistsByNames($settings)) {
            return true;
        }

        $orderState = $this->newOrderStatus($settings);
        $orderState->send_email = false;
        $orderState->color = '#32CD32';
        $orderState->hidden = false;
        $orderState->delivery = false;
        $orderState->logable = true;
        $orderState->invoice = true;
        $orderState->shipped = false;
        $orderState->paid = true;
        $orderState->unremovable = true;
        $orderState->module_name = $this->name;

        $paymentState = PaysPsModelOrderState::findOneByTemplateName('payment');
        if (Validate::isLoadedObject($paymentState)) {
            $orderState->send_email = $paymentState->send_email;
            foreach ($paymentState->template as $lang => $val) {
                $orderState->template[$lang] = $val;
            }
            $orderState->invoice = $paymentState->invoice;
            $orderState->delivery = $paymentState->delivery;
            $orderState->shipped = $paymentState->shipped;
        }

        if ($this->addOrderStatus($orderState, $settings)) {
            return true;
        }
        $this->_errors[] = Tools::displayError('Creation of payment received status failed.');
    }

    private function installOrderStatusUnrealized() {
        $settings = array(
            'names' => array(
                'en' => 'Pays payment UNREALIZED',
                'cs' => 'Platba Pays NEREALIZOVÁNA'
            ),
            'confkey' => 'PAYS_PS_ORDER_STATUS_PAYMENT_UNREALIZED',
            'gif' => 'pays_ps-status-unrealized'
        );

        if ($this->orderStatusExistsByNames($settings)) {
            return true;
        }

        $orderState = $this->newOrderStatus($settings);
        $orderState->send_email = false;
        $orderState->color = '#8f0621';
        $orderState->hidden = false;
        $orderState->delivery = false;
        $orderState->logable = false;
        $orderState->invoice = false;
        $orderState->shipped = false;
        $orderState->paid = false;
        $orderState->unremovable = true;
        $orderState->module_name = $this->name;

        $paymentErrState = PaysPsModelOrderState::findOneByTemplateName('payment_error');
        if (Validate::isLoadedObject($paymentErrState)) {
            $orderState->send_email = $paymentErrState->send_email;
            foreach ($paymentErrState->template as $lang => $val) {
                $orderState->template[$lang] = $val;
            }
        }

        if ($this->addOrderStatus($orderState, $settings)) {
            return true;
        }
        $this->_errors[] = Tools::displayError('Creation of payment unrealized status failed.');
    }

    private function uninstallOrderState($idOrderState) {
        $orderState = new OrderState($idOrderState);
        if (Validate::isLoadedObject($orderState)) {
            $orderState->deleted = true;
            $orderState->unremovable = false;
            $orderState->save();
        }
        return true;
    }

    private function isActive() {

        $password = Configuration::get('PAYS_PS_PASSWORD');
        return $this->active && !empty($password);
    }

    public function hookPayment($params) {
        if (!$this->isActive()) {
            return;
        }
        $currency = new Currency($params['cart']->id_currency);
        $payment_options = '';
        if ($this->modelCurrency->isCurrencyAvailable($currency->iso_code)) {
            $linkParams = array(
                'optionHash' => $this->getOptionHash('PAYMENTGATEWAY')
            );
            $this->context->smarty->assign(
                    array(
                        'paysPsPaymentIconPath' => _MODULE_DIR_ . $this->name . '/views/img/pays_ps-payment.png',
                        'paysPsPaymentGatewayLink' => $this->context->link->getModuleLink($this->name, 'payment', $linkParams, null, null, null, true),
                        'paysPsPaymentDescription' => Configuration::get('PAYS_PS_PAYMENT_DESCRIPTION', $this->context->customer->id_lang),
                    )
            );

            $payment_options .= $this->context->smarty->fetch(PAYS_PS_DIR . '/views/templates/hook/payment_paymentgateway.tpl');
        }

        return $payment_options;
    }

    public function hookPaymentReturn($params) {
        if (!$this->isActive()) {
            return;
        }
        $payment = new PaysPsModelPayment($params['objOrder']->id_cart);
        if ($payment instanceof PaysPsModelPayment) {
            $statusMsg = $paymentUrl = '';
            $response = $payment->getLastResponse();
            if (!$response instanceof PaysPsModelResponse) {
                if (array_key_exists($payment->gate_response_status, $this->paysPsUnsignedStatusMessage)) {
                    $statusMsg = $this->paysPsUnsignedStatusMessage[$payment->gate_response_status];
                } else {
                    $statusMsg = $this->l('Unknown response status of payment.');
                }
            }

            if ($this->isOrderForPayment($params['objOrder'])) {
                $paymentUrl = $this->createPaymentUrl($params['objOrder']);
            }

            $this->context->smarty->assign(array(
                'paysPsLogoPath' => $this->getLogoPath(),
                'paysPsMessages' => PaysPsModelMessage::getTranslatedMessages($payment->id),
                'paysPsStatusMsg' => $statusMsg,
                'paysPsPaymentUrl' => $paymentUrl
            ));
            return $this->context->smarty->fetch(PAYS_PS_DIR . '/views/templates/hook/payment_return.tpl');
        }
    }

    public function hookDisplayAdminOrder($params) {
        if (!$this->isActive()) {
            return;
        }
        $order = new Order($params['id_order']);
        if (Validate::isLoadedObject($order)) {
            $this->context->smarty->assign(array(
                'paysPsLogoPath' => $this->getLogoPath()
            ));
            $payment = new PaysPsModelPayment($order->id_cart);
            if (Validate::isLoadedObject($payment)) {
                $this->context->smarty->assign(array(
                    'paysPsMsgs' => PaysPsModelMessage::getTranslatedMessages($payment->id),
                    'paysPsPayment' => $payment,
                    'paysPsResponses' => $payment->getResponses()
                ));
            }
            if ($this->isOrderForPayment($order, false)) {
                $paymentUrl = $this->createPaymentUrl($order, false);
                $this->context->smarty->assign(array(
                    'paysPsPaymentUrl' => $paymentUrl
                ));
            }
            return $this->display(__FILE__, 'views/templates/hook/admin_order.tpl');
        }
    }

    public function hookDisplayHeader() {
        if (!$this->active) {
            return;
        }
        $this->context->controller->addCSS($this->_path . '/views/css/front.css', 'all');
    }

    public function hookActionAdminControllerSetMedia($params) {
        if (!$this->active) {
            return;
        }
        $this->context->controller->addJS($this->_path . '/views/js/admin-order-detail.js');
        $this->context->controller->addCSS($this->_path . '/views/css/admin.css');
    }

    public function hookDisplayOrderDetail($params) {
        if (!$this->isActive()) {
            return;
        }
        if ($this->isOrderForPayment($params['order'])) {
            $this->context->smarty->assign(array(
                'paysPsLogoPath' => $this->getLogoPath(),
                'paysPsPaymentUrl' => $this->createPaymentUrl($params['order']),
            ));
            return $this->context->smarty->fetch(PAYS_PS_DIR . '/views/templates/hook/order_detail.tpl');
        }
    }

    /**
     *
     * @since PrestaShop 1.6.1.0
     */
    public function hookActionEmailAddAfterContent($params) {
        if (!$this->isActive()) {
            return;
        }

        if ($params['template'] == 'order_conf' && !empty($this->currentOrderReference)) {
            $orders = Order::getByReference($this->currentOrderReference);
            if ($orders instanceof PrestaShopCollection && $orders->count()) {
                $order = $orders->getFirst();
                if (!empty($params['template_html'])) {
                    $params['template_html'] = preg_replace('~\{payment\}~', '{payment} <br/>' . $this->l('If you have not already made a payment, you can pay:') . ' <a class="pays_ps-payment-link" href="' . htmlspecialchars($this->createPaymentUrl($order)) . '">' . $this->l('PAY NOW') . '</a>', $params['template_html']);
                }
                if (!empty($params['template_txt'])) {
                    $params['template_txt'] = preg_replace('~\{payment\}~', "{payment} \r\n" . $this->l('If you have not already made a payment, you can pay:') . ' [' . $this->createPaymentUrl($order) . ']', $params['template_txt']);
                }
            }
        }
    }

    public function isOrderForPayment($order, $checkPaymentMethod = true) {
        $compare = PaysPsModelUtils::floatcmp($order->getTotalPaid(), $order->total_paid_tax_incl) == -1;
        if ($checkPaymentMethod) {
            return $order->module == $this->name && $compare;
        }
        return $compare;
    }

    public function getContent() {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $pays_ps_merchant = trim(Tools::getValue('PAYS_PS_MERCHANT'));
            $pays_ps_shop = trim(Tools::getValue('PAYS_PS_SHOP'));
            $pays_ps_password = trim(Tools::getValue('PAYS_PS_PASSWORD'));

            $pays_ps_payment_description = array();
            foreach (Language::getLanguages(false) as $lang) {
                $pays_ps_payment_description[$lang['id_lang']] = trim(Tools::getValue('PAYS_PS_PAYMENT_DESCRIPTION_' . (int) $lang['id_lang']));
            }

            if (empty($pays_ps_merchant)) {
                $output .= $this->displayError($this->l('Merchant ID is missing'));
            }

            if (empty($pays_ps_shop)) {
                $output .= $this->displayError($this->l('Shop ID is missing'));
            }

            if (empty($output)) {
                Configuration::updateValue('PAYS_PS_MERCHANT', $pays_ps_merchant);
                Configuration::updateValue('PAYS_PS_SHOP', $pays_ps_shop);
                if (!empty($pays_ps_password)) {
                    Configuration::updateValue('PAYS_PS_PASSWORD', $pays_ps_password);
                }
                Configuration::updateValue('PAYS_PS_PAYMENT_DESCRIPTION', $pays_ps_payment_description);
                $output .= $this->displayConfirmation($this->l('Configuration updated'));
            } else {
                $output .= $this->displayError($this->l('Nothing has been saved.'));
            }
        }

        $this->context->smarty->assign(array(
            'paysPsLogoPath' => $this->getLogoPath(),
            'paysPsModulePath' => _MODULE_DIR_ . $this->name,
            'paysPsSuccessOnlineURL' => $this->removeLanguageFromUrl($this->context->link->getModuleLink($this->name, 'validation', array('status' => 'online'), true)),
            'paysPsSuccessOfflineURL' => $this->removeLanguageFromUrl($this->context->link->getModuleLink($this->name, 'validation', array('status' => 'offline'), true)),
            'paysPsErrorURL' => $this->removeLanguageFromUrl($this->context->link->getModuleLink($this->name, 'validation', array('status' => 'error'), true)),
            'paysPsConfirmationURL' => $this->removeLanguageFromUrl($this->context->link->getModuleLink($this->name, 'confirm', array(), true))
        ));

        $output .= $this->context->smarty->fetch(PAYS_PS_DIR . '/views/templates/admin/panel_main_settings.tpl');

        $output .= $this->displayForm();

        $output .= $this->context->smarty->fetch(PAYS_PS_DIR . '/views/templates/admin/panel_payment_confirmation.tpl');

        return $output;
    }

    private function removeLanguageFromUrl($url) {
        return preg_replace('~^([^/]+//[^/]+/)([^/]{2}/)~', '$1', $url);
    }

    public function displayForm() {
        $helper = new HelperForm();
        // Get default language
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form = array();
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('SETTING PAYMENT GATEWAY'),
            ),
            'input' => array(
                array(
                    'id' => 'info_registering',
                    'type' => 'html',
                    'label' => '',
                    'name' => '<div style="margin-top:8px"><em>' . $this->l('You will receive this information after registering at Pays.cz') . '</em></div>'
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Merchant:'),
                    'name' => 'PAYS_PS_MERCHANT',
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Shop:'),
                    'name' => 'PAYS_PS_SHOP',
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'password',
                    'label' => $this->l('API password:'),
                    'name' => 'PAYS_PS_PASSWORD',
                    'size' => 20,
                    'required' => false,
                ),
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Description of payment option:'),
                    'lang' => true,
                    'name' => 'PAYS_PS_PAYMENT_DESCRIPTION',
                    'required' => false,
                    'desc' => $this->l('This message will be displayed to the customer when selecting the payment type.')
                ),
                array(
                    'id' => 'info_currencies',
                    'type' => 'html',
                    'label' => $this->l('Supported currencies:'),
                    'name' => '<div style="margin-top:8px">' . implode(', ', $this->modelCurrency->getAll()) . '</div>'
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $languages = Language::getLanguages(false);
        foreach ($languages as $key => $language) {
            $languages[$key]['is_default'] = (int) ($language['id_lang'] == $default_lang);
        }
        $helper->languages = $languages;
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;  // false -> remove toolbar
        $helper->toolbar_scroll = true;   // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' =>
            array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        if (Tools::isSubmit('submit' . $this->name)) {
            $helper->fields_value['PAYS_PS_MERCHANT'] = Tools::getValue('PAYS_PS_MERCHANT');
            $helper->fields_value['PAYS_PS_SHOP'] = Tools::getValue('PAYS_PS_SHOP');
            foreach ($helper->languages as $lang) {
                $helper->fields_value['PAYS_PS_PAYMENT_DESCRIPTION'][$lang['id_lang']] = Tools::getValue('PAYS_PS_PAYMENT_DESCRIPTION_' . (int) $lang['id_lang']);
            }
        } else {
            $helper->fields_value['PAYS_PS_MERCHANT'] = Configuration::get('PAYS_PS_MERCHANT');
            $helper->fields_value['PAYS_PS_SHOP'] = Configuration::get('PAYS_PS_SHOP');
            foreach ($helper->languages as $lang) {
                $helper->fields_value['PAYS_PS_PAYMENT_DESCRIPTION'][$lang['id_lang']] = Configuration::get('PAYS_PS_PAYMENT_DESCRIPTION', $lang['id_lang']);
            }
        }
        return $helper->generateForm($fields_form);
    }

    public function getLogoPath() {
        return _MODULE_DIR_ . $this->name . '/logo.png';
    }

    private function getOptionHash($option) {
        $hash = uniqid('', true);
        $this->context->cookie->{'pays_ps_option_' . $option . '_hash'} = $this->context->cart->id . '_' . $hash;
        return $hash;
    }

    public function checkOptionHash($option, $id_cart, $hash) {
        $cookie = $this->context->cookie->{'pays_ps_option_' . $option . '_hash'};
        $pair = explode('_', $cookie);
        if (!empty($pair[0]) && $pair[0] == $id_cart && !empty($pair[1]) && $pair[1] == $hash) {
            return true;
        }
        return false;
    }

    public function createPaymentUrl($order, $email = true) {
        $customer = new Customer($order->id_customer);
        $currency = new Currency($order->id_currency);
        $language = new Language($order->id_lang);

        $params = array(
            'Merchant' => Configuration::get('PAYS_PS_MERCHANT'),
            'Shop' => Configuration::get('PAYS_PS_SHOP'),
            'Currency' => $currency->iso_code,
            'Amount' => number_format(($order->total_paid_tax_incl - $order->getTotalPaid()) * 100, 0, '.', ''),
            'MerchantOrderNumber' => $order->reference
        );

        if ($email) {
            $params['Email'] = $customer->email;
        }

        if (array_key_exists(strtolower($language->iso_code), $this->paysPsAllowedLanguages)) {
            $params['Lang'] = $this->paysPsAllowedLanguages[$language->iso_code];
        }

        $url = 'https://www.pays.cz/paymentorder?' . http_build_query($params, '', '&');

        return $url;
    }

    public function validateResponseHash($PaymentOrderID, $MerchantOrderNumber, $PaymentOrderStatusID, $CurrencyID, $Amount, $CurrencyBaseUnits, $hash) {
        $computedHash = hash_hmac('md5', $PaymentOrderID . $MerchantOrderNumber . $PaymentOrderStatusID . $CurrencyID . $Amount . $CurrencyBaseUnits, Configuration::get('PAYS_PS_PASSWORD'));
        return $computedHash === $hash;
    }

    public function changeOrderStatus($order, $id_order_state) {
        try {
            $order_state = new OrderState($id_order_state);

            if (Validate::isLoadedObject($order_state)) {
                $current_order_state = $order->getCurrentOrderState();
                if ($current_order_state->id != $order_state->id) {
                    // Create new OrderHistory
                    $history = new OrderHistory();
                    $history->id_order = $order->id;

                    $use_existings_payment = false;
                    if (!$order->hasInvoice()) {
                        $use_existings_payment = true;
                    }
                    $history->changeIdOrderState((int) $order_state->id, $order, $use_existings_payment);

                    $carrier = new Carrier($order->id_carrier, $order->id_lang);
                    $templateVars = array();
                    if ($history->id_order_state == Configuration::get('PS_OS_SHIPPING') && $order->shipping_number) {
                        $templateVars = array('{followup}' => str_replace('@', $order->shipping_number, $carrier->url));
                    }

                    // Save all changes
                    if ($history->addWithemail(true, $templateVars)) {
                        // synchronizes quantities if needed..
                        if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
                            foreach ($order->getProducts() as $product) {
                                if (StockAvailable::dependsOnStock($product['product_id'])) {
                                    StockAvailable::synchronize($product['product_id'], (int) $product['id_shop']);
                                }
                            }
                        }
                        return true;
                    }
                }
            }
        } catch (Exception $e) {
            $emsg = $e->getMessage();
        }
        PrestaShopLogger::addLog($this->l('Pays: Unable to complete order status change to "Non-cash payment received" when confirming payment from service.') . (empty($emsg) ? '' : ' Exception: ' . $emsg), 1, null, 'Order', $order->id, true);
    }

}
