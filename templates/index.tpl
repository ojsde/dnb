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

    <div style="position: relative;">
        <!-- Help Button (visible across all tabs) -->
        <div class="dnb_help_button_container" style="position: absolute; top: 1.5rem; right: 0; display: flex; gap: 0.5rem; align-items: flex-end; z-index: 100;">
            <badge
                v-if="{$remoteEnabled|count_characters} > 0"
                class="dnb_info_tab">{$remoteEnabled}
            </badge>
            <badge v-if="{$suppDisabled|count_characters} > 0" class="dnb_info_tab">{$suppDisabled}</badge>
            <pkp-button
                @click="$refs.dnbHelpPanel.openHelp()"
                class="dnb_help_button">
                <icon icon="question-circle" :inline="true"></icon>
                {translate key="help.help"}
            </pkp-button>
        </div>

        <tabs :track-history="true">
        <tab id="settings" label="{translate key="plugins.importexport.common.settings"}">

            {* {help file="settings" class="pkp_help_tab" topic="settings"} *}
        
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

                {* {help file="export" class="pkp_help_tab"} *}

                <dnb-submissions-table
                    :data="components.{$smarty.const.DNB_SUBMISSIONS_LIST}"
                    :action-urls="{ldelim}
                        deposit: '{plugin_url path=$smarty.const.EXPORT_ACTION_DEPOSIT}',
                        export: '{plugin_url path=$smarty.const.EXPORT_ACTION_EXPORT}',
                        mark: '{plugin_url path=$smarty.const.EXPORT_ACTION_MARKREGISTERED}',
                        exclude: '{plugin_url path=$smarty.const.DNB_EXPORT_ACTION_MARKEXCLUDED}'
                    {rdelim}"
                    @set="set"
                />
                <badge>{translate key="plugins.importexport.dnb.deposit.notice"}</badge>
            </tab>
        {/if}

        {if $dnbCatalogInfo}
            <tab id="dnb-catalog-tab" label="{translate key="plugins.importexport.dnb.dnbCataloTabTitle"}">
                <badge>{translate key="plugins.importexport.dnb.dnbCatalogTabInfo"}</badge>
                {include file="../plugins/generic/dnb/templates/dnbCatalogTab.tpl"}
            </tab>
        {/if}

        {if $latestLogFile}
            <tab id="logfile-tab" label="{translate key="plugins.importexport.dnb.logFileTabTitle"}">
                <h4>{translate key="plugins.importexport.dnb.logFileTabInfo"}</h4>
                {foreach from=$latestLogFile item=line}
                    <p class="dnb_log_entry">{$line}</p>
                {/foreach}
            </tab>
        {/if}
        </tabs>
    </div>
    
    <dnb-help-panel
        ref="dnbHelpPanel"
        :help-api-url="'{$helpApiUrl}'"
        :locale="'{$currentLocale}'"
    />
{/block}
