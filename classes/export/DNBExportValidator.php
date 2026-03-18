<?php

/**
 * @file plugins/generic/dnb/classes/export/DNBExportValidator.php
 *
 * Copyright (c) 2021 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the plugin file LICENSE.
 *
 * @class DNBExportValidator
 * @brief Validator for DNB export requirements
 */

namespace APP\plugins\generic\dnb\classes\export;

use APP\facades\Repo;
use APP\submission\Submission;
use APP\issue\Issue;
use APP\journal\Journal;
use PKP\config\Config;
use PKP\db\DAORegistry;

class DNBExportValidator {
	
	private object $plugin;
	private object $galleyFilter;
	
	/**
	 * Constructor for the export validator.
	 *
	 * @param object $plugin The DNB export plugin instance.
	 * @param object $galleyFilter The galley filter service.
	 */
	public function __construct(object $plugin, object $galleyFilter) {
		$this->plugin = $plugin;
		$this->galleyFilter = $galleyFilter;
	}
	
	/**
	 * Determine whether a submission meets the requirements for export.
	 *
	 * @param Submission $submission The submission to test.
	 * @param Issue|null &$issue Optional issue; if not provided it will be looked up.
	 * @param array &$galleys Will be populated with eligible galleys (PDF/EPUB).
	 * @param array &$supplementaryGalleys Will be populated with supplementary files.
	 * @param object|null $newGalley Optionally provide a new galley object to merge.
	 * @return bool True if exportable, false otherwise.
	 */
	public function canBeExported(Submission $submission, ?Issue &$issue = null, array &$galleys = [], array &$supplementaryGalleys = [], ?object $newGalley = null): bool {

		// Ensure we have an issue and that it is published; unpublished
		// submissions are not exportable.
		$issue = $issue?:Repo::issue()->getBySubmissionId($submission->getId());
		
		if (!$issue || !$issue->getPublished()) {
			return false;
		}

		// Retrieve the current set of galleys attached to the submissions current publication.
		$galleys = $submission->getCurrentPublication()->getData('galleys')->toArray();

		// If the caller supplied a freshly-uploaded galley object (e.g. during
		// form submission), swap it into the array so validation can inspect its
		// properties.  The DB object may not yet contain the file ID, hence the
		// merge logic.
		if ($newGalley !== null) {
			$found = false;
			foreach ($galleys as $index => $galley) {
				if ($galley->getId() == $newGalley->getId()) {
					$galleys[$index] = $newGalley;
					$found = true;
					break;
				}
			}
			if (!$found) {
				$galleys[] = $newGalley;
			}
		}
		
		// Filter supplementary files if configured
		if ($this->plugin->getSetting($submission->getData('contextId'), 'submitSupplementaryMode') == 'all') {
			$supplementaryGalleys = array_filter($galleys, [$this->galleyFilter, 'filterSupplementary']);
			$supplementaryGalleys ? $submission->setData($this->plugin->getPluginSettingsPrefix().'::hasSupplementary', true) : NULL;
		}
		
		// From the full list, only keep galleys that represent PDF/EPUB
		// files; other formats are ignored by the DNB export.
		$galleys = array_filter($galleys, [$this->galleyFilter, 'filterPDFAndEPUB']);

		// If we have more than one main galley *and* we also classified some
		// as supplementary, the plugin currently cannot decide which file is the
		// primary export.  Memoize this situation for later UI warning.
		if ((count($galleys) > 1) && (isset($supplementaryGalleys) && count($supplementaryGalleys) > 0)) {
			$submission->setData($this->plugin->getPluginSettingsPrefix().'::supplementaryNotAssignable', true);
		} else {
			$submission->setData($this->plugin->getPluginSettingsPrefix().'::supplementaryNotAssignable', false);
		}

		// Submission is exportable only if there is at least one valid galley
		return (count($galleys) > 0);
	}
	
	/**
	 * Verify that the system tar command exists and is executable.
	 *
	 * @return bool|array True on success or an array of error messages.
	 */
	public function checkForTar(): bool|array {
		$tarBinary = Config::getVar('cli', 'tar');
		$tarBinary = explode(" ", $tarBinary)[0]; // Get binary path only
		
		if (empty($tarBinary) || !is_executable($tarBinary)) {
			return [['manager.plugins.tarCommandNotFound']];
		}
		
		return true;
	}
	
	/**
	 * Ensure that the configured submission filter is registered in the system.
	 *
	 * @return bool|array True if found or an array of errors otherwise.
	 */
	public function checkForExportFilter(): bool|array {
		$filterDao = DAORegistry::getDAO('FilterDAO'); /** @var FilterDAO $filterDao */
		$exportFilters = $filterDao->getObjectsByGroup($this->plugin->getSubmissionFilter());
		
		if (count($exportFilters) == 0) {
			return [['plugins.importexport.common.error.filter']];
		}
		
		return true;
	}
	
	/**
	 * Confirm that required plugin settings are present for the given journal.
	 *
	 * @param Journal $journal Journal object to inspect.
	 * @return bool True if configuration is sufficient.
	 */
	public function checkPluginSettings(Journal $journal): bool {
		// If journal is not open access, archive access setting is required
		return $this->isOAJournal($journal) || $this->plugin->getSetting($journal->getId(), 'archiveAccess');
	}
	
	/**
	 * Determine whether the journal is open access (no site or article restrictions).
	 *
	 * @param Journal|null $journal Optional journal object; current context is used if omitted.
	 * @return bool True if journal is open access.
	 */
	public function isOAJournal(?Journal $journal = null): bool {
		if (!isset($journal)) {
			$request = \APP\core\Application::get()->getRequest();
			$journal = $request->getContext();
		}
		
		return $journal->getSetting('publishingMode') == \APP\journal\Journal::PUBLISHING_MODE_OPEN &&
			$journal->getSetting('restrictSiteAccess') != 1 &&
			$journal->getSetting('restrictArticleAccess') != 1;
	}
}
