<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manufacturer extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'address'];

    public function medicines()
    {
        return $this->hasMany(Medicine::class);
    }
}
