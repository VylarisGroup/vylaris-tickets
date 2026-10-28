<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartyComment extends Model
{
    protected $fillable = ['party_id', 'guest_id', 'author', 'by_host', 'body'];

    protected function casts(): array
    {
        return ['by_host' => 'boolean'];
    }
}
