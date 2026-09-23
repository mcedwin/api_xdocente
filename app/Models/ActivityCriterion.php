<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityCriterion extends Model
{
    use SoftDeletes;

    protected $table = 'app_activity_criteria';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'uuid',
        'activity_id',
        'nombre',
        'puntaje_maximo',
        'created_at',
        'updated_at',
        'deleted_at',
        'sync_status',
        'device_id',
    ];

    protected $casts = [
        'puntaje_maximo' => 'decimal:2',
    ];

    public function activity()
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function scores()
    {
        return $this->hasMany(ActivityScore::class, 'criterio_id');
    }

    public function overrides()
    {
        return $this->hasMany(ActivityGroupOverride::class, 'criterio_id');
    }
}
