<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidatoReativacaoQuestionario extends Model
{
    public const RESULTADO_REATIVADO = 'REATIVADO';
    public const RESULTADO_ENCERRAMENTO_SOLICITADO = 'ENCERRAMENTO_SOLICITADO';

    protected $table = 'candidato_reativacao_questionarios';

    protected $fillable = [
        'candidato_matricula',
        'bloqueado_em',
        'bloqueio_notificacao_id',
        'resposta_1',
        'resposta_2',
        'resposta_3',
        'resposta_4',
        'resposta_5',
        'resposta_6',
        'resposta_7',
        'resposta_8',
        'resultado',
        'orientacoes',
        'concluido_em',
    ];

    protected $casts = [
        'bloqueado_em' => 'datetime',
        'resposta_4' => 'array',
        'orientacoes' => 'array',
        'concluido_em' => 'datetime',
    ];

    public function candidato()
    {
        return $this->belongsTo(Candidato::class, 'candidato_matricula', 'matricula');
    }

    public function bloqueioNotificacao()
    {
        return $this->belongsTo(CandidatoBloqueioNotificacao::class, 'bloqueio_notificacao_id');
    }
}
