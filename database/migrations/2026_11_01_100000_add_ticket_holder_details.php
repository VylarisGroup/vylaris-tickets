<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('personalize_tickets')->default(false);
            $table->json('attendee_fields')->nullable();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->date('holder_birthdate')->nullable();
            $table->string('holder_email', 190)->nullable();
            $table->string('holder_phone', 40)->nullable();
            $table->string('holder_city', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn(['personalize_tickets', 'attendee_fields']));
        Schema::table('tickets', fn (Blueprint $t) => $t->dropColumn(['holder_birthdate', 'holder_email', 'holder_phone', 'holder_city']));
    }
};
