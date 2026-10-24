<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function toFeed(): array
    {
        return [
            'id' => $this->id, 'status' => $this->status, 'title' => $this->title, 'detail' => $this->detail,
            'holder' => $this->holder, 'type' => $this->ticket_type, 'seat' => $this->seat, 'order' => $this->order_number,
            'time' => $this->created_at->format('H:i:s'),
        ];
    }
}
