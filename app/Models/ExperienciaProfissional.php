<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExperienciaProfissional extends Model
{
    protected $table = 'experiencias_profissionais';

    protected $fillable = [
        'tipo',
        'cargo',
        'empresa',
        'local',
        'data_inicio',
        'data_fim',
        'descricao',
        'candidato_matricula',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim'    => 'date',
    ];

    public function candidato()
    {
        return $this->belongsTo(Candidato::class, 'candidato_matricula', 'matricula');
    }
}
