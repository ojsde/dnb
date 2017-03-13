<?php

/**
 * @defgroup plugins_importexport_dnb
 */

/**
 * @file plugins/importexport/dnb/index.php
 *
 * Copyright (c) 2017 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v2. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan
 * Last update: March 15, 2017
 *
 * @ingroup plugins_importexport_dnb
 * @brief Wrapper for DNB export plugin.
 *
 */

require_once('DNBExportPlugin.inc.php');

return new DNBExportPlugin();

?>
