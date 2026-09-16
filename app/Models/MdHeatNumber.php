<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MdHeatNumber extends Model
{
    protected $table = 'md_heat_numbers';

    protected $fillable = [
        'traveler_number',
        'kode_produksi',
        'heat_date',
        'item_code',
        'item_name',
        'heat_number',
        'cor_qty',
        'size',
        'customer',
        'line',
        'status',
    ];

    protected $casts = [
        'heat_date' => 'date',
    ];

    /**
     * Scope: Active Only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Relation to Item (Optional if needed)
     */
    public function item()
    {
        return $this->belongsTo(MdItem::class, 'item_code', 'code');
    }
}
