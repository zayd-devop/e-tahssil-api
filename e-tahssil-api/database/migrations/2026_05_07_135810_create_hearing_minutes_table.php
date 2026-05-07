<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hearing_minutes', function (Blueprint $table) {
            $table->id();
            $table->string('file_number'); // رقم الملف
            $table->text('plaintiff_lawyer')->nullable(); // المدعي/المستأنف ومحاميه
            $table->text('defendant_lawyer')->nullable(); // المدعى عليه
            $table->text('subject')->nullable(); // موضوع الدعوى
            $table->text('judge')->nullable(); // القاضي
            $table->text('result')->nullable(); // النتيجة
            $table->string('next_date')->nullable(); // تاريخ الجلسة المقبلة
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('hearing_minutes');
    }
};
