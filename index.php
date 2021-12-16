<?php

/**
 * @defgroup plugins_importexport_dnb DNB Export Plugin
 */

/**
 * @file plugins/importexport/dnb/index.php
 *
 * Copyright (c) 2021 Center for Digital Systems (CeDiS), Universitätsbibliothek Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan, Ronald Steffen
 *
 * @ingroup plugins_importexport_dnb
 * @brief Wrapper for DNB XML export plugin.
 *
 */

require_once('DNBExportPlugin.inc.php');

return new DNBExportPlugin();

?>
