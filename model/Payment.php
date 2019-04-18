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
class PaysPsModelPayment extends ObjectModel {

    /** @var int */
    public $id_customer;

    /** @var string Order reference */
    public $merchant_order_number;

    /** @var string Response status from unsigned redirected response if any */
    public $gate_response_status;
    public $date_add;
    public $date_upd;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'pays_ps_payment',
        'primary' => 'id_cart',
        'multilang' => false,
        'multilang_shop' => false,
        'fields' => array(
            /* Classic fields */
            'id_customer' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'merchant_order_number' => array('type' => self::TYPE_STRING, 'size' => 15),
            'gate_response_status' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
        /* Lang fields */
        ),
    );

    public function __construct($id = null, $id_lang = null, $id_shop = null, $createNonExistent = false) {
        if ($id && $createNonExistent) {
            $sql = new DbQuery();
            $sql->select('count(*) cnt')
                    ->from(self::$definition['table'], 'a')
                    ->where('a.' . self::$definition['primary'] . ' = ' . (int) $id);
            $result = Db::getInstance()->executeS($sql);
            if (empty($result[0]['cnt'])) {
                $newEntity = array(
                    self::$definition['primary'] => (int) $id
                );

                Db::getInstance()->insert(self::$definition['table'], $newEntity, true);
            }
        }

        parent::__construct($id, $id_lang, $id_shop);
    }

    public function getPrestashopOrder() {
        $sql = new DbQuery();
        $sql->select('o.id_order')
                ->from('orders', 'o')
                ->where("o.id_cart = '" . pSQL($this->id) . "'");
        $order = Db::getInstance()->executeS($sql);
        if (empty($order[0]['id_order'])) {
            return null;
        } else {
            return new Order($order[0]['id_order']);
        }
    }

    public static function getByOrderReference($orderReference) {
        $sql = new DbQuery();
        $sql->select('pa.id_cart')
                ->from(self::$definition['table'], 'pa')
                ->where("pa.merchant_order_number = '" . pSQL($orderReference) . "'");
        $order = Db::getInstance()->executeS($sql);
        if (empty($order[0]['id_cart'])) {
            return null;
        } else {
            return new self($order[0]['id_cart']);
        }
    }

    public function getLastResponse() {
        $sql = new DbQuery();
        $sql->select('pr.payment_response_id')
                ->from('pays_ps_response', 'pr')
                ->where("pr.id_cart = " . (int) $this->id)
                ->orderBy('pr.date_add DESC, pr.payment_response_id DESC')
                ->limit(1);
        $result = Db::getInstance()->executeS($sql);
        if (empty($result[0]['payment_response_id'])) {
            return null;
        }
        return new PaysPsModelResponse($result[0]['payment_response_id']);
    }

    public function getResponses() {
        $responses = new PrestaShopCollection('PaysPsModelResponse');
        $responses->where('id_cart', '=', $this->id)->orderBy('date_add', 'asc')->orderBy('payment_response_id', 'asc');
        return $responses;
    }

}
