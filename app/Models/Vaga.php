<?php
// ============================================================
// Vaga.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Vaga extends Model
{
    protected $table = 'vagas';
    protected $primaryKey = 'id_vaga';

    protected $fillable = [
        'titulo', 'tipo', 'area', 'status', 'data_publicacao', 'empresa_cnpj',
    ];

    protected $casts = [
        'status'           => 'boolean',
        'data_publicacao'  => 'date',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_cnpj', 'cnpj');
    }

    public function convites()
    {
        return $this->hasMany(Convite::class, 'vagas_id_vaga', 'id_vaga');
    }
}