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
        Schema::create('judicial_assistances', function (Blueprint $table) {
            $table->id();
            $table->string('file_year')->nullable();
            $table->string('collectionFileNumber')->unique();
            $table->string('judgmentNumber')->nullable();
            $table->string('judgmentDate')->nullable();
            $table->string('fullName');
            $table->string('assumptionsNumber')->nullable();
            $table->string('assumptionsDate')->nullable();
            $table->decimal('fines', 15, 2)->default(0);
            $table->decimal('monetaryConvictions', 15, 2)->default(0);
            $table->decimal('expenses', 15, 2)->default(0);
            $table->string('lastProcedure')->nullable();
            $table->string('procedureDate')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('judicial_assistances');
    }
};
