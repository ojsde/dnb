<?php
/**
 * @file classes/components/DNBSubmissionsList.php
 *
 * Copyright (c) 2021 Center for Digital Systems (CeDiS), Universitätsbibliothek Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the plugin file LICENSE.
 *
 * @class DNBSubmissionsList
 *
 * @ingroup plugins_generic_dnb
 *
 * @brief A component for displaying DNB submissions in a table
 */

namespace APP\plugins\generic\dnb\classes\components;

use APP\core\Application;

define('DNB_SUBMISSIONS_LIST', 'dnbSubmissionsListComponent');

class DNBSubmissionsList
{
    /** @var string Component ID */
    public $id = DNB_SUBMISSIONS_LIST;

    /** @var string URL to the API endpoint where items can be retrieved */
    public $apiUrl = '';

    /** @var int How many items to display on one page in this list */
    public $count = 30;

    /** @var array Query parameters to pass if this list executes GET requests */
    public $getParams = [];

    /** @var int Max number of items available to display in this list panel */
    public $itemsMax = 0;

    /** @var array Submission items to display */
    public $items = [];

    /** @var object Context object */
    protected $context = null;

    /**
     * Constructor
     * 
     * @param string $apiUrl API endpoint URL
     * @param array $items Array of submission items
     * @param object $context Context object
     */
    public function __construct($apiUrl, $items = [], $context = null)
    {
        $this->apiUrl = $apiUrl;
        $this->items = $items;
        $this->itemsMax = count($items);
        $this->context = $context;
    }

    /**
     * Get the configuration for this component
     * 
     * @return array Configuration array
     */
    public function getConfig()
    {
        $request = Application::get()->getRequest();
        
        return [
            'id' => $this->id,
            'apiUrl' => $this->apiUrl,
            'count' => $this->count,
            'items' => $this->items,
            'itemsMax' => $this->itemsMax,
            'getParams' => [
                'contextId' => $this->context ? $this->context->getId() : null,
                'count' => $this->count,
            ],
            'columns' => [
                ['name' => 'select', 'label' => ''],
                ['name' => 'id', 'label' => __('common.id')],
                ['name' => 'details', 'label' => __('common.details')],
                ['name' => 'status', 'label' => __('common.status')],
            ],
            'i18n' => [
                'selectAll' => __('common.selectAll'),
                'selectNone' => __('common.selectNone'),
                'deselectAll' => __('plugins.importexport.dnb.deselectAll'),
                'deposit' => __('plugins.importexport.dnb.deposit'),
                'export' => __('plugins.importexport.dnb.export'),
                'markRegistered' => __('plugins.importexport.common.status.markedRegistered'),
                'exclude' => __('plugins.importexport.dnb.exclude'),
                'filterAll' => __('common.all'),
                'filterNotDeposited' => __('plugins.importexport.dnb.status.notDeposited'),
                'filterDeposited' => __('plugins.importexport.dnb.status.deposited'),
                'filterQueued' => __('plugins.importexport.dnb.status.queued'),
                'filterFailed' => __('plugins.importexport.dnb.status.failed'),
                'filterMarkedRegistered' => __('plugins.importexport.common.status.markedRegistered'),
                'filterExcluded' => __('plugins.importexport.dnb.status.excluded'),
                'searchLabel' => __('plugins.importexport.dnb.searchLabel'),
                'filterHint' => __('plugins.importexport.dnb.filterHint'),
                'noResultsFiltered' => __('plugins.importexport.dnb.noResultsFiltered'),
                'noResults' => __('plugins.importexport.dnb.noResults'),
                'itemCount' => __('plugins.importexport.dnb.itemCount'),
                'itemCountFiltered' => __('plugins.importexport.dnb.itemCountFiltered'),
            ],
            'constants' => [
                'EXPORT_STATUS_NOT_DEPOSITED' => \APP\plugins\PubObjectsExportPlugin::EXPORT_STATUS_NOT_DEPOSITED,
                'EXPORT_STATUS_MARKEDREGISTERED' => \APP\plugins\PubObjectsExportPlugin::EXPORT_STATUS_MARKEDREGISTERED,
                'EXPORT_STATUS_REGISTERED' => \APP\plugins\PubObjectsExportPlugin::EXPORT_STATUS_REGISTERED,
                'DNB_STATUS_DEPOSITED' => DNB_STATUS_DEPOSITED,
                'DNB_EXPORT_STATUS_QUEUED' => DNB_EXPORT_STATUS_QUEUED,
                'DNB_EXPORT_STATUS_FAILED' => DNB_EXPORT_STATUS_FAILED,
                'DNB_EXPORT_STATUS_MARKEXCLUDED' => DNB_EXPORT_STATUS_MARKEXCLUDED,
            ]
        ];
    }
}
