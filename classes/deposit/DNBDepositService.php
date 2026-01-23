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

class DNBDepositService {
	
	private $plugin;
	
	public function __construct($plugin) {
		$this->plugin = $plugin;
	}
	
	/**
	 * Deposit XML package to DNB
	 */
	public function deposit($object, $context, $filename): bool|array {
		$errors = [];

		// Validate credentials
		if (!($this->plugin->getSetting($context->getId(), 'username') &&
			$this->plugin->getSetting($context->getId(), 'password') &&
			$this->plugin->getSetting($context->getId(), 'folderId'))) {
			$errors[] = ['plugins.importexport.dnb.deposit.error.hotfolderCredentialsMissing'];
			return $errors;
		}

		if (!file_exists($filename)) {
			$param = __('plugins.importexport.dnb.deposit.error.fileUploadFailed.FileNotFound.param', [
				'package' => basename($filename), 
				'articleId' => $object->getFile()->getData('submissionId')
			]);
			$errors[] = ['plugins.importexport.dnb.deposit.error.fileUploadFailed', $param];
			return $errors;
		}

		// Dispatch a job to the queue with IDs instead of full objects to avoid serialization issues
		// The job will handle the complete deposit transfer via curl
		try {
			dispatch(
				new DNBExportJob(
					$object->getId(), 
					$context->getId(),
					$object->getData('submissionId'),
					$filename
				)
			);
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