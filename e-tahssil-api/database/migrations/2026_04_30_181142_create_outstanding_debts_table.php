<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outstanding_debts', function (Blueprint $table) {
            $table->id();

            $table->string('collectionFileNumber')->unique();
            $table->string('fullName');

            $table->string('judgmentNumber')->nullable();
            $table->string('judgmentDate')->nullable();

            $table->string('assumptionsNumber')->nullable();
            $table->string('assumptionsDate')->nullable();

            $table->decimal('fines', 10, 2)->default(0);
            $table->decimal('monetaryConvictions', 10, 2)->default(0);
            $table->decimal('expenses', 10, 2)->default(0);

            $table->string('lastProcedure')->nullable();
            $table->string('procedureDate')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outstanding_debts');
    }
};
