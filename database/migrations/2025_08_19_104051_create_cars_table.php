<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('cars');
        
        Schema::create('cars', function (Blueprint $table) {
            $table->string('ad_id', 255)->primary();
            $table->timestamp('activated_at')->nullable();
            $table->integer('category_id')->nullable();
            $table->string('uuid', 255)->nullable()->index();
            $table->boolean('has_whatsapp_number')->default(false);
            $table->integer('seating_capacity')->nullable();
            $table->string('engine_capacity', 50)->nullable();
            $table->string('target_market', 100)->nullable();
            $table->boolean('is_premium')->default(false);
            $table->string('make', 100)->nullable()->index();
            $table->string('model', 100)->nullable()->index();
            $table->string('trim', 100)->nullable();
            $table->text('url')->nullable();
            $table->text('title')->nullable();
            $table->string('seller_name', 100)->nullable();
            $table->string('seller_phone_number', 20)->nullable();
            $table->string('seller_type', 50)->nullable();
            $table->timestamp('posted_on')->nullable()->index();
            $table->integer('year')->nullable()->index();
            $table->decimal('price', 15, 2)->nullable()->index();
            $table->integer('kilometers')->nullable()->index();
            $table->string('color', 50)->nullable();
            $table->integer('doors')->nullable();
            $table->integer('cylinders')->nullable();
            $table->string('warranty', 100)->nullable();
            $table->string('body_condition', 50)->nullable();
            $table->string('mechanical_condition', 50)->nullable();
            $table->string('fuel_type', 50)->nullable()->index();
            $table->string('regional_specs', 50)->nullable();
            $table->string('body_type', 50)->nullable()->index();
            $table->string('steering_side', 50)->nullable();
            $table->string('horsepower', 50)->nullable();
            $table->string('transmission_type', 50)->nullable();
            $table->text('location')->nullable()->index();
            $table->text('image_urls')->nullable();
            $table->timestamps();
            
            $table->index(['make', 'model']);
            $table->index(['price', 'year']);
            $table->index(['body_type', 'fuel_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('cars');
    }
};