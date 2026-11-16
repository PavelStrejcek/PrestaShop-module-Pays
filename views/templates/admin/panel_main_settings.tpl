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
 * @copyright 2019 - 2026 Pavel Strejček
 * @license   Licensed under the Open Software License version 3.0  https://opensource.org/licenses/OSL-3.0
 *
 * Payment gateway operator and support: www.Pays.cz
 * Module development: www.BrainWeb.cz
 *}
<div class="panel">
    <div class="pays_ps-admin-header">
        <h1>
            <a href="https://www.pays.cz/" target="_blank" rel="noopener">
                <img src="{$paysPsLogoPath|escape:'html'}" alt="Pays" style="vertical-align:-45%"></a>
            &nbsp;{l s='Payment gateway module for' mod='pays_ps'} <a href="https://www.pays.cz/" target="_blank" rel="noopener">Pays.cz</a>
        </h1>
        {if !empty($paysPsManualOpenUrl)}
        <div class="pays_ps-manual-links">
            <a href="{$paysPsManualOpenUrl|escape:'html'}" target="_blank" rel="noopener" class="btn btn-default">
                <img alt="" src="{$paysPsModulePath|escape:'html'}/views/img/info-icon.svg" class="pays_ps-manual-icon" width="18" height="18" style="width:18px;height:18px;max-width:18px;max-height:18px;display:inline-block;flex-shrink:0;vertical-align:middle;"> {l s='Instructions for Use (PDF)' mod='pays_ps'}
            </a>
            <a href="{$paysPsManualDownloadUrl|escape:'html'}" class="btn btn-default">
                <i class="icon-download"></i> {l s='Download PDF' mod='pays_ps'}
            </a>
        </div>
        {/if}
    </div>
