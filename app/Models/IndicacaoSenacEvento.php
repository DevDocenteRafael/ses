<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Histórico somente de inserção de uma indicação (criação e mudanças de status).
 */
class IndicacaoSenacEvento extends Model
{
    public const TIPO_CRIADA = 'CRIADA';
    public const TIPO_STATUS_ALTERADO = 'STATUS_ALTERADO';

    public const UPDATED_AT = null;

    protected $table = 'indicacao_senac_eventos';

    protected $fillable = [
        'indicacao_id',
        'tipo',
        'status_anterior',
        'status_novo',
        'pessoa_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function indicacao()
    {
        return $this->belongsTo(IndicacaoSenac::class, 'indicacao_id');
    }

    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id', 'id_pessoa');
    }
}
