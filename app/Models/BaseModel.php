<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

abstract class BaseModel extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }
}
