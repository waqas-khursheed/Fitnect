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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreignId('influencer_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->enum('type', ['local_meetup', 'live_session']);
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('fee');
            $table->double('platform_fee');
            $table->double('merchant_fee');
            $table->double('profit');
            $table->string('strip_charge_id')->nullable();
            $table->string('strip_refund_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->enum('status', ['pending', 'cancel', 'complete'])->default('pending');
            $table->enum('is_reminder', ['0', '1'])->default('0');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
