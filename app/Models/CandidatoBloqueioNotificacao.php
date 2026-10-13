<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidatoBloqueioNotificacao extends Model
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_ENVIADO = 'enviado';
    public const STATUS_FALHOU = 'falhou';

    protected $table = 'candidato_bloqueio_notificacoes';

    protected $fillable = [
        'candidato_matricula',
        'tipo_bloqueio',
        'bloqueado_em',
        'tentativa_envio_em',
        'enviado_em',
        'status',
        'motivo_publico',
        'erro',
    ];

    protected $casts = [
        'bloqueado_em' => 'datetime',
        'tentativa_envio_em' => 'datetime',
        'enviado_em' => 'datetime',
    ];

    public function candidato()
    {
        return $this->belongsTo(Candidato::class, 'candidato_matricula', 'matricula');
    }
}
