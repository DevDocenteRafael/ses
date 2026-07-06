<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlunoMigrado extends Model
{
    protected $table = 'alunos_migrados';

    protected $fillable = [
        'status_ativacao',
        'ultima_sincronizacao',
        'administrativo_pessoa_id_pessoa',
    ];

    protected $casts = [
        'status_ativacao'      => 'boolean',
        'ultima_sincronizacao' => 'datetime',
    ];

    public function administrativo()
    {
        return $this->belongsTo(Administrativo::class, 'administrativo_pessoa_id_pessoa', 'pessoa_id_pessoa');
    }
}