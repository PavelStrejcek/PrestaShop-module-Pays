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
 * @copyright 2019 - 2023 Pavel Strejček
 * @license   Licensed under the Open Software License version 3.0  https://opensource.org/licenses/OSL-3.0
 *
 * Payment gateway operator and support: www.Pays.cz
 * Module development: www.BrainWeb.cz
 *}
<div class="panel">
    <div style="float:right;font-size: 120%;font-weight:bold;"><a href = "{$paysPsModulePath|escape:'html'}/doc/Navod_k_pouziti_modulu_Pays_v1.4-PS8.pdf" target="_blank"><img alt="" src="{$paysPsModulePath|escape:'html'}/views/img/info-icon.svg" height="20"> {l s='Instructions for Use (PDF)' mod='pays_ps'}</a></div>
    <h1>
        <a href = "https://www.pays.cz/" target="_blank">
        <img src="{$paysPsLogoPath|escape:'html'}" alt="Pays" style="vertical-align:-45%"></a>
        &nbsp;{l s='Payment gateway module for' mod='pays_ps'} <a href = "https://www.pays.cz/" target="_blank">Pays.cz</a>
    </h1>
