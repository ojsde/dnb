/**
 * @defgroup plugins_importexport_dnb_js
 */
/**
 * @file plugins/importexport/dnb/js/DNBSettingsFormHandler.js
 *
 * Copyright (c) 2021 Center for Digital Systems (CeDiS), Universitätsbibliothek Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan, Ronald Steffen
 *
 * @class DNBSettingsFormHandler.js
 * @ingroup plugins_importexport_dnb_js
 *
 * @brief Handle the DNB Settings form.
 */

jQuery(function() {
		// We register the HelpPanelHandler before it is called in backend.tpl
		// to rewrite the OJS default help handler url to fetch from our plugin directory
		// This causes a javascript error in the browser that we don't seem to be able to avoid.

		$('#pkpHelpPanel').pkpHandler(
		'$.pkp.controllers.HelpPanelHandler',
		{
			helpUrl: $.pkp.plugins.importexport.dnbexportplugin.helpUrl,
			helpLocale: $.pkp.app.currentLocale,
		}
	);
})