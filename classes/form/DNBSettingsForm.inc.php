<?php

/**
 * @file plugins/importexport/dnb/classes/form/DNBSettingsForm.inc.php
 *
 * Copyright (c) 2017 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v2. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan
 * Last update: May 15, 2017
 *
 * @class DNBSettingsForm
 * @ingroup plugins_importexport_dnb
 *
 * @brief Form for journal managers to setup DNB plugin
 */


import('lib.pkp.classes.form.Form');

class DNBSettingsForm extends Form {

	//
	// Private properties
	//
	/** @var integer */
	var $_contextId;

	/**
	 * Get the context ID.
	 * @return integer
	 */
	function _getContextId() {
		return $this->_contextId;
	}

	/** @var DNBExportPlugin */
	var $_plugin;

	/**
	 * Get the plugin.
	 * @return DNBExportPlugin
	 */
	function _getPlugin() {
		return $this->_plugin;
	}


	//
	// Constructor
	//
	/**
	 * Constructor
	 * @param $plugin DNBExportPlugin
	 * @param $contextId integer
	 */
	function __construct($plugin, $contextId) {
		$this->_contextId = $contextId;
		$this->_plugin = $plugin;
		parent::__construct(method_exists($plugin, 'getTemplateResource') ? $plugin->getTemplateResource('settingsForm.tpl') : $plugin->getTemplatePath() . 'settingsForm.tpl');
		// Add form validation checks.
		$this->addCheck(new FormValidatorCustom($this, 'archiveAccess', 'required', 'plugins.importexport.dnb.settings.form.archiveAccessRequired', create_function('$archiveAccess,$oa', 'if (!$oa && empty($archiveAccess)) { return false; } return true;'), array($this->isOAJournal())));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}


	//
	// Implement template methods from Form
	//
	/**
	 * @copydoc Form::initData()
	 */
	function initData() {
		foreach ($this->getFormFields() as $settingName => $settingType) {
			$this->setData($settingName, $this->getSetting($settingName));
		}
		// the access option for OA journals is always 'b'
		if ($this->isOAJournal()) $this->setData('archiveAccess', 'b');
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array_keys($this->getFormFields()));
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute($object = null) {
		$plugin = $this->_getPlugin();
		foreach($this->getFormFields() as $settingName => $settingType) {
			// do not save the access option for OA journals -- it is always 'b'
			// but also to be able to check the missing option for closed journals
			if ($this->isOAJournal() && $settingName == 'archiveAccess') continue;
			error_log("RS_DEBUG: ".print_r($settingName, TRUE));
			$plugin->updateSetting($this->_getContextId(), $settingName, $this->getData($settingName), $settingType);
		}
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request = null, $template = null) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('oa', $this->isOAJournal());
		return parent::fetch($request, $template);
	}

	//
	// Public helper methods
	//
	/**
	 * Get a plugin setting.
	 * @param $settingName
	 * @return mixed The setting value.
	 */
	function getSetting($settingName) {
		$plugin = $this->_getPlugin();
		$settingValue = $plugin->getSetting($this->_getContextId(), $settingName);
		return $settingValue;
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
		);
	}

	/**
	 * Is the form field optional
	 * @param $settingName string
	 * @return boolean
	 */
	function isOptional($settingName) {
		return in_array($settingName, array('archiveAccess', 'username', 'password', 'folderId', 'automaticDeposit'));
	}

	/**
	 * Check whether this journal is OA.
	 * @return boolean
	 */
	function isOAJournal() {
		$journalDao = DAORegistry::getDAO('JournalDAO'); /* @var $journalDao JournalDAO */
		$journal = $journalDao->getById($this->_getContextId());
		return  $journal->getSetting('publishingMode') == PUBLISHING_MODE_OPEN &&
		$journal->getSetting('restrictSiteAccess') != 1 &&
		$journal->getSetting('restrictArticleAccess') != 1;
	}
}

?>
