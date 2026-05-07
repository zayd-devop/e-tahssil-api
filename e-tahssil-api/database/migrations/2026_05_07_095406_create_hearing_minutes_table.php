<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('hearing_minutes', function (Blueprint $table) {
            $table->id();
            $table->string('file_number')->nullable();      // الرقم الكامل للملف
            $table->string('judgment_type')->nullable();    // نوع الحكم
            $table->string('judgment_date')->nullable();    // تاريخ الحكم
            $table->string('judgment_number')->nullable();  // رقم الحكم/القرار
            $table->string('ordinal_number')->nullable();   // رقم الترتيبي للحكم
            $table->text('decision_content')->nullable();   // مضمون المقرر
            $table->string('judge')->nullable();            // القاضي أو المستشار المقرر
            $table->string('subject')->nullable();          // الموضوع
            $table->string('result_color')->nullable();     // Pour l'affichage UI
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('hearing_minutes');
    }
};
