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

use PKP\config\Config;

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

		$curlCh = $this->initCurl();
		
		curl_setopt($curlCh, CURLOPT_UPLOAD, true);
		curl_setopt($curlCh, CURLOPT_HEADER, true);
		curl_setopt($curlCh, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlCh, CURLOPT_PROTOCOLS, CURLPROTO_SFTP | CURLPROTO_HTTPS);

		// Additional curl options from config
		if ($value = Config::getVar('dnb-plugin', 'CURLOPT_SSH_HOST_PUBLIC_KEY_MD5')) {
			curl_setopt($curlCh, CURLOPT_SSH_HOST_PUBLIC_KEY_MD5, $value);
		}
		if ($value = Config::getVar('dnb-plugin', 'CURLOPT_SSH_HOST_PUBLIC_KEY_SHA256')) {
			curl_setopt($curlCh, CURLOPT_SSH_HOST_PUBLIC_KEY_SHA256, $value);
		}
		if ($value = Config::getVar('dnb-plugin', 'CURLOPT_SSH_PUBLIC_KEYFILE')) {
			curl_setopt($curlCh, CURLOPT_SSH_PUBLIC_KEYFILE, $value);
		}

		$username = $this->plugin->getSetting($context->getId(), 'username');
		$password = $this->plugin->getSetting($context->getId(), 'password');
		$folderId = $this->plugin->getSetting($context->getId(), 'folderId');
		$folderId = trim($folderId, '/');

		assert(is_readable($filename));
		$fh = fopen($filename, 'rb');

		$server = DNB_SFTP_SERVER;
		$port = DNB_SFTP_PORT;

		if ($this->plugin->getSetting($context->getId(), 'connectionType')) {
			$server = DNB_WEBDAV_SERVER;
			$port = DNB_WEBDAV_PORT;
		}

		curl_setopt($curlCh, CURLOPT_URL, $server . $folderId . '/' . basename($filename));
		curl_setopt($curlCh, CURLOPT_PORT, $port);
		curl_setopt($curlCh, CURLOPT_USERPWD, "$username:$password");
		curl_setopt($curlCh, CURLOPT_INFILESIZE, filesize($filename));
		curl_setopt($curlCh, CURLOPT_INFILE, $fh);

		// Create curl log file
		curl_setopt($curlCh, CURLOPT_VERBOSE, true);
		$verbose = fopen(Config::getVar('files', 'files_dir') . '/' . $this->plugin->getPluginSettingsPrefix() . '/curl.log', 'w+');
		curl_setopt($curlCh, CURLOPT_STDERR, $verbose);

		// Execute request
		$response = curl_exec($curlCh);
		$curlError = curl_error($curlCh);

		if ($curlError || $response === false) {
			if ($curlError) {
				$param = __('plugins.importexport.dnb.deposit.error.fileUploadFailed.param', [
					'package' => basename($filename), 
					'articleId' => $object->getData('submissionId'), 
					'error' => $curlError
				]);
			} else {
				$param = __('plugins.importexport.dnb.deposit.error.fileUploadFailed.param', [
					'package' => basename($filename), 
					'articleId' => $object->getData('submissionId'), 
					'error' => 'Unknown error'
				]);
			}
			$errors[] = ['plugins.importexport.dnb.deposit.error.fileUploadFailed', $param];
		}
		
		curl_close($curlCh);

		if (!empty($errors)) return $errors;
		return true;
	}
	
	/**
	 * Initialize CURL with proxy settings
	 */
	private function initCurl() {
		$curlCh = curl_init();
		$httpProxyHost = Config::getVar('proxy', 'https_proxy') ?: Config::getVar('proxy', 'http_proxy');
		
		if ($httpProxyHost) {
			curl_setopt($curlCh, CURLOPT_PROXY, $httpProxyHost);
			
			if ($curlProxyTunnel = Config::getVar('dnb-plugin', 'CURLOPT_HTTPPROXYTUNNEL')) {
				curl_setopt($curlCh, CURLOPT_HTTPPROXYTUNNEL, $curlProxyTunnel);
			}
			
			if ($username = Config::getVar('proxy', 'username')) {
				curl_setopt($curlCh, CURLOPT_PROXYUSERPWD, $username . ':' . Config::getVar('proxy', 'password'));
			}
		}
		
		return $curlCh;
	}
}
