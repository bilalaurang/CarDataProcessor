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
            $table->id();
            
            // Primary listing information - adjusted for your tuple data
            $table->string('ad_id')->nullable()->index(); // Ad ID (from position 1)
            $table->timestamp('activated_at')->nullable(); // Activated At (from position 0)
            $table->string('category_id')->nullable(); // Category ID (from position 2)
            $table->string('uuid')->nullable()->index(); // UUID (not in your data)
            $table->boolean('has_whatsapp_number')->default(false); // Has Whatsapp Number (not in your data)
            
            // Vehicle specifications
            $table->integer('seating_capacity')->nullable(); // Seating Capacity
            $table->string('engine_capacity')->nullable(); // Engine Capacity (can be ranges like "2000-2499cc")
            $table->string('target_market')->nullable(); // Target Market
            $table->boolean('is_premium')->default(false); // Is Premium
            $table->string('make')->nullable()->index(); // Make
            $table->string('model')->nullable()->index(); // Model
            $table->string('trim')->nullable(); // Trim
            
            // Listing details - using TEXT for potentially long content
            $table->text('url')->nullable(); // Url
            $table->text('title')->nullable(); // Title
            $table->string('dealer_or_seller_name')->nullable(); // Dealer or seller name
            $table->string('seller_phone_number')->nullable(); // Seller phone number
            $table->string('seller_type')->nullable(); // Seller type
            $table->timestamp('posted_on')->nullable(); // Posted on
            
            // Vehicle details
            $table->integer('year_of_the_car')->nullable()->index(); // Year of the car
            $table->decimal('price', 12, 2)->nullable()->index(); // Price (using decimal for accuracy)
            $table->integer('kilometers')->nullable()->index(); // Kilometers
            $table->string('color')->nullable(); // Color
            $table->integer('doors')->nullable(); // Doors
            $table->integer('no_of_cylinders')->nullable(); // No. of Cylinders
            $table->string('warranty')->nullable(); // Warranty
            $table->string('body_condition')->nullable(); // Body condition
            $table->string('mechanical_condition')->nullable(); // Mechanical condition
            $table->string('fuel_type')->nullable()->index(); // Fuel type
            $table->string('regional_specs')->nullable(); // Regional specs
            $table->string('body_type')->nullable()->index(); // Body type
            $table->string('steering_side')->nullable(); // Steering side
            $table->string('horsepower')->nullable(); // Horsepower (can be ranges)
            $table->string('transmission_type')->nullable(); // Transmission type
            $table->string('location_of_the_car')->nullable()->index(); // Location of the car
            
            // Image URLs - using LONGTEXT for potentially many URLs
            $table->longText('image_urls')->nullable(); // Image urls
            
            $table->timestamps();
            
            // Additional indexes for common queries
            $table->index(['make', 'model']);
            $table->index(['price', 'year_of_the_car']);
            $table->index(['body_type', 'fuel_type']);
            $table->index('posted_on');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cars');
    }
};