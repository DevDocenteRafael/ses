<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Candidato extends Model
{
    protected $table = 'candidato';
    protected $primaryKey = 'matricula';
    public $incrementing = false;

    protected $fillable = [
        'matricula',
        'cpf',
        'status',
        'pessoa_id_pessoa',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    // Relacionamentos
    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id_pessoa', 'id_pessoa');
    }

    public function linkExterno()
    {
        return $this->hasOne(LinkExterno::class, 'candidato_matricula', 'matricula');
    }

    public function informacoesProfissionais()
    {
        return $this->hasOne(InformacoesProfissionais::class, 'candidato_matricula', 'matricula');
    }

    public function preferenciasDeTrabalho()
    {
        return $this->hasOne(PreferenciasDeTrabalho::class, 'candidato_matricula', 'matricula');
    }

    public function dadosAcademicos()
    {
        return $this->hasMany(DadosAcademicos::class, 'candidato_matricula', 'matricula');
    }

    public function cursosSenac()
    {
        return $this->hasMany(CursoSenac::class, 'candidato_matricula', 'matricula');
    }

    public function cursosExternos()
    {
        return $this->hasMany(CursoExterno::class, 'candidato_matricula', 'matricula');
    }

    public function experienciasProfissionais()
    {
        return $this->hasMany(ExperienciaProfissional::class, 'candidato_matricula', 'matricula');
    }

    public function convites()
    {
        return $this->hasMany(Convite::class, 'candidatos_matricula', 'matricula');
    }

    public function visualizacoes()
    {
        return $this->hasMany(VisualizacaoPerfil::class, 'candidato_matricula', 'matricula');
    }

    public function empresas()
    {
        return $this->belongsToMany(
            Empresa::class,
            'empresa_has_candidatos',
            'candidatos_matricula',
            'empresa_cnpj',
            'matricula',
            'cnpj'
        )->withTimestamps();
    }
}