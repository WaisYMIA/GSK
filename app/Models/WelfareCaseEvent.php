<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class WelfareCaseEvent extends Model { protected $fillable=['welfare_case_id','actor_user_id','event_type','note']; public function welfareCase(){return $this->belongsTo(WelfareCase::class);} }
