{**
 * @file plugins/importexport/dnb/templates/settings.tpl
 *
 * Copyright (c) 2017 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v2. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan
 * Last update: March 15, 2017
 *
 * DNB plugin settings
 *}
{strip}
{assign var="pageTitle" value="plugins.importexport.common.settings"}
{include file="common/header.tpl"}
{/strip}
<div id="dnbSettings">
	{include file="common/formErrors.tpl"}
	<br />
	<br />

	<div id="description"><b>{translate key="plugins.importexport.dnb.settings.form.description"}</b></div>

	<br />

	<form method="post" action="{plugin_url path="settings"}">
		<table width="100%" class="data">
			<tr valign="top">
				<td width="20%" class="label">{fieldLabel name="archiveAccess" key="plugins.importexport.dnb.settings.form.archiveAccess"}</td>
				<td width="80%" class="value">
					<input type="radio" name="archiveAccess" id="archiveAccess-a" value="a"{if $archiveAccess == 'a'} checked="checked"{/if}{if $oa} disabled="disabled"{/if} />&nbsp;{translate key="plugins.importexport.dnb.settings.form.archiveAccess.a"}<br />
					<input type="radio" name="archiveAccess" id="archiveAccess-b" value="b"{if $archiveAccess == 'b'} checked="checked"{/if}{if $oa} disabled="disabled"{/if} />&nbsp;{translate key="plugins.importexport.dnb.settings.form.archiveAccess.b"}<br />
					<input type="radio" name="archiveAccess" id="archiveAccess-d" value="d"{if $archiveAccess == 'd'} checked="checked"{/if}{if $oa} disabled="disabled"{/if} />&nbsp;{translate key="plugins.importexport.dnb.settings.form.archiveAccess.d"}<br />
					<br />{translate key="plugins.importexport.dnb.settings.form.archiveAccess.description"}
				</td>
			</tr>
			<tr><td colspan="2">&nbsp;</td></tr>
			<tr valign="top">
				<td width="20%" class="label">{fieldLabel name="username" key="plugins.importexport.dnb.settings.form.username"}</td>
				<td width="80%" class="value">
					<input type="text" name="username" value="{$username|escape}" size="20" maxlength="50" id="username" class="textField" />
				</td>
			</tr>
			<tr><td colspan="2">&nbsp;</td></tr>
			<tr valign="top">
				<td width="20%" class="label">{fieldLabel name="password" key="plugins.importexport.common.settings.form.password"}</td>
				<td width="80%" class="value">
					<input type="password" name="password" value="{$password|escape}" size="20" maxlength="50" id="password" class="textField" />
					<br />{translate key="plugins.importexport.dnb.settings.form.password.description"}
				</td>
			</tr>
			<tr><td colspan="2">&nbsp;</td></tr>
			<tr valign="top">
				<td width="20%" class="label">{fieldLabel name="folderId" key="plugins.importexport.dnb.settings.form.folderId"}</td>
				<td width="80%" class="value">
					<input type="text" name="folderId" value="{$folderId|escape}" size="20" maxlength="50" id="folderId" class="textField" />
					<br />{translate key="plugins.importexport.dnb.settings.form.folderId.description"}
				</td>
			</tr>
			<tr><td colspan="2">&nbsp;</td></tr>
			<tr valign="top">
				<td width="20%" class="label">{fieldLabel name="automaticDeposit" key="plugins.importexport.dnb.settings.form.automaticDeposit"}</td>
				<td width="80%" class="value">
					<input type="checkbox" name="automaticDeposit" id="automaticDeposit" value="1" {if $automaticDeposit} checked="checked"{/if} />&nbsp;{translate key="plugins.importexport.dnb.settings.form.automaticDeposit.description" articleListingURL=$articleListingURL}
				</td>
			</tr>
			<tr><td colspan="2">&nbsp;</td></tr>
		</table>

		<p><span class="formRequired">{translate key="common.requiredField"}</span></p>

		<p>
			<input type="submit" name="save" class="button defaultButton" value="{translate key="common.save"}"/>
			&nbsp;
			<input type="button" class="button" value="{translate key="common.cancel"}" onclick="history.go(-1)"/>
		</p>
	</form>

</div>
{include file="common/footer.tpl"}
