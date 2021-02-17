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
class PaysPsModelResponse extends ObjectModel
{

    /** @var int primary autoincrement */
    public $payment_response_id;

    /** @var int Pays payment reference */
    public $payment_order_id;

    /** @var int */
    public $id_cart;

    /** @var string Orer referance */
    public $merchant_order_number;

    /** @var int Status 2 = not realized, 3 = paid */
    public $payment_order_status_id;

    /** @var string currency code */
    public $currency_id;

    /** @var string Amount in the smallest units of a given currency  */
    public $amount;

    /** @var string units of a given currency, Total = Amount/CurrencyBaseUnits  */
    public $currency_base_units;

    /** @var string description */
    public $payment_order_status_description;

    /** @var string signature hash; Data2Hash = PaymentOrderID + MerchantOrderNumber + PaymentOrderStatusID + CurrencyID + Amount + CurrencyBaseUnits; Hash = MD5HMACEncode(Data2Hash, Password) */
    public $hash;

    /** @var datetime create at */
    public $date_add;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'pays_ps_response',
        'primary' => 'payment_response_id',
        'multilang' => false,
        'multilang_shop' => false,
        'fields' => array(
            /* Classic fields */
            'payment_order_id' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'id_cart' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'merchant_order_number' => array('type' => self::TYPE_STRING, 'size' => 15),
            'payment_order_status_id' => array('type' => self::TYPE_INT),
            'currency_id' => array('type' => self::TYPE_STRING, 'size' => 3),
            'amount' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'currency_base_units' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'payment_order_status_description' => array('type' => self::TYPE_STRING, 'size' => 1000),
            'hash' => array('type' => self::TYPE_STRING, 'size' => 32),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
        /* Lang fields */
        ),
    );

    public function getPrestashopOrder()
    {
        $sql = new DbQuery();
        $sql->select('o.id_order')
                ->from('orders', 'o')
                ->where("o.id_cart = '" . pSQL($this->id_cart) . "'");
        $order = Db::getInstance()->executeS($sql);
        if (empty($order[0]['id_order'])) {
            return null;
        } else {
            return new Order($order[0]['id_order']);
        }
    }

    public static function isLastResponse($paymentOrderId, $hash)
    {
        $sql = new DbQuery();
        $sql->select('pr.payment_response_id')
                ->from(self::$definition['table'], 'pr')
                ->where("pr.payment_order_id = " . (int) $paymentOrderId)
                ->orderBy('pr.date_add DESC, pr.payment_response_id DESC')
                ->limit(1);
        $result = Db::getInstance()->executeS($sql);
        if (empty($result[0]['payment_response_id'])) {
            return false;
        }
        $response = new self($result[0]['payment_response_id']);

        return $response->hash === $hash;
    }

    public function getPrice()
    {
        return number_format((int) $this->currency_base_units ? (int) $this->amount / (int) $this->currency_base_units : (int) $this->amount, 2, '.', '');
    }
}
