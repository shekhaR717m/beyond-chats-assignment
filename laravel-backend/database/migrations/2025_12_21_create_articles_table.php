<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('original_url');
            $table->longText('original_content');
            $table->longText('generated_content')->nullable();
            $table->unsignedBigInteger('generated_from_id')->nullable();
            $table->enum('status', ['original', 'generated'])->default('original');
            $table->timestamps();

            $table->foreign('generated_from_id')
                  ->references('id')
                  ->on('articles')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
