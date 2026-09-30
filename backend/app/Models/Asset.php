<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Asset extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'brand',
        'model',
        'serial_number',
        'area_id',
        'location_id',
        'responsible_name',
        'hostname',
        'ip_address',
        'mac_address',
        'status',
        'notes',
    ];

    protected $hidden = ['public_token'];

    protected static function booted(): void
    {
        static::creating(function (Asset $asset): void {
            if (empty($asset->public_token)) {
                $asset->public_token = (string) Str::uuid();
            }
        });
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(AssetHistory::class)
            ->orderByDesc('created_at');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }
}
