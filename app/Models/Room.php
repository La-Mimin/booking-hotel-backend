<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use  HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'image'
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute() {
        return $this->image ? asset('storage/' . $this->image) : null;
    }
}
