<?php
/**
 * @file classes/components/DNBCatalogTable.php
 *
 * Copyright (c) 2021 Center for Digital Systems (CeDiS), Universitaetsbibliothek Freie Universitaet Berlin
 * Distributed under the GNU GPL v3. For full terms see the plugin file LICENSE.
 *
 * @class DNBCatalogTable
 *
 * @ingroup plugins_generic_dnb
 *
 * @brief Component config for the DNB catalog table
 */

namespace APP\plugins\generic\dnb\classes\components;

define('DNB_CATALOG_TABLE', 'dnbCatalogTableComponent');

class DNBCatalogTable
{
    /** @var string Component ID */
    public $id = DNB_CATALOG_TABLE;

    /** @var array Catalog rows */
    public $items = [];

    /**
     * Constructor
     *
     * @param array $items Catalog rows
     */
    public function __construct($items = [])
    {
        $this->items = $items;
    }

    /**
     * Get the configuration for this component
     *
     * @return array Configuration array
     */
    public function getConfig()
    {
        return [
            'id' => $this->id,
            'items' => $this->items,
        ];
    }
}
