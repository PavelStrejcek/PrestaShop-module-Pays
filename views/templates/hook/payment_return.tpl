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
 * @author    Pavel Strejček <pavel.strejcek@brainweb.cz>
 * @copyright 2019 Pavel Strejček
 * @license   Licensed under the Open Software License version 3.0  https://opensource.org/licenses/OSL-3.0
 *
 * Payment gateway operator and support: www.Pays.cz
 * Module development: www.BrainWeb.cz
 *}
<section id="content" class="pays_ps-payment">
	<img src="{$paysPsLogoPath|escape:'html':'UTF-8'}" height="32" alt="">
    <h3>{l s='Information about using Pays payment gateway' mod='pays_ps'}</h3>
    {if !empty($paysPsStatusMsg)}
        <p class="well"><strong>{$paysPsStatusMsg|escape:'html':'UTF-8'}</strong></p>
	{elseif !empty($paysPsMessages)}

		<ul>
			{foreach $paysPsMessages as $message}
				<li{if $message['object']->type == 'ERROR'} class="pays_ps-messsage-error"{/if}>{$message['translation']|escape:'html':'UTF-8'}</li>
				{/foreach}
		</ul>

	{else}
		{l s='No information.' mod='pays_ps'}
	{/if}
    
    {if !empty($paysPsPaymentUrl)}
        <p><a href="{$paysPsPaymentUrl|escape:'html':'UTF-8'}" class="button btn btn-default button-medium">
                <span>{l s='New payment' mod='pays_ps'}<i class="icon-chevron-right right"></i></span>
           </a></p>
    {/if}
</section>

