<?php

/**
 * @file VGWortMigration.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Copyright (c) 2000-2020 Heidelberg University Library
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class VGWortMigration
 * @brief Describe database table structures.
 */

namespace APP\plugins\generic\vgwort;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class VgwortMigration extends Migration {
    /**
     * Run the migrations.
     * @return void
     */
    public function up() {
        Schema::create('pixel_tags', function (Blueprint $table) {
            $table->bigInteger('pixel_tag_id')->autoIncrement();
            $table->bigInteger('context_id');
            $table->bigInteger('submission_id')->nullable();
            $table->bigInteger('chapter_id')->nullable();
            $table->string('private_code', 255);
            $table->string('public_code', 255);
            $table->string('domain', 255);
            $table->dateTime('date_ordered');
            $table->dateTime('date_assigned')->nullable();
            $table->dateTime('date_registered')->nullable();
            $table->dateTime('date_removed')->nullable();
            $table->smallInteger('status');
            $table->smallInteger('text_type');
            $table->text('message')->nullable();
        });
    }
}
