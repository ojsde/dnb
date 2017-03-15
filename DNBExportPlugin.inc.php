<?php

/**
 * @file plugins/importexport/dnb/DNBExportPlugin.inc.php
 *
 * Copyright (c) 2017 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v2. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan
 * Last update: March 15, 2017
 *
 * @class DNBExportPlugin
 * @ingroup plugins_importexport_dnb
 *
 * @brief DNB export/deposit plugin
 */

import('lib.pkp.classes.xml.XMLCustomWriter');

import('classes.plugins.ImportExportPlugin');

define('DNB_STATUS_MARKEDREGISTERED', 'markedRegistered');
define('DNB_STATUS_DEPOSITED', 'deposited');
define('DNB_STATUS_NOT_DEPOSITED', 'notDeposited');
// The name of the setting used to save the article status.
define('DNB_STATUS', 'DNBStatus');

class DNBExportPlugin extends ImportExportPlugin {
	/** @var boolean */
	var $_checkedForTar = false;

	/**
	 * Called as a plugin is registered to the registry
	 * @param $category String Name of category plugin was registered to
	 * @param $path String The path the plugin was found in
	 * @return boolean True if plugin initialized successfully; if false,
	 * 	the plugin will not be registered.
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);
		$this->addLocaleData();
		HookRegistry::register('AcronPlugin::parseCronTab', array($this, 'callbackParseCronTab'));
		return $success;
	}

	/**
	 * @copydoc PKPPlugin::getTemplatePath()
	 */
	function getTemplatePath() {
		return parent::getTemplatePath().'templates/';
	}

	/**
	 * @copydoc ImportExportPlugin::getName()
	 */
	function getName() {
		return 'DNBExportPlugin';
	}

	/**
	 * @copydoc ImportExportPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.importexport.dnb.displayName');
	}

	/**
	 * @copydoc ImportExportPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.importexport.dnb.description');
	}

	/**
	 * Returns plugin ID.
	 * This is also the directory below the files folder where
	 * all export files belonging to this plugin should be placed.
	 * @return string
	 */
	function getPluginId() {
		return 'dnb';
	}

	/**
	 * @copydoc ImportExportPlugin::getManagementVerbs()
	 */
	function getManagementVerbs() {
		$verbs = parent::getManagementVerbs();
		$verbs[] = array('settings', __('plugins.importexport.common.settings'));
		return $verbs;
	}

	/**
	 * @copydoc ImportExportPlugin::manage()
	 */
	function manage($verb, $args, &$message, &$messageParams, &$request) {
		parent::manage($verb, $args, $message, $messageParams, $request);

		switch ($verb) {
			case 'settings':
				$journal = $request->getJournal();

				$this->import('classes.form.DNBSettingsForm');
				$form = new DNBSettingsForm($this, $journal->getId());

				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						$request->redirect(null, 'manager', 'importexport', array('plugin', $this->getName()));
					} else {
						$this->setBreadCrumbs(array(), true);
						$form->display($request);
					}
				} else {
					$this->setBreadCrumbs(array(), true);
					$form->initData();
					$form->display($request);
				}
				return true;

			default:
				// Unknown management verb.
				assert(false);
		}
		return false;
	}

	/**
	 * @see ImportExportPlugin::display()
	 * @param $args array The array of arguments the user supplied.
	 * @param $request Request
	 *
	 * This supports the following actions:
	 * - articles: lists with exportable objects
	 * - process: process (deposit, export or mark registered) the selected objects
	 */
	function display($args, $request) {
		$templateMgr = TemplateManager::getManager();
		parent::display($args, $request);
		$journal = $request->getJournal();

		switch (array_shift($args)) {
			case 'articles':
				$filter = $request->getUserVar('filter');
				return $this->displayArticleList($templateMgr, $journal, $filter);
			case 'process':
				return $this->process($request, $journal);
			default:
				return $this->displayPluginHomePage($templateMgr, $journal);
		}
	}

	/**
	 * Display the plugin home page.
	 * @param $templateMgr TemplageManager
	 * @param $journal Journal
	 */
	function displayPluginHomePage($templateMgr, $journal) {
		$this->setBreadcrumbs();
		$issn = $journal->getSetting('onlineIssn');
		if (empty($issn)) $issn = $journal->getSetting('printIssn');
		$checkForTarResult = $this->checkForTar();
		$templateMgr->assign('issn', !empty($issn));
		$templateMgr->assign('checkTar', !is_array($checkForTarResult));
		$templateMgr->assign('checkSettings', $this->checkPluginSettings($journal));
		$templateMgr->display($this->getTemplatePath() . 'index.tpl');
	}

	/**
	 * Display a list of articles for export.
	 * @param $templateMgr TemplateManager
	 * @param $journal Journal
	 * @param $filter string Status to filter article list by
	 */
	function displayArticleList($templateMgr, $journal, $filter = null) {
		// if required plugin settings are missing, redirect to the plugin home page
		if (!$this->checkPluginSettings($journal)) $request->redirect(null, null, null, array('plugin', $this->getName()));;

		$this->setBreadcrumbs(array(), true);

		// Retrieve all published articles
		$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO'); /* @var $publishedArticleDao PublishedArticleDAO */
		$articles = array();
		if ($filter) {
			// filter articles by status
			if ($filter == DNB_STATUS_NOT_DEPOSITED) {
				$articles = $publishedArticleDao->getBySetting($this->getStatusSettingName(), null, $journal->getId());
			} else {
				$articles = $publishedArticleDao->getBySetting($this->getStatusSettingName(), $filter, $journal->getId());
			}
		} else {
			// get all published articles
			$articleIterator = $publishedArticleDao->getPublishedArticlesByJournalId($journal->getId());
			$articles = $articleIterator->toArray();
		}

		// Retrieve articles that can be exported
		$articleData = array();
		foreach($articles as $article) {
			$preparedArticle = $this->prepareArticleData($article);
			if (!$preparedArticle) continue;
			assert(is_array($preparedArticle));
			$articleData[] = $preparedArticle;
		}
		unset($articles);

		// Paginate articles
		$totalArticles = count($articleData);
		$rangeInfo = Handler::getRangeInfo('articles');
		if ($rangeInfo->isValid()) {
			$articleData = array_slice($articleData, $rangeInfo->getCount() * ($rangeInfo->getPage()-1), $rangeInfo->getCount());
		}
		// Instantiate article iterator
		import('lib.pkp.classes.core.VirtualArrayIterator');
		$iterator = new VirtualArrayIterator($articleData, $totalArticles, $rangeInfo->getPage(), $rangeInfo->getCount());

		// Get all credentials/data needed for the deposit from within OJS
		$username = $this->getSetting($journal->getId(), 'username');
		$password = $this->getSetting($journal->getId(), 'password');
		$folderId = $this->getSetting($journal->getId(), 'folderId');

		// Prepare and display the article template.
		$templateMgr->assign_by_ref('articles', $iterator);
		$templateMgr->assign('statusSettingName', $this->getStatusSettingName());
		$templateMgr->assign('statusMapping', $this->getStatusMapping());
		$templateMgr->assign('filter', $filter);
		$templateMgr->assign('hasCredentials', (!empty($username) && !empty($password) && !empty($folderId)));
		$templateMgr->display($this->getTemplatePath() . 'articles.tpl');
	}

	/**
	 * Process activity request.
	 *
	 * This supports the following actions:
	 * - markRegistered: mark article(s) as registered
	 * - deposit: deposit article(s)
	 * - export: export article(s)
	 *
	 * @param $request PKPRequest
	 * @param $journal Journal
	 */
	function process($request, $journal) {
		// if required plugin settings are missing, redirect to the plugin home page
		if (!$this->checkPluginSettings($journal)) $request->redirect(null, null, null, array('plugin', $this->getName()));;

		// Dispatch the action
		switch(true) {
			case $request->getUserVar('export'):
			case $request->getUserVar('deposit'):
			case $request->getUserVar('markRegistered'):
				$selectedIds = (array) $request->getUserVar('articleId');
				if (empty($selectedIds)) {
					$request->redirect(null, null, null, array('plugin', $this->getName(), 'articles'));
				}

				if ($request->getUserVar('export')) {
					// Export selected objects
					$result = $this->exportArticles($request, $selectedIds, $journal);
				} elseif ($request->getUserVar('markRegistered')) {
					$this->updateStatus($request, $selectedIds, $journal, DNB_STATUS_MARKEDREGISTERED);
					// Notify user with some visual feedback
					$this->_sendNotification(
						$request,
						'plugins.importexport.dnb.markedRegistered.success',
						NOTIFICATION_TYPE_SUCCESS
					);
					// Redisplay the changed object list
					$request->redirect(null, null, null, array('plugin', $this->getName(), 'articles'));
					break;
				} else {
					// Deposit selected objects
					assert($request->getUserVar('deposit') != false);
					$result = $this->depositArticles($request, $selectedIds, $journal);
					// Provide the user with some visual feedback that deposit was successful
					if ($result === true) {
						$this->_sendNotification(
							$request,
							'plugins.importexport.dnb.deposited.success',
							NOTIFICATION_TYPE_SUCCESS
						);
						// Redisplay the changed object list
						$request->redirect(null, null, null, array('plugin', $this->getName(), 'articles'));
					}
				}
				break;
		}

		// Redirect to the index page
		if ($result !== true) {
			if (is_array($result)) {
				foreach($result as $error) {
					assert(is_array($error) && count($error) >= 1);
					$this->_sendNotification(
						$request,
						$error[0],
						NOTIFICATION_TYPE_ERROR,
						(isset($error[1]) ? $error[1] : null)
					);
				}
			}
			$request->redirect(null, null, null, array('plugin', $this->getName()));
		}
	}

	/**
	 * Mark selected articles as registered.
	 * @param $request Request
	 * @param $articleIds array
	 * @param $journal Journal
	 * @param $status string DNB_STATUS_...
	 */
	function updateStatus($request, $articleIds, $journal, $status) {
		$articleDao = DAORegistry::getDAO('ArticleDAO');
		foreach((array) $articleIds as $articleId) {
			// Get article
			$article = $articleDao->getArticle($articleId, $journal->getId());
			assert($article);
			// Update article status
			$articleDao->updateSetting($articleId, $this->getStatusSettingName(), $status, 'string');
		}
	}

	/**
	 * Export articles.
	 *
	 * @param $request Request
	 * @param $selectedIds array
	 * @param $journal Journal
	 *
	 * @return boolean|array True for success or an array of error messages.
	 */
	function exportArticles($request, $selectedIds, $journal) {
		$errors = $exportFiles = array();

		// Get the journal target export directory.
		// The data will be exported in this structure:
		// dnb/<journalId>-<dateTime>/
		$result = $this->getExportPath($journal->getId());
		if (is_array($result)) return $result;
		$journalExportPath = $result;

		$fileManager = new FileManager();

		// Get the TAR packages for each article
		$result = $this->getPackages($request, $selectedIds, $journal, $journalExportPath, $exportFiles);
		// If errors occured, remove all created directories and return the errors
		if (is_array($result)) {
			$fileManager->rmtree($journalExportPath);
			return $result;
		}

		// If there is more than one export package, package them all in a single .tar.gz
		$exportFilesNames = array_keys($exportFiles);
		assert(count($exportFilesNames) >= 1);
		if (count($exportFilesNames) > 1) {
			$finalExportFileName = $journalExportPath . $this->getPluginId() . '-export.tar.gz';
			$this->tarFiles($journalExportPath, $finalExportFileName, $exportFilesNames, true);
		} else {
			$finalExportFileName = reset($exportFilesNames);
		}

		// Stream the results to the browser
		$fileManager->downloadFile($finalExportFileName);
		$fileManager->rmtree($journalExportPath);
		return true;
	}

	/**
	 * Deposit articles.
	 *
	 * @param $request Request
	 * @param $selectedIds array
	 * @param $journal Journal
	 *
	 * @return boolean|array True for success or an array of error messages.
	 */
	function depositArticles($request, $selectedIds, $journal) {
		$errors = $exportFiles = array();

		// Get this journal target export directory.
		// The data will be exported in this structure:
		// dnb/<journalId>-<dateTime>/
		$result = $this->getExportPath($journal->getId());
		if (is_array($result)) return $result;
		$journalExportPath = $result;

		$fileManager = new FileManager();

		// Get the TAR packages for each article
		$result = $this->getPackages($request, $selectedIds, $journal, $journalExportPath, $exportFiles);
		// If errors occured, remove all created directories and return the errors
		if (is_array($result)) {
			$fileManager->rmtree($journalExportPath);
			return $result;
		}

		$notFullyDeposited = array();
		// upload all files per SFTP
		foreach ($exportFiles as $exportFile => $articleId) {
			$curlCh = curl_init();
			if ($httpProxyHost = Config::getVar('proxy', 'http_host')) {
				curl_setopt($curlCh, CURLOPT_PROXY, $httpProxyHost);
				curl_setopt($curlCh, CURLOPT_PROXYPORT, Config::getVar('proxy', 'http_port', '80'));
				if ($username = Config::getVar('proxy', 'username')) {
					curl_setopt($curlCh, CURLOPT_PROXYUSERPWD, $username . ':' . Config::getVar('proxy', 'password'));
				}
			}
			curl_setopt($curlCh, CURLOPT_UPLOAD, true);
			curl_setopt($curlCh, CURLOPT_HEADER, true);
			curl_setopt($curlCh, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curlCh, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);

			assert(is_readable($exportFile));
			$fh = fopen($exportFile, 'rb');

			$username = $this->getSetting($journal->getId(), 'username');
			$password = $this->getSetting($journal->getId(), 'password');
			$folderId = $this->getSetting($journal->getId(), 'folderId');
			$folderId = ltrim($folderId, '/');
			$folderId = rtrim($folderId, '/');

			curl_setopt($curlCh, CURLOPT_URL, 'sftp://@hotfolder.dnb.de/'.$folderId.'/'.basename($exportFile));
			curl_setopt($curlCh, CURLOPT_PORT, 22122);
			curl_setopt($curlCh, CURLOPT_USERPWD, "$username:$password");
			curl_setopt($curlCh, CURLOPT_INFILESIZE, filesize($exportFile));
			curl_setopt($curlCh, CURLOPT_INFILE, $fh);

			$response = curl_exec($curlCh);
			$curlError = curl_error($curlCh);
			if ($curlError) {
				// error occured
				$param = __('plugins.importexport.dnb.deposit.error.fileUploadFailed.param', array('package' => basename($exportFile), 'error' => $curlError));
				$errors[] = array('plugins.importexport.dnb.deposit.error.fileUploadFailed', $param);
				// add article to the list of not fully deposited articles
				if (!in_array($articleId, $notFullyDeposited)) $notFullyDeposited[] = $articleId;
			}
			curl_close($curlCh);
			fclose($fh);
		}

		// update status for fully, successfully deposited articles
		$successfullyDeposited = array_diff($selectedIds, $notFullyDeposited);
		$this->updateStatus($request, $successfullyDeposited, $journal, DNB_STATUS_DEPOSITED);

		// Remove the generated directories
		$fileManager->rmtree($journalExportPath);

		if (!empty($errors)) return $errors;
		return true;
	}

	/**
	 * Generate the export data model.
	 * @param $request Request
	 * @param $galley ArticleGalley
	 * @param $article PublishedArticle
	 * @param $issue Issue
	 * @param $journal Journal
	 * @param $errors array Output parameter for error details when
	 *  the function returns false.
	 * @return string|boolean Either the generated export file path
	 *  or false if not successful.
	 */
	function generateMetadataFile($request, $galley, $article, $issue, $journal, &$errors) {
		$this->import('classes.DNBExportDom');
		$exportFile = false;
		$dom = new DNBExportDom();
		$doc = $dom->generateDom($request, $galley, $article, $issue, $journal, $this->getSetting($journal->getId(), 'archiveAccess'));
		if ($doc === false) {
			$errors = $dom->getErrors();
			return false;
		}
		return XMLCustomWriter::getXML($doc);
	}

	/**
	 * Copy galley file to the export content path, that will be packed.
	 *
	 * @param $galley ArticleGalley
	 * @param $exportPath string The final directory path, containing data to be packed
	 *
	 * @return string|array The export directory name or an array with
	 *  errors if something went wrong.
	 */
	function copyGalleyFile($galley, $exportPath) {
		$sourceGalleyFilePath = $galley->getFilePath();
		$targetGalleyFilePath = $exportPath . '/' . 'content'  . '/' . $galley->getFileName();
		if (!file_exists($sourceGalleyFilePath)) {
			$errors = array(
					array('plugins.importexport.dnb.export.error.galleyFileNotFound', $sourceGalleyFilePath)
			);
			return $errors;
		}
		$fileManager = new FileManager();
		if (!$fileManager->copyFile($sourceGalleyFilePath, $targetGalleyFilePath)) {
			$errors = array(
					array('plugins.importexport.dnb.export.error.galleyFileNoCopy')
			);
			return $errors;
		}
		return realpath($targetGalleyFilePath);
	}

	/**
	 * Generate TAR packages for each selected article.
	 *
	 * @param $request Request
	 * @param $selectedIds array
	 * @param $journal Journal
	 * @param $journalExportPath string Directory path where to put all export files
	 * @param $exportFiles array Just to return the exported TAR packages
	 *
	 * @return boolean|array True for success or an array of error messages.
	 */
	function getPackages($request, $selectedIds, $journal, $journalExportPath, &$exportFiles) {
		// The tar tool for packaging is needed. Check this
		// early on to avoid unnecessary export processing.
		$result = $this->checkForTar();
		if (is_array($result)) return $result;

		$fileManager = new FileManager();

		// Run through the selected IDs and generate the export files.
		foreach($selectedIds as $selectedId) {
			$issue = null;
			$galleys = array();
			// Retrieve the article(s).
			$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO'); /* @var $publishedArticleDao PublishedArticleDAO */
			$publishedArticle = $publishedArticleDao->getPublishedArticleByArticleId($selectedId, $journal->getid());
			if (!$publishedArticle) {
				$errors[] = array('plugins.importexport.dnb.export.error.articleNotFound', $selectedId);
			} else {
				// Get issue and galleys, and check if the article can be exported
				if (!$this->canBeExported($publishedArticle, $issue, $galleys)) {
					$errors[] = array('plugins.importexport.dnb.export.error.articleCannotBeExported', $selectedId);
				}
			}
			if (!empty($errors)) return $errors;

			foreach ($galleys as $galley) {
				// Get the final target export directory.
				// The data will be exported in this structure:
				// dnb/<journalId>-<dateTime>/<journalId>-<articleId>-<galleyId>/
				$exportContentDir = $journal->getId() . '-' . $publishedArticle->getId() . '-' . $galley->getId();
				$result = $this->getExportPath($journal->getId(), $journalExportPath, $exportContentDir);
				if (is_array($result)) return $result;
				$exportPath = $result;

				// Export the galley metadata XML.
				$metadataXML = $this->generateMetadataFile($request, $galley, $publishedArticle, $issue, $journal, $errors);
				if ($metadataXML === false) return $errors;

				// Write the metadata XML to the file.
				$metadataFile = $exportPath . 'catalogue_md.xml';
				$fileManager->writeFile($metadataFile, $metadataXML);
				$fileManager->setMode($metadataFile, FILE_MODE_MASK);

				// Copy galley file.
				$result = $this->copyGalleyFile($galley, $exportPath);
				if (is_array($result)) return $result;
				$galleyFileExportPath = $result;

				// TAR the metadata and file.
				// The package file name will be then <journalId>-<articleId>-<galleyId>.tar
				$exportPackageName = $journalExportPath . $exportContentDir . '.tar';
				$this->tarFiles($exportPath, $exportPackageName);

				// Add the new package to the result array.
				$exportFiles[$exportPackageName] = $selectedId;
			}
		}
		return true;
	}

	/**
	 * The selected article can be exported if the issue is published and
	 * article contains either a PDF or an EPUB galley.
	 * @param $article PublishedArticle
	 * @param $issue Issue Just to return the issue
	 * @param $galleys array Filtered (i.e. PDF and EPUB) article galleys
	 * @return boolean
	 */
	function canBeExported($article, &$issue = null, &$galleys = array()) {
		$issueId = $article->getIssueId();
		$issueDao = DAORegistry::getDAO('IssueDAO'); /* @var $issueDao IssueDAO */
		$issue = $issueDao->getIssueById($issueId, $article->getJournalId(), true);
		assert(is_a($issue, 'Issue'));
		if (!$issue->getPublished()) return false;
		// get all galleys
		$galleys = $article->getGalleys();
		// filter PDF and EPUB galleys -- DNB concerns only PDF and EPUB formats
		$filteredGalleys = array_filter($galleys, array($this, 'filterGalleys'));
		$galleys = $filteredGalleys;
		return (count($filteredGalleys) > 0);
	}

	/**
	 * Identify the issue of the given article.
	 * Also check if the article can be exported.
	 * @param $article PublishedArticle
	 * @return array|null
	 */
	function prepareArticleData($article) {
		$nullVar = null;
		$issue = null;
		if (!$this->canBeExported($article, $issue)) return $nullVar;
		assert(is_a($issue, 'Issue'));
		return array(
			'article' => $article,
			'issue' => $issue
		);
	}

	/**
	 * Check if a galley is a PDF or an EPUB file.
	 * @param $galley ArticleGalley
	 * @return boolean
	 */
	function filterGalleys($galley) {
		return $galley->isPdfGalley() ||  $galley->getFileType() == 'application/epub+zip';
	}

	/**
	 * Get status setting name.
	 * @return string
	 */
	function getStatusSettingName() {
		return $this->getPluginId() . '::' . DNB_STATUS;
	}

	/**
	 * Get status mapping for the status display.
	 * @return array (internal status => string text to be displayed)
	 */
	function getStatusMapping() {
		return array(
			DNB_STATUS_DEPOSITED => __('plugins.importexport.dnb.status.deposited'),
			DNB_STATUS_MARKEDREGISTERED => __('plugins.importexport.dnb.status.markedRegistered')
		);
	}

	/**
	 * Return the plugins export directory.
	 * The data will be exported in this structure:
	 * dnb/<journalId>-<dateTime>/<articleId>-<galleyId>/
	 *
	 * This will create the directory if it doesn't exist yet.
	 *
	 * @param $journalId integer
	 * @param $currentExportPath string (optional) The base path, the content directory should be added to
	 * @param $exportContentDir string (optional) The final directory containing data to be packed
	 *
	 * @return string|array The export directory name or an array with
	 *  errors if something went wrong.
	 */
	function getExportPath($journalId, $currentExportPath = null, $exportContentDir = null) {
		if (!$currentExportPath) {
			$exportPath = Config::getVar('files', 'files_dir') . '/' . $this->getPluginId() . '/' . $journalId . '-' . date('Ymd-His');
		} else {
			$exportPath = $currentExportPath;
		}
		if ($exportContentDir) $exportPath .= '/' .$exportContentDir;
		if (!file_exists($exportPath)) {
			$fileManager = new FileManager();
			$fileManager->mkdirtree($exportPath);
		}
		if (!is_writable($exportPath)) {
			$errors = array(
				array('plugins.importexport.dnb.export.error.outputFileNotWritable', $exportPath)
			);
			return $errors;
		}
		return realpath($exportPath) . '/';
	}

	/**
	 * Test whether the tar binary is available.
	 * @return boolean|array True if available otherwise
	 *  an array with an error message.
	 */
	function checkForTar() {
		$tarBinary = Config::getVar('cli', 'tar');
		if (empty($tarBinary) || !is_executable($tarBinary)) {
			$result = array(
				array('manager.plugins.tarCommandNotFound')
			);
		} else {
			$result = true;
		}
		$this->_checkedForTar = true;
		return $result;
	}

	/**
	 * Create a tar archive.
	 * @param $targetPath string
	 * @param $targetFile string
	 * @param $sourceFiles array (optional) If null,
	 *  everything in the targeth path is considered (*).
	 * @param $gzip boolean (optional) If TAR file should be gzipped
	 */
	function tarFiles($targetPath, $targetFile, $sourceFiles = null, $gzip = false) {
		assert($this->_checkedForTar);

		$tarCommand = '';
		// Change directory to the target path, to be able to use
		// relative paths i.e. only file names and *
		$tarCommand .= 'cd ' . escapeshellarg($targetPath) . ' && ';

		// Should the result file be GZip compressed.
		$tarOptions = $gzip ? ' -czf ' : ' -cf ';
		// Construct the tar command: path to tar, options, target archive file name
		$tarCommand .= Config::getVar('cli', 'tar') . $tarOptions . escapeshellarg($targetFile);

		// Do not reveal our webserver user by forcing root as owner.
		$tarCommand .= ' --owner 0 --group 0 --';

		if (!$sourceFiles) {
			// Add everything
			$tarCommand .= ' *';
		} else {
			// Add each file individually so that other files in the directory
			// will not be included.
			foreach($sourceFiles as $articleId => $sourceFile) {
				assert(dirname($sourceFile) . '/' === $targetPath);
				if (dirname($sourceFile) . '/' !== $targetPath) continue;
				$tarCommand .= ' ' . escapeshellarg(basename($sourceFile));
			}
		}

		// Execute the command.
		exec($tarCommand);
	}

	/**
	 * Check if plugin settings are missing.
	 * @param $journal Journal
	 * @return boolean
	 */
	function checkPluginSettings($journal) {
		$oaJournal =  $journal->getSetting('publishingMode') == PUBLISHING_MODE_OPEN &&
			$journal->getSetting('restrictSiteAccess') != 1 &&
			$journal->getSetting('restrictArticleAccess') != 1;
		// if journal is not open access, the archive access setting has to be set
		return $oaJournal || $this->getSetting($journal->getId(), 'archiveAccess');
	}

	/**
	 * @copydoc AcronPlugin::parseCronTab()
	 */
	function callbackParseCronTab($hookName, $args) {
		$taskFilesPath =& $args[0];
		$taskFilesPath[] = $this->getPluginPath() . DIRECTORY_SEPARATOR . 'scheduledTasks.xml';
		return false;
	}

	/**
	 * Retrieve all undeposited articles.
	 * @param $journal Journal
	 * @return array
	 */
	function &_getNotDepositedArticles(&$journal) {
		// Retrieve all published articles that have not been deposited yet.
		$publishedArticleDao =& DAORegistry::getDAO('PublishedArticleDAO'); /* @var $publishedArticleDao PublishedArticleDAO */
		$articles = $publishedArticleDao->getBySetting($this->getStatusSettingName(), null, $journal->getId());
		return $articles;
	}

	/**
	 * Add a notification.
	 * @param $request Request
	 * @param $message string An i18n key.
	 * @param $notificationType integer One of the NOTIFICATION_TYPE_* constants.
	 * @param $param string An additional parameter for the message.
	 */
	function _sendNotification($request, $message, $notificationType, $param = null) {
		static $notificationManager = null;

		if (is_null($notificationManager)) {
			import('classes.notification.NotificationManager');
			$notificationManager = new NotificationManager();
		}

		if (!is_null($param)) {
			$params = array('param' => $param);
		} else {
			$params = null;
		}

		$user = $request->getUser();
		$notificationManager->createTrivialNotification(
			$user->getId(),
			$notificationType,
			array('contents' => __($message, $params))
		);
	}

}

?>