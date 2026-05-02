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
        Schema::create('procedures',function(Blueprint $table){
            $table->id();
            $table->string('fileNumber')->nullable();
            $table->json('parties')->nullable();
            $table->text('role')->nullable();
            $table->text('address')->nullable();
            $table->text('decision')->nullable();
            $table->text('judgmentNumber')->nullable();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procedures');
    }
};
