<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailModeloVersao extends Model
{
    protected $table = 'email_modelos_versoes';

    protected $fillable = [
        'email_modelo_id',
        'versao',
        'assunto_anterior',
        'assunto_novo',
        'conteudo_html',
        'acao',
        'administrador_pessoa_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function modelo()
    {
        return $this->belongsTo(EmailModelo::class, 'email_modelo_id');
    }

    public function administrador()
    {
        return $this->belongsTo(Pessoa::class, 'administrador_pessoa_id', 'id_pessoa');
    }
}
