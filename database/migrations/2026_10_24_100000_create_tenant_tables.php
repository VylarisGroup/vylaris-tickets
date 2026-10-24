<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resellers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable()->unique();
            $table->string('brand_name')->nullable();
            $table->string('brand_color', 7)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('support_email')->nullable();
            $table->boolean('allow_registration')->default(true);
            $table->unsignedInteger('max_organizers')->nullable();
            $table->unsignedInteger('max_events_per_organizer')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('organizers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('contact_email')->nullable();
            $table->string('street')->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('CH');
            $table->string('vat_number')->nullable();
            $table->boolean('vat_enabled')->default(false);
            $table->decimal('vat_rate', 4, 2)->default(8.10);
            $table->string('default_currency', 3)->default('CHF');
            $table->string('iban')->nullable();
            $table->text('payment_settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('reseller_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('admin');
            $table->timestamps();
            $table->unique(['reseller_id', 'user_id']);
        });

        Schema::create('organizer_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('scanner');
            $table->timestamps();
            $table->unique(['organizer_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_user');
        Schema::dropIfExists('reseller_user');
        Schema::dropIfExists('organizers');
        Schema::dropIfExists('resellers');
    }
};
