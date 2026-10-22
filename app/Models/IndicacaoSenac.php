<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndicacaoSenac extends Model
{
    public const STATUS_NOVA = 'NOVA';
    public const STATUS_EM_ANALISE = 'EM_ANALISE';
    public const STATUS_TENHO_INTERESSE = 'TENHO_INTERESSE';
    public const STATUS_SEM_INTERESSE = 'SEM_INTERESSE';

    public const ROTULOS_STATUS = [
        self::STATUS_NOVA => 'Nova indicação',
        self::STATUS_EM_ANALISE => 'Em análise',
        self::STATUS_TENHO_INTERESSE => 'Tenho interesse',
        self::STATUS_SEM_INTERESSE => 'Sem interesse',
    ];

    /** Status que a empresa pode registrar (a "Nova indicação" é sempre o estado inicial). */
    public const STATUS_ATUALIZAVEIS_PELA_EMPRESA = [
        self::STATUS_EM_ANALISE,
        self::STATUS_TENHO_INTERESSE,
        self::STATUS_SEM_INTERESSE,
    ];

    protected $table = 'indicacoes_senac';

    protected $fillable = [
        'candidato_matricula',
        'empresa_cnpj',
        'indicado_por_pessoa_id',
        'observacao',
        'status',
        'ativa',
        'indicado_em',
    ];

    protected $casts = [
        'indicado_em' => 'datetime',
        'ativa' => 'boolean',
    ];

    public static function rotuloStatus(?string $status): ?string
    {
        return $status === null ? null : (self::ROTULOS_STATUS[$status] ?? $status);
    }

    public function candidato()
    {
        return $this->belongsTo(Candidato::class, 'candidato_matricula', 'matricula');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_cnpj', 'cnpj');
    }

    public function indicadoPor()
    {
        return $this->belongsTo(Pessoa::class, 'indicado_por_pessoa_id', 'id_pessoa');
    }

    public function eventos()
    {
        return $this->hasMany(IndicacaoSenacEvento::class, 'indicacao_id')->orderBy('id');
    }
}
