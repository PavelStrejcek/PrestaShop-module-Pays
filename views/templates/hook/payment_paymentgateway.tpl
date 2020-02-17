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
{if $paysPsSimpleView}
    <p class="payment_module pays_ps_payment_module">
	<a href="{$paysPsPaymentGatewayLink|escape:'html':'UTF-8'}" title="{l s='Pays Payment Gateway' mod='pays_ps'}">
		<img src="{$paysPsPaymentIconPath|escape:'html':'UTF-8'}" alt="" width="100" > {l s='Pays Payment Gateway' mod='pays_ps'}&nbsp;<span>({$paysPsPaymentDescription|escape:'html':'UTF-8'})</span>
	</a>
    </p>
{else}
 <div class="payment_module panel panel-default pays_ps-option">
	<div class="panel-body">
        <h2><img src="{$paysPsPaymentIconPath|escape:'html':'UTF-8'}" alt="" width="100" > {l s='Pays Payment Gateway' mod='pays_ps'}</h2>
        <div class="row">
            <div class="col-md-7">
                <p class="lead">{$paysPsPaymentDescription|escape:'html':'UTF-8'}</p>
                <p><a href="https://www.pays.cz/" target="_blank">www.pays.cz</a></p>
            </div>
			<div class="col-md-5">
                <p><strong>{l s='To make an online payment, you will be redirected to a payment gateway.' mod='pays_ps'}</strong></p>
                <p><a href="{$paysPsPaymentGatewayLink|escape:'html':'UTF-8'}" class="button btn btn-default button-medium" id="payment-confirmation-pays-ps">
                    <span>
                        {l s='Confirm order and pay' mod='pays_ps'}
                        <i class="icon-chevron-right right"></i>
                    </span>
                </a></p>
            </div>

        </div>
	</div>
</div>
        
<script type="text/javascript">
    $(function () {
        window.paysPsOrderClicked = false;
        $('#payment-confirmation-pays-ps').click(function (e) {

            if (paysPsOrderClicked) {
                return false;
            }
            window.paysPsOrderClicked = true;

            return true;
        });
    });
</script>
{/if}