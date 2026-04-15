<?php
// ── app/Models/ProductServiceCategory.php ────────────────────────────────────
namespace App\Models;

use App\Models\ProductService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductServiceCategory extends Model
{
    protected $fillable = ['name', 'type', 'description', 'is_active', 'sort_order'];
    protected $casts    = ['is_active' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(ProductService::class, 'category_id');
    }

    public function scopeActive($query) { return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name'); }
    public function scopeProducts($query) { return $query->where('type', 'product'); }
    public function scopeServices($query) { return $query->where('type', 'service'); }
}