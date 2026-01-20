<?php

/**
 * @file plugins/generic/dnb/DNBPluginMigration.php
 *
 * Copyright (c) 2021 Universitätsbibliothek Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DNBPluginMigration
 * @brief Describe database table structures.
 */

namespace APP\plugins\generic\dnb;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class DNBPluginMigration extends Migration {
    /**
     * Run the migrations.
     * @return void
     */
    public function up() {
        echo "Running DNBPluginMigration ...\n";
        // ===== Filters ===== //
        DB::table('filters')
            ->where('class_name', '=', 'plugins.importexport.dnb.filter.DNBXmlFilter')
            ->update(['class_name' => 'APP\plugins\generic\dnb\filter\DNBXmlFilter']);
    }
}