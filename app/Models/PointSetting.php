<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PointSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'label',
        'description',
    ];
}
