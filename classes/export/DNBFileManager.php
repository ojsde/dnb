<?php

/**
 * @file plugins/generic/dnb/classes/export/DNBFileManager.php
 *
 * Copyright (c) 2021 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the plugin file LICENSE.
 *
 * @class DNBFileManager
 * @brief Service for managing file operations in DNB export
 */

namespace APP\plugins\generic\dnb\classes\export;

use APP\core\Services;
use PKP\config\Config;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToCopyFile;

class DNBFileManager {
	
	private $plugin;
	
	public function __construct($plugin) {
		$this->plugin = $plugin;
	}
	
	/**
	 * Copy galley file to export path
	 */
	public function copyGalleyFile($galley, $exportPath): string|array {
		$galleyFile = $galley->getFile();
		$basedir = Config::getVar('files', 'files_dir');
		
		if ($galleyFile == null) {
			// Remote galley - handle separately
			return $this->handleRemoteGalley($galley, $exportPath);
		}
		
		$sourceGalleyFilePath = $galleyFile->getData('path');
		$targetGalleyFilePath = $exportPath . basename($sourceGalleyFilePath);
		
		if (!Services::get('file')->fs->has($sourceGalleyFilePath)) {
			return [['plugins.importexport.dnb.export.error.galleyFileNotFound', $sourceGalleyFilePath ?: "NULL"]];
		}

		// Copy file
		try {
			Services::get('file')->fs->copy($sourceGalleyFilePath, $targetGalleyFilePath);
		} catch (FilesystemException | UnableToCopyFile $exception) {
			$param = __('plugins.importexport.dnb.export.error.galleyFileNoCopy.param', array('sourceGalleyFilePath' => $sourceGalleyFilePath, 'targetGalleyFilePath' => $targetGalleyFilePath));
			return [['plugins.importexport.dnb.export.error.galleyFileNoCopy', $param]];
		}

		return realpath($basedir . '/' . $targetGalleyFilePath);
	}
	
	/**
	 * Handle remote galley file
	 */
	private function handleRemoteGalley($galley, $exportPath): string|array {
		$basedir = Config::getVar('files', 'files_dir');
		
		if (!$this->plugin->exportRemote()) {
			return [['Remote galleys not enabled']];
		}
		
		// Download remote file with CURL
		$curlCh = curl_init();
		
		if ($httpProxyHost = Config::getVar('proxy', 'http_host')) {
			curl_setopt($curlCh, CURLOPT_PROXY, $httpProxyHost);
			curl_setopt($curlCh, CURLOPT_PROXYPORT, Config::getVar('proxy', 'http_port', '80'));
			if ($username = Config::getVar('proxy', 'username')) {
				curl_setopt($curlCh, CURLOPT_PROXYUSERPWD, $username . ':' . Config::getVar('proxy', 'password'));
			}
		}
		
		curl_setopt($curlCh, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curlCh, CURLOPT_URL, $galley->getData('urlRemote'));
		curl_setopt($curlCh, CURLOPT_RETURNTRANSFER, 1);
		
		$response = curl_exec($curlCh);
		$curlError = curl_error($curlCh);
		
		if ($curlError) {
			curl_close($curlCh);
			return [['plugins.importexport.dnb.export.error.curlError', $curlError]];
		}
		
		// Verify content type
		$contentType = curl_getinfo($curlCh, CURLINFO_CONTENT_TYPE);
		if (!preg_match('(application/pdf|application/epub+zip)', $contentType)) {
			curl_close($curlCh);
			return [['plugins.importexport.dnb.export.error.remoteGalleyContentTypeNotValid', $contentType]];
		}
		
		curl_close($curlCh);
		
		// Verify mime-type by magic bytes: PDF (%PDF-) or EPUB (PK..)
		if (!preg_match('/^(%PDF-|PK..)/', $response)) {
			return [['plugins.importexport.dnb.export.error.remoteFileMimeTypeNotValid', $galley->getSubmissionId()]];
		}
		
		// Store downloaded file temporarily
		$temporaryFilename = tempnam($basedir . '/' . $this->plugin->getPluginSettingsPrefix(), 'dnb');
		
		$file = fopen($temporaryFilename, "w+");
		if (!$file) {
			return [['plugins.importexport.dnb.export.error.tempFileNotCreated']];
		}
		fputs($file, $response);
		fclose($file);
		
		$galley->setData('fileSize', filesize($temporaryFilename));
		
		$sourceGalleyFilePath = $this->plugin->getPluginSettingsPrefix() . "/" . basename($temporaryFilename);
		$targetGalleyFilePath = $exportPath . basename($galley->getData('urlRemote'));
		$galley->setData('fileType', pathinfo($targetGalleyFilePath, PATHINFO_EXTENSION));
		
		// Copy to export path
		try {
			Services::get('file')->fs->copy($sourceGalleyFilePath, $targetGalleyFilePath);
			// Remove temporary file
			Services::get('file')->fs->delete($temporaryFilename);
		} catch (FilesystemException | UnableToCopyFile $exception) {
			$param = __('plugins.importexport.dnb.export.error.galleyFileNoCopy.param', array('sourceGalleyFilePath' => $sourceGalleyFilePath, 'targetGalleyFilePath' => $targetGalleyFilePath));
			return [['plugins.importexport.dnb.export.error.galleyFileNoCopy', $param]];
		}
		
		return realpath($basedir . '/' . $targetGalleyFilePath);
	}
	
	/**
	 * Get export path, creating directories if needed
	 */
	public function getExportPath($journalId = null, $currentExportPath = null, $exportContentDir = null): string|array {
		if (!$currentExportPath) {
			$exportPath = $this->plugin->getPluginSettingsPrefix() . '/' . $journalId . '-' . date('Ymd-His');
		} else {
			$exportPath = $currentExportPath;
		}
		
		if ($exportContentDir) {
			$exportPath .= $exportContentDir;
		}
		
		if (!Services::get('file')->fs->has($exportPath)) {
			try {
				Services::get('file')->fs->createDirectory($exportPath);
			} catch (\Exception $e) {
				return [['plugins.importexport.dnb.export.error.couldNotCreateDirectory', $exportPath]];
			}
		}
		
		if (!Services::get('file')->fs->has($exportPath)) {
			$errors = array(
				array('plugins.importexport.dnb.export.error.outputFileNotWritable', $exportPath)
			);
			return $errors;
		}
		
		return $exportPath . '/';
	}
	
	/**
	 * Read file from path
	 */
	public function readFileFromPath($filePath, $output = false) {
		if (is_readable($filePath)) {
			if ($output) {
				readfile($filePath);
				return true;
			}
			return file_get_contents($filePath);
		}
		return false;
	}
	
	/**
	 * Download file to browser
	 */
	public function downloadByPath($filePath, $mediaType = null, $inline = false, $fileName = null): bool {
		if (!is_readable($filePath)) {
			return false;
		}
		
		if (!$fileName) {
			$fileName = basename($filePath);
		}
		
		header('Content-Type: ' . ($mediaType ?: 'application/octet-stream'));
		header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $fileName . '"');
		header('Content-Length: ' . filesize($filePath));
		
		readfile($filePath);
		return true;
	}
}
