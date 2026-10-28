<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('host_name')->nullable();
            $t->text('description')->nullable();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at')->nullable();
            $t->string('venue_name')->nullable();
            $t->string('venue_address')->nullable();
            $t->string('cover_path')->nullable();
            $t->string('theme', 20)->default('ocean');
            $t->string('font', 20)->default('modern');
            $t->boolean('allow_companions')->default(true);
            $t->unsignedTinyInteger('max_companions')->default(2);
            $t->dateTime('rsvp_deadline')->nullable();
            $t->boolean('allow_items')->default(false);
            $t->boolean('allow_comments')->default(true);
            $t->timestamps();
        });

        Schema::create('party_guests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('party_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('token', 40)->unique();
            $t->string('response', 10)->nullable(); // yes | maybe | no
            $t->unsignedTinyInteger('companions')->default(0);
            $t->text('message')->nullable();
            $t->timestamp('responded_at')->nullable();
            $t->timestamp('invited_at')->nullable();
            $t->timestamp('reminded_at')->nullable();
            $t->timestamps();
        });

        Schema::create('party_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('party_id')->constrained()->cascadeOnDelete();
            $t->string('label', 120);
            $t->foreignId('guest_id')->nullable()->constrained('party_guests')->nullOnDelete();
            $t->boolean('added_by_guest')->default(false);
            $t->timestamps();
        });

        Schema::create('party_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('party_id')->constrained()->cascadeOnDelete();
            $t->foreignId('guest_id')->nullable()->constrained('party_guests')->nullOnDelete();
            $t->string('author', 120);
            $t->boolean('by_host')->default(false);
            $t->text('body');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_comments');
        Schema::dropIfExists('party_items');
        Schema::dropIfExists('party_guests');
        Schema::dropIfExists('parties');
    }
};
