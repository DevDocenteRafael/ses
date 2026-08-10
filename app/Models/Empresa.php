<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $table = 'empresa';
    protected $primaryKey = 'cnpj';
    public $incrementing = false;

    protected $fillable = [
        'cnpj',
        'razao_social',
        'atividade_economica',
        'status',
        'pessoa_id_pessoa',
        'responsavel_contratual_id_responsavel_contratual',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    // Relacionamentos
    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id_pessoa', 'id_pessoa');
    }

    public function responsavelContratual()
    {
        return $this->belongsTo(
            ResponsavelContratual::class,
            'responsavel_contratual_id_responsavel_contratual',
            'id_responsavel_contratual'
        );
    }

    public function vagas()
    {
        return $this->hasMany(Vaga::class, 'empresa_cnpj', 'cnpj');
    }

    public function convites()
    {
        return $this->hasMany(Convite::class, 'empresa_cnpj', 'cnpj');
    }

    public function historicoDeEngajamento()
    {
        return $this->hasOne(HistoricoDeEngajamento::class, 'empresa_cnpj', 'cnpj');
    }

    public function candidatos()
    {
        return $this->belongsToMany(
            Candidato::class,
            'empresa_has_candidatos',
            'empresa_cnpj',
            'candidatos_matricula',
            'cnpj',
            'matricula'
        );
    }
}