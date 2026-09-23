<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityGroup extends Model
{
    use SoftDeletes;

    protected $table = 'app_activity_groups';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'uuid',
        'activity_id',
        'nombre',
        'created_at',
        'updated_at',
        'deleted_at',
        'sync_status',
        'device_id',
    ];

    public function activity()
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function estudiantes()
    {
        return $this->belongsToMany(Estudiante::class, 'app_activity_group_members', 'grupo_id', 'estudiante_id');
    }

    public function scores()
    {
        return $this->hasMany(ActivityScore::class, 'grupo_id');
    }

    public function overrides()
    {
        return $this->hasMany(ActivityGroupOverride::class, 'grupo_id');
    }
}
