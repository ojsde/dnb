<?php

/**
 * @file plugins/generic/dnb/DNBPlugin.php
 *
 * Copyright (c) 2021 Center for Digital Systems (CeDiS), Universitätsbibliothek Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the plugin file LICENSE.
 *
 * @class DNBPlugin
 * @brief Generic plugin wrapper for DNB export functionality
 */

namespace APP\plugins\generic\dnb;

use APP\core\Application;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use APP\plugins\generic\dnb\DNBExportPlugin;
use APP\plugins\generic\dnb\DNBPluginMigration;

class DNBPlugin extends GenericPlugin {
    
    private ?DNBExportPlugin $_exportPlugin = null;

    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null) {
        $success = parent::register($category, $path, $mainContextId);
        
        if ($success) {
            // If the system isn't installed, or is performing an upgrade, don't
            // register hooks. This will prevent DB access attempts before the
            // schema is installed.
            if (Application::isUnderMaintenance()) {
                return true;
            }

            // Register the export plugin as a sub-plugin (must be done before getEnabled check)
            PluginRegistry::register('importexport', new DNBExportPlugin($this), $this->getPluginPath());
            $this->_exportPlugin = PluginRegistry::getPlugin('importexport', 'DNBExportPlugin');

            if ($this->getEnabled($mainContextId)) {
                $this->_pluginInitialization();
            }
        }
        
        return $success;
    }

    /**
     * Helper to register hooks
     */
    private function _pluginInitialization() {
        // Register API endpoints if this is an API request
        $request = Application::get()->getRequest();
        $router = $request->getRouter();
        
        if ($router instanceof \PKP\core\APIRouter) {
            Hook::add("APIHandler::endpoints::{$router->getEntity()}", 
                [$this->_exportPlugin, 'setupAPIEndpoints']);
        }
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName() {
        return __('plugins.generic.dnb.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription() {
        return __('plugins.generic.dnb.description');
    }

	/**
	 * @copydoc Plugin::getInstallMigration()
	 */
	function getInstallMigration() {
		return new DNBPluginMigration();
	}

    /**
     * Get the export plugin instance
     */
    public function getExportPlugin(): ?DNBExportPlugin {
        return $this->_exportPlugin;
    }
}
