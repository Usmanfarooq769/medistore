<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrugInteraction extends Model
{
    protected $fillable = ['ingredient_a', 'ingredient_b', 'severity', 'effect', 'management', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** Normalise + order the pair so (a,b) and (b,a) are the same record. */
    public function setIngredientAAttribute($v): void { $this->attributes['ingredient_a'] = mb_strtolower(trim((string) $v)); }
    public function setIngredientBAttribute($v): void { $this->attributes['ingredient_b'] = mb_strtolower(trim((string) $v)); }
}
