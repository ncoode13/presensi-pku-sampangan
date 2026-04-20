<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diklat_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); 
            $table->string('value');         
            $table->string('display_name');  
            $table->string('type')->default('number'); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diklat_settings');
    }
};