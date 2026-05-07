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
    Schema::create('case_references', function (Blueprint $table) {
        $table->id();
        $table->string('file_number')->unique(); // Le lien entre les deux fichiers
        $table->text('plaintiff')->nullable(); // المدعي
        $table->text('defendant')->nullable(); // المدعى عليه
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_references');
    }
};
