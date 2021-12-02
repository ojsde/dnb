/**
 * @defgroup plugins_importexport_dnb_js
 */
/**
 * @file plugins/importexport/dnb/js/DNBSettingsFormHandler.js
 *
 * Copyright (c) 2017 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v2. For full terms see the plugin file LICENSE.
 * Author: Ronald Steffen
 * Last update: Dec 1, 2021
 *
 * @class DNBSettingsFormHandler.js
 * @ingroup plugins_importexport_dnb_js
 *
 * @brief Handle the DNB Settings form.
 */

jQuery(function() {
		// rewrite the OJS default help handler url
		// FIXME @RS $('#pkpHelpPanel').pkpHandler('$.pkp.controllers.HelpPanelHandler').remove(); // this seems to remove all handlers, help is not opening anymore
		$('#pkpHelpPanel').pkpHandler(
		'$.pkp.controllers.HelpPanelHandler',
		{
			helpUrl: $.pkp.plugins.importexport.dnbexportplugin.helpUrl,
			helpLocale: $.pkp.app.currentLocale,
		}
	);
})