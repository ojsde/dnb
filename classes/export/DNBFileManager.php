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

use APP\core\Application;
use APP\core\Services;
use PKP\config\Config;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToCopyFile;
use PKP\file\FileManager;

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
			return [['plugins.importexport.dnb.export.error.remoteGalleysNotEnabled']];
		}
		
		// Download remote file with HttpClient
		$httpClient = Application::get()->getHttpClient();
		$remoteUrl = $galley->getData('urlRemote');
		
		try {
			$httpResponse = $httpClient->request('GET', $remoteUrl, [
				'allow_redirects' => true,
			]);
		} catch (\GuzzleHttp\Exception\RequestException $e) {
			$errorMessage = $e->getMessage();
			if ($e->hasResponse()) {
				$errorMessage = $e->getResponse()->getBody() . ' (' . $e->getResponse()->getStatusCode() . ')';
			}
			return [['plugins.importexport.dnb.export.error.curlError', $errorMessage]];
		}
		
		// Verify content type
		$contentType = $httpResponse->getHeaderLine('Content-Type');
		if (!preg_match('(application/pdf|application/epub+zip)', $contentType)) {
			return [['plugins.importexport.dnb.export.error.remoteGalleyContentTypeNotValid', $contentType]];
		}
		
		$response = $httpResponse->getBody()->getContents();
		
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
