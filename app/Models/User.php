<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'app_users';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'uuid',
        'firebase_uid',
        'nombre',
        'email',
        'avatar',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    public function cursos()
    {
        return $this->hasMany(Curso::class, 'usuario_id');
    }

    public function alertas()
    {
        return $this->hasMany(Alerta::class, 'usuario_id');
    }
}
