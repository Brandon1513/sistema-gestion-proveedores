<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductService extends Model
{
    protected $table    = 'products_services';
    protected $fillable = ['category_id', 'type', 'name', 'description', 'is_active', 'sort_order'];
    protected $casts    = ['is_active' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductServiceCategory::class, 'category_id');
    }

    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'provider_products_services', 'product_service_id', 'provider_id')
            ->withTimestamps();
    }

    public function scopeActive($query)    { return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name'); }
    public function scopeProducts($query)  { return $query->where('type', 'product'); }
    public function scopeServices($query)  { return $query->where('type', 'service'); }
}