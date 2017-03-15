<?php

/**
 * @file plugins/importexport/dnb/DNBInfoSender.php
 *
 * Copyright (c) 2017 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v2. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan
 * Last update: March 15, 2017
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
	function DNBInfoSender($args) {
		PluginRegistry::loadCategory('importexport');
		$plugin =& PluginRegistry::getPlugin('importexport', 'DNBExportPlugin'); /* @var $plugin DNBExportPlugin */
		$this->_plugin =& $plugin;

		if (is_a($plugin, 'DNBExportPlugin')) {
			$plugin->addLocaleData();
		}

		parent::ScheduledTask($args);
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

		// get all journals that meet the requirements
		$journals = $this->_getJournals();

		$request =& Application::getRequest();
		$errors = array();
		foreach ($journals as $journal) {
			// Get not deposited articles
			$notDepositedArticles = $plugin->_getNotDepositedArticles($journal);
			$notDepositedArticlesIds = array();
			foreach ($notDepositedArticles as $article) {
				if (is_a($article, 'PublishedArticle') && $plugin->canBeExported($article)) {
					$notDepositedArticlesIds[] = $article->getId();
				}
			}

			if (count($notDepositedArticlesIds)) {
				// Deposit articles
				$result = $plugin->depositArticles($request, $notDepositedArticlesIds, $journal);
				if ($result !== true) {
					// Error occured, add it to the scheduled task log
					if (is_array($result)) {
						foreach($result as $error) {
							assert(is_array($error) && count($error) >= 1);
							$this->addExecutionLogEntry(
								__($error[0], array('param' => (isset($error[1]) ? $error[1] : null))),
								SCHEDULED_TASK_MESSAGE_TYPE_WARNING
							);
						}
					}
				}
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
		$plugin =& $this->_plugin;
		$journalDao =& DAORegistry::getDAO('JournalDAO'); /* @var $journalDao JournalDAO */
		$journalFactory =& $journalDao->getJournals(true);
		$journals = array();
		while($journal =& $journalFactory->next()) {
			$journalId = $journal->getId();
			// check required plugin settings
			if (!$plugin->getSetting($journalId, 'username') ||
				!$plugin->getSetting($journalId, 'password') ||
				!$plugin->getSetting($journalId, 'folderId') ||
				!$plugin->getSetting($journalId, 'automaticDeposit') ||
				!$plugin->checkPluginSettings($journal)) continue;

			$journals[] =& $journal;
			unset($journal);
		}
		return $journals;
	}

}

?>
