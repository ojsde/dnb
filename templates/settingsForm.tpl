{**
 * plugins/importexport/dnb/templates/settingsForm.tpl
 *
 * Copyright (c) 2017 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v2. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan
 * Last update: May 15, 2017
 *
 * DNB plugin settings
 *
 *}
<script src="{$baseUrl}/plugins/importexport/dnb/js/DNBSettingsFormHandler.js"></script>
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#dnbSettingsForm').pkpHandler('$.pkp.plugins.importexport.dnb.js.DNBSettingsFormHandler');
	{rdelim});
</script>
<form class="pkp_form" id="dnbSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" plugin="DNBExportPlugin" category="importexport" verb="save"}">
	{csrf}
	{fbvFormArea id="dnbSettingsFormArea"}
		{fbvFormSection title="plugins.importexport.dnb.settings.form.archiveAccess" list="true"}
			<p class="pkp_help">{translate key="plugins.importexport.dnb.settings.form.archiveAccess.description"}</p>
			{fbvElement type="radio" id="archiveAccess-a" name="archiveAccess" value="a" label="plugins.importexport.dnb.settings.form.archiveAccess.a" checked=$archiveAccess|compare:'a' disabled=$oa|compare:true}
			{fbvElement type="radio" id="archiveAccess-b" name="archiveAccess" value="b" label="plugins.importexport.dnb.settings.form.archiveAccess.b" checked=$archiveAccess|compare:'b' disabled=$oa|compare:true}
			{fbvElement type="radio" id="archiveAccess-d" name="archiveAccess" value="d" label="plugins.importexport.dnb.settings.form.archiveAccess.d" checked=$archiveAccess|compare:'d' disabled=$oa|compare:true}
		{/fbvFormSection}
		{fbvFormSection title="plugins.importexport.dnb.settings.form.hotfolderAccess"}
			<p class="pkp_help">{translate key="plugins.importexport.dnb.registrationIntro"}</p>
			{fbvElement type="text" id="username" value=$username label="plugins.importexport.dnb.settings.form.username" maxlength="50" size=$fbvStyles.size.MEDIUM}
			{fbvElement type="text" password="true" id="password" value=$password label="plugins.importexport.common.settings.form.password" maxLength="50" size=$fbvStyles.size.MEDIUM}
			<span class="instruct">{translate key="plugins.importexport.common.settings.form.password.description"}</span><br/>
		{/fbvFormSection}
		{fbvFormSection}
			{fbvElement type="text" id="folderId" value=$folderId label="plugins.importexport.dnb.settings.form.folderId" maxlength="50" size=$fbvStyles.size.MEDIUM}
			<span class="instruct">{translate key="plugins.importexport.dnb.settings.form.folderId.description"}</span><br/>
		{/fbvFormSection}
		{fbvFormSection title="plugins.importexport.dnb.settings.form.automaticDeposit.title" list="true"}
			{fbvElement type="checkbox" id="automaticDeposit" label="plugins.importexport.dnb.settings.form.automaticDeposit.description" checked=$automaticDeposit|compare:true}
		{/fbvFormSection}
		{fbvFormSection title="plugins.importexport.dnb.settings.form.exportRemoteGalleys.title" list="true"}
			{fbvElement type="checkbox" id="exportRemoteGalleys" label="plugins.importexport.dnb.settings.form.exportRemoteGalleys.description" checked=$exportRemoteGalleys|compare:true}
			{fbvElement type="text" id="allowedReomoteIPs" value=$allowedReomoteIPs label="plugins.importexport.dnb.settings.form.allowedReomoteIPs" size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}
		{fbvFormSection title="plugins.importexport.dnb.settings.form.submitSupplementary.title" list="true"}
			<p class="pkp_help">{translate key="plugins.importexport.dnb.settings.form.submitSupplementary.Info"}</p>	
			{fbvElement type="radio" id="submitSupplementary-all" name="submitSupplementaryMode" value="all" label="plugins.importexport.dnb.settings.form.submitSupplementary.all" checked=$submitSupplementaryMode|compare:'all'}
			{fbvElement type="radio" id="submitSupplementary-none" name="submitSupplementaryMode" value="none" label="plugins.importexport.dnb.settings.form.submitSupplementary.none" checked=$submitSupplementaryMode|compare:'none'}
			<p class="pkp_help">{translate key="plugins.importexport.dnb.settings.form.submitSupplementary.warning"}</p>
		{/fbvFormSection}
	
	{/fbvFormArea}
	{fbvFormButtons submitText="common.save"}
</form>
