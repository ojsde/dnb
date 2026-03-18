<?php
/**
 * @file plugins/importexport/dnb/classes/form/DNBSettingsForm.inc.php
 *
 * Copyright (c) 2021 Universitätsbibliothek/Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v2. For full terms see the plugin file LICENSE.
 * Author: Ronald Steffen
 *
 * @class DNBSettingsForm
 * @ingroup plugins_generic_dnb
 *
 * @brief Form for journal managers to setup DNB plugin
 */

namespace APP\plugins\generic\dnb\classes\form;

use App;
use APP\core\Application;
use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldHTML;
use PKP\validation\ValidatorFactory;

define('FORM_DNB_SETTINGS', 'dnbsettingsform');

class DNBSettingsForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_DNB_SETTINGS;

	/** @copydoc FormComponent::$method */
	public $method = 'PUT';

	private $_plugin;

	private $_contextId;

	/**
	 * Constructor
	 *
	 * @param $action string URL to submit the form to
	 * @param $publication Publication The publication to change settings for
	 */
	public function __construct($plugin, $contextId) {
		$this->action = $plugin->getSettingsFormActionUrl();
		$this->_plugin = $plugin;
		$this->_contextId = $contextId;
		$context = Application::get()->getRequest()->getContext();

		$plugin->setSettingsForm($this);

		// hotfolder credentials
		$this->addGroup([
			'id' => 'hotfolderAccess',
			'label' => __('plugins.importexport.dnb.settings.form.hotfolderAccess'),
		])->addField(new FieldText('username', [
			'label' => __('plugins.importexport.dnb.settings.form.username'),
			'tooltip' => __('plugins.importexport.dnb.registrationIntro'),
			'groupId' => 'hotfolderAccess',
			'value' => $plugin->getSetting($contextId, 'username'),
		]))->addField(new FieldText('password', [
			'label' => __('plugins.importexport.common.settings.form.password'),
			'tooltip' => __('plugins.importexport.common.settings.form.password.description'),
			'groupId' => 'hotfolderAccess',
			'value' => $plugin->getSetting($contextId, 'password'),
		]))->addField(new FieldText('folderId', [
			'label' => __('plugins.importexport.dnb.settings.form.folderId'),
			'tooltip' => __('plugins.importexport.dnb.settings.form.folderId.description'),
			'groupId' => 'hotfolderAccess',
			'value' => $plugin->getSetting($contextId, 'folderId'),
		]));

		//connection type
		$this->addGroup([
			'id' => 'connectionType',
			'label' => __('plugins.importexport.dnb.settings.form.connectionType.title'),
		])->addField(new FieldOptions('connectionType', [
			'label' => __('plugins.importexport.dnb.settings.form.connectionType'),
			'description' => __('plugins.importexport.dnb.settings.form.connectionType.description'),
			'groupId' => 'connectionType',
			'options' => [
				['value' => false, 'label' => __('plugins.importexport.dnb.settings.form.connectionType.checkBoxLabel')],
			],
			'default' => false,
			'value' => $plugin->getSetting($contextId, 'connectionType') ? $plugin->getSetting($contextId, 'connectionType') : false,
		]));

		//automatic deposit
		$this->addGroup([
			'id' => 'automaticDeposit',
			'label' => __('plugins.importexport.dnb.settings.form.automaticDeposit.title'),
		])->addField(new FieldOptions('automaticDeposit', [
			'label' => __('plugins.importexport.dnb.settings.form.automaticDeposit'),
			'tooltip' => __('plugins.importexport.dnb.settings.form.automaticDeposit.description'),
			'groupId' => 'automaticDeposit',
			'options' => [
				['value' => false, 'label' => __('plugins.importexport.dnb.settings.form.automaticDeposit.checkBoxLabel')],
			],
			'default' => false,
			'value' => $plugin->getSetting($contextId, 'automaticDeposit'),
		]));

		// deposit supplementary material
		$this->addGroup([
			'id' => 'submitSupplementary',
			'label' => __('plugins.importexport.dnb.settings.form.submitSupplementary.title'),
		])->addField(new FieldOptions('submitSupplementaryMode', [
			'label' => __('plugins.importexport.dnb.settings.form.submitSupplementary.title'),
			'description' => __('plugins.importexport.dnb.settings.form.submitSupplementary.warning'),
			'tooltip' => __('plugins.importexport.dnb.settings.form.submitSupplementary.Info'),
			'type' => 'radio',
			'groupId' => 'submitSupplementary',
			'options' => [
				['value' => "all", 'label' => __('plugins.importexport.dnb.settings.form.submitSupplementary.all')],
				['value' => "none", 'label' => __('plugins.importexport.dnb.settings.form.submitSupplementary.none')],
			],
			'value' => $plugin->getSetting($contextId, 'submitSupplementaryMode') ? $plugin->getSetting($contextId, 'submitSupplementaryMode') : 'all',
		]));

		// group remote galleys
        $allowedRemoteIPs = $plugin->getSetting($contextId, 'allowedRemoteIPs');

		$this->addGroup([
			'id' => 'remoteGalleys',
			'label' => __('plugins.importexport.dnb.settings.form.exportRemoteGalleys.title'),
		])->addField(new FieldOptions('exportRemoteGalleys', [
				'label' => __('plugins.importexport.dnb.settings.form.exportRemoteGalleys.label'),
				'description' => __('plugins.importexport.dnb.settings.form.exportRemoteGalleys.description'),
				'groupId' => 'remoteGalleys',
				'options' => [
					['value' => true, 'label' => __('plugins.importexport.dnb.settings.form.exportRemoteGalleys.checkboxLabel')],
				],
				'default' => false,
				'value' => (bool)$plugin->getSetting($contextId, 'exportRemoteGalleys'),
		]))->addField(new FieldText('allowedRemoteIPs', [
				'label' => __('plugins.importexport.dnb.settings.form.allowedRemoteIPs.label'),
				'description' => __('plugins.importexport.dnb.settings.form.allowedRemoteIPs.description'),
				'groupId' => 'remoteGalleys',
				'value' => $allowedRemoteIPs ? $allowedRemoteIPs : "",
				'showWhen' => 'exportRemoteGalleys',
				'isRequired' => true
			]));

		// group archive access
		if ($plugin->isOAJournal($context)) {
			$this->addGroup([
				'id' => 'archiveAccess',
				'label' => __('plugins.importexport.dnb.archiveAccess'),
			])->addField(new FieldText('archiveAccess', [
				'label' => __('plugins.importexport.dnb.settings.form.archiveAccess'),
				'groupId' => 'archiveAccess',
				'description' => __('plugins.importexport.dnb.settings.form.archiveAccess.descriptionOA'),
				'tooltip' => __('plugins.importexport.dnb.settings.form.archiveAccess.description'),
			]));
		} else {
			$this->addGroup([
				'id' => 'archiveAccess',
				'label' => __('plugins.importexport.dnb.archiveAccess'),
			])->addField(new FieldOptions('archiveAccess', [
				'label' => __('plugins.importexport.dnb.settings.form.archiveAccess'),
				'type' => 'radio',
				'groupId' => 'archiveAccess',
				'options' => [
					['value' => "a", 'label' => __('plugins.importexport.dnb.settings.form.archiveAccess.a')],
					['value' => "b", 'label' => __('plugins.importexport.dnb.settings.form.archiveAccess.b')],
					['value' => "d", 'label' => __('plugins.importexport.dnb.settings.form.archiveAccess.d')],
				],
				'value' => $plugin->isOAJournal($context) ? "b" : $plugin->getSetting($contextId, 'archiveAccess'),
				'tooltip' => __('plugins.importexport.dnb.settings.form.archiveAccess.description'),
			]));
		}

		// group experimental features
		$this->addGroup([
			'id' => 'maintenance',
			'label' => __('plugins.importexport.dnb.settings.form.maintenance.title'),
		]);

		$refreshDescription = __('plugins.importexport.dnb.settings.form.refreshValidation.description');
		$refreshButtonLabel = __('plugins.importexport.dnb.settings.form.refreshValidation.button');
		$refreshHtml = '<p>' . $refreshDescription . '</p>' .
			'<p><button type="button" class="pkp_button" data-dnb-refresh-validation="1">' .
			$refreshButtonLabel .
			'</button> <span class="dnbRefreshValidation__spinner dnb-spinner" style="display:none;border:2px solid rgba(0,0,0,0.2);border-top-color:#333;vertical-align:middle;" aria-hidden="true"></span> <span class="dnbRefreshValidation__status" aria-live="polite"></span></p>';

		$this->addField(new FieldHTML('refreshValidation', [
			'label' => __('plugins.importexport.dnb.settings.form.refreshValidation.label'),
			'description' => $refreshHtml,
			'groupId' => 'maintenance',
		]));

		// group experimental features
		$this->addGroup([
			'id' => 'experimentalFeatures',
			'label' => __('plugins.importexport.dnb.settings.form.experimentalFeatures.title'),
		]);

		$catalogDescription = __('plugins.importexport.dnb.settings.form.dnbCatalog.description');
		$catalogButtonLabel = __('plugins.importexport.dnb.settings.form.dnbCatalog.fetchButton');
		$catalogHtml = '<p>' . $catalogDescription . '</p>' .
			'<p><button type="button" class="pkp_button" data-dnb-catalog-fetch="1">' .
			$catalogButtonLabel .
			'</button> <span class="dnbCatalogFetch__spinner dnb-spinner" style="display:none;border:2px solid rgba(0,0,0,0.2);border-top-color:#333;vertical-align:middle;" aria-hidden="true"></span> <span class="dnbCatalogFetch__status" aria-live="polite"></span></p>';

		$this->addField(new FieldHTML('dnbCatalogFetch', [
			'label' => __('plugins.importexport.dnb.settings.form.dnbCatalog.label'),
			'description' => $catalogHtml,
			'groupId' => 'experimentalFeatures',
		]));
	}

	// not used anymore, only for backwards compatability (called by PubObjectsExportPlugin)
	function initData() {}
	function readInputData() {}

	function fetch($request) {
		// Return the form config as JSON for the frontend
		// Errors are already added to the form via addError() in validate()
		return json_encode($this->getConfig());
	}

	function validate(): bool {
		$request = Application::get()->getRequest();

		if ($request->getUserVar('exportRemoteGalleys') == "true") {
			$props = ['allowedRemoteIPs' => $request->getUserVar('allowedRemoteIPs')];
			$rules = ['allowedRemoteIPs' => ['regex:/^[0-9\.\|]+$/', 'required_unless:exportRemoteGalleys,false']];
			$messages = [
				'regex' => __('plugins.importexport.dnb.settings.form.allowedRemoteIPs.error'),
				'required_unless' => __('plugins.importexport.dnb.settings.form.allowedRemoteIPs.errorRequired')
			];
			$validator = ValidatorFactory::make($props, $rules, $messages);

			if ($validator->fails()) {
				$errors = $validator->errors()->getMessages();

				if (!empty($errors['allowedRemoteIPs'])) {
					// Set errors directly on the FormComponent's errors property
					// Errors are an array where keys are field names and values are arrays of error messages
					$this->errors = $errors;
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$functionArgs) {
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		foreach($this->getFormFields() as $settingName => $settingType) {
			// do not save the access option for OA journals -- it is always 'b'
			// but also to be able to check the missing option for closed journals
			if ($this->_plugin->isOAJournal($context) && $settingName == 'archiveAccess') continue;
			if ($settingName == 'allowedRemoteIPs' &&
				$request->getUserVar('exportRemoteGalleys') == "false") continue; // handle remote galleys disabled
			$this->_plugin->updateSetting($this->_contextId, $settingName, $request->getUserVar($settingName), $settingType);
		}
	}

	/**
	 * Get form fields
	 * @return array (field name => field type)
	 */
	function getFormFields() {
		return array(
			'archiveAccess' => 'string',
			'username' => 'string',
			'password' => 'string',
			'folderId' => 'string',
			'automaticDeposit' => 'bool',
			'submitSupplementaryMode' => 'string',
			'exportRemoteGalleys' => 'bool',
			'allowedRemoteIPs' => 'string',
			'connectionType' => 'bool',
		);
	}

	/**
	 * Is the form field optional
	 * @param $settingName string
	 * @return boolean
	 */
	function isOptional($settingName) {
		return in_array($settingName, array('archiveAccess', 'username', 'password', 'folderId', 'automaticDeposit','submitSupplementaryMode','exportRemoteGalleys','allowedRemoteIPs', 'connectionType'));
	}

	/**
	 * Get a plugin setting.
	 * @param $settingName
	 * @return mixed The setting value.
	 */
	function getSetting($settingName) {
		return $this->_plugin->getSetting($this->_contextId, $settingName);
	}
}
