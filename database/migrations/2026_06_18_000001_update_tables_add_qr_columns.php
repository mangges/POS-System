<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds qr_token, name columns to the tables table and renames
     * the legacy "barcode" & "number" columns to align with the new
     * multi-tenant-ready schema (single-tenant for now).
     */
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            // Add human-readable name (e.g. "Meja 1", "VIP 3")
            $table->string('name')->nullable()->after('id');

            // Add unique token used in QR URL — not the table primary key
            $table->string('qr_token', 32)->unique()->nullable()->after('name');

            // Extend status enum to include 'reserved'
            $table->enum('status', ['available', 'occupied', 'reserved'])
                  ->default('available')
                  ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn(['name', 'qr_token']);
            $table->enum('status', ['available', 'occupied'])
                  ->default('available')
                  ->change();
        });
    }
};
