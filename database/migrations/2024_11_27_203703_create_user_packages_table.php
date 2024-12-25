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
        Schema::create('user_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->string('package_id')->nullable();
            $table->string('package_name')->nullable();
            $table->string('package_type')->nullable();
            $table->string('session')->nullable();
            $table->string('amount')->nullable();
            $table->dateTime('subscribed_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->enum('is_active', ['0', '1'])->default('1');
            $table->longText('json')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_packages');
    }
};
