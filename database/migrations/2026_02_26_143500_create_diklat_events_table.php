<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('diklat_events', function (Blueprint $table) {
        $table->id();
        $table->foreignId('diklat_category_id')->constrained('diklat_categories')->onDelete('cascade');
        $table->string('title');
        $table->date('date');
        $table->integer('jpl'); // Nilai JPL acara ini
        $table->text('description')->nullable();
        $table->enum('status', ['upcoming', 'completed'])->default('upcoming');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diklat_events');
    }
};
