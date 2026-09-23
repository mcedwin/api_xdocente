<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unidad extends Model
{
    use SoftDeletes;

    protected $table = 'app_units';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'uuid',
        'curso_id',
        'nombre',
        'orden',
        'created_at',
        'updated_at',
        'deleted_at',
        'sync_status',
        'device_id',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    public function curso()
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function sesiones()
    {
        return $this->hasMany(Sesion::class, 'unidad_id');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'unidad_id');
    }
}
