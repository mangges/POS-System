<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores generated QR code metadata. Each record corresponds to one
     * active QR code per table. When QR is re-generated the old record is
     * replaced (or a new one created) and the old file is deleted.
     */
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained('tables')->cascadeOnDelete();

            // The full URL encoded into the QR image
            $table->string('qr_url');

            // Relative path inside storage/app/public  (e.g. qrcodes/table_3/...)
            $table->string('file_path');

            $table->timestamps();

            // One active QR record per table at a time
            $table->unique('table_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
