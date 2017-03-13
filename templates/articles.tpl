{**
 * @file plugins/importexport/dnb/templates/articles.tpl
 *
 * Copyright (c) 2017 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v2. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan
 * Last update: March 15, 2017
 *
 * Select articles for export.
 *}
{strip}
{assign var="pageTitle" value="plugins.importexport.common.export.selectArticle"}
{assign var="pageCrumbTitle" value="plugins.importexport.common.export.selectArticle"}
{include file="common/header.tpl"} 
{/strip}

<script type="text/javascript">{literal}
	function toggleChecked() {
		var elements = document.getElementById('articlesForm').elements;
		for (var i=0; i < elements.length; i++) {
			if (elements[i].name == 'articleId[]') {
				elements[i].checked = !elements[i].checked;
			}
		}
	}
	
	// If an already deposited article is selected for deposit,
	// ask user to confirm.
	function checkDeposited(confirmMsg) {
		var inputElements = document.getElementsByName('articleId[]');
		for(var i = 0; i < inputElements.length; i++){
			if(inputElements[i].checked){
				// get the status from the status table cell id
				var statusCellIdPattern = 'articleStatus-' + inputElements[i].value + '-';
				var statusCell = document.querySelectorAll('[id^=' + statusCellIdPattern + ']');
				var statusCellId = statusCell[0].id;
				var statusCellIdParts = statusCellId.split("-");
				if (statusCellIdParts[2] == '{/literal}{$smarty.const.DNB_STATUS_DEPOSITED}{literal}' || statusCellIdParts[2] == '{/literal}{$smarty.const.DNB_STATUS_MARKEDREGISTERED}{literal}' ) {
					return confirm(confirmMsg);
				}
			}
		}
		return true;
	}
{/literal}</script>

<br />

<div id="articles">
	<br />
	<ul class="menu">
		<li><a href="{plugin_url path="articles"}"{if !$filter} class="current"{/if}>{translate key="plugins.importexport.dnb.status.all"}</a></li>
		<li><a href="{plugin_url path="articles" filter=$smarty.const.DNB_STATUS_NOT_DEPOSITED}"{if $filter == $smarty.const.DNB_STATUS_NOT_DEPOSITED} class="current"{/if}>{translate key="plugins.importexport.dnb.status.non"}</a></li>
		<li><a href="{plugin_url path="articles" filter=$smarty.const.DNB_STATUS_DEPOSITED}"{if $filter == $smarty.const.DNB_STATUS_DEPOSITED} class="current"{/if}>{translate key="plugins.importexport.dnb.status.deposited"}</a></li>
		<li><a href="{plugin_url path="articles" filter=$smarty.const.DNB_STATUS_MARKEDREGISTERED}"{if $filter == $smarty.const.DNB_STATUS_MARKEDREGISTERED} class="current"{/if}>{translate key="plugins.importexport.dnb.status.markedRegistered"}</a></li>
	</ul>
	<br />
	<form action="{plugin_url path="process"}" method="post" id="articlesForm">
		<table width="100%" class="listing">
			<tr>
				<td colspan="6" class="headseparator">&nbsp;</td>
			</tr>
			<tr class="heading" valign="bottom">
				<td width="5%">&nbsp;</td>
				<td width="5%">{translate key="common.id"}</td>
				<td width="25%">{translate key="issue.issue"}</td>
				<td width="30%">{translate key="article.title"}</td>
				<td width="25%">{translate key="article.authors"}</td>
				<td width="15%">{translate key="common.status"}</td>
			</tr>
			<tr>
				<td colspan="6" class="headseparator">&nbsp;</td>
			</tr>

			{iterate from=articles item=articleData}
				{assign var=article value=$articleData.article}
				{assign var=status value=$article->getData($statusSettingName)|default:$smarty.const.DNB_STATUS_NOT_DEPOSITED}
				{if ($filter && $filter == $status) || !$filter}
					{assign var=issue value=$articleData.issue}
					<tr valign="top">
						<td><input type="checkbox" name="articleId[]" value="{$article->getId()}"{if $status == $smarty.const.DNB_STATUS_NOT_DEPOSITED} checked="checked"{/if} /></td>
						<td>{$article->getId()|escape}</td>
						<td><a href="{url page="issue" op="view" path=$issue->getId()}" class="action">{$issue->getIssueIdentification()|strip_tags}</a></td>
						<td><a href="{url page="article" op="view" path=$article->getId()}" class="action">{$article->getLocalizedTitle()|strip_unsafe_html|truncate:60:"..."}</a></td>
						<td>{$article->getAuthorString()|truncate:40:"..."|escape}</td>
						{* the id of the status table cell is used in the javascript function checkDeposit above *}
						<td id="articleStatus-{$article->getId()}-{$status}">
							{if $status == $smarty.const.DNB_STATUS_NOT_DEPOSITED}
								{translate key="plugins.importexport.dnb.status.non"}
							{else}
								<input type="hidden" name="filter" value="{$filter|escape}" />
								{$statusMapping[$status]|escape}
							{/if}
						</td>
					</tr>
				{/if}
				
				<tr>
					<td colspan="6" class="{if $articles->eof()}end{/if}separator">&nbsp;</td>
				</tr>
			{/iterate}
			
			{if $articles->wasEmpty()}
				<tr>
					<td colspan="6" class="nodata">
						{if !$filter}
							{translate key="plugins.importexport.common.export.noArticles"}
						{else}
							{translate key="plugins.importexport.dnb.articles.$filter"}
						{/if}
					</td>
				</tr>
				<tr>
					<td colspan="6" class="endseparator">&nbsp;</td>
				</tr>
			{else}
				<tr>
					<td colspan="3" align="left">{page_info iterator=$articles}</td>
					<td colspan="3" align="right">{page_links anchor="articles" name="articles" iterator=$articles filter=$filter}</td>
				</tr>
			{/if}
		</table>
		<p>
			{if $hasCredentials}
				{* If an already deposited article is selected for deposit, ask user to confirm before submitting. *}			
				<input type="submit" name="deposit" value="{translate key="plugins.importexport.dnb.deposit"}" title="{translate key="plugins.importexport.dnb.depositDescription.multi"}" class="button defaultButton" onclick="return checkDeposited('{translate|escape:"jsparam" key="plugins.importexport.dnb.deposit.confirm"}')"/>
				&nbsp;
			{/if}
			<input type="submit" name="export" value="{translate key="plugins.importexport.dnb.export"}" title="{translate key="plugins.importexport.dnb.exportDescription"}" class="button{if !$hasCredentials}  defaultButton{/if}"/>
			&nbsp;
			<input type="submit" name="markRegistered" value="{translate key="plugins.importexport.dnb.markRegistered"}" title="{translate key="plugins.importexport.dnb.markRegisteredDescription"}" class="button"/>
			&nbsp;
			<input type="button" value="{translate key="common.selectAll"}" class="button" onclick="toggleChecked()" />
		</p>
	</form>
	{if $hasCredentials}
		<p>{translate key="plugins.importexport.dnb.deposit.notice"}</p>
		<p>{translate key="plugins.importexport.dnb.status.legend"}</p>
	{/if}
</div>

{include file="common/footer.tpl"}
