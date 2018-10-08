<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MusicData extends Model
{
    protected $table = "music_datas";
    protected $guarded = ['id'];

    function getAllMusicData(){
        $sql = DB::table($this->table)->select('*');
        return $sql->get();
    }
}
