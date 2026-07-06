<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricoDeEngajamento extends Model
{
    protected $table = 'historico_de_engajamento';

    protected $fillable = [
        'convites_enviados',
        'contratacoes',
        'empresa_cnpj',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_cnpj', 'cnpj');
    }
}