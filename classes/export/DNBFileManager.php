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
use PKP\galley\Galley;

class DNBFileManager {
	
	private object $plugin;
	
	/**
	 * Constructor for file manager service.
	 *
	 * @param object $plugin The DNB export plugin instance.
	 */
	public function __construct(object $plugin) {
		$this->plugin = $plugin;
	}
	
	/**
	 * Copy galley file to export path.
	 *
	 * @param Galley $galley The galley object to copy.
	 * @param string $exportPath Target export directory path.
	 * @return string|array Full path on success, or array of error messages.
	 */
	public function copyGalleyFile(Galley $galley, string $exportPath): string|array {
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
	 * Handle remote galley file by downloading and storing it.
	 *
	 * @param Galley $galley The remote galley object.
	 * @param string $exportPath Target export directory path.
	 * @return string|array Full path on success, or array of error messages.
	 */
	private function handleRemoteGalley(Galley $galley, string $exportPath): string|array {
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
	 * Get or create export path, preparing directories as needed.
	 *
	 * @param int|null $journalId Journal ID for path naming.
	 * @param string|null $currentExportPath Current export path base.
	 * @param string|null $exportContentDir Subdirectory name within export path.
	 * @return string|array Export path with trailing slash on success, or array of error messages.
	 */
	public function getExportPath(?int $journalId = null, ?string $currentExportPath = null, ?string $exportContentDir = null): string|array {
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
	 * Read file contents from a file path.
	 *
	 * @param string $filePath Path to the file to read.
	 * @param bool $output If true, output file directly; if false, return contents.
	 * @return string|bool File contents, true if output to browser, or false on failure.
	 */
	public function readFileFromPath(string $filePath, bool $output = false): string|bool {
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
	 * Download file to browser with appropriate headers.
	 *
	 * @param string $filePath Path to the file to download.
	 * @param string|null $mediaType MIME type; defaults to application/octet-stream.
	 * @param bool $inline If true, display inline; if false, force download.
	 * @param string|null $fileName Custom filename for download.
	 * @return bool True on success, false if file not readable.
	 */
	public function downloadByPath(string $filePath, ?string $mediaType = null, bool $inline = false, ?string $fileName = null): bool {
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
