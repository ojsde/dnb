<?php

/**
 * @file plugins\generic\dnb\classes\export\DNBExportJob.php
 */

namespace APP\plugins\generic\dnb\classes\export;

use APP\facades\Repo;
use PKP\jobs\BaseJob;
use PKP\config\Config;

class DNBExportJob extends BaseJob
{
    protected int $galleyId;
    protected int $contextId;
    protected int $submissionId;
    protected string $filename;

    public function __construct(int $galleyId, int $contextId, int $submissionId, string $filename)
    {
        parent::__construct();

        $this->galleyId = $galleyId;
        $this->contextId = $contextId;
        $this->submissionId = $submissionId;
        $this->filename = $filename;
    }

    public function handle(): void
    {
        // Retrieve the objects from their IDs
        $galley = Repo::galley()->get($this->galleyId);
        $context = app('context')->get($this->contextId);

        if (!$galley || !$context) {
            throw new \Exception('Galley or Context not found for DNB export');
        }

        // Get plugin from registry
        \PKP\plugins\PluginRegistry::loadCategory('generic');
        $plugin = \PKP\plugins\PluginRegistry::getPlugin('importexport', 'DNBExportPlugin');
        
        if (!$plugin) {
            throw new \Exception('DNB Export Plugin not found in registry');
        }

        // Perform the complete deposit transfer via curl
        $this->executeDeposit($galley, $context, $plugin);
    }

    /**
     * Get the display name for this job in the queue
     */
    public function displayName(): string
    {
        return sprintf('DNB Export Job - Submission %d, Galley %d', $this->submissionId, $this->galleyId);
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
    }

    /**
     * Initialize CURL with proxy settings
     */
    private function initCurl($plugin) {
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
        \Log::error('DNB export job failed: ' . $exception->getMessage(), [
            'job_name' => $this->displayName(),
            'submission_id' => $this->submissionId,
            'galley_id' => $this->galleyId,
            'context_id' => $this->contextId,
            'file' => $this->filename,
            'exception' => $exception->getTraceAsString()
        ]);
    }
}
