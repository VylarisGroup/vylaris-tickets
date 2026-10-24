<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reseller_domains', function (Blueprint $table) {
            $table->timestamp('plesk_alias_at')->nullable();
            $table->timestamp('plesk_cert_at')->nullable();
            $table->string('plesk_error', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reseller_domains', function (Blueprint $table) {
            $table->dropColumn(['plesk_alias_at', 'plesk_cert_at', 'plesk_error']);
        });
    }
};
