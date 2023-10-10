<?php
/**
 * @file plugins/importexport/dnb/classes/form/DNBSettingsForm.inc.php
 *
 * Copyright (c) 2021 Universitätsbibliothek/Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v2. For full terms see the plugin file LICENSE.
 * Author: Ronald Steffen
 *
 * @class DNBSettingsForm
 * @ingroup plugins_importexport_dnb
 *
 * @brief Form for journal managers to setup DNB plugin
 */

namespace APP\plugins\importexport\dnb\classes\form;

use APP\core\Application;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldText;
use \PKP\components\forms\FieldOptions;

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
				'isRequired' => false // this should actually be set to true but will also be evaluated if parent field is disabled => prevents saving the form, we have to handle this via form execute
			]));

		// group archive access
		if ($plugin->isOAJournal()) {
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
				'value' => $plugin->isOAJournal() ? "b" : $plugin->getSetting($contextId, 'archiveAccess'),
				'tooltip' => __('plugins.importexport.dnb.settings.form.archiveAccess.description'),
			]));
		}
	}

	// not used anymore, only for backwards compatability (called by PubObjectsExportPlugin)
	function initData() {}
	function fetch() {}
	function readInputData() {}

	function validate() {
		$request = Application::get()->getRequest();

		import('lib.pkp.classes.validation.ValidatorFactory');

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
					if (!empty($errors)) {
						import('lib.pkp.classes.core.APIResponse');
						$response = new APIResponse();

						$app = new \Slim\App();
						$app->respond($response->withStatus(400)->withJson($errors));
						exit();
					}
				}
			}
		}

		$this->execute();
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$functionArgs) {
		$request = Application::get()->getRequest();
		foreach($this->getFormFields() as $settingName => $settingType) {
			// do not save the access option for OA journals -- it is always 'b'
			// but also to be able to check the missing option for closed journals
			if ($this->_plugin->isOAJournal() && $settingName == 'archiveAccess') continue;
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
			'allowedRemoteIPs' => 'string'
		);
	}

	/**
	 * Is the form field optional
	 * @param $settingName string
	 * @return boolean
	 */
	function isOptional($settingName) {
		return in_array($settingName, array('archiveAccess', 'username', 'password', 'folderId', 'automaticDeposit','submitSupplementaryMode','exportRemoteGalleys','allowedRemoteIPs'));
	}

	/**
	 * Get a plugin setting.
	 * @param $settingName
	 * @return mixed The setting value.
	 */
	function getSetting($settingName) {
		return $this->_plugin->getSetting($this->contextId, $settingName);
	}
}
