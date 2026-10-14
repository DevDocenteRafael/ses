<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailEnvio extends Model
{
    protected $table = 'email_envios';

    protected $fillable = [
        'email_modelo_id',
        'email_modelo_versao_id',
        'candidato_matricula',
        'destinatario_email',
        'tipo_mensagem',
        'assunto',
        'origem',
        'status',
        'administrador_nome',
        'administrador_pessoa_id',
        'motivo_falha',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function modelo()
    {
        return $this->belongsTo(EmailModelo::class, 'email_modelo_id');
    }

    public function versao()
    {
        return $this->belongsTo(EmailModeloVersao::class, 'email_modelo_versao_id');
    }

    public function administrador()
    {
        return $this->belongsTo(Pessoa::class, 'administrador_pessoa_id', 'id_pessoa');
    }
}
