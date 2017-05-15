{**
 * @file plugins/importexport/dnb/index.tpl
 *
 * Copyright (c) 2017 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v2. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan
 * Last update: May 15, 2017
 *
 * List of operations this plugin can perform
 *}
{strip}
{include file="common/header.tpl" pageTitle="plugins.importexport.dnb.displayName"}
{/strip}

<script type="text/javascript">{literal}
	function toggleChecked() {
		var elements = document.getElementById('exportSubmissionXmlForm').elements;
		for (var i=0; i < elements.length; i++) {
			if (elements[i].name == 'selectedSubmissions[]') {
				elements[i].checked = !elements[i].checked;
			}
		}
	}
	
	// If an already deposited article is selected for deposit,
	// ask user to confirm.
	function checkDeposited(confirmMsg) {
		var inputElements = document.getElementsByName('selectedSubmissions[]');
		for(var i = 0; i < inputElements.length; i++){
			if(inputElements[i].checked){
				// get the status from the status table cell id
				var statusCellIdPattern = 'cell-' + inputElements[i].value + '-status';
				var statusCell = document.querySelectorAll('[id^=' + statusCellIdPattern + ']');
				var statusCellText = statusCell[0].firstElementChild.innerHTML.trim();
				if (statusCellText == '{/literal}{translate key="plugins.importexport.dnb.status.deposited"}{literal}' || statusCellText == '{/literal}{translate key="plugins.importexport.common.status.markedRegistered"}{literal}' ) {
					return confirm(confirmMsg);
				}
			}
		}
		return true;
	}
{/literal}</script>

{if !empty($configurationErrors) || 
	!$checkTar || 
	!$checkSettings || 
	(!$currentContext->getSetting('onlineIssn') && !$currentContext->getSetting('printIssn'))}
	{assign var="allowExport" value=false}
{else}
	{assign var="allowExport" value=true}
{/if}

<script type="text/javascript">
	// Attach the JS file tab handler.
	$(function() {ldelim}
		$('#importExportTabs').pkpHandler('$.pkp.controllers.TabHandler');
	{rdelim});
</script>
<div id="importExportTabs">
	<ul>
		<li><a href="#settings-tab">{translate key="plugins.importexport.common.settings"}</a></li>
		{if $allowExport}
			<li><a href="#exportSubmissions-tab">{translate key="plugins.importexport.common.export.articles"}</a></li>
		{/if}
	</ul>
	<div id="settings-tab">
		<div class="pkp_notification" id="dnbConfigurationErrors">
		{if !empty($configurationErrors)}
				{foreach from=$configurationErrors item=configurationError}
					{if $configurationError == $smarty.const.EXPORT_CONFIG_ERROR_SETTINGS}
						{include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=dnbConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents="plugins.importexport.common.error.pluginNotConfigured"|translate}
					{/if}
				{/foreach}
		{/if}
		{if !$currentContext->getSetting('onlineIssn') && !$currentContext->getSetting('printIssn')}
			{url|assign:journalSettingsUrl router=$smarty.const.ROUTE_PAGE page="management" op="settings" path="context" escape=false}
			{capture assign=missingIssnMessage}{translate key="plugins.importexport.dnb.noISSN" journalSettingsUrl=$journalSettingsUrl}{/capture}
			{include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=dnbConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents=$missingIssnMessage}
		{/if}
		{if !$checkTar}
			{include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=dnbConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents="plugins.importexport.dnb.noTAR"|translate}
		{/if}
		{if !$checkSettings}
			{include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=dnbConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents="plugins.importexport.dnb.archiveAccess.required"|translate}
		{/if}
		</div>

		{url|assign:dnbSettingsGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.settings.plugins.settingsPluginGridHandler" op="manage" plugin="DNBExportPlugin" category="importexport" verb="index" escape=false}
		{load_url_in_div id="dnbSettingsGridContainer" url=$dnbSettingsGridUrl}
	</div>
	{if $allowExport}
		<div id="exportSubmissions-tab">
			<script type="text/javascript">
				$(function() {ldelim}
					// Attach the form handler.
					$('#exportSubmissionXmlForm').pkpHandler('$.pkp.controllers.form.FormHandler');
				{rdelim});
			</script>
			<form id="exportSubmissionXmlForm" class="pkp_form" action="{plugin_url path="exportSubmissions"}" method="post">
				{csrf}
				<input type="hidden" name="tab" value="exportSubmissions-tab" />
				{fbvFormArea id="submissionsXmlForm"}
					{url|assign:submissionsListGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.submissions.ExportPublishedSubmissionsListGridHandler" op="fetchGrid" plugin="dnb" category="importexport" escape=false}
					{load_url_in_div id="submissionsListGridContainer" url=$submissionsListGridUrl}
					{fbvFormSection list="true"}
						{fbvElement type="checkbox" id="validation" label="plugins.importexport.common.validation" checked=$validation|default:true}
					{/fbvFormSection}
					{if !empty($actionNames)}
						{fbvFormSection}
						<ul class="export_actions">
							{foreach from=$actionNames key=action item=actionName}
								<li class="export_action">	
									{if $action == $smarty.const.EXPORT_ACTION_DEPOSIT}
										{capture assign=confirmationMessage}{translate|escape:"jsparam" key="plugins.importexport.dnb.deposit.confirm"}{/capture}
										{fbvElement type="submit" label="$actionName" id="$action" name="$action" value="1" class="$action" translate=false inline=true onclick="return checkDeposited('$confirmationMessage')"}
									{else}
										{fbvElement type="submit" label="$actionName" id="$action" name="$action" value="1" class="$action" translate=false inline=true}
									{/if}
								</li>
							{/foreach}
							<li class="export_action">
								<button id="selectAll" class="pkp_button selectAll" value="1" name="selectAll" type="button" onclick="toggleChecked()">{translate key="plugins.importexport.dnb.selectDeselectAll"}</button>
							</li>
						</ul>
						{/fbvFormSection}
					{/if}					
				{/fbvFormArea}
			</form>
			{if $confirmationMessage}<p>{translate key="plugins.importexport.dnb.deposit.notice"}</p>{/if}
			<p>{translate key="plugins.importexport.dnb.status.legend"}</p>
		</div>
	{/if}
</div>

{include file="common/footer.tpl"}
