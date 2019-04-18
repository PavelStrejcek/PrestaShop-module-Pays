{**
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
 *}
 <div id="pays_ps-order-detail" class="box">
	<h4>
		 {l s='Payment' mod='pays_ps'}
	</h4>
    <p>{l s='The order is not paid. If the payment has been made, wait until the payment is complete.' mod='pays_ps'}</p>
    <p>
        <img src="{$paysPsLogoPath|escape:'html'}" height="32" alt=""></p>
    <p>
        <a href="{$paysPsPaymentUrl|escape:'html'}" class="btn btn-primary">{l s='Pay now through Pays' mod='pays_ps'}</a>
    </p>
    
</div>
