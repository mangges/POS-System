<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('unit')->constrained()->nullOnDelete();
        });

        DB::table('raw_materials')->select('unit')->distinct()->pluck('unit')->each(function (string $symbol) {
            $unitId = DB::table('units')->where('symbol', $symbol)->value('id');

            if (! $unitId) {
                $unitId = DB::table('units')->insertGetId([
                    'name' => $symbol,
                    'symbol' => $symbol,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('raw_materials')->where('unit', $symbol)->update(['unit_id' => $unitId]);
        });

        Schema::table('raw_materials', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }

    public function down(): void
    {
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->string('unit')->default('')->after('name');
        });

        DB::table('raw_materials')->get()->each(function ($row) {
            $symbol = DB::table('units')->where('id', $row->unit_id)->value('symbol') ?? '';
            DB::table('raw_materials')->where('id', $row->id)->update(['unit' => $symbol]);
        });

        Schema::table('raw_materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
        });
    }
};
