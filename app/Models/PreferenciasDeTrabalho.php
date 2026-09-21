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
        'aceita_todas_regioes',
        'pretensao_salarial',
        'candidato_matricula',
    ];

    protected $casts = [
        'candidato_matricula' => 'string',
        'aceita_todas_regioes' => 'boolean',
        'disponibilidade_de_horario' => 'array',
    ];

    public function candidato()
    {
        return $this->belongsTo(Candidato::class, 'candidato_matricula', 'matricula');
    }
}
