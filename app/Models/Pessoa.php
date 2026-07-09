<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Pessoa extends Authenticatable
{
    protected $table = 'pessoa';
    protected $primaryKey = 'id_pessoa';
    public $incrementing = false;

    protected $fillable = [
        'id_pessoa',
        'nome',
        'email',
        'telefone',
        'senha',
        'data_cadastro',
    ];

    protected $hidden = [
        'senha',
    ];

    protected $casts = [
        'data_cadastro' => 'datetime',
    ];

    // Sobrescreve o campo de senha padrão do Laravel
    public function getAuthPassword()
    {
        return $this->senha;
    }

    /**
     * Resolve o papel da pessoa no sistema ('administrativo' | 'empresa' | 'candidato').
     */
    public function tipo(): string
    {
        if ($this->administrativo) {
            return 'administrativo';
        }

        if ($this->empresa) {
            return 'empresa';
        }

        return 'candidato';
    }

    // Relacionamentos
    public function candidato()
    {
        return $this->hasOne(Candidato::class, 'pessoa_id_pessoa', 'id_pessoa');
    }

    public function empresa()
    {
        return $this->hasOne(Empresa::class, 'id_pessoa', 'id_pessoa');
    }

    public function administrativo()
    {
        return $this->hasOne(Administrativo::class, 'pessoa_id_pessoa', 'id_pessoa');
    }

    public function responsavelContratual()
    {
        return $this->hasOne(ResponsavelContratual::class, 'pessoa_id_pessoa', 'id_pessoa');
    }
}
