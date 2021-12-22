<?php

/**
 * @file plugins/importexport/dnb/DNBExportPlugin.inc.php
 *
 * Copyright (c) 2021 Center for Digital Systems (CeDiS), Universitätsbibliothek Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan, Ronald Steffen
 *
 * @class DNBExportPlugin
 * @ingroup plugins_importexport_dnb
 *
 * @brief DNB export plugin
 */

import('classes.plugins.PubObjectsExportPlugin');

use APP\components\forms\FieldSelectIssues;

define('DEBUG', false);

define('DNB_STATUS_DEPOSITED', 'deposited');
define('ADDITIONAL_PACKAGE_OPTIONS','--format=gnu');//use --format=gnu with tar to avoid PAX-Headers

define('REMOTE_IP_NOT_ALLOWED_EXCEPTION', 103);

if (!DEBUG) {
	define('SFTP_SERVER','sftp://@hotfolder.dnb.de/');
	define('SFTP_PORT', 22122);
} else {
	define('SFTP_SERVER','sftp://ojs@sftp/');
	define('SFTP_PORT', 22);
}

class DNBExportPlugin extends PubObjectsExportPlugin {

	private $_settingsForm;

	private $_settingsFormURL;

	private $_currentContextId; // We need to set this for runnning deposition from the command line via scheduledTasks. No request object is available to query the context when running from CLI.

	function __construct() {
		$context = Application::get()->getRequest()->getContext();
		if (isset($context))
		{
			$this->setContextId($context->getId());
		}
	}
	
	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		if (!parent::register($category, $path, $mainContextId)) return false;

		AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER);
		$this->addLocaleData();

		HookRegistry::register('Schema::get::submission', array($this, 'addToSchema'));
		HookRegistry::register('Submission::getBackendListProperties::properties', array($this, 'addBackendProperties'));

		return true;
	}	

	public function addToSchema($hookName, $params) {
		if ($hookName == 'Schema::get::submission') {
			$schema =& $params[0];
			$schema->properties->{$this->getPluginSettingsPrefix().'::status'} = (object) [
				'type' => 'string',
				'apiSummary' => true,
				'validation' => ['nullable'],
			];
		}
		return false;
	}

	function addBackendProperties($hookName, $params) {
		switch ($hookName){
			case "Submission::getBackendListProperties::properties":
				$props = &$params[0];
				$props = array_merge($props, [$this->getPluginSettingsPrefix().'::status']);
				return true;
				break;
		};
	}

	/**
	 * @copydoc Plugin::getName()
	 */
	function setSettingsForm($form) {
		$this->_settingsForm = $form;
	}

	/**
	 * @copydoc Plugin::getName()
	 */
	function getSettingsFormActionURL() {
		return $this->_settingsFormURL;
	}

	/**
	 * @copydoc Plugin::getName()
	 */
	function getName() {
		return 'DNBExportPlugin';
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.importexport.dnb.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.importexport.dnb.description');
	}

	/**
	 * @copydoc setContextId()
	 */
	function setContextId($Id) {
		$this->_currentContextId = $Id;
	}

	/**
	 * @copydoc ImportExportPlugin::display()
	 */
	function display($args, $request) {
		$context = $request->getContext();
		
		if (!empty($args)) {
			if ((($args[0] == 'exportSubmissions') ||
				($args[0] == EXPORT_ACTION_EXPORT) || 
				($args[0] == EXPORT_ACTION_DEPOSIT) || 
				($args[0] == EXPORT_ACTION_MARKREGISTERED)) &&
				empty((array) $request->getUserVar('selectedSubmissions'))) {
				
				//show error
				$this->errorNotification($request, array(array('plugins.importexport.dnb.deposit.error.noObjectsSelected')));
				// redirect back to exportSubmissions-tab
				$path = array('plugin', $this->getName());
				$request->redirect(null, null, null, $path, null, 'exportSubmissions-tab');
				return;
			}

			// redirect export actions
			// this is a work around due to combining old PubObjectsExportPlugin with the new form components
			// the vue-page submit action for the form doesn't take parameters and PubObjectsExportPlugin cannot handle our buttons
			// we therefore change the formsubmit url dynamically (see index.tpl) to provide the below parameters
			switch ($args[0]) {
				case EXPORT_ACTION_EXPORT:
					$this->_exportAction = EXPORT_ACTION_EXPORT;
					break;
				case EXPORT_ACTION_DEPOSIT:
					$this->_exportAction = EXPORT_ACTION_DEPOSIT;
					break;
				case EXPORT_ACTION_MARKREGISTERED:
					$this->_exportAction = EXPORT_ACTION_MARKREGISTERED;
					$request->_requestVars[EXPORT_ACTION_MARKREGISTERED] = true;
					break;
			}
			$args[0] = 'exportSubmissions';
		}

		// settings form action url (needs to be set before parent::display initiliazes the settingd form)
		$this->import("classes.form.DNBSettingsForm");
		$this->_settingsFormURL = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'grid.settings.plugins.settingsPluginGridHandler',
			'manage',
			null,
			array(
				'plugin' => $this->getName(),
				'category' => 'importexport',
				'verb' => 'save')
		);

		parent::display($args, $request);
		
		switch (array_shift($args)) {
			case 'index':
			case '':			
				// settings form
				$settingsFormConfig = $this->_settingsForm->getConfig();
				$settingsFormConfig['fields'][1]['inputType'] = "password";
				
				// rediret API url to our handler
				$apiUrl = $request->getDispatcher()->url(
					$request,
					ROUTE_COMPONENT,
					null,
					'grid.settings.plugins.settingsPluginGridHandler',
					'manage',
					null,
					array(
						'plugin' => $this->getName(),
						'category' => 'importexport',
						'verb' => 'get')
				);

				// instantinate SubmissionListPanel
				$submissionsListPanel = new \APP\components\listPanels\SubmissionsListPanel(
					'submissions',
					__('common.publications'),
					[
						'apiUrl' => $apiUrl,
						'count' => 100,
						'getParams' => [
							'contextId' => $context->getId(),
							'status' => STATUS_PUBLISHED
						],
						'lazyLoad' => true,
					]
				);

				// we can not hook into the API call because we can not register when API is called from SubmissionListPanel
				// => prepare additonal data and pass it as state
				$publishedSubmissions = Services::get('submission')->getMany([
					'contextId' => $context->getId(),
					'status' => STATUS_PUBLISHED
				]);
				$dnbStatus = [];
				$nNotRegistered = 0;
				foreach ($publishedSubmissions as $submission) {
					$status = $submission->getData($this->getPluginSettingsPrefix().'::status');
					$issue = Services::get('issue')->get($submission->getCurrentPublication()->getData('issueId'));
					
					$galleys = $submission->getGalleys();

					$documentGalleys = $supplementaryGalleys = [];
					try {
						$this->canBeExported($submission, $issue, $documentGalleys, $supplementaryGalleys); 
					} catch (ErrorException $e) {
						// currently this only throws REMOTE_IP_NOT_ALLOWED_EXCEPTION
						// we handle this during export
					}
					$msg = "";
					if ($submission->getData('supplementaryNotAssignable')) {
						$plural = AppLocale::getLocale() == "de_DE" ? "n" : "s";
						$msg = __('plugins.importexport.dnb.warning.supplementaryNotAssignable',
							array(
								'nDoc' => count($documentGalleys),
								'nSupp' => count($supplementaryGalleys),
								'pSupp' => count($supplementaryGalleys) > 1 ? $plural : ""
							));
					}

					$dnbStatus[(int)$submission->getId()] = [
						'status' => $this->getStatusNames()[$status],
						'statusConst' => empty($status)?EXPORT_STATUS_NOT_DEPOSITED:$status,
						'issueTitle' => $issue->getLocalizedTitle(),
						'publishedUrl' => Services::get('issue')->getProperties($issue,['publishedUrl'],['request' => $request])['publishedUrl'],
						'supplementariesNotAssignable' => $msg
					];
					if (empty($status)) $nNotRegistered++;
				}

				// configure filter settings
				$submissionsConfig = $submissionsListPanel->getConfig();
				$submissionsConfig['addUrl'] = '';
				$submissionsConfig['filters'] = [array_pop($submissionsConfig['filters'])];
				// add issue filter
				$issueAutosuggestField = new FieldSelectIssues('issueIds', [
					'value' => [],
					'apiUrl' => $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'issues'),
				]);
				$issueFilter =
					[
						'heading' => __('metadata.property.displayName.issue'),
						'filters' => [
							[
								'title' => __('issue.issues'),
								'param' => 'issueIds',
								'value' => [],
								'filterType' => 'pkp-filter-autosuggest',
								'component' => 'field-select-issues',
								'autosuggestProps' => $issueAutosuggestField->getConfig(),
							]
						],
					];
				array_unshift($submissionsConfig['filters'], $issueFilter);
				// add status filter
				$statusFilter =
					[
						'heading' => 'Status',
						'filters' => [
							[
								'param' => $this->getPluginSettingsPrefix().'::status',
								'value' => EXPORT_STATUS_NOT_DEPOSITED,
								'title' => __('plugins.importexport.dnb.status.notDeposited')
							],
							[
								'param' => $this->getPluginSettingsPrefix().'::status',
								'value' => DNB_STATUS_DEPOSITED,
								'title' => __('plugins.importexport.dnb.status.deposited')
							],
							[
								'param' => $this->getPluginSettingsPrefix().'::status',
								'value' => EXPORT_STATUS_MARKEDREGISTERED,
								'title' => __('plugins.importexport.common.status.markedRegistered')
							],
						],
					];
				array_unshift($submissionsConfig['filters'], $statusFilter);

				// set properties
				$templateMgr = TemplateManager::getManager($request);

				$checkForTarResult = $this->checkForTar();
				$templateMgr->assign('checkFilter', !is_array($this->checkForExportFilter()));
				$templateMgr->assign('checkTar', !is_array($checkForTarResult));
				$templateMgr->assign('checkSettings', $this->checkPluginSettings($context));
				$templateMgr->assign([
					'pageComponent' => 'ImportExportPage',
					'baseurl' => $request->getBaseUrl(),
					'debugModeWarning' => __("plugins.importexport.dnb.settings.debugModeActive.contents", ['server' => SFTP_SERVER, 'port' => SFTP_PORT]),
					'nNotRegistered' => $nNotRegistered,
					'remoteEnabled' => $this->getSetting($context->getId(), 'exportRemoteGalleys') ? "Remote On" : "",
					'suppDisabled' => $this->getSetting($context->getId(), 'submitSupplementaryMode') === "none" ? "Supplementary Off" : ""
				]);

				$templateMgr->setConstants([
					'FORM_DNB_SETTINGS',
				]);

				$state = [
					'components' => [
						FORM_DNB_SETTINGS => $settingsFormConfig,
						'submissions' => array_merge(
								$submissionsConfig,
								['dnbStatus' => $dnbStatus])
					],
				];		
				$templateMgr->setState($state);

				$templateMgr->addStyleSheet(
					'dnbexportplugin',
					$request->getBaseUrl() . '/' . $this->getStyleSheet(),
					array(
						'contexts' =>  ['backend']
					)
				);

				$templateMgr->addJavaScript(
					'dnbexportplugin',
					$request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getPluginPath() . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'DNBSettingsFormHandler.js',
					array(
						'inline' => false,
						'contexts' => ['backend'],
					)
				);

				$helpUrl = $request->getDispatcher()->url(
					$request,
					ROUTE_COMPONENT,
					null,
					'grid.settings.plugins.settingsPluginGridHandler',
					'manage',
					null,
					array(
						'plugin' => $this->getName(),
						'category' => 'importexport',
						'verb' => 'help')
				);

				$script_data = [
					'helpUrl' => $helpUrl
				];

				// proivde url for help pages
				$templateMgr->addJavaScript(
					'dnbexportpluginData',
					'$.pkp.plugins.importexport = $.pkp.plugins.importexport || {};' .
						'$.pkp.plugins.importexport.' . strtolower(get_class($this)) . ' = ' . json_encode($script_data) . ';',
					[
						'inline' => true,
						'contexts' => 'backend',
					]
				);

				// show logs for automatic deposit
				if ($this->getSetting($this->_currentContextId, 'automaticDeposit')) {
					$logFiles = Services::get('file')->fs->listContents(SCHEDULED_TASK_EXECUTION_LOG_DIR);
					// filter dnb plugin log files
					$logFiles = array_filter($logFiles, function($f) {
						return str_contains($f['filename'],"DNBautomaticdeposittask");
					});
					// get latest log file
					usort($logFiles, function ($f1, $f2) {
						return $f1['timestamp'] < $f2['timestamp'];
					});
					if (count($logFiles) > 0) {
						// filter context specific messages
						$latestLogFile = Services::get('file')->fs->read($logFiles[0]['path']);
						$latestLogFile = preg_split("/\r\n|\n|\r/", $latestLogFile, NULL, PREG_SPLIT_NO_EMPTY);
						$lastIndex = count($latestLogFile) - 1;
						$latestLogFile = array_filter($latestLogFile, function ($line, $index) use ($context, $lastIndex) {
							return (bool) preg_match('#\['.$context->getData('urlPath').'\]#', $line) || ($index == 1) || ($index == $lastIndex);
						},
						ARRAY_FILTER_USE_BOTH);
					}
					$templateMgr->assign('latestLogFile', $latestLogFile);
				}

				$templateMgr->display($this->getTemplateResource('index.tpl'));
				break;
		}
	}

	function manage($args, $request) {
		$verbs = explode('/', $args['verb']);
		switch (array_shift($verbs)) {
			case 'help':
				array_shift($verbs);
				$this->fetchHelpMessage($verbs, $request);
				break;
			case 'get':
				$context = $request->getContext();
				
				$submissionsIterator = \Services::get('submission')->getMany([
					'contextId' => $context->getId(),
					'status' => STATUS_PUBLISHED,
					'searchPhrase' => $args['searchPhrase'],
					'sectionIds' => isset($args['sectionIds'])?$args['sectionIds']:NULL,
					'issueIds' => isset($args['issueIds'])?$args['issueIds']:NULL
				]);

				$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
				$items = [];
				foreach ($submissionsIterator as $submission) {
					// we need this to get the AuthorsString
					$items[] = \Services::get('submission')->getBackendListProperties($submission, [
						'request' => $request,
						'userGroups' => $userGroupDao->getByContextId($context->getId())->toArray()
					]);
				}
				
				// filter items by dnb status
				$items = array_filter($items, function ($item) use ($args) {
					if (isset($args[$this->getPluginSettingsPrefix().'::status'])) {
						$result = false;
						foreach ($args[$this->getPluginSettingsPrefix().'::status'] as $status) {
							if ($item[$this->getPluginSettingsPrefix().'::status'] == NULL && $status == EXPORT_STATUS_NOT_DEPOSITED) $result = $result || true;
							$result = $result || $item[$this->getPluginSettingsPrefix().'::status'] == $status;
						}
						return $result;
					}
					return true;
				});

				$data['itemsMax'] = count($items);
				$data['items'] = array_values($items);

				import('lib.pkp.classes.core.APIResponse');
				$response = new APIResponse();

				$app = new \Slim\App();
				$app->respond($response->withStatus(200)->withJson($data));
				exit();
				break;
			default:
				parent::manage($args, $request);
		}
	}

	function fetchHelpMessage($args, $request) {

		// this code ist largely copied from HelpHandler.inc.php
		$path = $this->getHelpPath();
		$args[0] = substr($args[0], 0, 2);
		$args[1] = $args[1] ? $args[1] : "SUMMARY";
		$urlPart = join('/', $args);
		$filename = $urlPart . '.md';

		$language = AppLocale::getIso1FromLocale(AppLocale::getLocale());
		$summaryFile = $path . $language . '/SUMMARY.md';

		// Use the summary document to find next/previous links.
		// (Yes, we're grepping markdown outside the parser, but this is much faster.)
		$previousLink = $nextLink = null;
		if (preg_match_all('/\(([^)]+)\)/sm', file_get_contents($summaryFile), $matches)) {
			$matches = $matches[1];
			if (($i = array_search(substr($urlPart, strpos($urlPart, '/')+1), $matches)) !== false) {
				if ($i>0) $previousLink = $matches[$i-1];
				if ($i<count($matches)-1) $nextLink = $matches[$i+1];
			}
		}

		// Use a URL filter to prepend the current path to relative URLs.
		$parser = new \Michelf\MarkdownExtra;
		$parser->url_filter_func = function ($url) use ($filename) {
			return (empty(parse_url($url)['host']) ? dirname($filename) . '/' : '') . $url;
		};
		$msg = new JSONMessage(
			true,
			array(
				'content' => $parser->transform(file_get_contents($path . $filename)),
				'previous' => $previousLink,
				'next' => $nextLink,
			)
		);

		header('Content-Type: application/json');
		echo $msg->getString();
		exit();
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getStatusNames()
	 */
	function getStatusNames() {
		return array(
			EXPORT_STATUS_ANY => __('plugins.importexport.dnb.status.notDeposited'),
			EXPORT_STATUS_NOT_DEPOSITED => __('plugins.importexport.dnb.status.notDeposited'),
			DNB_STATUS_DEPOSITED => __('plugins.importexport.dnb.status.deposited'),
			EXPORT_STATUS_MARKEDREGISTERED => __('plugins.importexport.common.status.markedRegistered'),
		);
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getExportActionNames()
	 */
	function getExportActionNames() {
		return array_merge(parent::getExportActionNames(), array(
			EXPORT_ACTION_DEPOSIT => __('plugins.importexport.dnb.deposit'),
		));
	}

	/**
	 * @copydoc ImportExportPlugin::getPluginSettingsPrefix()
	 */
	function getPluginSettingsPrefix() {
		return 'dnb';
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getSubmissionFilter()
	 */
	function getSubmissionFilter() {
		return 'galley=>dnb-xml';
	}

	/**
	 * Return the location of the plugin's CSS file
	 *
	 * @return string
	 */
	function getStyleSheet() {
		return $this->getPluginPath() . '/css/dnbplugin.css';
	}

	/**
	 * Return the location of the help files
	 *
	 * @return string
	 */
	function getHelpPath() {
		return $this->getPluginPath() . '/docs/manual/';
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getExportActions()
	 */
	function getExportActions($context) {
		return array(EXPORT_ACTION_DEPOSIT, EXPORT_ACTION_EXPORT, EXPORT_ACTION_MARKREGISTERED);
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getExportDeploymentClassName()
	 */
	function getExportDeploymentClassName() {
		return 'DNBExportDeployment';
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getSettingsFormClassName()
	 */
	function getSettingsFormClassName() {
		return 'DNBSettingsForm';
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getDepositSuccessNotificationMessageKey()
	 */
	function getDepositSuccessNotificationMessageKey() {
		return 'plugins.importexport.dnb.deposited.success';
	}

	/**
	 * @copydoc PubObjectsExportPlugin::depositXML()
	 */
	function depositXML($object, $context, $filename) {   
		$errors = [];
		$filename = Config::getVar('files', 'files_dir') . '/' .  $filename;

		if (!($this->getSetting($context->getId(), 'username') &&
			$this->getSetting($context->getId(), 'password') &&
			$this->getSetting($context->getId(), 'folderId'))) {
			$errors[] = array('plugins.importexport.dnb.deposit.error.hotfolderCredentialsMissing');
				return $errors;
		}

		if (!file_exists($filename)) {
			$param = __('plugins.importexport.dnb.deposit.error.fileUploadFailed.FileNotFound.param', array('package' => basename($filename), 'articleId' => $object->getFile()->getData('submissionId')));
			$errors[] = array('plugins.importexport.dnb.deposit.error.fileUploadFailed', $param);
			return $errors;
		}

		$curlCh = curl_init();
		if ($httpProxyHost = Config::getVar('proxy', 'http_host')) {
			curl_setopt($curlCh, CURLOPT_PROXY, $httpProxyHost);
			curl_setopt($curlCh, CURLOPT_HTTPPROXYTUNNEL, 1);
			curl_setopt($curlCh, CURLOPT_PROXYPORT, Config::getVar('proxy', 'http_port', '80'));
			if ($username = Config::getVar('proxy', 'username')) {
				curl_setopt($curlCh, CURLOPT_PROXYUSERPWD, $username . ':' . Config::getVar('proxy', 'password'));
			}
		}
		curl_setopt($curlCh, CURLOPT_UPLOAD, true);
		curl_setopt($curlCh, CURLOPT_HEADER, true);
		curl_setopt($curlCh, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlCh, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);

		$username = $this->getSetting($context->getId(), 'username');
		$password = $this->getSetting($context->getId(), 'password');
		$folderId = $this->getSetting($context->getId(), 'folderId');
		$folderId = ltrim($folderId, '/');
		$folderId = rtrim($folderId, '/');

		assert(is_readable($filename));
		$fh = fopen($filename, 'rb');

		curl_setopt($curlCh, CURLOPT_URL, SFTP_SERVER.$folderId.'/'.basename($filename));
		curl_setopt($curlCh, CURLOPT_PORT, SFTP_PORT);
		curl_setopt($curlCh, CURLOPT_USERPWD, "$username:$password");
		curl_setopt($curlCh, CURLOPT_INFILESIZE, filesize($filename)); // Todo @RS Config::getVar('files', 'files_dir') . '/' .
		curl_setopt($curlCh, CURLOPT_INFILE, $fh);

		$response = curl_exec($curlCh);
		
		$curlError = curl_error($curlCh);
		
		if ($curlError) {
			// error occured
			$param = __('plugins.importexport.dnb.deposit.error.fileUploadFailed.param', array('package' => basename($filename), 'articleId' => $object->getFile()->getData('submissionId'), 'error' => $curlError));
			$errors[] = array('plugins.importexport.dnb.deposit.error.fileUploadFailed', $param);
		}
		curl_close($curlCh);

		if (!empty($errors)) return $errors;
		return true;
	}

	/**
	 * @copydoc PubObjectsExportPlugin::executeExportAction()
	 */
	function executeExportAction($request, $submissions, $filter, $tab, $submissionsFileNamePart, $noValidation = null) {

		$journal = $request->getContext();
		$path = array('plugin', $this->getName());
		
		if ($this->_exportAction == EXPORT_ACTION_EXPORT ||
			$this->_exportAction == EXPORT_ACTION_DEPOSIT) {

			assert($filter != null);

			// The tar tool for packaging is needed. Check this
			// early on to avoid unnecessary export processing.
			$result = $this->checkForTar();
			if (is_array($result)) {
				$this->errorNotification($request, $result);
				// redirect back to the right tab
				$request->redirect(null, null, null, $path, null, $tab);
			}

			// Get the journal target export directory.
			// The data will be exported in this structure:
			// dnb/<journalId>-<dateTime>/
			$result = $this->getExportPath($journal->getId());
			if (is_array($result)) {
				$this->errorNotification($request, $result);
				// redirect back to the right tab
				$request->redirect(null, null, null, $path, null, $tab);
			}
			$journalExportPath = $result;

			$errors = $exportFilesNames = [];
			$submissionDao = DAORegistry::getDAO('SubmissionDAO');
			$genreDao = DAORegistry::getDAO('GenreDAO');

			// For each selected article
			foreach ($submissions as $submission) {
				$issue = null;
				$galleys = [];
				$supplementaryGalleys = [];
				// Get issue and galleys, and check if the article can be exported.
				// canBeExported(...) returns galleys and supplementary galleys seperately
				try {
					if (!$this->canBeExported($submission, $issue, $galleys, $supplementaryGalleys)) {
					$errors[] = array('plugins.importexport.dnb.export.error.articleCannotBeExported', $submission->getId());
					// continue with other articles
					continue;
					}
				} catch (ErrorException $e) {
					// convert ErrorException to error messages that will be shown to the user
					$result = $this->hanndleExceptions($e);
					$errors = array_merge($errors, [$result]);
				}
				
				$fullyDeposited = true;

				foreach ($galleys as $galley) {

					// store submission Id in galley object for internal use
					$galley->setData('submissionId', $submission->getId());

					// check if it is a full text
					$galleyFile = $galley->getFile();

					// if $galleyFile is not set it might be a remote URL
					if (!isset($galleyFile)) {
						if ($galley->getRemoteURL() == null) continue;
					} else {
						$exportPath = $journalExportPath;
					}
					
					$exportFile = '';
					// Get the TAR package for the galley
					$result = $this->getGalleyPackage($galley, $supplementaryGalleys, $filter, $noValidation, $journal, $exportPath, $exportFile, $submission->getData('id'));
					
					// If errors occured, remove all created directories and return the errors
					if (is_array($result)) {
					    // If error occured add it to the list of errors
					    $errors[] = $result;
					    $fullyDeposited = false;
					}
					if ($this->_exportAction == EXPORT_ACTION_EXPORT) {
						// Add the galley package to the list of all exported files
						$exportFilesNames[] = $exportFile;
					} elseif ($this->_exportAction == EXPORT_ACTION_DEPOSIT) {
						// Deposit the galley
						$result = $this->depositXML($galley, $journal, $exportFile);
						if (is_array($result)) {
							// If error occured add it to the list of errors
							$errors = array_merge($errors, $result);
							$fullyDeposited = false;
						}
					}
				}
				
				if ($fullyDeposited && $this->_exportAction == EXPORT_ACTION_DEPOSIT) {
					// Update article status
					$submission->setData($this->getDepositStatusSettingName(), DNB_STATUS_DEPOSITED);
					$submissionDao->updateObject($submission);
				}
			}
			
			if ( $this->_exportAction == EXPORT_ACTION_EXPORT) {
			    if (!empty($errors)) {
			        // If there were some deposit errors, display them to the user
			        $this->errorNotification($request, $errors);	
			    } else {
    				// If there is more than one export package, package them all in a single .tar.gz
    			    assert(count($exportFilesNames) >= 1);
    				if (count($exportFilesNames) > 1) {
    					$finalExportFileName = $journalExportPath . $this->getPluginSettingsPrefix() . '-export.tar.gz';
    					$this->tarFiles($journalExportPath, $finalExportFileName, $exportFilesNames, true);
    				} else {
    					$finalExportFileName = reset($exportFilesNames);
    				}
       				// Stream the results to the browser
					// Starting from OJS 3.3 this would be the prefered way to stream a file for download:
					// 	Services::get('file')->download($finalExportFileName, basename($finalExportFileName));
					// However, this function exits execution after dowload not allowing for clean up of the intermediate zip file
					// We therfore copied the appropriate functions from OJS 3.2 FileManager
					// It was suggested (Alec) to use OJS-queues for clean up which are supposed to come with OJS 3.4 
					$finalExportFileName = Config::getVar('files', 'files_dir') . '/' . $finalExportFileName;
					$this->downloadByPath($finalExportFileName, null, false, basename($finalExportFileName));
				}
			    // Remove the generated directories
				Services::get('file')->fs->deleteDir($journalExportPath);
			    // redirect back to the right tab
				// redirect causes a PHP Warning because headers were already sent by above downloadByPath call
				// we disable warning before redirect not to spam error log
				error_reporting(~E_WARNING);
				$request->redirect(null, null, null, $path, null, $tab);
			} elseif ($this->_exportAction == EXPORT_ACTION_DEPOSIT) {
				if (!empty($errors)) {
					// If there were some deposit errors, display them to the user
					$this->errorNotification($request, $errors);
				} else {
					// Provide the user with some visual feedback that deposit was successful
					$this->_sendNotification(
						$request->getUser(),
						$this->getDepositSuccessNotificationMessageKey(),
						NOTIFICATION_TYPE_SUCCESS
					);
				}
				// Remove the generated directories
				Services::get('file')->fs->deleteDir($journalExportPath);
				// redirect back to the right tab
				$request->redirect(null, null, null, $path, null, $tab);
			}
		} else {
			return parent::executeExportAction($request, $submissions, $filter, $tab, $submissionsFileNamePart, $noValidation);
		}
	}

	/**
	 * Generate TAR package for a galley.
	 *
	 * @param $galley ArticleGalley
	 * @param $filter string Filter to use
	 * @param $noValidation boolean If set to true no XML validation will be done
	 * @param $journal Journal
	 * @param $journalExportPath string Directory path where to put all export files
	 * @param $exportPackageName string Just to return the exported TAR package
	 *
	 * @return boolean|array True for success or an array of error messages.
	 */
	function getGalleyPackage($galley, $supplementaryGalleys, $filter, $noValidation, $journal, $journalExportPath, &$exportPackageName, $submissionId) {
		// Get the final target export directory.
		// The data will be exported in this structure:
		// dnb/<journalId>-<dateTime>/<journalId>-<submissionId>-<galleyId>/
		
		$exportContentDir = $journal->getId() . '-' . $submissionId . '-' . ($galley->getId());
	
		$result = $this->getExportPath($journal->getId(), $journalExportPath, $exportContentDir);
		if (is_array($result)) return $result;
		$exportPath = $result;
		
		try {		
			// Copy galley files
			$result = $this->copyGalleyFile($galley, $exportPath . 'content/');
			if (is_array($result)) return $result;

			// add supplementary files
			foreach ($supplementaryGalleys as $supplementaryGalley) {
				// Copy supplementary alley files
				$result = $this->copyGalleyFile($supplementaryGalley, $exportPath . 'content/supplementary/');
				if (is_array($result)) return $result;
			}

			// Export the galley metadata XML.
			$metadataXML = $this->exportXML($galley, $filter, $journal, $noValidation);
		} catch (ErrorException $e) {
            // we don't remove these automatically because user has to be aware of these issues
		    return $this->hanndleExceptions($e);
		}

		// Write the metadata XML to the file.
		$metadataFile = $exportPath . 'catalogue_md.xml';
		$res = Services::get('file')->fs->write($metadataFile, $metadataXML);
		
		// TAR the metadata and files.
		// tar supplementary files
		if (Services::get('file')->fs->has($exportPath . 'content/supplementary')) {
			$this->tarFiles($exportPath . '/content/supplementary', $exportPath . '/content/supplementary.tar');
			Services::get('file')->fs->deleteDir($exportPath . 'content/supplementary');
		}
		// The package file name will be then <journalId>-<articleId>-<galleyId>.tar
		$exportPackageName = $journalExportPath . $exportContentDir . '.tar';
		$this->tarFiles($exportPath, $exportPackageName);

		return true;
	}

	function hanndleExceptions($e) {
		switch ($e->getCode()) {
			case XML_NON_VALID_CHARCTERS_EXCEPTION:
				$param = __('plugins.importexport.dnb.export.error.articleMetadataInvalidCharacters.param', array('submissionId' => $submissionId, 'node' => $e->getMessage()));		       
				return array('plugins.importexport.dnb.export.error.articleMetadataInvalidCharacters', $param);
			case URN_SET_EXCEPTION:
				return array('plugins.importexport.dnb.export.error.urnSet');
			case FIRST_AUTHOR_NOT_REGISTERED_EXCEPTION:
				$param = __('plugins.importexport.dnb.export.error.firestAuthorNotRegistred.param', array('submissionId' => $submissionId, 'msg' => $e->getMessage()));		       
				return array('plugins.importexport.dnb.export.error.firestAuthorNotRegistred', $param);
			case REMOTE_IP_NOT_ALLOWED_EXCEPTION:
				return array('plugins.importexport.dnb.export.error.exception', $e->getMessage());
		}
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
	    //galley->getFile() can only be null for remote galleys
		$galleyFile = $galley->getFile();
	    if ($galleyFile == null) {
	        if ($this->exportRemote()) {
	            
    	        // its a remote URL and export of remote URLs is enabled, curl it
    	        $curlCh = curl_init();
    	        
    	        if ($httpProxyHost = Config::getVar('proxy', 'http_host')) {
    	            curl_setopt($curlCh, CURLOPT_PROXY, $httpProxyHost);
    	            curl_setopt($curlCh, CURLOPT_PROXYPORT, Config::getVar('proxy', 'http_port', '80'));
    	            if ($username = Config::getVar('proxy', 'username')) {
    	                curl_setopt($curlCh, CURLOPT_PROXYUSERPWD, $username . ':' . Config::getVar('proxy', 'password'));
    	            }
    	        }
    	        
    	        curl_setopt($curlCh, CURLOPT_FOLLOWLOCATION, true); //follow redirects
    	        curl_setopt($curlCh, CURLOPT_URL, $galley->getRemoteURL());
    	        curl_setopt($curlCh, CURLOPT_RETURNTRANSFER, 1);   
    	        
    	        $response = curl_exec($curlCh);
    	        
    	        $curlError = curl_error($curlCh);
    	        if ($curlError) {
    	            // error occured
    	            curl_close($curlCh);
    	            return array('plugins.importexport.dnb.export.error.curlError', $curlError);
    	        }
    	        
    	        //verify content type claimed by host
    	        $contentType = curl_getinfo($curlCh, CURLINFO_CONTENT_TYPE);
    	        if (!preg_match('(application/pdf|application/epub+zip)',$contentType)) {
    	           // error occured
    	            curl_close($curlCh);
    	            return array('plugins.importexport.dnb.export.error.remoteGalleyContentTypeNotValid', $contentType);
    	        }
    	        
    	        curl_close($curlCh);
    	        
    	        //verify mime-type by magic bytes pdf (%PDF-) or epub (PK..)	        
    	        if (!preg_match('/^(%PDF-|PK..)/',$response)) {
    	           // error occured
    	           return array('plugins.importexport.dnb.export.error.remoteFileMimeTypeNotValid', $galley->getSubmissionId());
    	        }
    	        
				// temporarily store downloaded file
    	        $temporaryFilename = tempnam(Config::getVar('files', 'files_dir') . '/' . $this->getPluginSettingsPrefix(), 'dnb');
    	        
    	        $file = fopen($temporaryFilename, "w+");
    	        if (!$file) {
					return array('plugins.importexport.dnb.export.error.tempFileNotCreated');
    	        }
    	        fputs($file, $response);
    	        fclose($file);
    	        $galley->setData('fileSize',filesize($temporaryFilename));
    	        
    	        $sourceGalleyFilePath =  $this->getPluginSettingsPrefix(). "/" . basename($temporaryFilename);
    	        $targetGalleyFilePath = $exportPath . basename($galley->getRemoteURL());
	        }
	    } else {
	       	$sourceGalleyFilePath = $galleyFile->getData('path');
			$targetGalleyFilePath = $exportPath . basename($sourceGalleyFilePath);
		}
	    
		if (!Services::get('file')->fs->has($sourceGalleyFilePath)) {
			return array('plugins.importexport.dnb.export.error.galleyFileNotFound', $sourceGalleyFilePath ?: "NULL");
		}

		// copy galley files
		if (!Services::get('file')->fs->copy($sourceGalleyFilePath, $targetGalleyFilePath)) {
			$param = __('plugins.importexport.dnb.export.error.galleyFileNoCopy.param', array('sourceGalleyFilePath' => $sourceGalleyFilePath, 'targetGalleyFilePath' => $targetGalleyFilePath));
			return array('plugins.importexport.dnb.export.error.galleyFileNoCopy', $param);
		}

		// remove temporary file
		if (!empty($temporaryFilename))	Services::get('file')->fs->deleteDir($temporaryFilename);
		return realpath(Config::getVar('files', 'files_dir') . '/' . $targetGalleyFilePath);
	}

	/**
	 * Return the plugin export directory.
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
	
	function getExportPath($journalId = null, $currentExportPath = null, $exportContentDir = null) {
		if (!$currentExportPath) {
			$exportPath = $this->getPluginSettingsPrefix() . '/' . $journalId . '-' . date('Ymd-His');
		} else {
			$exportPath = $currentExportPath;
		}
		if ($exportContentDir) $exportPath .= $exportContentDir;
		if (!Services::get('file')->fs->has($exportPath)) {
			Services::get('file')->fs->createDir($exportPath);
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
	 * The selected submission can be exported if the issue is published and
	 * submission contains either a PDF or an EPUB full text galley.
	 * @param $submission Submission
	 * @param $issue Issue Just to return the issue
	 * @param $galleys array Filtered (i.e. PDF and EPUB) submission full text galleys
	 * @return boolean
	 */
	function canBeExported($submission, &$issue = null, &$galleys = [], &$supplementaryGalleys = []) {
		$cache = $this->getCache();
		if (!$cache->isCached('articles', $submission->getId())) {
			$cache->add($submission, null);
		}
		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issueId = $issueDao->getBySubmissionId($submission->getId())->getId();

		if ($cache->isCached('issues', $issueId)) {
			$issue = $cache->get('issues', $issueId);
		} else {
			$issueDao = DAORegistry::getDAO('IssueDAO'); /* @var $issueDao IssueDAO */
			$issue = $issueDao->getById($issueId, $submission->getContextId());
			if ($issue) $cache->add($issue, null);
		}
		assert(is_a($issue, 'Issue'));
		if (!$issue->getPublished()) return false;
		// get all galleys
		$galleys = $submission->getGalleys();
		
		// filter supplementary files
		if ($this->getSetting($submission->getData('contextId'), 'submitSupplementaryMode') == 'all') {
			$supplementaryGalleys = array_filter($galleys, array($this, 'filterSupplementaryGalleys'));
		}
		// filter PDF and EPUB full text galleys -- DNB concerns only PDF and EPUB formats
		$galleys = array_filter($galleys, array($this, 'filterGalleys'));

		// in case the galley(s) can be exported with supplementary material which cannot umambiuously be assigned we need to flag this submission
		if ((count($galleys) > 1) && (isset($supplementaryGalleys) ? count($supplementaryGalleys) > 0 : FALSE)) {
			$submission->setData('supplementaryNotAssignable', TRUE);
		} else {
			$submission->setData('supplementaryNotAssignable', FALSE);
		}

		return (count($galleys) > 0);
	}

	/**
	 * Check if a galley is a full text as well as PDF or an EPUB file.
	 * @param $galley ArticleGalley
	 * @return boolean
	 */
	function filterGalleys($galley) {
		// check if it is a full text
		$genreDao = DAORegistry::getDAO('GenreDAO');
		$galleyFile = $galley->getFile();

		//if $galleyFile is not set it might be a remote URL
		if (!isset($galleyFile)) {
		    if ($this->exportRemote()) {
    			$galleyFile = $galley->getRemoteURL();
    			
    			if (isset($galleyFile)) {
					//verify remote URL is not executable file
    			    $isValidFileType = !preg_match('/\.(exe|bat|sh)$/i',$galleyFile);
    			    //file type of remote galley will be verified after download
    			    //verify allowed domain
    			    return $isValidFileType && $this->isAllowedRemoteIP($galleyFile);
    			} else return false;
		    }
		} else {
			$genre = $genreDao->getById($galleyFile->getGenreId());
			// if it is not a document galley, continue
			if ($genre->getCategory() != GENRE_CATEGORY_DOCUMENT) {
				return false;
			}
			return $galley->isPdfGalley() ||  $galley->getFileType() == 'application/epub+zip';
		}
		return false;
	}

	/**
	 * Check if a galley is a supplementary file.
	 * @param $galley ArticleGalley
	 * @return boolean
	 */
	function filterSupplementaryGalleys($galley) {
		// check if it is supplementary file
		$genreDao = DAORegistry::getDAO('GenreDAO');
		$galleyFile = $galley->getFile();

		// if $galleyFile is not set it might be a remote URL
		// currently OJS doesn't handle supplementary remote galleys
		// remote galleys are automatically treated as document galleys
		// we return false until this changes
		if (!isset($galleyFile)) {
		    return false; 
			if ($this->exportRemote()) {
    			$galleyFile = $galley->getRemoteURL();
    			
    			if (isset($galleyFile)) {
    			    //verify remote URL is not executable file
    			    $isValidFileType = !preg_match('/\.(exe|bat|sh)$/i',$galleyFile);
    			    //verify allowed domain
    			    return $isValidFileType && $this->isAllowedRemoteIP($galleyFile);
    			} else return false;
		    }
		} else {
			$genre = $genreDao->getById($galleyFile->getGenreId());
			// if it is not a supplementary galley, continue
			if ($genre->getCategory() != GENRE_CATEGORY_SUPPLEMENTARY) {
				return false;
			}
			$isValidFileType = !preg_match('/\.(exe|bat|sh)$/i',$galleyFile->getData('path'));
			return $isValidFileType;
		}
		return false;
	}

	/**
	 * Test whether the tar binary is available.
	 * @return boolean|array True if available otherwise
	 *  an array with an error message.
	 */
	function checkForTar() {
		$tarBinary = Config::getVar('cli', 'tar');
		# just check binary part (in case tar command line options have been provided in config.inc.php)
		$tarBinary = explode(" ", $tarBinary)[0];
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
	 * Test whether the export filter was registered.
	 * @return boolean|array True if available otherwise
	 *  an array with an error message.
	 */
	function checkForExportFilter() {
	   $filterDao = DAORegistry::getDAO('FilterDAO');
	   $exportFilters = $filterDao->getObjectsByGroup($this->getSubmissionFilter());
	   if (count($exportFilters) == 0) {
	       return array('plugins.importexport.dnb.export.error.NoExportFilter');
	   }
	   return true;
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

		$basedir = Config::getVar('files', 'files_dir') . '/';

		$targetPath = $basedir . $targetPath;
		$targetFile = $basedir . $targetFile;

		$tarCommand = '';
		// Change directory to the target path, to be able to use
		// relative paths i.e. only file names and *
		$tarCommand .= 'cd ' . escapeshellarg($targetPath) . ' && ';

		// Should the result file be GZip compressed.
		$tarOptions = $gzip ? ' -czf ' : ' -cf ';
		// Construct the tar command: path to tar, options, target archive file name
		$tarCommand .= Config::getVar('cli', 'tar'). " " . ADDITIONAL_PACKAGE_OPTIONS . $tarOptions . escapeshellarg($targetFile);

		// Do not reveal our webserver user by forcing root as owner.
		$tarCommand .= ' --owner 0 --group 0 --';

		if (!$sourceFiles) {
			// Add everything
			$tarCommand .= ' *';
		} else {
			// Add each file individually so that other files in the directory
			// will not be included.
			foreach($sourceFiles as $sourceFile) {
				assert($basedir . dirname($sourceFile) . '/' === $targetPath);
				if ($basedir . dirname($sourceFile) . '/' !== $targetPath) continue;
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
		// if journal is not open access, the archive access setting has to be set
		return $this->isOAJournal($journal) || $this->getSetting($journal->getId(), 'archiveAccess');
	}

	/**
	 * Check whether this journal is OA.
	 * @return boolean
	 */
	function isOAJournal($journal = NULL) {
		if (!isset($journal)) {
			$journal = Services::get('context')->get($this->_currentContextId);
		}
		return  $journal->getSetting('publishingMode') == PUBLISHING_MODE_OPEN &&
			$journal->getSetting('restrictSiteAccess') != 1 &&
			$journal->getSetting('restrictArticleAccess') != 1;
	}	

	/**
	 * Get the name of the settings file
	 * creation.
	 * @return string
	 */
	function getContextSpecificPluginSettingsFile() {
	    return $this->getPluginPath() . '/settings.xml';
	}
	
	/**
	 * Display error notification.
	 * @param $request Request
	 * @param $errors array
	 */
	function errorNotification($request, $errors) {
		foreach($errors as $error) {		    
			assert(is_array($error) && count($error) >= 1);
			$this->_sendNotification(
				$request->getUser(),
				$error[0],
				NOTIFICATION_TYPE_ERROR,
				(isset($error[1]) ? $error[1] : null)
			);
		}
	}

	
	/**
	 * Read a file's contents.
	 * @param $filePath string the location of the file to be read
	 * @param $output boolean output the file's contents instead of returning a string
	 * @return string|boolean
	 */
	function readFileFromPath($filePath, $output = false) {
		if (is_readable($filePath)) {
			$f = fopen($filePath, 'rb');
			if (!$f) return false;
			$data = '';
			while (!feof($f)) {
				$data .= fread($f, 4096);
				if ($output) {
					echo $data;
					$data = '';
				}
			}
			fclose($f);

			if ($output) return true;
			return $data;
		}
		return false;
	}

	/**
	 * Download a file.
	 * Outputs HTTP headers and file content for download
	 * @param $filePath string the location of the file to be sent
	 * @param $mediaType string the MIME type of the file, optional
	 * @param $inline boolean print file as inline instead of attachment, optional
	 * @param $fileName string Optional filename to use on the client side
	 * @return boolean
	 */
	function downloadByPath($filePath, $mediaType = null, $inline = false, $fileName = null) {
		$result = null;
		if (is_readable($filePath)) {
			if ($mediaType === null) {
				// If the media type wasn't specified, try to detect.
				$mediaType = PKPString::mime_content_type($filePath);
				if (empty($mediaType)) $mediaType = 'application/octet-stream';
			}
			if ($fileName === null) {
				// If the filename wasn't specified, use the server-side.
				$fileName = basename($filePath);
			}

			// Stream the file to the end user.
			header("Content-Type: $mediaType");
			header('Content-Length: ' . filesize($filePath));
			header('Accept-Ranges: none');
			header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename=\"$fileName\"");
			header('Cache-Control: private'); // Workarounds for IE weirdness
			header('Pragma: public');
			$this->readFileFromPath($filePath, true);
			$returner = true;
		} else {
			$returner = false;
		}
		return $returner;
	}

	function getFileGenre($galleyFile) {
		$genreDao = DAORegistry::getDAO('GenreDAO');
	    return $genreDao->getById($galleyFile->getGenreId())->getCategory();
	}

	function exportRemote() {
		return $this->getSetting($this->_currentContextId, 'exportRemoteGalleys') == "on";
	}

	function isAllowedRemoteIP($url) {
		$remoteIP = gethostbyname(parse_url($url, PHP_URL_HOST));
		$pattern = $this->getSetting($this->_currentContextId, 'allowedRemoteIPs');
		$isAllowed = preg_match("/".$pattern."/", $remoteIP);
		if ($isAllowed) {
			return true;
		} else {
			throw new ErrorException(__('plugins.importexport.dnb.export.error.remoteIPNotAllowed', ['remoteIP' => $remoteIP]), REMOTE_IP_NOT_ALLOWED_EXCEPTION);
			return false;
		}
	}

}

?>
