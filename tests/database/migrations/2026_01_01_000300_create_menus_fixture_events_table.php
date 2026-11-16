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
 * Events: dates of activity, a name per language, no `active` and no `sort`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_test_events', function (Blueprint $table): void {
            $table->id();
            $table->text('name');
            $table->string('code');
            $table->dateTime('active_from')->nullable();
            $table->dateTime('active_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_test_events');
    }
};
