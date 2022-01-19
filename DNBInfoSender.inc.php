<?php

/**
 * @file plugins/importexport/dnb/DNBInfoSender.php
 *
 * Copyright (c) 2021 Center for Digital Systems (CeDiS), Universitätsbibliothek Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan, Ronald Steffen
 *
 * @class DNBInfoSender
 * @ingroup plugins_importexport_dnb
 *
 * @brief Scheduled task to send article information to the DNB server.
 */

import('lib.pkp.classes.scheduledTask.ScheduledTask');


class DNBInfoSender extends ScheduledTask {
	/** @var $_plugin DNBExportPlugin */
	var $_plugin;

	/**
	 * Constructor.
	 * @param $argv array task arguments
	 */
	function __construct($args) {
		PluginRegistry::loadCategory('importexport');
		$plugin = PluginRegistry::getPlugin('importexport', 'DNBExportPlugin'); /* @var $plugin DNBExportPlugin */
		
		$this->_plugin = $plugin;

		if (is_a($plugin, 'DNBExportPlugin')) {
			$plugin->addLocaleData();
		}
		
		parent::__construct($args);
	}

	/**
	 * @see ScheduledTask::getName()
	 */
	function getName() {
		return __('plugins.importexport.dnb.senderTask.name');
	}

	/**
	 * @see ScheduledTask::executeActions()
	 */
	function executeActions() {
		if (!$this->_plugin) return false;
		$plugin = $this->_plugin;
		
		// check if TAR command is configured
		$checkForTarResult = $plugin->checkForTar();
		if (is_array($checkForTarResult)) {
			$this->addExecutionLogEntry(
				__('plugins.importexport.dnb.noTAR'),
				SCHEDULED_TASK_MESSAGE_TYPE_WARNING
			);
			return false;
		}

		$filter = $plugin->getSubmissionFilter();
		$fileManager = new FileManager();

		// get all journals that meet the requirements
		$journals = $this->_getJournals();
		$errors = [];
		foreach ($journals as $journal) {
			// load pubIds for this journal (they are currently not loaded in the base class)
			PluginRegistry::loadCategory('pubIds', true, $journal->getId());
			// set the context for all further actions
			$this->_plugin->setContextId($journal->getId());
			// Get not deposited articles
			$notDepositedArticles = $plugin->getUnregisteredArticles($journal);
			if (!empty($notDepositedArticles)) {
				// Get the journal target export directory.
				// The data will be exported in this structure:
				// dnb/<journalId>-<dateTime>/
				$result = $plugin->getExportPath($journal->getId());
				if (is_array($result)) {
					$errors = array_merge($errors, [$result]);
					return false;
				}
				$journalExportPath = $result;

				$this->addExecutionLogEntry("[" . $journal->getData('urlPath') ."] " .
					__('plugins.importexport.dnb.logFile.info.conutArticles', array('param' => count($notDepositedArticles))),
					SCHEDULED_TASK_MESSAGE_TYPE_NOTICE
				);
				foreach ($notDepositedArticles as $submission) {
					if (is_a($submission, 'Submission')) {
						$issue = null;
						$galleys = array();

						try {
							// Get issue and galleys, and check if the article can be exported
							$galleys = $supplementaryGalleys = [];
							if (!$plugin->canBeExported($submission, $issue, $galleys, $supplementaryGalleys)) {
								$errors = array_merge($errors, [array('plugins.importexport.dnb.export.error.articleCannotBeExported', $submission->getId())]);
								// continue with other articles
								continue;
							}

							$fullyDeposited = true;
							$submissionId = $submission->getId();
							foreach ($galleys as $galley) {

								// check if it is a full text
								$galleyFile = $galley->getFile();

								// if $galleyFile is not set it might be a remote URL
								if (!isset($galleyFile)) {
									if ($galley->getRemoteURL() == null) continue;
								} else {
									$exportPath = $journalExportPath;
								}

								// store submission Id in galley object for internal use
								$galley->setData('submissionId', $submission->getId());
								
								$exportFile = '';
								// Get the TAR package for the galley
								$result = $plugin->getGalleyPackage($galley, $supplementaryGalleys, $filter, null, $journal, $journalExportPath, $exportFile, $submissionId);
								// If errors occured, remove all created directories and log the errors
								if (is_array($result)) {
									$fileManager->rmtree($journalExportPath);
									$errors = array_merge($errors, [$result]);
									$fullyDeposited = false;
									continue;
								}
								// Deposit the article
								$result = $plugin->depositXML($galley, $journal, $exportFile);
								if (is_array($result)) {
									// If error occured add it to the list of errors
									$errors = array_merge($errors, $result);
									$fullyDeposited = false;
								}
							}
						} catch (ErrorException $e) {
							// convert ErrorException to error messages that will be logged below
							$result = $plugin->hanndleExceptions($e);
							$errors = array_merge($errors, [$result]);
						}
						if ($fullyDeposited) {
							// Update article status
							$submissionDao = DAORegistry::getDAO('SubmissionDAO');
							$submission->setData($plugin->getDepositStatusSettingName(), DNB_STATUS_DEPOSITED);
							$submissionDao->updateObject($submission);
						}
					}
				}
				// Remove the generated directories
				$fileManager->rmtree($journalExportPath);
			}
			if (empty($errors)) {
				$errors = array_merge($errors, [array('plugins.importexport.dnb.export.error.noError')]);
			}
			// log all error messages
			foreach($errors as $error) {
				$this->addExecutionLogEntry("[" . $journal->getData('urlPath') ."] " .
					__($error[0], array('param' => (isset($error[1]) ? $error[1] : null))),
					SCHEDULED_TASK_MESSAGE_TYPE_WARNING
				);
			}
			$errors = [];
		}
		return true;
	}

	/**
	 * Get all journals that meet the requirements for
	 * automatic articles deposit to DNB.
	 * @return array
	 */
	function _getJournals() {
		$plugin = $this->_plugin;
		$contextDao = Application::getContextDAO(); /* @var $contextDao JournalDAO */
		$journalFactory = $contextDao->getAll(true);

		$journals = array();
		while($journal = $journalFactory->next()) {
			$journalId = $journal->getId();
			// check required plugin settings
			if (!$plugin->getSetting($journalId, 'username') ||
				!$plugin->getSetting($journalId, 'password') ||
				!$plugin->getSetting($journalId, 'folderId') ||
				!$plugin->getSetting($journalId, 'automaticDeposit') ||
				!$plugin->checkPluginSettings($journal)) {
					$this->addExecutionLogEntry("[" . $journal->getData('urlPath') ."] " .
						__('plugins.importexport.dnb.logFile.info.nocredentials'),
						SCHEDULED_TASK_MESSAGE_TYPE_NOTICE
					);
					continue;
				}

			$journals[] = $journal;
			unset($journal);
		}
		return $journals;
	}

}

?>
