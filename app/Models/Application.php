<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Application extends Model { protected $fillable=['vacancy_id','candidate_user_id','status','cover_note','submitted_at']; protected function casts():array{return ['submitted_at'=>'datetime'];} public function vacancy(){return $this->belongsTo(Vacancy::class);} public function candidate(){return $this->belongsTo(User::class,'candidate_user_id');} public function messages(){return $this->hasMany(ApplicationMessage::class);} }
