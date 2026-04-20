<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Index untuk tabel attendances
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['user_id', 'date']);
        });

        // 2. Index untuk tabel change_shift_requests (Tabel asli dari model ShiftSubmission)
        Schema::table('change_shift_requests', function (Blueprint $table) {
            $table->index(['user_id', 'date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'date']);
        });

        Schema::table('change_shift_requests', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'date', 'status']);
        });
    }
};
