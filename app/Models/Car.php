<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    protected $fillable = [
        'ad_id', 'activated_at', 'category_id', 'uuid', 'has_whatsapp_number',
        'seating_capacity', 'engine_capacity', 'target_market', 'is_premium',
        'make', 'model', 'trim', 'url', 'title', 'seller_name',
        'seller_phone_number', 'seller_type', 'posted_on', 'year', 'price',
        'kilometers', 'color', 'doors', 'cylinders', 'warranty',
        'body_condition', 'mechanical_condition', 'fuel_type', 'regional_specs',
        'body_type', 'steering_side', 'horsepower', 'transmission_type', 'location',
        'image_urls'
    ];
}