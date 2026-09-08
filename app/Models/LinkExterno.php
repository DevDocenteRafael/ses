<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LinkExterno extends Model
{
    protected $table = 'link_externo';

    protected $fillable = [
        'linkedin',
        'portfolio',
        'github',
        'candidato_matricula',
    ];

    protected $casts = [
        'candidato_matricula' => 'string',
    ];

    public function candidato()
    {
        return $this->belongsTo(Candidato::class, 'candidato_matricula', 'matricula');
    }
}
