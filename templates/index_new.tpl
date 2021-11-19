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
                
                <a id="dnb_legend">Help</a>
                <div class="dnb_legend_tooltip">
                    {translate key="plugins.importexport.dnb.status.legend"}
                </div>
                
                <form id="exportXmlForm" class="pkp_form" action="" method="post">
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
                                </div>
                                <div class="listPanel__item">
                                    <icon icon="exclamation-triangle" class="has_tooltip"></icon>
                                </div>
                                <div class="listPanel__item dnb_align_right">
                                    <button class="listPanel__item pkpBadge pkpBadge--button pkpBadge--dot pkpBadge--production dnb_align_right">
                                        <template  v-if="item.dnbStatus == 'markedRegistered'">ghjk</template>
                                    </button>
                                </div>
{*                                 <pkp-button element="a" :href="item.urlWorkflow" style="margin-left: auto;">
                                    {{ __('common.view') }}
                                </pkp-button> *}
                            </div>
                        </template>
                    </submissions-list-panel>
                    {fbvFormSection}
                        <pkp-button id="dnb_deposit" @click="$('#dnb_deposit').closest('form').attr('action', '{plugin_url path='deposit'}');submit('#exportXmlForm')">
                            {translate key="plugins.importexport.dnb.deposit"}
                        </pkp-button>
                        <pkp-button id="dnb_export" @click="$('#dnb_export').closest('form').attr('action', '{plugin_url path='export'}');submit('#exportXmlForm')">
                            {translate key="plugins.importexport.dnb.export"}
                        </pkp-button>
                        <pkp-button id="dnb_mark" @click="$('#dnb_mark').closest('form').attr('action', '{plugin_url path='markregistered'}');submit('#exportXmlForm')">
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
                <p>Tabelle: Anzahl Treffer; Link Ausgabe; Status</p>
                <badge>{translate key="plugins.importexport.dnb.deposit.notice"}</badge>
            </tab>
        {/if}
    </tabs>
{/block}
