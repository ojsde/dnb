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

namespace APP\plugins\generic\dnb;

use PKP\scheduledTask\ScheduledTask;
use APP\core\Application;
use PKP\db\DAORegistry;
use APP\core\Services;
use PKP\plugins\PluginRegistry;
use APP\plugins\generic\dnb\classes\export\DNBExportJob;
use APP\facades\Repo;
use APP\plugins\generic\dnb\DNBExportPlugin;

if (!defined('SCHEDULED_TASK_MESSAGE_TYPE_NOTICE')) define('SCHEDULED_TASK_MESSAGE_TYPE_NOTICE', 'notice');
if (!defined('SCHEDULED_TASK_MESSAGE_TYPE_WARNING')) define('SCHEDULED_TASK_MESSAGE_TYPE_WARNING', 'warning');

class DNBInfoSender extends ScheduledTask
{
	private DNBExportPlugin $_plugin;

	/**
	 * Constructor.
	 * @param $argv array task arguments
	 */
	function __construct($args)
	{
		PluginRegistry::loadCategory('generic');
		PluginRegistry::loadCategory('importexport');

		$this->_plugin = PluginRegistry::getPlugin('importexport', 'DNBExportPlugin');
		if (is_a($this->_plugin, 'APP\plugins\generic\dnb\DNBExportPlugin')) {
			$this->_plugin->addLocaleData();
		}

		parent::__construct($args);
	}

	/**
	 * @see ScheduledTask::getName()
	 */
	function getName(): string
	{
		return __('plugins.importexport.dnb.senderTask.name');
	}

	/**
	 * @see ScheduledTask::executeActions()
	 */
	function executeActions(): bool
	{
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

		// get all journals that meet the requirements
		$journals = $this->_getJournals();
		$errors = [];
		foreach ($journals as $journal) {
			// load pubIds for this journal (they are currently not loaded in the base class)
			PluginRegistry::loadCategory('pubIds', true, $journal->getId());
			// set the context for all further actions
			$plugin->setContextId($journal->getId());
			// Get not deposited articles
			$notDepositedArticles = $plugin->getUnregisteredArticles($journal);
			if (!empty($notDepositedArticles)) {

				$this->addExecutionLogEntry(
					"[" . $journal->getData('urlPath') . "] " .
						__('plugins.importexport.dnb.logFile.info.conutArticles', array('param' => count($notDepositedArticles))),
					SCHEDULED_TASK_MESSAGE_TYPE_NOTICE
				);


				foreach ($notDepositedArticles as $submission) {
					if (is_a($submission, 'Submission')) {
						$issue = null;
						$galleys = $supplementaryGalleys = [];

						try {
							// Get issue and galleys, and check if the article can be exported
							if (!$plugin->canBeExported($submission, $issue, $galleys, $supplementaryGalleys)) {
								$errors = array_merge($errors, [array('plugins.importexport.dnb.export.error.articleCannotBeExported', $submission->getId())]);
								// continue with other articles
								continue;
							}

							$submissionId = $submission->getId();
							// Collect supplementary galley IDs for this submission
							$suppIds = array_map(fn($g) => $g->getId(), $supplementaryGalleys);
							foreach ($galleys as $galley) {

								// check if it is a full text
								$galleyFile = $galley->getFile();

								// if $galleyFile is not set it might be a remote URL
								if (!isset($galleyFile)) {
									if ($galley->getData('urlRemote') == null) continue;
								}

								// Dispatch job (connection determined by BaseJob::defaultConnection)
								dispatch(new DNBExportJob(
									$galley->getId(),
									$journal->getId(),
									$submission->getId(),
									$suppIds,
									$filter,
									true // noValidation
								));

								// Update status to queued immediately
								Repo::submission()->edit($submission, [
									$plugin->getPluginSettingsPrefix() . '::status' => DNB_EXPORT_STATUS_QUEUED,
									$plugin->getPluginSettingsPrefix() . '::lastError' => null
								]);
							}
						} catch (DNBPluginException | \ErrorException $e) {
							// convert ErrorException to error messages that will be logged below
							$result = $plugin->handleExceptions($e, $submission->getId());
							$errors = array_merge($errors, [$result]);
						}
					}
				}
			} else {
				// there were no articles to deposit
				$errors = array_merge($errors, [array('plugins.importexport.dnb.export.error.noNoArticlesToDeposit')]);
			}

			if (empty($errors)) {
				$errors = array_merge($errors, [array('plugins.importexport.dnb.export.error.noError')]);
			}
			// log all error messages
			foreach ($errors as $error) {
				$this->addExecutionLogEntry(
					"[" . $journal->getData('urlPath') . "] " .
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
	function _getJournals()
	{
		$plugin = $this->_plugin;
		$contextDao = Application::getContextDAO(); /* @var $contextDao JournalDAO */
		$journalFactory = $contextDao->getAll(true);

		$journals = array();
		while ($journal = $journalFactory->next()) {
			$journalId = $journal->getId();
			// check required plugin settings
			if (
				!$plugin->getSetting($journalId, 'username') ||
				!$plugin->getSetting($journalId, 'password') ||
				!$plugin->getSetting($journalId, 'folderId') ||
				!$plugin->getSetting($journalId, 'automaticDeposit') ||
				!$plugin->checkPluginSettings($journal)
			) {
				$this->addExecutionLogEntry(
					"[" . $journal->getData('urlPath') . "] " .
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
