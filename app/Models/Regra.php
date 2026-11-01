<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Se a descrição contém este texto, a categoria é esta."
 */
#[Fillable(['texto', 'categoria_id'])]
class Regra extends Model
{
    protected $table = 'regras';

    /**
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }
}
