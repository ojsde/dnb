<?php

/**
 * @file plugins\generic\dnb\classes\export\DNBExportJob.php
 */

namespace APP\plugins\generic\dnb\classes\export;

use APP\facades\Repo;
use PKP\jobs\BaseJob;
use PKP\config\Config;
use APP\core\Services;

if (!defined('DNB_STATUS_DEPOSITED')) define('DNB_STATUS_DEPOSITED', 'deposited');
if (!defined('DNB_EXPORT_STATUS_FAILED')) define('DNB_EXPORT_STATUS_FAILED', 'failed');
if (!defined('DNB_SFTP_SERVER')) define('DNB_SFTP_SERVER', 'sftp://ojs@rs-dev-3.5-ojs-3.5-sftp/');
if (!defined('DNB_SFTP_PORT')) define('DNB_SFTP_PORT', 22);
if (!defined('DNB_WEBDAV_SERVER')) define('DNB_WEBDAV_SERVER', 'NOT CONFIGURED IN DEBUG MODE');
if (!defined('DNB_WEBDAV_PORT')) define('DNB_WEBDAV_PORT', 443);

class DNBExportJob extends BaseJob
{
    protected int $galleyId;
    protected int $contextId;
    protected int $submissionId;
    protected array $supplementaryGalleyIds;
    protected string $filter;
    protected bool $noValidation;
    protected ?string $filename = null; // Pre-built package path (for manual exports)

    public function __construct(
        int $galleyId,
        int $contextId,
        int $submissionId,
        array $supplementaryGalleyIds = [],
        string $filter = 'galley=>dnb-xml',
        bool $noValidation = false,
        ?string $filename = null
    ) {
        parent::__construct();

        $this->galleyId = $galleyId;
        $this->contextId = $contextId;
        $this->submissionId = $submissionId;
        $this->supplementaryGalleyIds = $supplementaryGalleyIds;
        $this->filter = $filter;
        $this->noValidation = $noValidation;
        $this->filename = $filename; // If provided, skip package building
    }

    public function handle(): void
    {
        // Retrieve the objects from their IDs
        $galley = Repo::galley()->get($this->galleyId);
        $context = app('context')->get($this->contextId);
        $submission = Repo::submission()->get($this->submissionId);

        if (!$galley || !$context || !$submission) {
            throw new \Exception('Galley, Context, or Submission not found for DNB export');
        }

        // Get plugin from registry
        $plugin = $this->getDNBExportPlugin();

        // Step 1: Build the package (TAR archive with XML + PDFs) - only if not pre-built
        if ($this->filename === null) {
            $this->filename = $this->buildPackage($galley, $context, $submission, $plugin);
        } else {
            // Package already built (manual export) - just validate it exists
            if (!file_exists($this->filename)) {
                throw new \Exception('Pre-built package not found: ' . $this->filename);
            }
        }

        // Step 2: Perform the complete deposit transfer via curl
        $this->executeDeposit($galley, $context, $plugin);

        // Step 3: Cleanup temporary files (only if we built the package)
        if ($this->filename !== null && empty($this->supplementaryGalleyIds) === false) {
            // Job built the package, so clean it up
            $this->cleanup($plugin);
        }
    }

    /**
     * Build the export package (TAR archive with XML + files)
     */
    private function buildPackage($galley, $context, $submission, $plugin): string
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
        
        $result = $packageBuilder->buildPackage(
            $galley,
            $supplementaryGalleys,
            $this->filter,
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
     * Cleanup temporary export files
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
        return sprintf('DNB Export Job - Submission %d, Galley %d', $this->submissionId, $this->galleyId);
    }

    /**
     * Get the DNB Export Plugin from the registry
     */
    private function getDNBExportPlugin()
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
        // Guzzle HttpClient cannot be used here because DNB requires SFTP or WebDAV upload. 
        // WebDAV over HTTPS could theoretically work, but it still requires careful SSH/certificate handling.
        $curlCh = $this->initCurl($plugin);
        
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
        @mkdir($logDir, 0755, true); // Create if doesn't exist
        $verbose = fopen($logDir . '/curl.log', 'w+');
        if ($verbose) {
            curl_setopt($curlCh, CURLOPT_STDERR, $verbose);
        }

        // Execute request
        $response = curl_exec($curlCh);
        $curlError = curl_error($curlCh);

        if ($verbose) {
            fclose($verbose);
        }

        if ($curlError || $response === false) {
            curl_close($curlCh);
            fclose($fh);
            
            $error = $curlError ?: 'Unknown curl error';
            throw new \Exception('DNB upload failed: ' . $error);
        }

        curl_close($curlCh);
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
        // Log the failure with submission context
        $message = $exception->getMessage() . ', ' .
            'job_name: ' . $this->displayName() . ', ' .
            'submission_id: ' . $this->submissionId . ', ' .
            'galley_id: ' . $this->galleyId . ', ' .
            'file: ' . ($this->filename ?? 'not created');

        // Update submission status to 'failed'
        $plugin = $this->getDNBExportPlugin();
        $submission = Repo::submission()->get($this->submissionId);
        if ($submission) {
            Repo::submission()->edit($submission, [
                $plugin->getPluginSettingsPrefix() . '::status' => DNB_EXPORT_STATUS_FAILED,
                $plugin->getPluginSettingsPrefix() . '::lastError' => $message
            ]);
        }

        // Cleanup any partial files
        try {
            $this->cleanup($plugin);
        } catch (\Exception $e) {
            // Ignore cleanup errors during failure handling
        }
    }
}
