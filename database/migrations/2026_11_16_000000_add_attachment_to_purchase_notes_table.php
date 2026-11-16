<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Optional scan/photo/PDF of the physical nota. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_notes', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('notes');
            $table->string('attachment_name')->nullable()->after('attachment_path');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_notes', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name']);
        });
    }
};
