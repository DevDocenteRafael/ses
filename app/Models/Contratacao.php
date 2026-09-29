<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contratacao extends Model
{
    protected $table = 'contratacoes';

    protected $fillable = [
        'candidato_matricula',
        'empresa_cnpj',
        'registrado_por_pessoa_id',
        'origem',
        'contratado_em',
    ];

    protected $casts = [
        'contratado_em' => 'date',
    ];

    public function candidato()
    {
        return $this->belongsTo(Candidato::class, 'candidato_matricula', 'matricula');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_cnpj', 'cnpj');
    }

    public function registradoPor()
    {
        return $this->belongsTo(Pessoa::class, 'registrado_por_pessoa_id', 'id_pessoa');
    }
}
