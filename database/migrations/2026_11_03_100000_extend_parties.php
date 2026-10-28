<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $t) {
            $t->text('invite_message')->nullable();
            $t->json('schedule')->nullable();
            $t->json('questions')->nullable();
            $t->unsignedTinyInteger('auto_remind_days')->nullable();
        });
        Schema::table('party_guests', function (Blueprint $t) {
            $t->json('companion_names')->nullable();
            $t->json('answers')->nullable();
            $t->timestamp('auto_reminded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('party_guests', fn (Blueprint $t) => $t->dropColumn(['companion_names', 'answers', 'auto_reminded_at']));
        Schema::table('parties', fn (Blueprint $t) => $t->dropColumn(['invite_message', 'schedule', 'questions', 'auto_remind_days']));
    }
};
