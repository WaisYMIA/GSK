<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class ApplicationMessage extends Model { protected $fillable=['application_id','sender_user_id','body','read_at']; protected function casts():array{return ['read_at'=>'datetime'];} public function application(){return $this->belongsTo(Application::class);} public function sender(){return $this->belongsTo(User::class,'sender_user_id');} }
