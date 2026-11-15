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
    function initPaysAdminOrder() {
        var panel = document.getElementById('pays_ps-payment');
        if (!panel) {
            return;
        }
        var target = document.querySelector('#view_order_payments_block .card-body');
        if (target) {
            target.appendChild(panel);
        }
        var copyButton = document.getElementById('pays_ps-paymentUrlCopyButton');
        var copySource = document.getElementById('pays_ps-paymentUrlCopy');
        if (copyButton && copySource) {
            copyButton.addEventListener('click', function () {
                var copyText = copySource.textContent || '';
                if (!copyText || !navigator.clipboard || !navigator.clipboard.writeText) {
                    return;
                }
                navigator.clipboard.writeText(copyText).then(function () {
                    alert('Copied to clipboard');
                }).catch(function () {});
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPaysAdminOrder);
    } else {
        initPaysAdminOrder();
    }
})();

