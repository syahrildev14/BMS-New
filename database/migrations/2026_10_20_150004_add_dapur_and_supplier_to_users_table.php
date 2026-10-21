<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('dapur_id')
                ->nullable()
                ->after('id')
                ->constrained('dapurs')
                ->nullOnDelete();

            $table->foreignId('supplier_id')
                ->nullable()
                ->after('dapur_id')
                ->constrained('suppliers')
                ->nullOnDelete();

            $table->boolean('is_active')->default(true)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dapur_id');
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn('is_active');
        });
    }
};
