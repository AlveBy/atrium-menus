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
 * Menus of a site, each in one language or in all of them, and their items
 * as a tree (parent_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->nullable()->index();
            // Null: the menu is shown in every language that has no menu of its own.
            $table->string('language', 16)->nullable();
            $table->string('code', 64);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['code', 'site_id']);
        });

        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->index();
            $table->foreignId('parent_id')->nullable()->index();
            $table->integer('sort')->default(500);
            $table->string('title');
            $table->string('url', 2048)->nullable();
            $table->string('linkable_type')->nullable();
            $table->unsignedBigInteger('linkable_id')->nullable();
            $table->string('target', 16)->nullable();
            $table->string('icon', 100)->nullable();
            $table->boolean('active')->default(true);
            // A model whose records are listed under the item, to this depth.
            $table->string('source')->nullable();
            $table->unsignedTinyInteger('source_depth')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
