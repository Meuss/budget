<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One Avoir's balance (and Versement, if it tracks them) in a Relevé. */
class ReleveLigne extends Model
{
    protected $table = 'patrimoine_releve_lignes';

    protected $guarded = [];

    protected $casts = [
        'avoir_id' => 'integer',
        'balance' => 'decimal:2',
        'versement' => 'decimal:2',
    ];

    public function releve(): BelongsTo
    {
        return $this->belongsTo(Releve::class);
    }

    public function avoir(): BelongsTo
    {
        return $this->belongsTo(Avoir::class);
    }
}
