{**
 * plugins/importexport/dnb/templates/index.tpl
 *
 * Copyright (c) 2017 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v2. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan
 * Last update: March 15, 2017
 *
 * Plugin home/start page
 *
 *}
{strip}
{assign var="pageTitle" value="plugins.importexport.dnb.displayName"}
{include file="common/header.tpl"}
{/strip}

<p>{translate key="plugins.importexport.dnb.intro"}</p>
<p>{translate key="plugins.importexport.dnb.archiveAccess.intro"}</p>
<p>{translate key="plugins.importexport.dnb.registrationIntro"}</p>
{capture assign="settingsUrl"}{plugin_url path="settings"}{/capture}

<br />
<h3>{translate key="plugins.importexport.dnb.export"}</h3>
{if !$issn}
<p>{translate key="plugins.importexport.dnb.noISSN"}</p>
{elseif !$checkTar}
<p>{translate key="plugins.importexport.dnb.noTAR"}</p>
{elseif !$checkSettings}
<p>{translate key="plugins.importexport.dnb.archiveAccess.required"}</p>
{else}
<ul>
	<li><a href="{plugin_url path="articles"}">{translate key="plugins.importexport.dnb.export.article"}</a></li>
</ul>
{/if}
<h3>{translate key="plugins.importexport.common.settings"}</h3>
<p>{translate key="plugins.importexport.dnb.settings.description" settingsUrl=$settingsUrl}</p>

{include file="common/footer.tpl"}
