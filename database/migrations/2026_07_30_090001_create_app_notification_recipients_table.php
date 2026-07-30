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
        Schema::create('app_notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('app_notifications')->cascadeOnDelete();
            $table->enum('recipient_scope', ['role', 'individual'])->default('individual');
            $table->enum('recipient_role', ['admin', 'cashier'])->nullable();
            $table->nullableMorphs('recipient'); // recipient_type stores morph map alias ('user', 'order'), see AppServiceProvider::boot()
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['recipient_role', 'is_read']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_notification_recipients');
    }
};
