<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResponsavelContratual extends Model
{
    protected $table = 'responsavel_contratual';
    protected $primaryKey = 'id_responsavel_contratual';

    protected $fillable = [
        'pessoa_id_pessoa',
    ];

    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id_pessoa', 'id_pessoa');
    }

    public function empresas()
    {
        return $this->hasMany(Empresa::class, 'responsavel_contratual_id_responsavel_contratual', 'id_responsavel_contratual');
    }
}