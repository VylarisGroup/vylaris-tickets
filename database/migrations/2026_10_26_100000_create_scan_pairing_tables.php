<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kopplung: PC-Anzeige <-> Smartphone als Kamera-Scanner
        Schema::create('scan_pairings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->string('code', 6)->index();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        // Protokoll aller Einlass-Prüfungen (Quelle der Live-Anzeige)
        Schema::create('scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pairing_id')->nullable()->constrained('scan_pairings')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20);
            $table->string('title');
            $table->string('detail')->nullable();
            $table->string('holder')->nullable();
            $table->string('ticket_type')->nullable();
            $table->string('seat')->nullable();
            $table->string('order_number')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['event_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_logs');
        Schema::dropIfExists('scan_pairings');
    }
};
