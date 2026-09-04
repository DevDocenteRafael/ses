<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CursoSenac extends Model
{
    protected $table = 'cursos_senac';

    protected $fillable = [
        'nome_curso',
        'unidade',
        'carga_horaria',
        'concluido_em',
        'candidato_matricula',
    ];

    protected $casts = [
        'concluido_em'  => 'date',
        'carga_horaria' => 'integer',
        'candidato_matricula' => 'string',
    ];

    public function candidato()
    {
        return $this->belongsTo(Candidato::class, 'candidato_matricula', 'matricula');
    }
}
