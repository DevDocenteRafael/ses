<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contratacao extends Model
{
    public const STATUS_VIGENTE = 'vigente';
    public const STATUS_CANCELADA = 'cancelada';

    protected $table = 'contratacoes';

    protected $fillable = [
        'candidato_matricula',
        'empresa_cnpj',
        'registrado_por_pessoa_id',
        'origem',
        'contratado_em',
        'status',
        'cancelado_em',
        'cancelado_por_pessoa_id',
        'motivo_cancelamento',
        'observacao_cancelamento',
        'historico_alteracoes',
    ];

    protected $casts = [
        'contratado_em' => 'date',
        'cancelado_em' => 'datetime',
        'historico_alteracoes' => 'array',
    ];

    public function scopeVigentes($query)
    {
        return $query->where('status', self::STATUS_VIGENTE);
    }

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

    public function canceladoPor()
    {
        return $this->belongsTo(Pessoa::class, 'cancelado_por_pessoa_id', 'id_pessoa');
    }
}
