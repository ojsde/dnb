/**
 * @defgroup plugins_importexport_dnb_js
 */
/**
 * @file plugins/importexport/dnb/js/DNBSettingsFormHandler.js
 *
 * Copyright (c) 2017 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v2. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan
 * Last update: May 15, 2017
 *
 * @class DNBSettingsFormHandler.js
 * @ingroup plugins_importexport_dnb_js
 *
 * @brief Handle the DNB Settings form.
 */
(function($) {

	/** @type {Object} */
	$.pkp.plugins.importexport.dnb =
			$.pkp.plugins.importexport.dnb ||
			{ js: { } };



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
	$.pkp.plugins.importexport.dnb.js.DNBSettingsFormHandler =
			function($form, options) {

		this.parent($form, options);

		$('[id^="automaticDeposit"]', $form).click(
				this.callbackWrapper(this.updateRequiredFormElementStatus_));
		//ping our handler to set the form's initial state.
		this.callbackWrapper(this.updateRequiredFormElementStatus_());
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.plugins.importexport.dnb.js.DNBSettingsFormHandler,
			$.pkp.controllers.form.AjaxFormHandler);


	/**
	 * Callback to replace the element's content.
	 *
	 * @private
	 */
	$.pkp.plugins.importexport.dnb.js.DNBSettingsFormHandler.prototype.
			updateRequiredFormElementStatus_ = 
			function() {
		//var $element = this.getHtmlElement();
		if (!$('[id^="automaticDeposit"]').is(':checked')) {
			$('[id^="username"]').removeAttr('required');
			$('[id^="username"]').removeClass('required');
			$('[id^="password"]').removeAttr('required');
			$('[id^="password"]').removeClass('required');
			$('[id^="folderId"]').removeAttr('required');
			$('[id^="folderId"]').removeClass('required');
		} else {
			$('[id^="username"]').attr('required', 'required');
			$('[id^="username"]').addClass('required');
			$('[id^="password"]').attr('required', 'required');
			$('[id^="password"]').addClass('required');
			$('[id^="folderId"]').attr('required', 'required');
			$('[id^="folderId"]').addClass('required');
		}
	};

/** @param {jQuery} $ jQuery closure. */
}(jQuery));
