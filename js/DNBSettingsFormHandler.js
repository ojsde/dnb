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

	// When the catalog fetch button is clicked we need to POST to the
	// backend endpoint.  The handler collects a CSRF token either from the
	// global pkp object or from the meta tag (for pages rendered before the
	// pkp JS is initialised), disables the button and shows a spinner
	// while the request is in progress.
	$(document).on('click', '[data-dnb-catalog-fetch]', function(event) {
		event.preventDefault();
		const $button = $(this);
		const $status = $button.closest('p').find('.dnbCatalogFetch__status');
		const $spinner = $button.closest('p').find('.dnbCatalogFetch__spinner');
		const fetchUrl = $.pkp.plugins.importexport.dnbexportplugin.catalogFetchUrl;
		// fallback chain for CSRF token retrieval
		const metaCsrf = document.querySelector('meta[name="csrf-token"]');
		const csrfToken = pkp?.currentUser?.csrfToken || metaCsrf?.getAttribute('content') || '';
		if (!fetchUrl) {
			return;
		}
		if (!csrfToken) {
			$status.text('Request failed. Missing CSRF token.');
			return;
		}

		$button.prop('disabled', true);
		$spinner.show();
		$status.text('');
		$.ajax({
			url: fetchUrl,
			type: 'POST',
			data: {csrfToken: csrfToken},
			headers: {
				'X-Csrf-Token': csrfToken
			},
		})
			.done(function() {
				// On success the page is reloaded to show updated catalog info
				window.location.reload();
			})
			.fail(function(xhr) {
				const message = xhr && xhr.responseJSON && xhr.responseJSON.content
					? xhr.responseJSON.content
					: 'Request failed.';
				$status.text(message);
			})
			.always(function() {
				$spinner.hide();
				$button.prop('disabled', false);
			});
	});

	// Similar to the catalog fetch handler, this button triggers a server-side
	// process to recalculate which submissions are exportable.  We show a
	// spinner, disable the button, and display the returned status message.
	$(document).on('click', '[data-dnb-refresh-validation]', function(event) {
		event.preventDefault();
		const $button = $(this);
		const $status = $button.closest('p').find('.dnbRefreshValidation__status');
		const $spinner = $button.closest('p').find('.dnbRefreshValidation__spinner');
		const refreshUrl = $.pkp.plugins.importexport.dnbexportplugin.refreshValidationUrl;
		const metaCsrf = document.querySelector('meta[name="csrf-token"]');
		const csrfToken = pkp?.currentUser?.csrfToken || metaCsrf?.getAttribute('content') || '';
		if (!refreshUrl) {
			return;
		}
		if (!csrfToken) {
			$status.text('Request failed. Missing CSRF token.');
			return;
		}

		$button.prop('disabled', true);
		$spinner.show();
		$status.text('');
		$.ajax({
			url: refreshUrl,
			type: 'POST',
			data: {csrfToken: csrfToken},
			headers: {
				'X-Csrf-Token': csrfToken
			},
		})
			.done(function(response) {
				const message = response && response.content ? response.content : 'Done.';
				$status.text(message);
			})
			.fail(function(xhr) {
				const message = xhr && xhr.responseJSON && xhr.responseJSON.content
					? xhr.responseJSON.content
					: 'Request failed.';
				$status.text(message);
			})
			.always(function() {
				$spinner.hide();
				$button.prop('disabled', false);
			});
	});
})