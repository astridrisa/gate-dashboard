<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GateLog extends Model
{
    protected $fillable = [
        'tap_time',
        'area_name',
        'device_name',
        'direction',
        'event_point',
        'event_description',
        'event_level',
        'personnel_id',
        'full_name',
        'card_number',
        'department_name',
        'reader_name',
        'verification_mode',
    ];

    protected $casts = [
        'tap_time' => 'datetime',
    ];
}
