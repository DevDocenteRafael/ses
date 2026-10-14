<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidatoInatividadeCampanha extends Model
{
    public const TIPO_GLOBAL = 'manual_global';

    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_PROCESSANDO = 'processando';
    public const STATUS_AGUARDANDO_WORKER = 'aguardando_worker';
    public const STATUS_INTERROMPIDA = 'interrompida';
    public const STATUS_FINALIZADA = 'finalizada';

    protected $fillable = [
        'tipo',
        'status',
        'administrador_pessoa_id',
        'quantidade_prevista',
        'pendentes',
        'enviados',
        'ignorados',
        'falhas',
        'iniciado_em',
        'finalizado_em',
    ];

    protected $casts = [
        'iniciado_em' => 'datetime',
        'finalizado_em' => 'datetime',
    ];
}
