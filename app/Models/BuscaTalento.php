<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuscaTalento extends Model
{
    protected $table = 'buscas_talentos';

    protected $fillable = [
        'empresa_cnpj',
        'filtros',
        'buscado_em',
    ];

    protected $casts = [
        'filtros'    => 'array',
        'buscado_em' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_cnpj', 'cnpj');
    }
}
