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
class PaysPsModelCurrency {

    public function insert($data) {
        array_walk($data, array('PaysPsModelDb', 'escapeCallback'));
        return Db::getInstance()->insert('pays_ps_currency', $data);
    }

    public function deleteAll() {
        return Db::getInstance()->delete('pays_ps_currency');
    }

    public function isCurrencyAvailable($currency) {
        $sql = new DbQuery;
        $sql->select("count(*) cnt")
                ->from('pays_ps_currency', 'vc')
                ->where("vc.currency = '" . pSQL($currency) . "'");
        $result = Db::getInstance()->executeS($sql);
        return !empty($result[0]['cnt']);
    }

    public function getAll() {
        $sql = new DbQuery;
        $sql->select("*")
                ->from('pays_ps_currency', 'vc')
                ->orderBy("vc.currency");
        $result = Db::getInstance()->executeS($sql);
        $currencies = array();
        foreach ($result as $row) {
            $currencies[] = $row['currency'];
        }
        return $currencies;
    }

}
