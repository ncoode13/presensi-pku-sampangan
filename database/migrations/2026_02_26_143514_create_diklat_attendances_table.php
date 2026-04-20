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
    Schema::create('diklat_attendances', function (Blueprint $table) {
        $table->id();
        $table->foreignId('diklat_event_id')->constrained('diklat_events')->onDelete('cascade');
        $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        $table->foreignId('marked_by')->constrained('users'); // Admin SDI yang input
        $table->timestamp('marked_at')->useCurrent();
        $table->timestamps();

        // Mencegah input ganda user yang sama di event yang sama
        $table->unique(['diklat_event_id', 'user_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diklat_attendances');
    }
};
