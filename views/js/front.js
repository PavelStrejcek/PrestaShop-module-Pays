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

$(function () {
    window.paysPsOrderClicked = false;
    if (! window.paysPsClickEventDefined) {
        $('#payment-confirmation').click(function (e) {
            if (window.paysPsOrderClicked) {
                return false;
            }
            window.paysPsOrderClicked = true;
            return true;
        });
        $('.payment-option label img[src*="pays_ps-payment"]').addClass('pays_ps-option-img');
        window.paysPsClickEventDefined = true;
    }
});