<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Convite extends Model
{
    protected $table = 'convites';

    protected $fillable = [
        'descricao',
        'data_envio',
        'status',
        'empresa_cnpj',
        'candidatos_matricula',
        'vagas_id_vaga',
    ];

    protected $casts = [
        'status'     => 'boolean',
        'data_envio' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_cnpj', 'cnpj');
    }

    public function candidato()
    {
        return $this->belongsTo(Candidato::class, 'candidatos_matricula', 'matricula');
    }

    public function vaga()
    {
        return $this->belongsTo(Vaga::class, 'vagas_id_vaga', 'id_vaga');
    }
}