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
class PaysPsModelOrderState extends OrderState {

    public static function findOneByTemplateName($templateName) {
        $sql = new DbQuery();
        $sql->select('os.id_order_state')
                ->from('order_state', 'os')
                ->innerJoin('order_state_lang', 'osl', 'os.id_order_state = osl.id_order_state')
                ->where("os.deleted = 0 AND osl.template='" . pSQL($templateName) . "'")
                ->orderBy('os.id_order_state')
                ->limit(1);
        $result = Db::getInstance()->executeS($sql);

        if (!empty($result[0]['id_order_state'])) {
            return new self($result[0]['id_order_state']);
        }
    }

    /**
     *
     * @param array $names
     * @return \self
     */
    public static function findOneByNames($names) {
        array_walk($names, array('PaysPsModelDb', 'escapeCallback'));
        $sql = new DbQuery();
        $sql->select('os.id_order_state')
                ->from('order_state', 'os')
                ->innerJoin('order_state_lang', 'osl', 'os.id_order_state = osl.id_order_state')
                ->where("osl.name IN ('" . implode("','", $names) . "')")
                ->orderBy('os.id_order_state')
                ->limit(1);
        $result = Db::getInstance()->executeS($sql);

        if (!empty($result[0]['id_order_state'])) {
            return new self($result[0]['id_order_state']);
        }
    }

}
