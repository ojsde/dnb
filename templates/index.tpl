{**
 * plugins/importexport/native/templates/index.tpl
 *
 * Copyright (c) 2021 Center for Digital Systems (CeDiS), Universitätsbibliothek Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan, Ronald Steffen
 *
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}

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

            {help file="settings" class="pkp_help_tab" topic="settings"}
        
            <div class="pkp_notification" id="dnbConfigurationErrors">
                {if DEBUG}
                    {include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=dnbConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.dnb.settings.debugModeActive.title"|translate notificationContents=$debugModeWarning}
                {/if}
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
            <tab id="exportSubmissions-tab" label="{translate key="plugins.importexport.dnb.exportArticle"}" :badge={$nNotRegistered}>

                {help file="export" class="pkp_help_tab"}
                <div class="pkp_help_tab dnb_info_center">
                    <badge
                        v-if="{$remoteEnabled|count_characters} > 0"
                        class="dnb_info_tab">{$remoteEnabled}
                    </badge>
                    <badge v-if="{$suppDisabled|count_characters} > 0" class="dnb_info_tab">{$suppDisabled}</badge>
                </div>

                <form id="exportXmlForm" class="pkp_form dnb_form" action="" method="post">
                    <submissions-list-panel
                        v-bind="components.submissions"
                        @set="set"
                    >
                        <template v-slot:item="{ldelim}item{rdelim}">
                            <div class="listPanel__itemSummary">
                                <input id="checkbox"
                                    type="checkbox"
                                    name="selectedSubmissions[]"
                                    :value="item.id"
                                    v-model="selectedSubmissions"
                                />
                                <label for="checkbox" title="">
                                </label>
                                <div class="listPanel__item">
                                    <badge>{{ item.id }}</badge>
                                </div>
                                <div class="listPanel__item">
                                    <span class="dnb_authors">
                                        {{ (item.publications.find(p => p.id == item.currentPublicationId).authorsString) }}
                                    </span>
                                    <br>
                                    <a :href="item.urlWorkflow">
                                        <span>
                                            {{ localize(item.publications.find(p => p.id == item.currentPublicationId).fullTitle) }}
                                        </span>
                                    </a>
                                    <br>
                                    <a :href="components.submissions.dnbStatus[item.id]['publishedUrl']">
                                        <span>
                                            {{ components.submissions.dnbStatus[item.id]['issueTitle'] }}
                                        </span>
                                    </a>
                                </div>
                                <div v-if="components.submissions.dnbStatus[item.id]['supplementariesNotAssignable'] !== ''" class="listPanel__item">
                                    <icon
                                        icon="exclamation-triangle"
                                        class="has_tooltip"
                                        :title="components.submissions.dnbStatus[item.id]['supplementariesNotAssignable']">
                                    </icon>
                                </div>
                                <div class="listPanel__item dnb_align_right">
                                    <button v-if="components.submissions.dnbStatus[item.id]['statusConst'] !== '{$smarty.const.EXPORT_STATUS_NOT_DEPOSITED}'" 
                                        class="listPanel__item pkpBadge dnb_align_right dnb_deposited">
                                        <template>
                                            {{ components.submissions.dnbStatus[item.id]['status'] }}
                                        </template>
                                    </button>
                                    <button v-else 
                                        class="listPanel__item pkpBadge dnb_align_right dnb_not_deposited">
                                        <template>
                                            {{ components.submissions.dnbStatus[item.id]['status'] }}
                                        </template>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </submissions-list-panel>
                    {fbvFormSection}
                        <pkp-button id="dnb_deposit" onclick="$('#dnb_deposit').closest('form').attr('action', '{plugin_url path=$smarty.const.EXPORT_ACTION_DEPOSIT}');submit('#exportXmlForm')">
                            {translate key="plugins.importexport.dnb.deposit"}
                        </pkp-button>
                        <pkp-button id="dnb_export" onclick="$('#dnb_export').closest('form').attr('action', '{plugin_url path=$smarty.const.EXPORT_ACTION_EXPORT}');submit('#exportXmlForm')">
                            {translate key="plugins.importexport.dnb.export"}
                        </pkp-button>
                        <pkp-button id="dnb_mark" onclick="$('#dnb_mark').closest('form').attr('action', '{plugin_url path=$smarty.const.EXPORT_ACTION_MARKREGISTERED}');submit('#exportXmlForm')">
                            {translate key="plugins.importexport.common.status.markedRegistered"}
                        </pkp-button>
                        <pkp-button :disabled="!components.submissions.itemsMax" @click="toggleSelectAll">
                            <template v-if="components.submissions.itemsMax && selectedSubmissions.length >= components.submissions.itemsMax">
                                {translate key="common.selectNone"}
                            </template>
                            <template v-else>
                                {translate key="common.selectAll"}
                            </template>
                        </pkp-button>
                    {/fbvFormSection}
                </form>
                <badge>{translate key="plugins.importexport.dnb.deposit.notice"}</badge>
            </tab>
        {/if}
        {if $latestLogFile}
            <tab id="logfile-tab" label="{translate key="plugins.importexport.dnb.logFileTabTitle"}">
                {foreach from=$latestLogFile item=line}
                    <p class="dnb_log_entry">{$line}</p>
                {/foreach}
            </tab>
        {/if}
    </tabs>
{/block}
