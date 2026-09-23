<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use SoftDeletes;

    protected $table = 'app_activities';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'uuid',
        'unidad_id',
        'type',
        'nombre',
        'fecha',
        'is_group_based',
        'uses_rubric',
        'created_at',
        'updated_at',
        'deleted_at',
        'sync_status',
        'device_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'is_group_based' => 'boolean',
        'uses_rubric' => 'boolean',
    ];

    public function unidad()
    {
        return $this->belongsTo(Unidad::class, 'unidad_id');
    }

    public function criteria()
    {
        return $this->hasMany(ActivityCriterion::class, 'activity_id');
    }

    public function groups()
    {
        return $this->hasMany(ActivityGroup::class, 'activity_id');
    }

    public function scores()
    {
        return $this->hasMany(ActivityScore::class, 'activity_id');
    }
}
