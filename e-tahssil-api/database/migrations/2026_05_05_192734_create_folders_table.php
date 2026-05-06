<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();

            // --- Informations Générales (React) ---
            $table->string('dossier_num');       // رقم الملف
            $table->string('debtor_name');                 // الاسم الكامل للمدين
            $table->string('debtor_cin')->nullable();      // رقم البطاقة الوطنية
            $table->decimal('debt_amount', 15, 2);
            $table->string('document_type')->nullable();      // المبلغ المستحق
            $table->text('debtor_address')->nullable();
               // عنوان المدين

            // --- Traçabilité ---
            // L'ID du greffier (user) qui a créé le dossier
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folders');
    }
};
