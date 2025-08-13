<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarsTable extends Migration
{   
    public function up()
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->string('ad_id')->unique();
            #$table->integer('activated_at')->nullable();
            $table->dateTime('activated_at')->nullable();
            $table->integer('category_id')->nullable();
            $table->string('uuid')->unique();
            $table->boolean('has_whatsapp_number')->default(false);
            $table->string('seating_capacity')->nullable();
            $table->string('engine_capacity')->nullable();
            $table->string('target_market')->nullable();
            $table->boolean('is_premium')->default(false);
            $table->string('make');
            $table->string('model');
            $table->string('trim')->nullable();
            $table->string('url');
            $table->string('title');
            $table->string('seller_name')->nullable();
            $table->string('seller_phone_number')->nullable();
            $table->string('seller_type');
            #$table->integer('posted_on')->nullable();
            $table->dateTime('posted_on')->nullable();

            $table->integer('year');
            $table->decimal('price', 10, 2);
            $table->integer('kilometers');
            $table->string('color');
            $table->integer('doors')->nullable();
            $table->integer('cylinders')->nullable();
            $table->string('warranty')->nullable();
            $table->string('body_condition')->nullable();
            $table->string('mechanical_condition')->nullable();
            $table->string('fuel_type');
            $table->string('regional_specs');
            $table->string('body_type');
            $table->string('steering_side');
            $table->string('horsepower')->nullable();
            $table->string('transmission_type');
            $table->string('location');
            $table->text('image_urls');
            $table->timestamps();
        });
        
    }
   
  

    public function down()
    {
        Schema::dropIfExists('cars');
    }
}
