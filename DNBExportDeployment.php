<?php
/**
 * @defgroup plugins_importexport_dnb DNB export plugin
 */

/**
 * @file plugins/importexport/dnb/DNBExportDeployment.inc.php
 *
 * Copyright (c) 2021 Center for Digital Systems (CeDiS), Universitätsbibliothek Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan, Ronald Steffen
 *
 * @class DNBExportDeployment
 * @ingroup plugins_importexport_dnb
 *
 * @brief Base class configuring the DNB export process to an
 * application's specifics.
 */

namespace APP\plugins\importexport\dnb;

// XML attributes
define('DNB_XMLNS' , 'http://www.loc.gov/MARC21/slim');
define('DNB_XMLNS_XSI' , 'http://www.w3.org/2001/XMLSchema-instance');
define('DNB_XSI_SCHEMALOCATION' , 'http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd');

class DNBExportDeployment {
	/** @var Context The current import/export context */
	var $_context;

	/** @var Plugin The current import/export plugin */
	var $_plugin;

	/**
	 * Get the plugin cache
	 * @return PubObjectCache
	 */
	function getCache() {
		return $this->_plugin->getCache();
	}

	/**
	 * Constructor
	 * @param $context Context
	 * @param $plugin PubObjectsPubIdExportPlugin
	 */
	function __construct($context, $plugin) {
		$this->setContext($context);
		$this->setPlugin($plugin);
	}

	//
	// Deployment items for subclasses to override
	//
	/**
	 * Get the root lement name
	 * @return string
	 */
	function getRootElementName() {
		return 'collection';
	}

	/**
	 * Get the namespace URN
	 * @return string
	 */
	function getNamespace() {
		return DNB_XMLNS;
	}

	/**
	 * Get the schema instance URN
	 * @return string
	 */
	function getXmlSchemaInstance() {
		return DNB_XMLNS_XSI;
	}

	/**
	 * Get the schema location URL
	 * @return string
	 */
	function getXmlSchemaLocation() {
		return DNB_XSI_SCHEMALOCATION;
	}

	/**
	 * Get the schema filename.
	 * @return string
	 */
	function getSchemaFilename() {
		return $this->getXmlSchemaLocation();
	}

	//
	// Getter/setters
	//
	/**
	 * Set the import/export context.
	 * @param $context Context
	 */
	function setContext($context) {
		$this->_context = $context;
	}

	/**
	 * Get the import/export context.
	 * @return Context
	 */
	function getContext() {
		return $this->_context;
	}

	/**
	 * Set the import/export plugin.
	 * @param $plugin ImportExportPlugin
	 */
	function setPlugin($plugin) {
		$this->_plugin = $plugin;
	}

	/**
	 * Get the import/export plugin.
	 * @return ImportExportPlugin
	 */
	function getPlugin() {
		return $this->_plugin;
	}

}

?>
