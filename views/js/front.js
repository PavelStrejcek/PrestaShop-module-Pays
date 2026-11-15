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
 * @copyright 2019 - 2026 Pavel Strejček
 * @license   Licensed under the Open Software License version 3.0  https://opensource.org/licenses/OSL-3.0
 *
 * Payment gateway operator and support: www.Pays.cz
 * Module development: www.BrainWeb.cz
 */

(function () {
    // Checkout validation/retries belong to PrestaShop. A global click guard
    // also blocks other payment methods and retries after validation errors.
    // Vanilla JS is required because PS 9.1+ Hummingbird loads no global jQuery.
    function markPaymentLogo() {
        document.querySelectorAll('.payment-option label img[src*="pays_ps-payment"]').forEach(function (img) {
            img.classList.add('pays_ps-option-img');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', markPaymentLogo);
    } else {
        markPaymentLogo();
    }
})();
