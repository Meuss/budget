<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'value_date' => 'date',
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'is_savings' => 'boolean',
        'is_transfer' => 'boolean',
        'raw' => 'array',
    ];

    /** Expense rows have a negative amount; income rows positive. */
    public function scopeExpenses($query)
    {
        return $query->where('direction', 'debit');
    }

    public function scopeIncome($query)
    {
        return $query->where('direction', 'credit');
    }

    public function scopeUnclassified($query)
    {
        return $query->whereNull('category_id')->where('is_savings', false);
    }
}
