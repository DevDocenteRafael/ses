<?php

namespace App\Models;

use App\Support\RegioesAdministrativasDf;
use Illuminate\Database\Eloquent\Model;

class RegiaoPreferidaTrabalho extends Model
{
    protected $table = 'regioes_preferidas_trabalho';

    protected $fillable = [
        'candidato_matricula',
        'codigo_regiao',
    ];

    protected $casts = [
        'candidato_matricula' => 'string',
        'codigo_regiao' => 'integer',
    ];

    protected $appends = [
        'nome',
    ];

    public function candidato()
    {
        return $this->belongsTo(Candidato::class, 'candidato_matricula', 'matricula');
    }

    public function getNomeAttribute(): ?string
    {
        return RegioesAdministrativasDf::nome((int) $this->codigo_regiao);
    }
}
