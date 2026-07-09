<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisualizacaoPerfil extends Model
{
    protected $table = 'visualizacoes_perfil';

    protected $fillable = [
        'candidato_matricula',
        'empresa_cnpj',
        'visualizado_em',
    ];

    protected $casts = [
        'visualizado_em' => 'datetime',
    ];

    public function candidato()
    {
        return $this->belongsTo(Candidato::class, 'candidato_matricula', 'matricula');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_cnpj', 'cnpj');
    }
}
