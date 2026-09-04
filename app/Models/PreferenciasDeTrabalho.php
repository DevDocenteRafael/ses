<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreferenciasDeTrabalho extends Model
{
    protected $table = 'preferencias_de_trabalho';

    protected $fillable = [
        'tipo_de_contratacao',
        'disponibilidade_de_horario',
        'regiao_administrativa',
        'pretensao_salarial',
        'candidato_matricula',
    ];

    protected $casts = [
        'candidato_matricula' => 'string',
    ];

    public function candidato()
    {
        return $this->belongsTo(Candidato::class, 'candidato_matricula', 'matricula');
    }
}
