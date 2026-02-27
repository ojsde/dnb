<?php

/**
 * @file plugins/generic/dnb/classes/deposit/DNBDepositService.php
 *
 * Copyright (c) 2021 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the plugin file LICENSE.
 *
 * @class DNBDepositService
 * @brief Service for depositing packages to DNB hotfolder
 */

namespace APP\plugins\generic\dnb\classes\deposit;

use APP\facades\Repo;
use PKP\config\Config;
use APP\plugins\generic\dnb\classes\export\DNBExportJob;

if (!defined('DNB_STATUS_DEPOSITED')) define('DNB_STATUS_DEPOSITED', 'deposited');
if (!defined('DNB_EXPORT_STATUS_QUEUED')) define('DNB_EXPORT_STATUS_QUEUED', 'queued');

class DNBDepositService {
	
	private $plugin;
	
	/**
	 * Constructor.
	 *
	 * @param object $plugin The plugin instance used for configuration.
	 */
	public function __construct($plugin) {
		$this->plugin = $plugin;
	}
	
	/**
	 * Deposit XML package to DNB
	 * 
	 * @param $object Object (Galley) with submission data
	 * @param $context Context
	 * @param $filename string Full path to pre-built package file (used for manual exports)
	 *                         If null, will build package in job (used for scheduled exports)
	 * @return bool|array True on success, or error array on failure
	 */
	public function deposit($object, $context, $filename = null): bool|array {
		$errors = [];

		// Validate credentials
		if (!($this->plugin->getSetting($context->getId(), 'username') &&
			$this->plugin->getSetting($context->getId(), 'password') &&
			$this->plugin->getSetting($context->getId(), 'folderId'))) {
			$errors[] = ['plugins.importexport.dnb.deposit.error.hotfolderCredentialsMissing'];
			return $errors;
		}

		// If filename is provided (manual export), validate it exists
		if ($filename !== null && !file_exists($filename)) {
			$param = __('plugins.importexport.dnb.deposit.error.fileUploadFailed.FileNotFound.param', [
				'package' => basename($filename), 
				'articleId' => $object->getFile()->getData('submissionId')
			]);
			$errors[] = ['plugins.importexport.dnb.deposit.error.fileUploadFailed', $param];
			return $errors;
		}

		// For manual exports with pre-built packages, dispatch job with filename
		// For scheduled exports, job will build package itself (filename = null)
		try {
			if ($filename !== null) {
				// Manual export - package already built, just upload
				dispatch(
					new DNBExportJob(
						$object->getId(), 
						$context->getId(),
						$object->getData('submissionId'),
						[], // No supplementary galleys needed when package is pre-built
						'', // No filter needed
						false, // No validation flag needed
						$filename // Pre-built package path
					)
				);
			} else {
				// This path is not used anymore - scheduled task dispatches jobs directly
				// Keeping for backward compatibility
				throw new \Exception('Direct deposit without filename not supported - use DNBInfoSender to dispatch jobs');
			}
		} catch (\Exception $e) {
			$errors[] = ['plugins.importexport.dnb.deposit.error.jobDispatchFailed', $e->getMessage()];
			return $errors;
		}

		// If we reach this point, the job was successfully dispatched
		// Set the submissions export status to 'queued' if the job wasn't even faster and completed immediately, i.e. it already has status 'deposited'
		$submission = Repo::submission()->get($object->getData('submissionId'));
		if ($submission->getData($this->plugin->getPluginSettingsPrefix().'::status') !== DNB_STATUS_DEPOSITED) {
			Repo::submission()->edit($submission, [
				$this->plugin->getPluginSettingsPrefix().'::status' => DNB_EXPORT_STATUS_QUEUED
			]);
			// We need to set it also on the object in memory, otherwise the edit on last error will overwrite it because $submission object is not updated by OJS
			$submission->setData($this->plugin->getPluginSettingsPrefix().'::status', DNB_EXPORT_STATUS_QUEUED);
		}
		// delete last error message
		Repo::submission()->edit($submission, [$this->plugin->getPluginSettingsPrefix().'::lastError' => null]);

		return true;
	}
}