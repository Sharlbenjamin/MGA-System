<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_titles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->unsignedInteger('level')->default(1)->comment('Higher = more senior, for hierarchy');
            $table->string('department')->comment('Operation, Financial, Client Network, Provider Network');
            $table->decimal('bonus_multiplier', 5, 2)->default(1.00)->comment('1.0, 1.5, 2.0 for bonus calculation');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_titles');
    }
};
