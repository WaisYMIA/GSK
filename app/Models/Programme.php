<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes;
class Programme extends Model { use SoftDeletes; protected $fillable=['name','slug','description','status']; public function consents(){return $this->hasMany(ProgrammeConsent::class);} }
