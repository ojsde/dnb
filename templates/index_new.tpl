{**
 * plugins/importexport/native/templates/index.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * List of operations this plugin can perform
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}

    <script type="text/javascript">{literal}
        function articleSselectAll() {
            var elements = document.getElementById('exportSubmissionXmlForm').elements;
            for (var i=0; i < elements.length; i++) {
                if (elements[i].name == 'selectedSubmissions[]') {
                    elements[i].checked = true;
                }
            }
        }

        function articleDeselectAll() {
            var elements = document.getElementById('exportSubmissionXmlForm').elements;
            for (var i=0; i < elements.length; i++) {
                if (elements[i].name == 'selectedSubmissions[]') {
                    elements[i].checked = false;
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
        !$checkFilter ||
        !$checkSettings || 
        (!$currentContext->getSetting('onlineIssn') && !$currentContext->getSetting('printIssn'))}
        {assign var="allowExport" value=false}
    {else}
        {assign var="allowExport" value=true}
    {/if}

    <tabs :track-history="true">
        <tab id="settings" label="{translate key="plugins.importexport.common.settings"}">
            <div class="pkp_notification" id="dnbConfigurationErrors">
                {if !empty($configurationErrors)}
                        {foreach from=$configurationErrors item=configurationError}
                            {if $configurationError == $smarty.const.EXPORT_CONFIG_ERROR_SETTINGS}
                                {include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=dnbConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents="plugins.importexport.common.error.pluginNotConfigured"|translate}
                            {/if}
                        {/foreach}
                {/if}
                {if !$currentContext->getSetting('onlineIssn') && !$currentContext->getSetting('printIssn')}
                    {capture assign="journalSettingsUrl"}{url  router=$smarty.const.ROUTE_PAGE page="management" op="settings" path="context" escape=false}{/capture}
                    {capture assign=missingIssnMessage}{translate key="plugins.importexport.dnb.noISSN" journalSettingsUrl=$journalSettingsUrl}{/capture}
                    {include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=dnbConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents=$missingIssnMessage}
                {/if}
                {if !$checkTar}
                    {include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=dnbConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents="plugins.importexport.dnb.noTAR"|translate}
                {/if}
                {if !$checkFilter}
                    {include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=dnbConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents="plugins.importexport.dnb.noExportFilter"|translate}
                {/if}
                {if !$checkSettings}
                    {include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=dnbConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents="plugins.importexport.dnb.archiveAccess.required"|translate}
                {/if}
            </div>
            <pkp-form
                v-bind="components.{$smarty.const.FORM_DNB_SETTINGS}"
                @set="set" 
            />
        </tab>
        {if $allowExport}
            <tab id="exportSubmissions-tab" label="{translate key="plugins.importexport.dnb.exportArticle"}" :badge=4>
                <script type="text/javascript">
                    $(function() {ldelim}
                        // Attach the form handler.
                        $('#exportXmlForm').pkpHandler('$.pkp.controllers.form.FormHandler');
                    {rdelim});
                </script>
                <button class="tooltipButton has-tooltip" data-original-title="null" aria-hidden="true" title="dasdasd">
                    <span aria-hidden="true" class="fa fa-question-circle"></span>
                    <span class="-screenReader"></span>
                </button>
                <form id="exportXmlForm" class="pkp_form" action="{plugin_url path="exportSubmissions"}" method="post">
                    <submissions-list-panel
                        v-bind="components.submissions"
                        @set="set"
                    >
                        <template v-slot:item="{ldelim}item{rdelim}">
                            <div class="listPanel__itemSummary">
                                <label>
                                    <div class="listPanel__item--submission__id">
                                        {{ item.id }}
                                    </div>
                                    <input
                                        type="checkbox"
                                        name="selectedSubmissions[]"
                                        :value="item.id"
                                        v-model="selectedSubmissions"
                                    />
                                    <span class="listPanel__itemSubTitle">
                                        {{ localize(item.publications.find(p => p.id == item.currentPublicationId).fullTitle) }}
                                    </span>
                                </label>
                                <button class="pkpBadge pkpBadge--button listPanel__item--submission__stage pkpBadge--dot pkpBadge--production">
                                    {{if item.status  == 1 }} {$status[$smarty.const.EXPORT_STATUS_NOT_DEPOSITED]} {/if}
                                </button>
                                <pkp-button element="a" :href="item.urlWorkflow" style="margin-left: auto;">
                                    {{ __('common.view') }}
                                </pkp-button>
                            </div>
                        </template>
                    </submissions-list-panel>
                    {fbvFormSection}
                        <pkp-button @click="submit('#exportXmlForm')">
                            {translate key="plugins.importexport.native.exportSubmissions"}
                        </pkp-button>
                        <pkp-button :disabled="!components.submissions.itemsMax" @click="toggleSelectAll">
                            <template v-if="components.submissions.itemsMax && selectedSubmissions.length >= components.submissions.itemsMax">
                                {translate key="common.selectNone"}
                            </template>
                            <template v-else>
                                {translate key="common.selectAll"}
                            </template>
                        </pkp-button>
                        <pkp-button :enabled="!components.submissions.itemsMin" @click="toggleSelectAll">
                            <template v-if="components.submissions.itemsMin && selectedSubmissions.length <= components.submissions.itemsMax">
                                {translate key="common.selectAll"}
                            </template>
                            <template v-else>
                                {translate key="common.selectNone"}
                            </template>
                        </pkp-button>
                        <pkp-button @click="submit('#exportXmlForm')">
                            {translate key="plugins.importexport.native.exportSubmissions"}
                        </pkp-button>
                    {/fbvFormSection}
                </form>
            </tab>
        {/if}
    </tabs>

{* alte Seite *}
	<h1 class="app__pageHeading">
		{$pageTitle}
	</h1>

	<script type="text/javascript">
		// Attach the JS file tab handler.
		$(function() {ldelim}
			$('#exportTabs').pkpHandler('$.pkp.controllers.TabHandler');
			$('#exportTabs').tabs('option', 'cache', true);
		{rdelim});
	</script>
	<div id="exportTabs">
		<ul>
			<li><a href="#setup-tab">{translate key="plugins.importexport.native.setup"}</a></li>
            {if $allowExport}
                <li><a href="#exportSubmissions_old-tab">{translate key="plugins.importexport.native.exportSubmissions"}</a></li>
            {/if}
		</ul>
        <div id="setup-tab">
        	<div class="pkp_notification" id="dnbConfigurationErrors">
            {if !empty($configurationErrors)}
                    {foreach from=$configurationErrors item=configurationError}
                        {if $configurationError == $smarty.const.EXPORT_CONFIG_ERROR_SETTINGS}
                            {include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=dnbConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents="plugins.importexport.common.error.pluginNotConfigured"|translate}
                        {/if}
                    {/foreach}
            {/if}
            {if !$currentContext->getSetting('onlineIssn') && !$currentContext->getSetting('printIssn')}
                {capture assign="journalSettingsUrl"}{url  router=$smarty.const.ROUTE_PAGE page="management" op="settings" path="context" escape=false}{/capture}
                {capture assign=missingIssnMessage}{translate key="plugins.importexport.dnb.noISSN" journalSettingsUrl=$journalSettingsUrl}{/capture}
                {include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=dnbConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents=$missingIssnMessage}
            {/if}
            {if !$checkTar}
                {include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=dnbConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents="plugins.importexport.dnb.noTAR"|translate}
            {/if}
            {if !$checkFilter}
                {include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=dnbConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents="plugins.importexport.dnb.noExportFilter"|translate}
            {/if}
            {if !$checkSettings}
                {include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=dnbConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents="plugins.importexport.dnb.archiveAccess.required"|translate}
            {/if}
            </div>
            {capture assign="dnbSettingsGridUrl"}{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.plugins.settingsPluginGridHandler" op="manage" plugin="DNBExportPlugin" category="importexport" verb="index" escape=false}{/capture}
            
            {load_url_in_div id="dnbSettingsGridContainer" url=$dnbSettingsGridUrl}
        </div>
        {if $allowExport}
            
            {* old exportsubmissiontab *}
            <div id="exportSubmissions_old-tab">
                {translate key="plugins.importexport.dnb.status.legend"}
                <br/>
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
                        {capture assign="submissionsListGridUrl"}{url router=$smarty.const.ROUTE_COMPONENT component="grid.submissions.ExportPublishedSubmissionsListGridHandler" op="fetchGrid" plugin="dnb" category="importexport" escape=false}{/capture}
                        {load_url_in_div id="submissionsListGridContainer" url=$submissionsListGridUrl}
                        {**
                        * This checkbox set the PKP NativeExportFilter.inc.php $_noValidation variable which is currently not evaluated in the Plugin
                        {fbvFormSection list="true"}
                            {fbvElement type="checkbox" id="validation" label="plugins.importexport.common.validation" checked=$validation|default:true}
                        {/fbvFormSection}
                        *}
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
                                    <button id="selectAll" class="pkp_button selectAll" value="1" name="selectAll" type="button" onclick="articleSselectAll()">{translate key="plugins.importexport.dnb.selectAll"}</button>
                                </li>
                                <li class="export_action">
                                    <button id="deselectAll" class="pkp_button deselectAll" value="1" name="deselectAll" type="button" onclick="articleDeselectAll()">{translate key="plugins.importexport.dnb.deselectAll"}</button>
                                </li>
                            </ul>
                            {/fbvFormSection}
                        {/if}					
                    {/fbvFormArea}
                </form>
                {if $confirmationMessage}<p>{translate key="plugins.importexport.dnb.deposit.notice"}</p>{/if}
            </div>
        {/if}
	</div>
{/block}
