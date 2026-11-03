<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InsuranceCoverage extends Model { protected $guarded=[]; protected $casts=['is_covered'=>'boolean','coverage_percent'=>'decimal:3','max_amount'=>'decimal:2']; }