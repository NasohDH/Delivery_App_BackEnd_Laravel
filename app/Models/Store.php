<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    protected $fillable = [
        'name',
        'location',
    ];
    use HasFactory;
    public function products() : HasMany
    {
        return $this->hasMany(Product::class);
    }
    public function ads() : HasMany
    {
        return $this->hasMany(Ad::class);
    }

    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
