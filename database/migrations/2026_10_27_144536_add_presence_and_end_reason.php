<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::table('game_players', function (Blueprint $table) {
        $table->timestamp('last_seen_at')->nullable()->after('ready');
    });

    Schema::table('games', function (Blueprint $table) {
        $table->string('end_reason', 12)->nullable()->after('rematch_code'); // 'sunk' or 'forfeit'
    });
}

public function down(): void
{
    Schema::table('game_players', function (Blueprint $table) {
        $table->dropColumn('last_seen_at');
    });

    Schema::table('games', function (Blueprint $table) {
        $table->dropColumn('end_reason');
    });
}
};
