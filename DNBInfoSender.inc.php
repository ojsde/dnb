<?php

/**
 * @file plugins/importexport/dnb/DNBInfoSender.php
 *
 * Copyright (c) 2017 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v2. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan
 * Last update: May 15, 2017
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
		//$articleDao = DAORegistry::getDAO('ArticleDAO');//TODO @RS !!! hier weitermachen !!! und automatic deposit testen
		//$submissionDao = DAORegistry::getDAO('SubmissionDAO');
		$genreDao = DAORegistry::getDAO('GenreDAO');
		$fileManager = new FileManager();

		// get all journals that meet the requirements
		$journals = $this->_getJournals();
		$errors = array();
		foreach ($journals as $journal) {
			// Get not deposited articles
			$notDepositedArticles = $plugin->getUnregisteredArticles($journal);
			if (!empty($notDepositedArticles)) {
				// Get the journal target export directory.
				// The data will be exported in this structure:
				// dnb/<journalId>-<dateTime>/
				$result = $plugin->getExportPath($journal->getId());
				if (is_array($result)) {
					$this->addExecutionLogEntry(
						__($result[0], array('param' => (isset($result[1]) ? $result[1] : null))),
						SCHEDULED_TASK_MESSAGE_TYPE_WARNING
					);
					return false;
				}
				$journalExportPath = $result;

				foreach ($notDepositedArticles as $article) {
					if (is_a($article, 'PublishedArticle')) {
						$issue = null;
						$galleys = array();
						// Get issue and galleys, and check if the article can be exported
						if (!$plugin->canBeExported($article, $issue, $galleys)) {
							$errors[] = array('plugins.importexport.dnb.export.error.articleCannotBeExported', $article->getId());
							// continue with other articles
							continue;
						}

						$fullyDeposited = true;
						$articleId = $article->getId();
						foreach ($galleys as $galley) {
							// check if it is a full text
							$galleyFile = $galley->getFile();
							$genre = $genreDao->getById($galleyFile->getGenreId());
							// if it is not a full text, continue
							if ($genre->getCategory() != 1 || $genre->getSupplementary() || $genre->getDependent()) continue;

							$exportFile = '';
							// Get the TAR package for the galley
							$result = $plugin->getGalleyPackage($galley, $filter, null, $journal, $journalExportPath, $exportFile);
							// If errors occured, remove all created directories and log the errors
							if (is_array($result)) {
								$fileManager->rmtree($journalExportPath);
								$this->addExecutionLogEntry(
									__($result[0], array('param' => (isset($result[1]) ? $result[1] : null))),
									SCHEDULED_TASK_MESSAGE_TYPE_WARNING
								);
								return false;
							}
							// Depost the article
							$result = $plugin->depositXML($galley, $journal, $exportFile);
							if (is_array($result)) {
								// If error occured add it to the list of errors
								$errors[] = $result;
								$fullyDeposited = false;
							}
						}
						if ($fullyDeposited) {
							// Update article status
							//TODO @RS cleanup
							//$articleDao->updateSetting($articleId, $plugin->getDepositStatusSettingName(), DNB_STATUS_DEPOSITED, 'string');
							$object->setData($plugin->getDepositStatusSettingName(), DNB_STATUS_DEPOSITED);
+							$this->updateObject($object);
						}
					}
				}
				// Remove the generated directories
				$fileManager->rmtree($journalExportPath);
			}
		}
		if (!empty($errors)) {
			// If there were some deposit errors, log them
			foreach($errors as $error) {
				$this->addExecutionLogEntry(
					__($error[0], array('param' => (isset($error[1]) ? $error[1] : null))),
					SCHEDULED_TASK_MESSAGE_TYPE_WARNING
				);
			}
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
				!$plugin->checkPluginSettings($journal)) continue;

			$journals[] = $journal;
			unset($journal);
		}
		return $journals;
	}

}

?>
