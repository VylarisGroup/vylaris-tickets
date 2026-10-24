<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('type', 10)->default('onsite');       // onsite | digital | hybrid
            $table->string('status', 10)->default('draft');      // draft | published | cancelled
            $table->string('venue_name')->nullable();
            $table->string('venue_address')->nullable();
            $table->string('online_url')->nullable();
            $table->text('online_instructions')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('doors_open_at')->nullable();
            $table->dateTime('sales_start_at')->nullable();
            $table->dateTime('sales_end_at')->nullable();
            $table->string('currency', 3)->default('CHF');
            $table->boolean('door_sales_enabled')->default(true);
            $table->timestamps();
            $table->unique(['organizer_id', 'slug']);
            $table->index(['status', 'starts_at']);
        });

        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('price_cents')->default(0);
            $table->unsignedInteger('quota')->nullable();
            $table->unsignedSmallInteger('max_per_order')->default(10);
            $table->dateTime('sales_start_at')->nullable();
            $table->dateTime('sales_end_at')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('type', 10)->default('percent');      // percent | fixed
            $table->unsignedInteger('value');                    // Prozent (1-100) oder Rappen/Cent
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organizer_id', 'code']);
        });

        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'ticket_type_id', 'notified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('ticket_types');
        Schema::dropIfExists('events');
    }
};
