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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('password')->nullable();
            $table->enum('user_type', ['admin','user','influencer']);
            $table->enum('gender', ['male','female','other'])->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('profile_image')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('phone_number')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('website_link')->nullable();
            $table->longText('interest')->nullable();
            $table->longText('expertise')->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->longText('about')->nullable();
            $table->string('timezone')->nullable();

            $table->string('session')->default('1');
            $table->enum('package_type', ['free', 'monthly', 'yearly'])->default('free');
            $table->enum('package_name', ['plus', 'pro', 'premium']);

            $table->rememberToken()->nullable();
            $table->enum('is_profile_complete', ['0','1'])->default('0');
            $table->enum('device_type', ['ios','android','web'])->nullable();
            $table->longText('device_token')->nullable();
            $table->enum('social_type', ['google','facebook','twitter','instagram','apple','phone'])->nullable();
            $table->longText('social_token')->nullable();
            $table->enum('push_notification', ['0','1'])->default('1');
            $table->enum('is_verified', ['0','1'])->default('0');
            $table->enum('is_admin', ['0','1'])->default('0');
            $table->enum('is_social', ['0','1'])->default('0');
            $table->integer('verified_code')->nullable();
            $table->enum('is_blocked', ['0','1'])->default('0');
            $table->string('customer_id')->nullable();
            $table->string('account_id')->nullable();
            $table->enum('is_merchant_setup', ['0', '1'])->default('0');
            $table->enum('online_status', ['offline','online'])->default('online');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
