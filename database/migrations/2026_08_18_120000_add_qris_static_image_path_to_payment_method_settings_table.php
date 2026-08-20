<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_method_settings', function (Blueprint $table) {
            $table->string('qris_static_image_path')->nullable()->after('qris_static_string');
        });
    }

    public function down(): void
    {
        Schema::table('payment_method_settings', function (Blueprint $table) {
            $table->dropColumn('qris_static_image_path');
        });
    }
};
