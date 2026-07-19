<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SkillProfile extends Pivot
{
    protected $hidden = ['profile_id', 'skill_id'];
}
