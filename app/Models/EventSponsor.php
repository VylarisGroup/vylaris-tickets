<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EventSponsor extends Model
{
    protected $guarded = [];

    public function logoUrl(): string
    {
        return Storage::disk('public')->url($this->logo_path);
    }
}
