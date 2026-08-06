<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DadosAcademicos extends Model
{
    protected $table = 'dados_academicos';

    protected $fillable = [
        'instituicao',
        'curso',
        'segmento',
        'tipo_curso',
        'unidade',
        'ano_de_conclusao',
        'candidato_matricula',
    ];

    protected $casts = [
        'ano_de_conclusao' => 'date',
    ];

    public function candidato()
    {
        return $this->belongsTo(Candidato::class, 'candidato_matricula', 'matricula');
    }
}