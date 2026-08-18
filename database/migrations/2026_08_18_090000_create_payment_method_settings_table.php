<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_method_settings', function (Blueprint $table) {
            $table->id();
            $table->string('method')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('qris_mode')->nullable();
            $table->text('qris_static_string')->nullable();
            $table->timestamps();
        });

        DB::table('payment_method_settings')->insert([
            ['method' => 'cash', 'is_active' => true, 'qris_mode' => null, 'qris_static_string' => null, 'created_at' => now(), 'updated_at' => now()],
            ['method' => 'qris', 'is_active' => true, 'qris_mode' => 'edc', 'qris_static_string' => null, 'created_at' => now(), 'updated_at' => now()],
            ['method' => 'transfer', 'is_active' => true, 'qris_mode' => null, 'qris_static_string' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_method_settings');
    }
};
