<?php

/**
 * @file plugins/importexport/dnb/classes/form/DNBSettingsForm.inc.php
 *
 * Copyright (c) 2017 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v2. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan
 * Last update: March 15, 2017
 *
 * @class DNBSettingsForm
 * @ingroup plugins_importexport_dnb_classes_form
 *
 * @brief Form for journal managers to setup the DNB plugin.
 */

import('lib.pkp.classes.form.Form');

class DNBSettingsForm extends Form {

	/** @var integer */
	var $_journalId;

	/**
	 * Get the journal ID.
	 * @return integer
	 */
	function getJournalId() {
		return $this->_journalId;
	}

	/** @var DNBExportPlugin */
	var $_plugin;

	/**
	 * Get the plugin.
	 * @return DNBExportPlugin
	 */
	function &getPlugIn() {
		return $this->_plugin;
	}

	//
	// Constructor
	//
	/**
	 * Constructor
	 * @param $plugin DNBExportPlugin
	 * @param $journalId integer
	 */
	function DNBSettingsForm(&$plugin, $journalId) {
		// Configure the object.
		parent::Form($plugin->getTemplatePath() . 'settings.tpl');
		$this->_journalId = $journalId;
		$this->_plugin =& $plugin;

		// Add form validation checks.
		$this->addCheck(new FormValidatorCustom($this, 'archiveAccess', 'required', 'plugins.importexport.dnb.settings.form.archiveAccessRequired', create_function('$archiveAccess,$oa', 'if (!$oa && empty($archiveAccess)) { return false; } return true;'), array($this->isOAJournal())));
		$this->addCheck(new FormValidatorCustom($this, 'folderId', 'required', 'plugins.importexport.dnb.settings.form.folderIDRequired', create_function('$folderId,$form', 'if ($form->getData(\'automaticDeposit\') && empty($folderId)) { return false; } return true;'), array(&$this)));
		$this->addCheck(new FormValidatorCustom($this, 'username', 'required', 'plugins.importexport.dnb.settings.form.usernameRequired', create_function('$username,$form', 'if ($form->getData(\'automaticDeposit\') && empty($username)) { return false; } return true;'), array(&$this)));
		$this->addCheck(new FormValidatorCustom($this, 'password', 'required', 'plugins.importexport.dnb.settings.form.passwordRequired', create_function('$password,$form', 'if ($form->getData(\'automaticDeposit\') && empty($password)) { return false; } return true;'), array(&$this)));
		$this->addCheck(new FormValidatorPost($this));
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
	function execute() {
		$plugin =& $this->getPlugIn();
		foreach($this->getFormFields() as $settingName => $settingType) {
			// do not save the access option for OA journals -- it is always 'b'
			// but also to be able to check the missing option for closed journals
			if ($this->isOAJournal() && $settingName == 'archiveAccess') continue;
			$plugin->updateSetting($this->getJournalId(), $settingName, $this->getData($settingName), $settingType);
		}
	}

	/**
	 * @copydoc Form::display()
	 */
	function display($request) {
		$templateMgr =& TemplateManager::getManager($request);
		$plugin = $this->_plugin;
		$templateMgr->assign('oa', $this->isOAJournal());
		$templateMgr->assign('articleListingURL', $request->url(null, null, 'importexport', array('plugin', $plugin->getName(), 'articles')));
		parent::display($request);
	}

	//
	// Public methods
	//
	/**
	 * Get a plugin setting.
	 * @param $settingName
	 * @return mixed The setting value.
	 */
	function getSetting($settingName) {
		$plugin =& $this->getPlugIn();
		$settingValue = $plugin->getSetting($this->getJournalId(), $settingName);
		return $settingValue;
	}

	/**
	 * Return a list of form fields.
	 * @return array
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
	 * Check whether a given setting is optional.
	 * @param $settingName string
	 * @return boolean
	 */
	function isOptional($settingName) {
		return in_array($settingName, array('username', 'password', 'folderId', 'automaticDeposit'));
	}

	/**
	 * Check whether this journal is OA.
	 * @return boolean
	 */
	function isOAJournal() {
		$journalDao = DAORegistry::getDAO('JournalDAO'); /* @var $journalDao JournalDAO */
		$journal = $journalDao->getById($this->getJournalId());
		return  $journal->getSetting('publishingMode') == PUBLISHING_MODE_OPEN &&
			$journal->getSetting('restrictSiteAccess') != 1 &&
			$journal->getSetting('restrictArticleAccess') != 1;
	}

}

?>
