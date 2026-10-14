<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailModelo extends Model
{
    protected $table = 'email_modelos';

    protected $fillable = [
        'codigo',
        'nome',
        'tipo_notificacao',
        'descricao',
        'assunto_padrao',
        'conteudo_html',
        'conteudo_rascunho',
        'status_configuracao',
        'administrador_pessoa_id',
        'versao_atual',
        'ultima_alteracao_em',
        'publicado_em',
        'variaveis_permitidas',
        'meta',
    ];

    protected $casts = [
        'variaveis_permitidas' => 'array',
        'meta' => 'array',
        'ultima_alteracao_em' => 'datetime',
        'publicado_em' => 'datetime',
    ];

    public function ultimaVersao()
    {
        return $this->hasOne(EmailModeloVersao::class, 'email_modelo_id')->orderByDesc('versao');
    }

    public function versoes(): HasMany
    {
        return $this->hasMany(EmailModeloVersao::class, 'email_modelo_id');
    }

    public function envios(): HasMany
    {
        return $this->hasMany(EmailEnvio::class, 'email_modelo_id');
    }

    public function administrador()
    {
        return $this->belongsTo(Pessoa::class, 'administrador_pessoa_id', 'id_pessoa');
    }
}
