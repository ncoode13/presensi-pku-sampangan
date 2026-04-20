<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('content');
            
            $table->foreignId('category_id')->nullable()->constrained('announcement_categories')->nullOnDelete();
            
            $table->unsignedBigInteger('form_id')->nullable();
            
            $table->boolean('is_publish_to_all')->default(true);
            
            $table->json('target_criteria')->nullable(); 
            
            $table->string('attachment')->nullable();
            
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('announcements');
    }
};