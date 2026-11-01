<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TaxProfile extends Model { protected $guarded=[]; protected $casts=['rate'=>'decimal:3','is_active'=>'boolean','is_default'=>'boolean']; }
