<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityScore extends Model
{
    use SoftDeletes;

    protected $table = 'app_activity_scores';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'uuid',
        'activity_id',
        'grupo_id',
        'estudiante_id',
        'criterio_id',
        'puntaje',
        'created_at',
        'updated_at',
        'deleted_at',
        'sync_status',
        'device_id',
    ];

    protected $casts = [
        'puntaje' => 'decimal:2',
    ];

    public function activity()
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function grupo()
    {
        return $this->belongsTo(ActivityGroup::class, 'grupo_id');
    }

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id');
    }

    public function criterio()
    {
        return $this->belongsTo(ActivityCriterion::class, 'criterio_id');
    }
}
