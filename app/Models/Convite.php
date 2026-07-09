<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Convite extends Model
{
    protected $table = 'convites';

    public const STATUS_PENDENTE = 0;
    public const STATUS_ACEITO = 1;
    public const STATUS_RECUSADO = 2;
    public const STATUS_ARQUIVADO = 3;

    protected $fillable = [
        'descricao',
        'data_envio',
        'status',
        'empresa_cnpj',
        'candidatos_matricula',
        'vagas_id_vaga',
    ];

    protected $casts = [
        'status'     => 'integer',
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
