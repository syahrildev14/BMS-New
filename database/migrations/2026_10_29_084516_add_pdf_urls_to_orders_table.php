<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // One consolidated Surat Jalan PDF and one consolidated Tanda
            // Terima PDF per Order, covering every PRIMARY/ADDON SuratJalan
            // underneath it — Dapur downloads one file regardless of how many
            // separate shipments the Order ended up needing. Regenerated
            // (overwritten) whenever a new SuratJalan/STT is added.
            $table->string('pdf_url')->nullable()->after('notes');
            $table->string('tanda_terima_pdf_url')->nullable()->after('pdf_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['pdf_url', 'tanda_terima_pdf_url']);
        });
    }
};
