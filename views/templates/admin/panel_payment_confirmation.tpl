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
     <div class="panel">
        <div class="panel-heading">
            {l s='Communication addresses' mod='pays_ps'}	
        </div>
        <p>
            <em>
            {l s='Copy and send this data to Pays.cz' mod='pays_ps'}
            </em>
        </p>
        <table>
            <tr><td>{l s='Successful online payment:' mod='pays_ps'}</td><td><code>{$paysPsSuccessOnlineURL|escape:'html':'UTF-8'}</code></td></tr>
            <tr><td>{l s='Successful offline payment:' mod='pays_ps'}</td><td><code>{$paysPsSuccessOfflineURL|escape:'html':'UTF-8'}</code></td></tr>
            <tr><td>{l s='Error payment:' mod='pays_ps'}</td><td><code>{$paysPsErrorURL|escape:'html':'UTF-8'}</code></td></tr>
            <tr><td>{l s='Background confirmation:' mod='pays_ps'}</td><td><code>{$paysPsConfirmationURL|escape:'html':'UTF-8'}</code></td></tr>
        </table>	
    </div>
    <div style="float:left">{l s='Payment gateway operator and support:' mod='pays_ps'} <a href="https://www.pays.cz" target="_blank">pays.cz s.r.o.</a></div>
    <div class="text-right">{l s='Module development:' mod='pays_ps'}  <a href="https://www.brainweb.cz/" target="_blank">Pavel Strejček @ BrainWeb.cz</a></div>
</div>