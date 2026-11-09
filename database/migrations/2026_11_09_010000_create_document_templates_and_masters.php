<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('document_type')->index();
            $table->string('file_path');
            $table->string('original_filename');
            $table->json('placeholders');
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('armadas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('plate_number');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('account_holder');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('surat_jalans', function (Blueprint $table) {
            $table->foreignId('document_template_id')->nullable()->constrained('document_templates')->restrictOnDelete();
            $table->string('armada_name')->nullable();
            $table->string('plate_number')->nullable();
            $table->string('driver_name')->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('document_template_id')->nullable()->constrained('document_templates')->restrictOnDelete();
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('bank_owner')->nullable();
            // A re-rendered PDF must keep the date the invoice was approved.
            $table->date('issued_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_template_id');
            $table->dropColumn(['bank_name', 'bank_account', 'bank_owner', 'issued_at']);
        });

        Schema::table('surat_jalans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_template_id');
            $table->dropColumn(['armada_name', 'plate_number', 'driver_name']);
        });

        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('armadas');
        Schema::dropIfExists('document_templates');
    }
};
