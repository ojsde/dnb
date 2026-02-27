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
use PKP\galley\Galley;
use PKP\context\Context;

class DNBPackageBuilder
{

	private object $plugin;
	private DNBFileManager $fileManager;

	/**
	 * Constructor for the package builder service.
	 *
	 * @param object $plugin The DNB export plugin instance.
	 * @param DNBFileManager $fileManager File operations manager.
	 */
	public function __construct(object $plugin, DNBFileManager $fileManager)
	{
		$this->plugin = $plugin;
		$this->fileManager = $fileManager;
	}

	/**
	 * Assemble the export package (TAR archive with XML + files).
	 *
	 * Performs the low-level construction: runs the XML filter on the submission,
	 * gathers supplementary files, creates the package directory structure,
	 * and generates the final TAR archive ready for deposit to the DNB.
	 *
	 * @param Galley $galley Primary galley object.
	 * @param Galley[] $supplementaryGalleys Array of supplementary galley objects.
	 * @param string $filter Filter identifier used for metadata export.
	 * @param bool|null $noValidation Skip XML validation if true.
	 * @param Context $journal Journal context object.
	 * @param string $exportPathBase Base path within files_dir where export occurs.
	 * @param string &$exportPackageName Output parameter; will hold full path to final TAR file.
	 * @param int $submissionId Submission identifier used in path naming.
	 * @return bool|array True on success or array of error messages.
	 */
	public function assemblePackage(Galley $galley, array $supplementaryGalleys, string $filter, ?bool $noValidation = true, Context $journal, string $exportPathBase, string &$exportPackageName, int $submissionId): bool|array
	{
		// Export filter must be provided and not empty
		if (empty($filter)) {
			return [['plugins.importexport.dnb.export.error.missingFilter']];
		}

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
		$outputErrors = [];
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
	 * TAR all supplemental files contained under the given export path and
	 * remove the original directory after archiving.
	 *
	 * @param string $exportPath Relative export directory path inside files_dir.
	 * @return bool|array True on success or array with error info on failure.
	 */
	private function tarSupplementaryFiles(string $exportPath): bool|array
	{
		$supplementaryPath = Config::getVar('files', 'files_dir') . '/' . $exportPath . 'content/supplementary/';
		$supplementaryTar = Config::getVar('files', 'files_dir') . '/' . $exportPath . 'content/supplementary.tar';
		$result = $this->createTarArchive($supplementaryPath, $supplementaryTar);
		if (is_array($result)) {
			return $result;
		}

		Services::get('file')->fs->deleteDirectory($exportPath . 'content/supplementary');
		return true;
	}

	/**
	* Execute system tar command to create an archive.
	 *
	 * @param string $targetPath Directory to change into before running tar.
	 * @param string $targetFile Full path to output tar file.
	 * @param string[]|null $sourceFiles List of files (relative to targetPath) to include. If null all files are added.
	 * @param bool $gzip Whether to gzip the resulting archive.
	* @return bool|array True on success or array with error info on failure.
	*/
    public function createTarArchive(string $targetPath, string $targetFile, ?array $sourceFiles = null, bool $gzip = false): bool|array
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

		exec($tarCommand, $output, $returnVar);

		if ($returnVar !== 0) {
			return [['plugins.importexport.dnb.export.error.createTarFailed', implode("\n", $output)]];
		}

		// Ensure the archive exists
		if (!file_exists($targetFile)) {
			return [['plugins.importexport.dnb.export.error.createTarFailed', 'archive_not_created']];
		}

		return true;
	}
}
