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
use PKP\config\Config;
use PKP\db\DAORegistry;

class DNBExportValidator {
	
	private $plugin;
	private $galleyFilter;
	
	public function __construct($plugin, $galleyFilter) {
		$this->plugin = $plugin;
		$this->galleyFilter = $galleyFilter;
	}
	
	/**
	 * Check if submission can be exported
	 */
	public function canBeExported($submission, &$issue = null, &$galleys = [], &$supplementaryGalleys = [], $newGalley = null): bool {

		$issue = $issue?:Repo::issue()->getBySubmissionId($submission->getId());
		
		if (!$issue || !$issue->getPublished()) {
			return false;
		}

		// Get all galleys
		$galleys = $submission->getGalleys();

		// If a new galley is passed, replace galley with identical ID (this updates uploaded file ID still missing in the database object)
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
			$supplementaryGalleys ? $submission->setData('hasSupplementary', true) : NULL;
		}
		
		// Filter PDF and EPUB full text galleys
		$galleys = array_filter($galleys, [$this->galleyFilter, 'filterPDFAndEPUB']);

		// Flag if supplementary files can't be unambiguously assigned
		if ((count($galleys) > 1) && (isset($supplementaryGalleys) && count($supplementaryGalleys) > 0)) {
			$submission->setData('supplementaryNotAssignable', true);
		} else {
			$submission->setData('supplementaryNotAssignable', false);
		}

		return (count($galleys) > 0);
	}
	
	/**
	 * Check if tar binary is available
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
	 * Check if export filter is registered
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
	 * Check if plugin settings are complete
	 */
	public function checkPluginSettings($journal): bool {
		// If journal is not open access, archive access setting is required
		return $this->isOAJournal($journal) || $this->plugin->getSetting($journal->getId(), 'archiveAccess');
	}
	
	/**
	 * Check if journal is open access
	 */
	public function isOAJournal($journal = null): bool {
		if (!isset($journal)) {
			$request = \APP\core\Application::get()->getRequest();
			$journal = $request->getContext();
		}
		
		return $journal->getSetting('publishingMode') == \APP\journal\Journal::PUBLISHING_MODE_OPEN &&
			$journal->getSetting('restrictSiteAccess') != 1 &&
			$journal->getSetting('restrictArticleAccess') != 1;
	}
}
