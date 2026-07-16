<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->index();
            $table->string('event_type')->index();
            $table->json('channels'); // stores e.g. {"mail": true, "database": true, "sms": false}
            $table->timestamps();

            $table->unique(['user_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
