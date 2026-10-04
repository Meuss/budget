<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One place that holds wealth and has a balance (see CONTEXT.md). */
class Avoir extends Model
{
    protected $table = 'patrimoine_avoirs';

    protected $guarded = [];

    protected $casts = [
        'classe_id' => 'integer', // strict comparisons must hold on MySQL as on SQLite
        'versement_mensuel' => 'decimal:2',
        'archived_on' => 'date',
    ];

    /** Store a plain Y-m-d: the date cast would write "Y-m-d 00:00:00" on SQLite and break date comparisons. */
    public function setArchivedOnAttribute($value): void
    {
        $this->attributes['archived_on'] = $value === null ? null : Carbon::parse($value)->toDateString();
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(ReleveLigne::class);
    }

    public function suitLesVersements(): bool
    {
        return $this->versement_mensuel !== null;
    }

    /** Avoirs that belong in a Relevé dated $date (Y-m-d): archived ones drop out from their archive date. */
    public function scopeActiveOn($query, string $date)
    {
        return $query->where(fn ($q) => $q->whereNull('archived_on')->orWhere('archived_on', '>', $date));
    }
}
