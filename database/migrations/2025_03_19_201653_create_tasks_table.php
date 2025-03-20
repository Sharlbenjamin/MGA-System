<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Assigned user
            $table->foreignId('file_id')->nullable()->constrained()->onDelete('cascade'); // Linked file
            // Related models (Lead, ProviderLead, Branch, Patient, Client)
            $table->nullableMorphs('taskable'); 
            
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('department');
            $table->dateTime('due_date')->nullable();
            $table->boolean('is_done')->default(false);
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete(); // Who completed it

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};