<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The balance of every active Avoir on one date (see CONTEXT.md). */
class Releve extends Model
{
    protected $table = 'patrimoine_releves';

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
    ];

    /** Store a plain Y-m-d: the date cast would write "Y-m-d 00:00:00" on SQLite and break date comparisons. */
    public function setDateAttribute($value): void
    {
        $this->attributes['date'] = $value === null ? null : Carbon::parse($value)->toDateString();
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(ReleveLigne::class);
    }
}
