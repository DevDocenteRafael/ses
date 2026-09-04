<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InformacoesProfissionais extends Model
{
    protected $table = 'informacoes_profissionais';

    protected $fillable = [
        'sobre_mim',
        'cargo_de_interesse',
        'area_de_atuacao',
        'habilidades',
        'candidato_matricula',
    ];

    protected $casts = [
        'habilidades' => 'array',
        'candidato_matricula' => 'string',
    ];

    public function candidato()
    {
        return $this->belongsTo(Candidato::class, 'candidato_matricula', 'matricula');
    }
}
