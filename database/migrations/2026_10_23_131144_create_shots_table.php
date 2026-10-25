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
        Schema::create('shots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('seat');      // who fired
            $table->unsignedTinyInteger('cell_row');
            $table->unsignedTinyInteger('cell_col');
            $table->string('result', 4);              // hit or miss
            $table->timestamps();

            // a second safety net: the database refuses a repeat shot at the same cell
            $table->unique(['game_id', 'seat', 'cell_row', 'cell_col']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shots');
    }
};
