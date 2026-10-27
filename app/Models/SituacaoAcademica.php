<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SituacaoAcademica extends Model
{
    protected $table = 'situacoes_academicas';

    protected $fillable = [
        'candidato_matricula',
        'nivel_escolaridade',
        'nivel_escolaridade_concluido',
        'situacao_academica',
        'curso',
        'instituicao',
        'data_inicio',
        'previsao_conclusao',
        'data_conclusao',
        'descricao_situacao',
        'atual',
    ];

    protected $casts = [
        'candidato_matricula' => 'string',
        'data_inicio' => 'date',
        'previsao_conclusao' => 'date',
        'data_conclusao' => 'date',
        'atual' => 'boolean',
    ];

    public function candidato()
    {
        return $this->belongsTo(Candidato::class, 'candidato_matricula', 'matricula');
    }
}
