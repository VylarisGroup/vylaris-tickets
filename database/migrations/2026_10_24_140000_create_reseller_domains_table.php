<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->index();
            $table->string('verified_key')->nullable()->unique(); // = domain, solange verifiziert: eine Domain gehört höchstens einem Reseller
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('last_checked_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
            $table->unique(['reseller_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_domains');
    }
};
