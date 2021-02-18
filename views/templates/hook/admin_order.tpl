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
 * @copyright 2019 - 2021 Pavel Strejček
 * @license   Licensed under the Open Software License version 3.0  https://opensource.org/licenses/OSL-3.0
 *
 * Payment gateway operator and support: www.Pays.cz
 * Module development: www.BrainWeb.cz
 *}
 {if !empty($paysPsResponses) && $paysPsResponses->count() || !empty($paysPsMsgs) || !empty($paysPsPaymentUrl)}
 <div id="pays_ps-payment">
    <br/>
	<h4>
		<img src="{$paysPsLogoPath|escape:'html'}" height="32" alt=""> &nbsp;&nbsp;{l s='Payment Gateway Pays ' mod='pays_ps'}
	</h4>

		
       {if !empty($paysPsResponses) && $paysPsResponses->count()}
        <table class="table table-bordered">
            <thead>
                <tr><th colspan="6"><strong>{l s='Signed responses from gateway' mod='pays_ps'}</strong></th></tr>
            <tr>
                <th>{l s='Time' mod='pays_ps'}</th>
                <th>{l s='ID' mod='pays_ps'}</th>
                <th>{l s='Status' mod='pays_ps'}</th>
                <th>{l s='Currency' mod='pays_ps'}</th>
                <th>{l s='Price' mod='pays_ps'}</th>
                <th>{l s='Additional description' mod='pays_ps'}</th>
            </tr>
            </thead>
            <tbody
            {foreach from=$paysPsResponses item=paysPsResponse}
            <tr>
                <td>{dateFormat date=$paysPsResponse->date_add full=1}</td>
                <td>{$paysPsResponse->payment_order_id|escape:'html'}</td>
                <td>{if $paysPsResponse->payment_order_status_id == 2}
                        {l s='UNREALIZED' mod='pays_ps'}
                    {elseif $paysPsResponse->payment_order_status_id == 3}
                        {l s='COMPLETED' mod='pays_ps'}
                    {else}
                        {l s='UNKNOWN' mod='pays_ps'}
                    {/if}
                </td>
                <td>{$paysPsResponse->currency_id|escape:'html'}</td>
                <td>{$paysPsResponse->getPrice()|escape:'html'}</td>
                <td>{$paysPsResponse->payment_order_status_description|escape:'html'}</td>
            </tr>
            {/foreach}
            </tbody>
        </table>
        {/if}
		

		{if !empty($paysPsMsgs)}
        <div class="panel">
            
                <p>{l s='Messages:' mod='pays_ps'}</p>
                <ul class="text-info">
                    {foreach $paysPsMsgs as $msg}
                        <li{if $msg['object']->type == 'ERROR'} class="pays_ps-messsage-error"{/if}>{dateFormat date=$msg['object']->date_add full=1} {$msg['translation']|escape:'html'}</li>
                        {/foreach}
                </ul>
            
        </div>
        {/if}
        
        {if !empty($paysPsPaymentUrl)}
        <div class="panel">
            <p>{l s='Payment URL:' mod='pays_ps'}</p>
            <p><code>{$paysPsPaymentUrl|escape:'html'}</code></p>
        </div>
        {/if}

</div>
{/if}