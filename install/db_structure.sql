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

DROP TABLE IF EXISTS `PREFIX_pays_ps_message`;

DROP TABLE IF EXISTS `PREFIX_pays_ps_currency`;

DROP TABLE IF EXISTS `PREFIX_pays_ps_response`;

DROP TABLE IF EXISTS `PREFIX_pays_ps_payment`;

CREATE TABLE `PREFIX_pays_ps_payment` (
  `id_cart` int(11) NOT NULL,
  `id_customer` int(11) NOT NULL,
  `merchant_order_number` varchar(15) DEFAULT NULL,
  `gate_response_status` ENUM('unknown','online','offline','error') NULL DEFAULT NULL,
  `date_add` timestamp NULL DEFAULT NULL,
  `date_upd` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATION;

ALTER TABLE `PREFIX_pays_ps_payment`
  ADD PRIMARY KEY (`id_cart`),
  ADD KEY `merchant_order_number` (`merchant_order_number`),
  ADD KEY `id_customer` (`id_customer`);

CREATE TABLE `PREFIX_pays_ps_response` (
  `payment_response_id` int(11) NOT NULL,
  `payment_order_id` int(11) UNSIGNED NOT NULL,
  `id_cart` int(11) NOT NULL,
  `merchant_order_number` varchar(15) NOT NULL,
  `payment_order_status_id` tinyint(1) DEFAULT NULL,
  `currency_id` varchar(3) DEFAULT NULL,
  `amount` int(11) DEFAULT NULL,
  `currency_base_units` int(11) DEFAULT NULL,
  `payment_order_status_description` varchar(1000) DEFAULT NULL,
  `hash` varchar(32) NOT NULL,
  `date_add` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATION;


ALTER TABLE `PREFIX_pays_ps_response`
  ADD PRIMARY KEY (`payment_response_id`),
  ADD KEY `id_cart` (`id_cart`),
  ADD KEY `merchant_order_number` (`merchant_order_number`),
  ADD KEY `payment_order_id` (`payment_order_id`);

ALTER TABLE `PREFIX_pays_ps_response`
  MODIFY `payment_response_id` int(11) NOT NULL AUTO_INCREMENT;


CREATE TABLE `PREFIX_pays_ps_currency` (
  `currency` varchar(3) CHARACTER SET utf8 NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATION;

INSERT INTO `PREFIX_pays_ps_currency` (`currency`) VALUES
('CZK'),
('EUR'),
('USD');

ALTER TABLE `PREFIX_pays_ps_currency`
  ADD PRIMARY KEY (`currency`);

CREATE TABLE `PREFIX_pays_ps_message` (
  `id_message` int(11) NOT NULL,
  `id_cart` int(11) NOT NULL,
  `type` enum('STATUS','ERROR') NOT NULL,
  `code` varchar(30) DEFAULT NULL,
  `message` varchar(1000) DEFAULT NULL,
  `param` varchar(4000) DEFAULT NULL,
  `date_add` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATION;

ALTER TABLE `PREFIX_pays_ps_message`
  ADD PRIMARY KEY (`id_message`),
  ADD KEY `id_cart` (`id_cart`);

ALTER TABLE `PREFIX_pays_ps_message`
  MODIFY `id_message` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `PREFIX_pays_ps_message`
  ADD CONSTRAINT `PREFIX_pays_ps_message_ibfk_1` FOREIGN KEY (`id_cart`) REFERENCES `PREFIX_pays_ps_payment` (`id_cart`) ON DELETE CASCADE ON UPDATE CASCADE;

