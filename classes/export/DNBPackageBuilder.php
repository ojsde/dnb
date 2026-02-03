<?php

/**
 * @file plugins/generic/dnb/classes/export/DNBPackageBuilder.php
 *
 * Copyright (c) 2021 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the plugin file LICENSE.
 *
 * @class DNBPackageBuilder
 * @brief Service for building DNB export packages
 */

namespace APP\plugins\generic\dnb\classes\export;

use APP\core\Services;
use PKP\config\Config;

class DNBPackageBuilder
{

	private $plugin;
	private $fileManager;

	public function __construct($plugin, $fileManager)
	{
		$this->plugin = $plugin;
		$this->fileManager = $fileManager;
	}

	/**
	 * Generate TAR package for a galley
	 */
	public function buildPackage($galley, $supplementaryGalleys, $filter, $noValidation, $journal, $exportPathBase, &$exportPackageName, $submissionId): bool|array
	{
		$exportContentDir = $journal->getId() . '-' . $submissionId . '-' . $galley->getId();
		$exportPath = $this->fileManager->getExportPath($journal->getId(), $exportPathBase, $exportContentDir);

		if (is_array($exportPath)) {
			return $exportPath; // Error occurred
		}

		// Store submissionId on galley for filter's use (required by DNBXmlFilter)
		$galley->setData('submissionId', $submissionId);

		// Copy galley files
		$result = $this->fileManager->copyGalleyFile($galley, $exportPath . 'content/');
		if (is_array($result)) {
			return $result; // Error occurred during copy
		}

		// Copy supplementary galley files
		foreach ($supplementaryGalleys as $supplementaryGalley) {
			$result = $this->fileManager->copyGalleyFile($supplementaryGalley, $exportPath . 'content/supplementary/');
			if (is_array($result)) {
				return $result; // Error occurred during copy
			}
		}

		// Export metadata XML
		$outputErrors = null;
		try {
			$metadataXML = $this->plugin->exportXML($galley, $filter, $journal, $noValidation, $outputErrors);

			// Check if there were validation errors
			if (!empty($outputErrors)) {
				$errorMessages = array_map(function ($error) {
					return $error->message . ' (line ' . $error->line . ')';
				}, $outputErrors);
				return [['plugins.importexport.dnb.export.error.xmlValidationFailed', implode('; ', $errorMessages)]];
			}
		} catch (\Exception $e) {
			// Return error in the standard format [['locale_key_or_message', 'param']]
			return [['plugins.importexport.dnb.export.error.xmlExportFailed', $e->getMessage()]];
		}

		// Write metadata XML
		$metadataFile = $exportPath . 'catalogue_md.xml';
		Services::get('file')->fs->write($metadataFile, $metadataXML);

		// TAR supplementary files if present
		if (Services::get('file')->fs->has($exportPath . 'content/supplementary')) {
			$this->tarSupplementaryFiles($exportPath);
		}

		// Create final TAR package
		$exportPackageName = Config::getVar('files', 'files_dir') . '/' . $exportPathBase . $exportContentDir . '.tar';
		$this->createTarArchive(Config::getVar('files', 'files_dir') . '/' . $exportPath, $exportPackageName);

		return true;
	}

	/**
	 * TAR supplementary files
	 */
	private function tarSupplementaryFiles($exportPath): void
	{
		$supplementaryPath = Config::getVar('files', 'files_dir') . '/' . $exportPath . 'content/supplementary/';
		$supplementaryTar = Config::getVar('files', 'files_dir') . '/' . $exportPath . 'content/supplementary.tar';
		$this->createTarArchive($supplementaryPath, $supplementaryTar);
		Services::get('file')->fs->deleteDirectory($exportPath . 'content/supplementary');
	}

	/**
	 * Create TAR archive
	 */
	public function createTarArchive($targetPath, $targetFile, $sourceFiles = null, $gzip = false): void
	{
		$tarCommand = '';
		$tarOptions = $gzip ? ' -czf ' : ' -cf ';

		$tarCommand .= Config::getVar('cli', 'tar') . ' -C ' . escapeshellarg($targetPath) . ' ';
		$tarCommand .= DNB_ADDITIONAL_PACKAGE_OPTIONS . $tarOptions . escapeshellarg($targetFile);
		$tarCommand .= ' --owner 0 --group 0 --';

		// Get source files
		if (!$sourceFiles) {
			$sourceFiles = [];
			$dirIterator = new \RecursiveDirectoryIterator(
				$targetPath,
				\RecursiveDirectoryIterator::SKIP_DOTS
			);
			$iterator = new \RecursiveIteratorIterator($dirIterator);

			foreach ($iterator as $file) {
				// strip $targetPath from the file path
				$relativePath = str_replace($targetPath, '', $file->getPathname());
				$sourceFiles[] = $relativePath;
			}
		}

		// Add files to command
		foreach ($sourceFiles as $sourceFile) {
			$tarCommand .= ' ' . escapeshellarg($sourceFile);
		}

		exec($tarCommand);
	}
}
