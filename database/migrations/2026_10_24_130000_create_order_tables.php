<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('opening_float_cents')->default(0);
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->integer('counted_cash_cents')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('number', 20)->unique();
            $table->string('access_token', 48)->unique();
            $table->string('status', 12)->default('pending');   // pending | paid | cancelled | expired | refunded
            $table->string('channel', 8)->default('online');    // online | door
            $table->string('payment_method', 12)->nullable();   // mollie | stripe | bank | cash | external | free
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('currency', 3);
            $table->integer('subtotal_cents')->default(0);
            $table->integer('discount_cents')->default(0);
            $table->integer('total_cents')->default(0);
            $table->integer('vat_cents')->default(0);
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('voucher_code', 40)->nullable();
            $table->string('provider_ref')->nullable()->index();
            $table->text('payment_url')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['event_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seat_id')->nullable()->constrained()->nullOnDelete();
            // Gleich seat_id solange das Ticket aktiv ist, sonst NULL. Der Unique-Index verhindert doppelt vergebene Plätze.
            $table->unsignedBigInteger('seat_key')->nullable()->unique();
            $table->string('code', 32)->unique();
            $table->string('holder_name')->nullable();
            $table->integer('price_cents')->default(0);
            $table->string('status', 10)->default('valid');     // valid | cancelled
            $table->dateTime('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['event_id', 'ticket_type_id', 'status']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 30);
            $table->text('message')->nullable();
            $table->string('reporter_email')->nullable();
            $table->string('status', 12)->default('open');      // open | resolved | dismissed
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cash_sessions');
    }
};
