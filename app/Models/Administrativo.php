<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Administrativo extends Model
{
    protected $table = 'administrativo';
    protected $primaryKey = 'pessoa_id_pessoa';
    public $incrementing = false;

    protected $fillable = [
        'pessoa_id_pessoa',
    ];

    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id_pessoa', 'id_pessoa');
    }

    public function alunosMigrados()
    {
        return $this->hasMany(AlunoMigrado::class, 'administrativo_pessoa_id_pessoa', 'pessoa_id_pessoa');
    }

    public function engajamentoPorUnidade()
    {
        return $this->hasMany(EngajamentoPorUnidadeSenac::class, 'administrativo_pessoa_id_pessoa', 'pessoa_id_pessoa');
    }
}