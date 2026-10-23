<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'description', 'image', 'link', 'position', 'active'])]
class Banner extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['active' => 'boolean', 'position' => 'integer'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
