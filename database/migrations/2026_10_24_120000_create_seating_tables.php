<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('seating_mode', 10)->default('none')->after('status'); // none | grid | map
            $table->boolean('is_blocked')->default(false)->after('door_sales_enabled');
            $table->string('blocked_reason')->nullable()->after('is_blocked');
        });

        Schema::create('seat_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seat_section_id')->constrained()->cascadeOnDelete();
            $table->string('row_label', 10)->nullable();
            $table->unsignedSmallInteger('number')->default(1);
            $table->string('label', 40);
            $table->unsignedInteger('x')->default(0);
            $table->unsignedInteger('y')->default(0);
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();
            $table->unique(['event_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
        Schema::dropIfExists('seat_sections');
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['seating_mode', 'is_blocked', 'blocked_reason']);
        });
    }
};
