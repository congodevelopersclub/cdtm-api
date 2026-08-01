<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
     use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'image',
        'color',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Les développeurs appartenant à cette catégorie.
     */
    public function developers()
    {
        return $this->hasMany(DeveloperProfile::class);
    }

    /**
     * Les projets appartenant à cette catégorie.
     */
    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}
