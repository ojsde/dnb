<?php

/**
 * @file plugins\generic\dnb\classes\export\DNBExportJob.php
 */

namespace APP\plugins\generic\dnb\classes\export;

use APP\facades\Repo;
use PKP\jobs\BaseJob;
use PKP\config\Config;
use APP\core\Services;
use APP\plugins\generic\dnb\DNBPluginException;
use APP\plugins\generic\dnb\DNBExportPlugin;

if (!defined('DNB_STATUS_DEPOSITED')) define('DNB_STATUS_DEPOSITED', 'deposited');
if (!defined('DNB_EXPORT_STATUS_FAILED')) define('DNB_EXPORT_STATUS_FAILED', 'failed');
if (!defined('DNB_SFTP_SERVER')) define('DNB_SFTP_SERVER', 'sftp://ojs@rs-dev-3.5-ojs-3.5-sftp/');
if (!defined('DNB_SFTP_PORT')) define('DNB_SFTP_PORT', 22);
if (!defined('DNB_WEBDAV_SERVER')) define('DNB_WEBDAV_SERVER', 'NOT CONFIGURED IN DEBUG MODE');
if (!defined('DNB_WEBDAV_PORT')) define('DNB_WEBDAV_PORT', 443);

class DNBExportJob extends BaseJob
{
    public const DISPLAY_NAME_PREFIX = 'DNB Export Job - Submission';

    protected int $galleyId;
    protected int $contextId;
    protected int $submissionId;
    protected array $supplementaryGalleyIds;
    protected bool $noValidation;
    protected ?string $filename = null; // Pre-built package path (for manual exports)

    /**
     * DNBExportJob constructor.
     *
     * @param int $galleyId Identifier for the galley to export.
     * @param int $contextId Journal context ID.
     * @param int $submissionId Submission ID being exported.
     * @param array $supplementaryGalleyIds Optional array of additional galley IDs.
     * @param bool $noValidation Skip XML validation if true.
     * @param string|null $filename Pre-built package path (for manual exports).
     */
    public function __construct(
        int $galleyId,
        int $contextId,
        int $submissionId,
        array $supplementaryGalleyIds = [],
        bool $noValidation = true,
        ?string $filename = null
    ) {
        parent::__construct();

        $this->galleyId = $galleyId;
        $this->contextId = $contextId;
        $this->submissionId = $submissionId;
        $this->supplementaryGalleyIds = $supplementaryGalleyIds;
        $this->noValidation = $noValidation;
        $this->filename = $filename; // If provided, skip package building
    }

    /**
     * Override to ensure jobs are queued, not executed synchronously
     * even when scheduler runs in maintenance mode
     */
    protected function defaultConnection(): string
    {
        return Config::getVar('queues', 'default_connection', 'database');
    }

    public function handle(): void
    {
        // The job orchestrates three major phases:
        // 1. (Optional) build the export package if not already provided
        // 2. perform the deposit transfer to the DNB hotfolder
        // 3. clean up any temporary files generated during package creation
        try {
            // Retrieve the objects from their IDs.  If any of them is missing
            // something is seriously wrong and we bail out immediately.
            $galley = Repo::galley()->get($this->galleyId);
            $context = app('context')->get($this->contextId);
            $submission = Repo::submission()->get($this->submissionId);

            if (!$galley || !$context || !$submission) {
                throw new \Exception('Galley, Context, or Submission not found for DNB export');
            }

            if ($context->getId() !== $submission->getData('contextId')) {
                throw new \Exception('Context ID mismatch between submission and provided context');
            }

            // Get plugin from registry
            $plugin = $this->getDNBExportPlugin();
            $plugin->setContextId($this->contextId); // Ensure plugin has context for settings retrieval

            // Step 1: Build the package (TAR archive with XML + PDFs) - only if
            // the filename was not supplied by the caller.  Manual exports pass a
            // pre‑built package path; scheduled jobs construct the archive here.
            if ($this->filename === null || !file_exists($this->filename)) {
                $this->filename = $this->orchestratePackageBuild($galley, $context, $submission, $plugin);
            } else {
                    throw new \Exception('Package could not be built and no valid filename provided for DNB export.');
            }

            // Step 2: Perform the complete deposit transfer via curl
            $this->executeDeposit($galley, $context, $plugin);

            // Step 3: Cleanup temporary files (only if we built the package)
            if ($this->filename !== null && empty($this->supplementaryGalleyIds) === false) {
                // Job built the package, so clean it up
                $this->cleanup($plugin);
            }
        } catch (\Throwable $exception) {
            // Log to error_log to suppress CLI stack trace noise
            error_log('DNB Export Job attempt failed: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());

            // Re-throw to allow framework retry mechanism
            // After retries exhausted, framework will call failed()
            throw $exception;
        }
    }

    /**
     * Orchestrate the export package build for this job.
     *
     * Coordinates the package builder, manages paths, and returns the path to
     * the generated TAR archive. This is the job-level wrapper around the lower-level
     * DNBPackageBuilder::assemblePackage() method.
     *
     * @param \APP\submission\Galley $galley Primary galley object.
     * @param \APP\core\Context $context Journal context.
     * @param \APP\submission\Submission $submission Submission object.
     * @param DNBExportPlugin $plugin Plugin instance used for configuration.
     * @return string Full path to the generated TAR file.
     * @throws \Exception on failure.
     */
    private function orchestratePackageBuild($galley, $context, $submission, $plugin): string
    {
        // Get supplementary galleys from IDs
        $supplementaryGalleys = array_map(
            fn($id) => Repo::galley()->get($id),
            $this->supplementaryGalleyIds
        );

        // Filter out any null results
        $supplementaryGalleys = array_filter($supplementaryGalleys);

        // Create export path
        $exportPathBase = $plugin->getExportPath($context->getId());
        if (is_array($exportPathBase)) {
            throw new \Exception('Failed to create export path: ' . json_encode($exportPathBase));
        }

        // Use existing packageBuilder
        $fileManager = new DNBFileManager($plugin);
        $packageBuilder = new DNBPackageBuilder($plugin, $fileManager);

        $exportPackageName = '';

        $result = $packageBuilder->assemblePackage(
            $galley,
            $supplementaryGalleys,
            $plugin->getSubmissionFilter(),
            $this->noValidation,
            $context,
            $exportPathBase,
            $exportPackageName,
            $this->submissionId
        );

        if (is_array($result)) {
            throw new \Exception('Package building failed: ' . json_encode($result));
        }

        return $exportPackageName; // Full path to TAR file
    }

    /**
     * Cleanup temporary export files generated for the job.
     *
     * @param DNBExportPlugin $plugin Plugin instance (used to determine paths).
     * @return void
     */
    private function cleanup($plugin): void
    {
        // Extract the export directory from the filename
        // Filename format: dnb/<contextId>-<timestamp>/<submissionId>-<galleyId>/package.tar.gz
        if ($this->filename && file_exists($this->filename)) {
            $exportDir = dirname(dirname($this->filename)); // Go up two levels to get base export dir
            $basedir = Config::getVar('files', 'files_dir');
            $fullExportPath = $basedir . '/' . $exportDir;

            // Only delete if it's within the expected export structure
            if (strpos($fullExportPath, $basedir . '/dnb/') === 0) {
                Services::get('file')->fs->deleteDirectory($fullExportPath);
            }
        }
    }

    /**
     * Get the display name for this job in the queue
     */
    public function displayName(): string
    {
        return sprintf('%s %d, Galley %d', self::DISPLAY_NAME_PREFIX, $this->submissionId, $this->galleyId);
    }

    /**
     * Fetch the DNB export plugin instance from the PKP registry.
     *
     * @return DNBExportPlugin
     * @throws \Exception if the plugin is not registered.
     */
    private function getDNBExportPlugin():DNBExportPlugin
    {
        \PKP\plugins\PluginRegistry::loadCategory('generic');
        $plugin = \PKP\plugins\PluginRegistry::getPlugin('importexport', 'DNBExportPlugin');

        if (!$plugin) {
            throw new \Exception('DNB Export Plugin not found in registry');
        }

        return $plugin;
    }

    private function executeDeposit($object, $context, $plugin): void
    {
        // Perform the actual transfer of the archive to the DNB hotfolder.  The
        // protocol may be SFTP or WebDAV; the plugin abstracted most of the
        // configuration in initCurl(), but we still need to set upload-specific
        // options here.  Curl is used because the DNB service does not accept a
        // simple HTTP POST. Guzzle and other HTTP client libraries were considered 
        // but do not support certificate handling and other required options as flexibly as 
        // curl for this use case.
        $curlCh = $this->initCurl($plugin);

        // Ensure curl is in upload mode and that we capture headers for
        // diagnostic purposes.  CURLOPT_PROTOCOLS restricts to SFTP/HTTPS so we
        // don't accidentally follow redirects to other protocols.
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

        $username = $plugin->getSetting($context->getId(), 'username');
        $password = $plugin->getSetting($context->getId(), 'password');
        $folderId = $plugin->getSetting($context->getId(), 'folderId');
        $folderId = trim($folderId, '/');

        if (!is_readable($this->filename)) {
            throw new \Exception('Package file is not readable: ' . $this->filename);
        }

        $fh = fopen($this->filename, 'rb');
        if (!$fh) {
            throw new \Exception('Unable to open package file: ' . $this->filename);
        }

        $server = DNB_SFTP_SERVER;
        $port = DNB_SFTP_PORT;

        if ($plugin->getSetting($context->getId(), 'connectionType')) {
            $server = DNB_WEBDAV_SERVER;
            $port = DNB_WEBDAV_PORT;
        }

        curl_setopt($curlCh, CURLOPT_URL, $server . $folderId . '/' . basename($this->filename));
        curl_setopt($curlCh, CURLOPT_PORT, $port);
        curl_setopt($curlCh, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($curlCh, CURLOPT_INFILESIZE, filesize($this->filename));
        curl_setopt($curlCh, CURLOPT_INFILE, $fh);

        // Create curl log file
        curl_setopt($curlCh, CURLOPT_VERBOSE, true);
        $logDir = Config::getVar('files', 'files_dir') . '/' . $plugin->getPluginSettingsPrefix();
        if (!is_dir($logDir) && !@mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            $logDir = null;
        }
        $logFile = $logDir ? $logDir . '/lastCurlError.log' : null;
        $logHandle = $logFile ? fopen($logFile, 'wb') : false;
        if ($logHandle) {
            curl_setopt($curlCh, CURLOPT_STDERR, $logHandle);
        }

        // Execute request
        $response = curl_exec($curlCh);
        $curlError = curl_error($curlCh);

        if ($logHandle) {
            fclose($logHandle);
        }

        if ($curlError || $response === false) {
            fclose($fh);

            $error = $curlError ?: 'Unknown curl error';
            throw new \Exception('DNB upload failed: ' . $error);
        }

        fclose($fh);

        // If we reach this point, the deposit was successful
        // Update submission status to 'deposited'
        $submission = Repo::submission()->get($this->submissionId);
        if ($submission) {
            Repo::submission()->edit($submission, [
                $plugin->getPluginSettingsPrefix() . '::status' => DNB_STATUS_DEPOSITED,
                $plugin->getPluginSettingsPrefix() . '::lastError' => null
            ]);
        }
    }

    /**
     * Initialize CURL with proxy settings
     */
    private function initCurl($plugin)
    {
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

    public function failed(\Throwable $exception): void
    {
        // Get plugin instance
        $plugin = $this->getDNBExportPlugin();

        // Format the error message properly
        $errorMessage = '';

        // Check if it's a DNB-specific exception that can be handled by handleExceptions()
        if ($exception instanceof DNBPluginException || $exception instanceof \ErrorException) {
            $errorTuple = $plugin->handleExceptions($exception, $this->submissionId);

            // handleExceptions() returns null for unhandled codes, so check the result
            if (is_array($errorTuple) && count($errorTuple) >= 1) {
                // Format: [locale_key, param] or just [locale_key]
                $localeKey = $errorTuple[0];
                $param = $errorTuple[1] ?? '';
                $errorMessage = __($localeKey, ['param' => $param]);
            } else {
                // handleExceptions returned null - use raw exception message
                $errorMessage = $exception->getMessage();
            }
        } else {
            // For standard exceptions (curl errors, etc.), use the exception message directly
            $errorMessage = $exception->getMessage();
        }

        // Add detailed context to the error message for debugging
        $detailedMessage = $errorMessage . ', ' .
            'job_name: ' . $this->displayName() . ', ' .
            'file: ' . ($this->filename ?? 'not created');

        // Update submission status to 'failed' and store the detailed error
        $submission = Repo::submission()->get($this->submissionId);
        if ($submission) {
            Repo::submission()->edit($submission, [
                $plugin->getPluginSettingsPrefix() . '::status' => DNB_EXPORT_STATUS_FAILED,
                $plugin->getPluginSettingsPrefix() . '::lastError' => $detailedMessage
            ]);
        }

        // Log to PHP error log for debugging
        error_log('DNB Export Job Failed: ' . $detailedMessage);

        // Cleanup any partial files
        try {
            $this->cleanup($plugin);
        } catch (\Exception $e) {
            // Ignore cleanup errors during failure handling
            error_log('DNB Export Job cleanup failed: ' . $e->getMessage());
        }
    }
}
