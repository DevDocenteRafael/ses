<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EngajamentoPorUnidadeSenac extends Model
{
    protected $table = 'engajamento_por_unidade_senac';
    protected $primaryKey = 'unidade';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'unidade',
        'elegibilidade',
        'status',
        'administrativo_pessoa_id_pessoa',
    ];

    protected $casts = [
        'elegibilidade' => 'boolean',
        'status'        => 'boolean',
    ];

    public function administrativo()
    {
        return $this->belongsTo(Administrativo::class, 'administrativo_pessoa_id_pessoa', 'pessoa_id_pessoa');
    }
}