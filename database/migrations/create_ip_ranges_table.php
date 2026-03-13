<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('iptools.storage.table', 'ip_ranges'), function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedTinyInteger('version');
            $table->binary('start_bin', 16);
            $table->binary('end_bin', 16);
            $table->json('metadata')->nullable();

            $table->index(['version', 'start_bin', 'end_bin'], 'ip_ranges_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('iptools.storage.table', 'ip_ranges'));
    }
};
