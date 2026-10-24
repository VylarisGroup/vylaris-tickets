<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('is_blocked');
            $table->string('access_key', 60)->nullable()->after('is_private');
            $table->timestamp('reminder_sent_at')->nullable();
        });

        Schema::table('organizers', function (Blueprint $table) {
            $table->string('terms_url', 500)->nullable();
            $table->string('privacy_url', 500)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('ticket_color', 7)->nullable();
            $table->text('ticket_note')->nullable();
            $table->unsignedSmallInteger('reminder_hours')->nullable();
            $table->string('report_frequency', 10)->default('none');
            $table->timestamp('report_sent_at')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('checked_out_at')->nullable();
        });

        Schema::create('event_sponsors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('url', 500)->nullable();
            $table->string('logo_path');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_sponsors');
        Schema::table('tickets', fn (Blueprint $t) => $t->dropColumn('checked_out_at'));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['terms_accepted_at', 'reminder_sent_at']));
        Schema::table('organizers', fn (Blueprint $t) => $t->dropColumn(['terms_url', 'privacy_url', 'logo_path', 'ticket_color', 'ticket_note', 'reminder_hours', 'report_frequency', 'report_sent_at']));
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn(['is_private', 'access_key', 'reminder_sent_at']));
    }
};
