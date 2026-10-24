<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('plan_path')->nullable();
            $table->unsignedSmallInteger('plan_w')->nullable();
            $table->unsignedSmallInteger('plan_h')->nullable();
            $table->timestamps();
        });

        Schema::table('seats', function (Blueprint $table) {
            $table->foreignId('seat_floor_id')->nullable()->after('seat_section_id')->constrained('seat_floors')->cascadeOnDelete();
            $table->string('kind', 10)->default('seat')->after('label');          // seat | table
            $table->unsignedSmallInteger('capacity')->default(1)->after('kind');   // Plätze am Tisch
            $table->string('table_mode', 10)->default('whole')->after('capacity'); // whole | seats
        });

        // Belegungsschlüssel: Platz-ID * 1000 + Sitz-Nummer am Tisch (0 bei Einzelplätzen).
        DB::table('tickets')->whereNotNull('seat_key')->update(['seat_key' => DB::raw('seat_key * 1000')]);

        // Bestehende Saalpläne bekommen eine Ebene.
        foreach (DB::table('seats')->select('event_id')->distinct()->pluck('event_id') as $eventId) {
            $floorId = DB::table('seat_floors')->insertGetId([
                'event_id' => $eventId, 'name' => 'Hauptraum', 'sort' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('seats')->where('event_id', $eventId)->update(['seat_floor_id' => $floorId]);
        }
    }

    public function down(): void
    {
        DB::table('tickets')->whereNotNull('seat_key')->update(['seat_key' => DB::raw('seat_key / 1000')]);

        Schema::table('seats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seat_floor_id');
            $table->dropColumn(['kind', 'capacity', 'table_mode']);
        });
        Schema::dropIfExists('seat_floors');
    }
};
