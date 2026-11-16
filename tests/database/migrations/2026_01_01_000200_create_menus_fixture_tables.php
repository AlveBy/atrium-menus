<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tree of sections and a flat list of articles to link and unfold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_test_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->string('name');
            $table->string('code');
            $table->boolean('active')->default(true);
            $table->integer('sort')->default(500);
            $table->timestamps();
        });

        Schema::create('menu_test_articles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->boolean('active')->default(true);
            $table->integer('sort')->default(500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_test_articles');
        Schema::dropIfExists('menu_test_sections');
    }
};
