<?php

/**
 * @file plugins/generic/dnb/DNBExportPlugin.php
 *
 * Copyright (c) 2021 Center for Digital Systems (CeDiS), Universitätsbibliothek Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan, Ronald Steffen
 *
 * @class DNBExportPlugin
 * @ingroup plugins_generic_dnb
 *
 * @brief DNB export plugin
 */

namespace APP\plugins\generic\dnb;

// Import constants
require_once __DIR__ . '/classes/form/DNBSettingsForm.php';

use APP\core\Application;
use APP\plugins\PubObjectsExportPlugin;
use PKP\plugins\Hook;
use APP\core\Services;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\config\Config;
use APP\submission\Submission;
use APP\journal\Journal;
use APP\plugins\generic\dnb\classes\components\DNBSubmissionsList;
use APP\plugins\generic\dnb\classes\components\DNBCatalogTable;
use PKP\plugins\GenericPlugin;
use ErrorException;
use PKP\scheduledTask\ScheduledTaskHelper as PKPScheduledTaskHelper;
use PKP\userGroup\UserGroup;
use PKP\core\PKPRequest;
use PKP\galley\Galley;
use PKP\core\PKPApplication;
use APP\issue\Issue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PKP\security\Role;
use PKP\notification\Notification;
use PKP\scheduledTask\PKPScheduler;

// Import new service classes
use APP\plugins\generic\dnb\classes\api\DNBHelpApiHandler;
use APP\plugins\generic\dnb\classes\api\DNBSubmissionsApiHandler;
use APP\plugins\generic\dnb\classes\deposit\DNBDepositService;
use APP\plugins\generic\dnb\classes\export\DNBPackageBuilder;
use APP\plugins\generic\dnb\classes\export\DNBExportJob;
use APP\plugins\generic\dnb\classes\export\DNBFileManager;
use APP\plugins\generic\dnb\classes\export\DNBExportValidator;
use APP\plugins\generic\dnb\classes\filter\GalleyFilter;
use APP\plugins\generic\dnb\classes\DNBCatalogInfoProvider;
use APP\plugins\generic\dnb\DNBInfoSender;


define('DEBUG', false);

define('DNB_STATUS_DEPOSITED', 'deposited');
define('DNB_ADDITIONAL_PACKAGE_OPTIONS', '--format=gnu'); //use --format=gnu with tar to avoid PAX-Headers
define('DNB_EXPORT_ACTION_MARKEXCLUDED', 'exclude');
define('DNB_EXPORT_STATUS_MARKEXCLUDED', 'markExcluded');
define('DNB_EXPORT_STATUS_QUEUED', 'queued');
define('DNB_EXPORT_STATUS_FAILED', 'failed');
define('DNB_REMOTE_IP_NOT_ALLOWED_EXCEPTION', 103);

if (!DEBUG) {
	define('DNB_SFTP_SERVER', 'sftp://@hotfolder.dnb.de/');
	define('DNB_SFTP_PORT', 22122);
	define('DNB_WEBDAV_SERVER', 'https://@hotfolder.dnb.de/');
	define('DNB_WEBDAV_PORT', 443);
} else {
	define('DNB_SFTP_SERVER', 'sftp://ojs@rs-dev-3.5-ojs-3.5-sftp/');
	define('DNB_SFTP_PORT', 22);
	define('DNB_WEBDAV_SERVER', 'NOT CONFIGURED IN DEBUG MODE');
	define('DNB_WEBDAV_PORT', 443);
}



class DNBExportPlugin extends PubObjectsExportPlugin
{
	/**
	 * @copydoc Plugin::manage()
	 */
	public function manage($args, $request)
	{
		switch ($request->getUserVar('verb')) {
			case 'clearFailedDnbJobs':
				if (!$request->checkCSRF()) {
					return new \PKP\core\JSONMessage(false, __('form.csrfInvalid'));
				}

				$user = $request->getUser();
				$context = $request->getContext();
				if (!$user || !$context) {
					return new \PKP\core\JSONMessage(false, __('common.error'));
				}

				$isManager = $user->hasRole([Role::ROLE_ID_MANAGER], $context->getId());
				$isSiteAdmin = $user->hasRole([Role::ROLE_ID_SITE_ADMIN], PKPApplication::SITE_CONTEXT_ID);
				if (!$isManager && !$isSiteAdmin) {
					return new \PKP\core\JSONMessage(false, __('api.403.unauthorized'));
				}

				$displayNamePrefix = DNBExportJob::DISPLAY_NAME_PREFIX;
				$failedJobs = Repo::failedJob()
					->newQuery()
					->where(function ($query) use ($displayNamePrefix) {
						$query->where('payload', 'like', '%"displayName":"' . $displayNamePrefix . '%')
							->orWhere('payload', 'like', '%DNBExportJob%');
					})
					->get(['id', 'payload']);

				$failedJobIds = $failedJobs->pluck('id')->all();
				if (empty($failedJobIds)) {
					return new \PKP\core\JSONMessage(true, __('plugins.importexport.dnb.failedJobs.success', ['count' => 0]));
				}

				$submissionIds = [];
				foreach ($failedJobs as $failedJob) {
					$payloadArray = is_array($failedJob->payload) ? $failedJob->payload : [];
					$displayName = $failedJob->display_name ?? ($payloadArray['displayName'] ?? '');
					$displayNamePattern = '/^' . preg_quote($displayNamePrefix, '/') . '\s+(\d+)/';
					if (preg_match($displayNamePattern, (string) $displayName, $matches)) {
						$submissionIds[] = (int) $matches[1];
						continue;
					}

					$payloadJson = $payloadArray ? json_encode($payloadArray) : (string) $failedJob->payload;
					if (preg_match('/submissionId"\s*:\s*(\d+)/', $payloadJson, $matches)) {
						$submissionIds[] = (int) $matches[1];
						continue;
					}
					if (preg_match('/submissionId";i:(\d+)/', $payloadJson, $matches)) {
						$submissionIds[] = (int) $matches[1];
						continue;
					}

					$command = $failedJob->command ?? [];
					if (is_array($command)) {
						if (isset($command['submissionId'])) {
							$submissionIds[] = (int) $command['submissionId'];
							continue;
						}
						if (isset($command["\0*\0submissionId"])) {
							$submissionIds[] = (int) $command["\0*\0submissionId"];
							continue;
						}
					}
				}

				$deletedCount = Repo::failedJob()->deleteJobs(null, $failedJobIds);
				if (!empty($submissionIds)) {
					$submissionIds = array_values(array_unique($submissionIds));
					$statusKey = $this->getDepositStatusSettingName();
					$lastErrorKey = $this->getPluginSettingsPrefix() . '::lastError';
					foreach ($submissionIds as $submissionId) {
						$submission = Repo::submission()->get($submissionId);
						if (!$submission || $submission->getData('contextId') !== $context->getId()) {
							continue;
						}
						Repo::submission()->edit($submission, [
							$statusKey => self::EXPORT_STATUS_NOT_DEPOSITED,
							$lastErrorKey => null,
						]);
					}
				}

				return new \PKP\core\JSONMessage(true, __('plugins.importexport.dnb.failedJobs.success', ['count' => $deletedCount]));
			case 'fetchCatalogInfo':
				$context = $request->getContext();
				if (!$context) {
					return new \PKP\core\JSONMessage(false, __('common.error'));
				}
				try {
					$dbnCatalogInfoProvider = new DNBCatalogInfoProvider();
					$dnbCatalogInfo = $dbnCatalogInfoProvider->getCatalogInfo($context, Config::getVar('files', 'files_dir') . '/' . $this->getPluginSettingsPrefix());
					$this->updateSetting($context->getId(), 'dnbCatalogInfo', $dnbCatalogInfo, 'object');
					return new \PKP\core\JSONMessage(true, __('plugins.importexport.dnb.settings.form.dnbCatalog.fetchSuccess'));
				} catch (\Throwable $e) {
					error_log($e->getMessage());
					$param = __('plugins.importexport.dnb.error.catalogQueryFailed.param', array('error' => $e->getMessage()));
					return new \PKP\core\JSONMessage(false, __('plugins.importexport.dnb.error.catalogQueryFailed', ['param' => $param]));
				}
			case 'refreshExportValidation':
				if (!$request->checkCSRF()) {
					return new \PKP\core\JSONMessage(false, __('form.csrfInvalid'));
				}

				$user = $request->getUser();
				$context = $request->getContext();
				if (!$user || !$context) {
					return new \PKP\core\JSONMessage(false, __('common.error'));
				}

				$isManager = $user->hasRole([Role::ROLE_ID_MANAGER], $context->getId());
				$isSiteAdmin = $user->hasRole([Role::ROLE_ID_SITE_ADMIN], PKPApplication::SITE_CONTEXT_ID);
				if (!$isManager && !$isSiteAdmin) {
					return new \PKP\core\JSONMessage(false, __('api.403.unauthorized'));
				}

				$submissions = Repo::submission()
					->getCollector()
					->filterByContextIds([$context->getId()])
					->filterByStatus([Submission::STATUS_PUBLISHED])
					->getMany();

				$count = 0;
				foreach ($submissions as $submission) {
					$this->updateExportValidation($submission->getId());
					$count++;
				}

				return new \PKP\core\JSONMessage(true, __('plugins.importexport.dnb.settings.form.refreshValidation.success', ['count' => $count]));
		}

		return parent::manage($args, $request);
	}

	private $_settingsForm;
	private $_settingsFormURL;
	private $_currentContextId;
	private string $_exportAction = '';
	private bool $_checkedForTar = false;

	// Service instances
	private ?DNBHelpApiHandler $helpApiHandler = null;
	private ?DNBSubmissionsApiHandler $submissionsApiHandler = null;
	private ?DNBDepositService $depositService = null;
	private ?DNBPackageBuilder $packageBuilder = null;
	private ?DNBFileManager $fileManager = null;
	private ?DNBExportValidator $validator = null;
	private ?GalleyFilter $galleyFilter = null;

	/**
	 * Constructor
	 * @param GenericPlugin $parentPlugin The parent generic plugin
	 */
	public function __construct(protected GenericPlugin $parentPlugin)
	{
		parent::__construct();
		$this->pluginPath = $parentPlugin->getPluginPath();

		// Initialize services
		$this->initializeServices();

		$request = Application::get()->getRequest();
		$context = $request->getContext();

		if (isset($context)) {
			$this->setContextId($context->getId());
			// settings form action url (needs to be set before parent::display initiliazes the settings form)
			$this->_settingsFormURL = $request->getDispatcher()->url(
				$request,
				Application::ROUTE_COMPONENT,
				null,
				'grid.settings.plugins.settingsPluginGridHandler',
				'manage',
				null,
				array(
					'plugin' => $this->getName(),
					'category' => 'importexport',
					'verb' => 'save'
				)
			);
		} else {
			// We might be called from the cli and need to register
			$this->register('generic', $this->getPluginPath());
		}
	}

	public function registerSchedules(PKPScheduler $scheduler): void
	{
		if (DEBUG) {
			$scheduler
				->addSchedule(new DNBInfoSender([]))
				->name(DNBInfoSender::class);
		} else {
			$scheduler
				->addSchedule(new DNBInfoSender([]))
				->daily()
				->name(DNBInfoSender::class)
				->withoutOverlapping();
		}
	}

	/**
	 * Initialize service instances
	 */
	private function initializeServices(): void
	{
		$this->galleyFilter = new GalleyFilter();
		$this->fileManager = new DNBFileManager($this);
		$this->validator = new DNBExportValidator($this, $this->galleyFilter);
		$this->packageBuilder = new DNBPackageBuilder($this, $this->fileManager);
		$this->depositService = new DNBDepositService($this);
		$this->helpApiHandler = new DNBHelpApiHandler($this->getHelpPath());
		$this->submissionsApiHandler = new DNBSubmissionsApiHandler($this);
	}

	/**
	 * @copydoc Plugin::register()
	 */
	public function register($category, $path, $mainContextId = null)
	{
		$success = parent::register($category, $path, $mainContextId);

		if ($success) {

			if (Application::isUnderMaintenance()) {
				return true;
			}

			// Add our variables to the submission schema
			Hook::add('Schema::get::submission', function ($hookname, $params) {
				$schema = &$params[0];
				$schema->properties->{$this->getPluginSettingsPrefix() . '::lastError'} = (object)[
					'type' => 'string',
					'apiSummary' => true,
					'validation' => ['nullable'],
					'description' => 'Last error message from DNB export',
				];
				$schema->properties->{$this->getPluginSettingsPrefix() . '::canExport'} = (object)[
					'type' => 'boolean',
					'apiSummary' => true,
					'validation' => ['nullable'],
					'description' => 'Cached result: Can this submission be exported to DNB',
				];
				$schema->properties->{$this->getPluginSettingsPrefix() . '::supplementaryNotAssignable'} = (object)[
					'type' => 'boolean',
					'apiSummary' => true,
					'validation' => ['nullable'],
					'description' => 'Cached result: Supplementary files cannot be unambiguously assigned',
				];
				$schema->properties->{$this->getPluginSettingsPrefix() . '::hasSupplementary'} = (object)[
					'type' => 'boolean',
					'apiSummary' => true,
					'validation' => ['nullable'],
					'description' => 'Cached result: Has supplementary files',
				];
				$schema->properties->{$this->getPluginSettingsPrefix() . '::galleyCount'} = (object)[
					'type' => 'integer',
					'apiSummary' => true,
					'validation' => ['nullable'],
					'description' => 'Cached count of PDF/EPUB galleys',
				];
				$schema->properties->{$this->getPluginSettingsPrefix() . '::supplementaryCount'} = (object)[
					'type' => 'integer',
					'apiSummary' => true,
					'validation' => ['nullable'],
					'description' => 'Cached count of supplementary galleys',
				];
				return Hook::CONTINUE;
			});

			// Listen to galley changes to update cached export validation
			Hook::add('Galley::add', [$this, 'handleGalleyChange']); // At this stage an empty galley (i.e. without galley file) is added, we should be able to catch remote galleys here		Hook::add('Galley::edit', [$this, 'handleGalleyChange']);
			Hook::add('Galley::edit', [$this, 'handleGalleyChange']); // Galley file is changed or uploaded. ISSUE: $galley->getFile() returns null here!
			Hook::add('Galley::delete', [$this, 'handleGalleyChange']); // Galley is deleted, we update galley counts
			Hook::add('Publication::publish', [$this, 'handleGalleyChange']);

			// Register API endpoints if this is an API request
			$request = Application::get()->getRequest();
			$router = $request->getRouter();

			if ($router instanceof \PKP\core\APIRouter) {
				Hook::add(
					"APIHandler::endpoints::{$router->getEntity()}",
					[$this, 'setupAPIEndpoints']
				);
			}
		}

		return $success;
	}

	/**
	 * Handle galley changes to recalculate export status
	 * Called when galleys are added, edited, or deleted
	 */
	public function handleGalleyChange(string $hookName, array $args): int
	{
		$newGalley = $args[0]; // First argument is always (add/edit) the new galley object
		$oldGalley = $args[1]; // Second argument is the old galley object for edit/delete hooks
		$submissionFileId = $args[2]; // Third argument is the submission file ID of the new galley file

		// Galleys have publicationId, not submissionId directly
		$publicationId = $newGalley->getData('publicationId');
		if (!$publicationId) {
			return Hook::CONTINUE;
		}

		// Get the publication to find the submission
		$publication = Repo::publication()->get($publicationId);
		if (!$publication) {
			return Hook::CONTINUE;
		}

		$submissionId = $publication->getData('submissionId');
		if (!$submissionId) {
			return Hook::CONTINUE;
		}

		// Recalculate and cache export validation for this submission
		$this->updateExportValidation($submissionId, $newGalley);

		return Hook::CONTINUE;
	}

	/**
	 * Update cached export validation data for a submission
	 */
	public function updateExportValidation(int $submissionId, ?Galley $newGalley = null): void
	{
		$submission = Repo::submission()->get($submissionId);
		if (!$submission) {
			return;
		}

		$issue = null;
		$galleys = [];
		$supplementaryGalleys = [];

		// Run the validation
		$canExport = $this->canBeExported($submission, $issue, $galleys, $supplementaryGalleys, $newGalley);

		// Get the supplementaryNotAssignable flag that was set by canBeExported
		$supplementaryNotAssignable = $submission->getData($this->getPluginSettingsPrefix() . '::supplementaryNotAssignable') ?? false;

		// Cache the results in the database
		Repo::submission()->edit($submission, [
			$this->getPluginSettingsPrefix() . '::canExport' => $canExport,
			$this->getPluginSettingsPrefix() . '::supplementaryNotAssignable' => $supplementaryNotAssignable,
			$this->getPluginSettingsPrefix() . '::galleyCount' => count($galleys),
			$this->getPluginSettingsPrefix() . '::supplementaryCount' => count($supplementaryGalleys),
		]);
	}

	/**
	 * Register API endpoints for this plugin
	 */
	public function setupAPIEndpoints(string $hook, $controller, $apiHandler): int
	{
		$request = Application::get()->getRequest();
		$router = $request->getRouter();
		$context = $request->getContext();

		// Only register for 'contexts' entity endpoint
		if ($hook !== 'APIHandler::endpoints::contexts' || !$context) {
			return Hook::CONTINUE;
		}

		// GET /contexts/{contextId}/_plugins/generic/dnb/submissions - List submissions with DNB status
		$apiHandler->addRoute(
			'GET',
			$context->getId() . '/_plugins/generic/dnb/submissions',
			function (Request $illuminateRequest) use ($request): JsonResponse {
				return $this->getSubmissionsForExport($illuminateRequest, $request);
			},
			'dnb.submissions.list',
			[Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN]
		);

		// GET /contexts/{contextId}/_plugins/generic/dnb/help?lang={lang}&topic={topic} - Help documentation
		$apiHandler->addRoute(
			'GET',
			$context->getId() . '/_plugins/generic/dnb/help',
			function (Request $illuminateRequest) use ($request): JsonResponse {
				$lang = $illuminateRequest->query('lang');
				$topic = $illuminateRequest->query('topic');
				return $this->getHelpContent($lang, $topic, $request);
			},
			'dnb.help.get',
			[Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN]
		);

		return Hook::CONTINUE;
	}

	/**
	 * @copydoc Plugin::getName()
	 */
	public function setSettingsForm(object $form): void
	{
		$this->_settingsForm = $form;
	}

	/**
	 * @copydoc Plugin::getName()
	 */
	public function getSettingsFormActionURL(): ?string
	{
		return $this->_settingsFormURL;
	}

	/**
	 * @copydoc Plugin::getName()
	 */
	public function getName(): string
	{
		return 'DNBExportPlugin';
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	public function getDisplayName(): string
	{
		return __('plugins.importexport.dnb.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	public function getDescription(): string
	{
		return __('plugins.importexport.dnb.description');
	}

	/**
	 * @copydoc setContextId()
	 */
	public function setContextId(int $Id): void
	{
		$this->_currentContextId = $Id;
	}

	/**
	 * @copydoc ImportExportPlugin::display()
	 */
	function display($args, $request)
	{
		$context = $request->getContext();
		$currentLocale = $request->getSession()->get('currentLocale') ?? $context->getPrimaryLocale();

		// if no issue is published go back to tools 
		// get all published submissions in the context
		$issueIds = Repo::issue()
			->getCollector()
			->filterByContextIds([$context->getId()])
			->filterByPublished(true)
			->getIds()
			->toArray();
		if (count($issueIds) < 1) {
			//show error
			$this->errorNotification($request, array(array('plugins.importexport.dnb.deposit.error.noIssuesPublished')));
			// redirect back to exportSubmissions-tab
			$path = array('plugin', $this->getName());
			$request->redirectUrl($request->getRouter()->url($request, null, 'management', 'tools'));
			return;
		}

		// if no object is selected go back to export submission tab
		if (!empty($args)) {
			if (
				empty((array) $request->getUserVar('selectedSubmissions')) || (($args[0] !== 'exportSubmissions') &&
					($args[0] !== self::EXPORT_ACTION_EXPORT) &&
					($args[0] !== self::EXPORT_ACTION_DEPOSIT) &&
					($args[0] !== self::EXPORT_ACTION_MARKREGISTERED))
			) {

				//show error
				$this->errorNotification($request, array(array('plugins.importexport.dnb.deposit.error.noObjectsSelected')));
				// redirect back to exportSubmissions-tab
				$path = array('plugin', $this->getName());
				$request->redirect(null, null, null, $path, null, 'exportSubmissions-tab');
				return;
			}

			// handle parent export actions
			switch ($args[0]) {
				case self::EXPORT_ACTION_EXPORT:
					$this->_exportAction = self::EXPORT_ACTION_EXPORT;
					break;
				case self::EXPORT_ACTION_DEPOSIT:
					$this->_exportAction = self::EXPORT_ACTION_DEPOSIT;
					break;
				case self::EXPORT_ACTION_MARKREGISTERED:
					$this->_exportAction = self::EXPORT_ACTION_MARKREGISTERED;
					$request->_requestVars[self::EXPORT_ACTION_MARKREGISTERED] = true;
					break;
				case DNB_EXPORT_ACTION_MARKEXCLUDED:
					$this->_exportAction = DNB_EXPORT_ACTION_MARKEXCLUDED;
					break;
			}
			$selectedSubmissions = (array) $request->getUserVar('selectedSubmissions');
			if (!empty($selectedSubmissions)) {
				$args[0] = 'exportSubmissions';
			} else {
				$args[0] = '';
			}
		}

		parent::display($args, $request);

		switch (array_shift($args)) {
			case 'index':
			case '':
				// settings form
				$settingsFormConfig = $this->_settingsForm->getConfig();
				$settingsFormConfig['fields'][1]['inputType'] = "password";

				// Use API endpoint for submissions list
				$apiUrl = $request->getDispatcher()->url(
					$request,
					Application::ROUTE_API,
					$context->getPath(),
					"contexts/" . $context->getId() . "/_plugins/generic/dnb/submissions"
				);

				$submissionItems = [];
				$nNotRegistered = 0;

				// Instantiate DNBSubmissionsList
				$dnbSubmissionsList = new DNBSubmissionsList(
					$apiUrl,
					$submissionItems,
					$context
				);

				// Get config for Vue component
				$submissionsConfig = $dnbSubmissionsList->getConfig();

				// set properties
				$templateMgr = TemplateManager::getManager($request);

				$checkForTarResult = $this->checkForTar();
				$templateMgr->assign('checkFilter', !is_array($this->checkForExportFilter()));
				$templateMgr->assign('checkTar', !is_array($checkForTarResult));
				$templateMgr->assign('checkSettings', $this->checkPluginSettings($context));
				// Build help API URL
				$helpApiUrl = $request->getDispatcher()->url(
					$request,
					Application::ROUTE_API,
					$context->getPath(),
					"contexts/" . $context->getId() . "/_plugins/generic/dnb/help"
				);

				$templateMgr->assign([
					'pageComponent' => 'ImportExportPage',
					'baseurl' => $request->getBaseUrl(),
					'debugModeWarning' => $this->getSetting($context->getId(), 'connectionType') ? __("plugins.importexport.dnb.settings.debugModeActive.contents", ['server' => DNB_WEBDAV_SERVER, 'port' => DNB_WEBDAV_PORT]) : __("plugins.importexport.dnb.settings.debugModeActive.contents", ['server' => DNB_SFTP_SERVER, 'port' => DNB_SFTP_PORT]),
					'nNotRegistered' => $nNotRegistered,
					'remoteEnabled' => $this->getSetting($context->getId(), 'exportRemoteGalleys') ? "Remote On" : "",
					'suppDisabled' => $this->getSetting($context->getId(), 'submitSupplementaryMode') === "none" ? "Supplementary Off" : "",
					'helpApiUrl' => $helpApiUrl,
					'currentLocale' => $currentLocale
				]);

				$dnbCatalogInfo = $this->getSetting($this->_currentContextId, 'dnbCatalogInfo');
				if (!empty($dnbCatalogInfo)) {
					$templateMgr->assign('dnbCatalogInfo', $dnbCatalogInfo);
				}
				$dnbCatalogTable = new DNBCatalogTable($dnbCatalogInfo ?? []);
				$catalogConfig = $dnbCatalogTable->getConfig();

				$templateMgr->setConstants([
					'FORM_DNB_SETTINGS',
					'DNB_SUBMISSIONS_LIST',
					'DNB_CATALOG_TABLE',
				]);

				$components = [
					FORM_DNB_SETTINGS => $settingsFormConfig,
					DNB_SUBMISSIONS_LIST => $submissionsConfig,
				];
				if (!empty($dnbCatalogInfo)) {
					$components[DNB_CATALOG_TABLE] = $catalogConfig;
				}

				$state = [
					'components' => $components,
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

				// Add Vue component JavaScript
				$templateMgr->addJavaScript(
					'dnbPluginVue',
					"{$request->getBaseUrl()}/{$this->getPluginPath()}/build/dnb.iife.js",
					[
						'inline' => false,
						'contexts' => ['backend'],
						'priority' => TemplateManager::STYLE_SEQUENCE_LAST
					]
				);

				// Add Vue component CSS
				$templateMgr->addStyleSheet(
					'dnbPluginVueStyles',
					"{$request->getBaseUrl()}/{$this->getPluginPath()}/build/dnb-plugin.css",
					[
						'contexts' => ['backend']
					]
				);

				$helpUrl = $request->getDispatcher()->url(
					$request,
					Application::ROUTE_API,
					$context->getPath(),
					"contexts/" . $context->getId() . "/_plugins/generic/dnb/help"
				);

				$catalogFetchUrl = $request->getDispatcher()->url(
					$request,
					Application::ROUTE_COMPONENT,
					null,
					'grid.settings.plugins.settingsPluginGridHandler',
					'manage',
					null,
					array(
						'plugin' => $this->getName(),
						'category' => 'importexport',
						'verb' => 'fetchCatalogInfo'
					)
				);

				$refreshValidationUrl = $request->getDispatcher()->url(
					$request,
					Application::ROUTE_COMPONENT,
					null,
					'grid.settings.plugins.settingsPluginGridHandler',
					'manage',
					null,
					array(
						'plugin' => $this->getName(),
						'category' => 'importexport',
						'verb' => 'refreshExportValidation'
					)
				);

				$clearFailedJobsUrl = $request->getDispatcher()->url(
					$request,
					Application::ROUTE_COMPONENT,
					null,
					'grid.settings.plugins.settingsPluginGridHandler',
					'manage',
					null,
					array(
						'plugin' => $this->getName(),
						'category' => 'importexport',
						'verb' => 'clearFailedDnbJobs'
					)
				);

				$script_data = [
					'helpUrl' => $helpUrl,
					'catalogFetchUrl' => $catalogFetchUrl,
					'refreshValidationUrl' => $refreshValidationUrl,
				];

				$templateMgr->assign('clearFailedJobsUrl', $clearFailedJobsUrl);

				// provide url for help pages
				$templateMgr->addJavaScript(
					'dnbexportpluginData',
					'$.pkp.plugins.importexport = $.pkp.plugins.importexport || {};' .
						'$.pkp.plugins.importexport.' . strtolower($this->getName()) . ' = ' . json_encode($script_data) . ';',
					[
						'inline' => true,
						'contexts' => 'backend',
					]
				);

				// show logs for automatic deposit
				if ($this->getSetting($this->_currentContextId, 'automaticDeposit')) {
					$logFiles = Services::get('file')->fs->listContents(PKPScheduledTaskHelper::SCHEDULED_TASK_EXECUTION_LOG_DIR)
						->filter(fn(\League\Flysystem\StorageAttributes $attributes) => str_contains($attributes->path(), "DNBInfoSender"))
						->toArray();

					usort($logFiles, function ($f1, $f2) {
						return (int) $f1['lastModified'] < $f2['lastModified'];
					});

					$latestLogFile = [];
					if (count($logFiles) > 0) {
						// filter context specific messages
						$latestLogFile = Services::get('file')->fs->read($logFiles[0]['path']);
						$latestLogFile = preg_split("/\r\n|\n|\r/", $latestLogFile, 0, PREG_SPLIT_NO_EMPTY);
						$lastIndex = count($latestLogFile) - 1;
						$latestLogFile = array_filter(
							$latestLogFile,
							function ($line, $index) use ($context, $lastIndex) {
								return (bool) preg_match('#\[' . $context->getData('urlPath') . '\]#', $line) || ($index == 1) || ($index == $lastIndex);
							},
							ARRAY_FILTER_USE_BOTH
						);
					}
					$templateMgr->assign('latestLogFile', $latestLogFile);
				}

				$templateMgr->display($this->getTemplateResource('index.tpl'));
				break;
		}
	}

	/**
	 * Get submissions list for export with DNB status
	 * API endpoint handler
	 */
	protected function getSubmissionsForExport(Request $illuminateRequest, PKPRequest $request): JsonResponse
	{
		return $this->submissionsApiHandler->handle($illuminateRequest, $request);
	}



	/**
	 * Get help content for documentation
	 * API endpoint handler
	 */
	protected function getHelpContent(?string $lang, ?string $topic, PKPRequest $request): JsonResponse
	{
		return $this->helpApiHandler->handle($lang, $topic);
	}


	/**
	 * @copydoc PubObjectsExportPlugin::getStatusNames()
	 */
	public function getStatusNames(): array
	{
		return array(
			self::EXPORT_STATUS_ANY => __('plugins.importexport.dnb.status.notDeposited'),
			self::EXPORT_STATUS_NOT_DEPOSITED => __('plugins.importexport.dnb.status.notDeposited'),
			DNB_STATUS_DEPOSITED => __('plugins.importexport.dnb.status.deposited'),
			self::EXPORT_STATUS_MARKEDREGISTERED => __('plugins.importexport.common.status.markedRegistered'),
			DNB_EXPORT_STATUS_MARKEXCLUDED => __('plugins.importexport.dnb.status.excluded'),
			DNB_EXPORT_STATUS_QUEUED => __('plugins.importexport.dnb.status.queued'),
			DNB_EXPORT_STATUS_FAILED => __('plugins.importexport.dnb.status.failed'),
		);
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getExportActionNames()
	 */
	public function getExportActionNames(): array
	{
		return array_merge(parent::getExportActionNames(), array(
			self::EXPORT_ACTION_DEPOSIT => __('plugins.importexport.dnb.deposit'),
			DNB_EXPORT_ACTION_MARKEXCLUDED => __('plugins.importexport.dnb.exclude')
		));
	}

	/**
	 * @copydoc ImportExportPlugin::getPluginSettingsPrefix()
	 */
	public function getPluginSettingsPrefix(): string
	{
		return 'dnb';
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getSubmissionFilter()
	 */
	public function getSubmissionFilter(): string
	{
		return 'galley=>dnb-xml';
	}

	/**
	 * Return the location of the plugin's CSS file
	 */
	public function getStyleSheet(): string
	{
		return $this->getPluginPath() . '/css/dnbplugin.css';
	}

	/**
	 * Return the location of the help files
	 */
	public function getHelpPath(): string
	{
		return $this->getPluginPath() . '/docs/manual/';
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getExportActions()
	 */
	public function getExportActions($context): array
	{
		return array(self::EXPORT_ACTION_DEPOSIT, self::EXPORT_ACTION_EXPORT, self::EXPORT_ACTION_MARKREGISTERED, DNB_EXPORT_ACTION_MARKEXCLUDED);
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getExportDeploymentClassName()
	 */
	public function getExportDeploymentClassName(): string
	{
		return '\\APP\\plugins\\generic\\dnb\\DNBExportDeployment';
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getSettingsFormClassName()
	 */
	public function getSettingsFormClassName(): string
	{
		return '\\APP\\plugins\\generic\\dnb\\classes\\form\\DNBSettingsForm';
	}

	/**
	 * @copydoc PubObjectsExportPlugin::depositXML()
	 * @return bool|array[] True on success, or 2D array [['locale.key', 'param'], ...] on error
	 */
	public function depositXML($object, $context, $filename): bool|array
	{
		// Dispatch through the deposit service which will queue the job
		return $this->depositService->deposit($object, $context, $filename);
	}


	/**
	 * @copydoc PubObjectsExportPlugin::executeExportAction()
	 */

	public function executeExportAction($request, $submissions, $filter, $tab, $submissionsFileNamePart, $noValidation = true, $shouldRedirect = true): mixed
	{
		$journal = $request->getContext();
		$path = array('plugin', $this->getName());
		$tab = "exportSubmissions-tab";
		$basedir = Config::getVar('files', 'files_dir');

		switch ($this->_exportAction) {
			case self::EXPORT_ACTION_EXPORT:
			case self::EXPORT_ACTION_DEPOSIT:

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
				$exportPath = $result;
				// Keep both relative (for Flysystem) and absolute (for direct FS operations) paths
				$exportPathRelative = $result;
				$journalExportPath = $basedir . '/' . $result;

				$errors = $exportFilesNames = [];

				// Go through all submissions, create an deposit packages
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
					} catch (DNBPluginException $e) {
						// convert DNBPluginException to error messages that will be shown to the user
						// handleExceptions() returns a single error tuple like ['key', 'param'], so wrap it in array
						$result = $this->handleExceptions($e, $submission->getId());
						$errors = array_merge($errors, [$result]);
					}

					// Go through all gellyes, prepare packages and deposit
					foreach ($galleys as $galley) {

						// store submission Id in galley object for internal use
						$galley->setData('submissionId', $submission->getId());

						// check if it is a full text
						// if $galleyFile is not set it might be a remote URL
						$galleyFile = $galley->getFile();
						if (!isset($galleyFile)) {
							if ($galley->getData('urlRemote') == null) continue;
						}

						$exportFile = '';
						// Get the TAR package for the galley
						$result = $this->getGalleyPackage($galley, $supplementaryGalleys, $filter, $noValidation, $journal, $exportPath, $exportFile, $submission->getData('id'));

						// If errors occured, remove all created directories and return the errors
						if (is_array($result)) {
							// If error occured add it to the list of errors
							$errors = array_merge($errors, $result);
						}

						// deposit the package
						if ($this->_exportAction == self::EXPORT_ACTION_EXPORT) {
							// Add the galley package to the list of all exported files
							$exportFilesNames[] = $exportFile;
						} elseif ($this->_exportAction == self::EXPORT_ACTION_DEPOSIT) {
							// Deposit the galley
							// $exportfile will be empty if XML file could not be created
							$result = false;
							if ($exportFile) {
								$result = $this->depositXML($galley, $journal, $exportFile);
							}
							if (is_array($result)) {
								// If error occured add it to the list of errors
								$errors = array_merge($errors, $result);
							}
						}
					}
					// }
				}

				// Handle errors and cleanup
				if ($this->_exportAction == self::EXPORT_ACTION_EXPORT) {
					if (!empty($errors)) {
						// If there were some deposit errors, display them to the user
						$this->errorNotification($request, $errors);
					} else {
						// If there is more than one export package, package them all in a single .tar.gz
						assert(count($exportFilesNames) >= 1);
						if (count($exportFilesNames) > 1) {
							$finalExportFileName = $journalExportPath . $this->getPluginSettingsPrefix() . '-export.tar.gz';
							$this->createTarArchive($journalExportPath, $finalExportFileName, $exportFilesNames, true);
						} else {
							$finalExportFileName = reset($exportFilesNames);
						}
						// Stream the results to the browser
						$this->downloadByPath($finalExportFileName, null, false, basename($finalExportFileName));
					}
					// Remove the generated directories
					Services::get('file')->fs->deleteDirectory($exportPathRelative);
					// redirect back to the right tab
					// redirect causes a PHP Warning because headers were already sent by above downloadByPath call
					// we disable warning before redirect not to spam error log
					error_reporting(~E_WARNING);
					$request->redirect(null, null, null, $path, null, $tab);
				} elseif ($this->_exportAction == self::EXPORT_ACTION_DEPOSIT) {
					if (!empty($errors)) {
						// If there were some deposit errors, display them to the user
						$this->errorNotification($request, $errors);
					} else {
						// Provide the user with some visual feedback that deposit was successful
						$this->_sendNotification(
							$request->getUser(),
							'plugins.importexport.dnb.deposited.success',
							Notification::NOTIFICATION_TYPE_SUCCESS
						);
					}
					// Remove the generated directories
					Services::get('file')->fs->deleteDirectory($exportPathRelative);
					// redirect back to the right tab
					$request->redirect(null, null, null, $path, null, $tab);
				}
				break;
			case DNB_EXPORT_ACTION_MARKEXCLUDED:
				foreach ($submissions as $object) {
					switch ($object->getData($this->getDepositStatusSettingName())) {
						case self::EXPORT_STATUS_NOT_DEPOSITED:
						case NULL:
							$object->setData($this->getDepositStatusSettingName(), DNB_EXPORT_STATUS_MARKEXCLUDED);
							break;
						case DNB_EXPORT_STATUS_MARKEXCLUDED:
							$object->setData($this->getDepositStatusSettingName(), NULL);
							break;
					}
					$this->updateObject($object);
				}
				// redirect back to the right tab
				$request->redirect(null, null, null, $path, null, $tab);
				break;
			default:
				return parent::executeExportAction($request, $submissions, $filter, $tab, $submissionsFileNamePart, $noValidation);
		}
		return null;
	}

	function updateSubmissionStatus(Submission $submission): void
	{
		// update submission status in memory
		$submission->setData($this->getDepositStatusSettingName(), DNB_STATUS_DEPOSITED);
		// update submission status in database
		Repo::submission()->edit($submission, [$this->getDepositStatusSettingName() => DNB_STATUS_DEPOSITED]);
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
	 * @return bool|array[] True for success or 2D array [['locale.key', 'param'], ...] on error
	 */
	function getGalleyPackage(Galley $galley, array $supplementaryGalleys, string $filter, ?bool $noValidation = true, Journal $journal, string $exportPathBase, string &$exportPackageName, int $submissionId): bool|array
	{
		return $this->packageBuilder->assemblePackage($galley, $supplementaryGalleys, $filter, $noValidation, $journal, $exportPathBase, $exportPackageName, $submissionId);
	}



	function handleExceptions($e, $submissionId = null)
	{
		switch ($e->getCode()) {
			case \DNB_XML_NON_VALID_CHARCTERS_EXCEPTION:
				$param = __('plugins.importexport.dnb.export.error.articleMetadataInvalidCharacters.param', array('submissionId' => $submissionId, 'node' => $e->getMessage()));
				return array('plugins.importexport.dnb.export.error.articleMetadataInvalidCharacters', $param);
			case \DNB_URN_SET_EXCEPTION:
				return ['plugins.importexport.dnb.export.error.urnSet.description', $e->getMessage()];
			case \DNB_FIRST_AUTHOR_NOT_REGISTERED_EXCEPTION:
				$param = __('plugins.importexport.dnb.export.error.firstAuthorNotRegistred.param', array('submissionId' => $submissionId, 'msg' => $e->getMessage()));
				return array('plugins.importexport.dnb.export.error.firstAuthorNotRegistred', $param);
			case \DNB_REMOTE_IP_NOT_ALLOWED_EXCEPTION:
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
	function copyGalleyFile($galley, string $exportPath): string|array
	{
		return $this->fileManager->copyGalleyFile($galley, $exportPath);
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
	 * @return string|array[] String with export directory path on success, or 2D array [['locale.key', 'param'], ...] on error
	 */

	public function getExportPath(?int $journalId = null, ?string $currentExportPath = null, ?string $exportContentDir = null): string|array
	{
		return $this->fileManager->getExportPath($journalId, $currentExportPath, $exportContentDir);
	}



	/**
	 * The selected submission can be exported if the issue is published and
	 * submission contains either a PDF or an EPUB full text galley.
	 * @param $submission Submission
	 * @param $issue Issue Just to return the issue
	 * @param $galleys array Filtered (i.e. PDF and EPUB) submission full text galleys
	 * @return boolean
	 */
	public function canBeExported(Submission $submission, ?Issue &$issue = null, array &$galleys = [], array &$supplementaryGalleys = [], ?Galley $newGalley = null): bool
	{
		return $this->validator->canBeExported($submission, $issue, $galleys, $supplementaryGalleys, $newGalley);
	}



	/**
	 * Check if a galley is a full text as well as PDF or an EPUB file.
	 * @param $galley ArticleGalley
	 * @return boolean
	 */
	public function filterGalleys(Galley $galley): bool
	{
		return $this->galleyFilter->filterPDFAndEPUB($galley);
	}



	/**
	 * Check if a galley is a supplementary file.
	 * @param $galley ArticleGalley
	 * @return boolean
	 */
	public function filterSupplementaryGalleys(Galley $galley): bool
	{
		return $this->galleyFilter->filterSupplementary($galley);
	}



	/**
	 * Test whether the tar binary is available.
	 * @return bool|array[] True if available, otherwise 2D array [['locale.key', 'param'], ...] on error
	 */
	public function checkForTar(): array|bool
	{
		$result = $this->validator->checkForTar();
		$this->_checkedForTar = true;
		return $result;
	}

	/**
	 * Test whether the export filter was registered.
	 * @return boolean|array True if available otherwise
	 *  an array with an error message.
	 */
	public function checkForExportFilter(): array|bool
	{
		return $this->validator->checkForExportFilter();
	}



	/**
	 * Create a tar archive.
	 * Delegates to PackageBuilder service for consistency.
	 * 
	 * @param $targetPath string
	 * @param $targetFile string
	 * @param $sourceFiles array (optional) If null, everything in the target path is considered
	 * @param $gzip boolean (optional) If TAR file should be gzipped
	 */
	public function createTarArchive(string $targetPath, string $targetFile, ?array $sourceFiles = null, bool $gzip = false): bool|array
	{
		assert($this->_checkedForTar);

		// If sourceFiles provided, extract just basenames for relative paths
		if ($sourceFiles) {
			$sourceFiles = array_map('basename', $sourceFiles);
		}

		// Delegate to PackageBuilder's createTarArchive method and return its result
		return $this->packageBuilder->createTarArchive($targetPath, $targetFile, $sourceFiles, $gzip);
	}

	/**
	 * Check if plugin settings are missing.
	 * @param $journal Journal
	 * @return boolean
	 */
	public function checkPluginSettings(Journal $journal): array|bool
	{
		return $this->validator->checkPluginSettings($journal);
	}

	/**
	 * Check whether this journal is OA.
	 * @return boolean
	 */
	public function isOAJournal(Journal $journal): bool
	{
		return !$journal->getData('publishingMode') || $journal->getData('publishingMode') != Journal::PUBLISHING_MODE_SUBSCRIPTION;
	}
	/**
	 * Get the name of the settings file
	 * creation.
	 * @return string
	 */
	public function getContextSpecificPluginSettingsFile(): ?string
	{
		return $this->getPluginPath() . '/settings.xml';
	}

	/**
	 * Display error notification.
	 * @param $request Request
	 * @param $errors array
	 */
	public function errorNotification(PKPRequest $request, array $errors): void
	{
		foreach ($errors as $error) {
			assert(is_array($error) && count($error) >= 1);
			$this->_sendNotification(
				$request->getUser(),
				$error[0],
				Notification::NOTIFICATION_TYPE_ERROR,
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
	public function readFileFromPath(string $filePath, bool $output = false): string|bool
	{
		return $this->fileManager->readFileFromPath($filePath, $output);
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
	public function downloadByPath(string $filePath, ?string $mediaType = null, bool $inline = false, ?string $fileName = null): bool
	{
		return $this->fileManager->downloadByPath($filePath, $mediaType, $inline, $fileName);
	}



	public function getFileGenre($galleyFile): int
	{
		return $this->galleyFilter->getFileGenre($galleyFile);
	}

	public function exportRemote(): bool
	{
		return $this->getSetting($this->_currentContextId, 'exportRemoteGalleys') == "on";
	}

	public function isAllowedRemoteIP(string $url): bool
	{
		$remoteIP = gethostbyname(parse_url($url, PHP_URL_HOST));
		$pattern = $this->getSetting($this->_currentContextId, 'allowedRemoteIPs');
		$isAllowed = preg_match("/" . $pattern . "/", $remoteIP);
		if ($isAllowed) {
			return true;
		} else {
			throw new DNBPluginException(__('plugins.importexport.dnb.export.error.remoteIPNotAllowed', ['remoteIP' => $remoteIP]), DNB_REMOTE_IP_NOT_ALLOWED_EXCEPTION);
		}
	}

	/**
	 * Retrieve all unregistered articles.
	 * @param $context Context
	 * @return array
	 */
	public function getUnregisteredArticles($context): array
	{
		$submissions = parent::getUnregisteredArticles($context);
		return array_filter($submissions, array($this, 'filterMarkExcluded'));
	}

	/**
	 * Get published submissions from submission IDs.
	 * @param $submissionIds array
	 * @param $context Context
	 * @return array
	 */
	public function getPublishedSubmissions($submissionIds, $context): array
	{
		$submissions = parent::getPublishedSubmissions($submissionIds, $context);
		if ($this->_exportAction != DNB_EXPORT_ACTION_MARKEXCLUDED) {
			return array_filter($submissions, array($this, 'filterMarkExcluded'));
		}
		return $submissions;
	}

	public function filterMarkExcluded($submission): bool
	{
		return ($submission->getData($this->getDepositStatusSettingName()) !== DNB_EXPORT_STATUS_MARKEXCLUDED);
	}

	public function array_keys_multi(array $array): array
	{
		$keys = array();

		foreach ($array as $key => $value) {
			$keys[] = $key;

			if (is_array($value)) {
				$keys = array_merge($keys, $this->array_keys_multi($value));
			}
		}

		return $keys;
	}
}

class DNBPluginException extends ErrorException {}
