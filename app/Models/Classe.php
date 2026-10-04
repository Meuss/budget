<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A Patrimoine Classe: an owner-defined group of Avoirs (see CONTEXT.md). */
class Classe extends Model
{
    protected $table = 'patrimoine_classes';

    protected $guarded = [];

    public function avoirs(): HasMany
    {
        return $this->hasMany(Avoir::class)->orderBy('position')->orderBy('id');
    }
}
