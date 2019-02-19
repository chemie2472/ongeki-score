<?php

namespace App\Http\Controllers;

use App;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SimpleViewController extends Controller
{
    public function getIndex(){
        return view('top');
    }

    public function getHowto(){
        return view('howto');
    }

    public function getEula(){
        return view('eula');
    }

    public function getChangelog(){
        $v = new App\ApplicationVersion();
        $version = $v->getAllVersion();
        return view('changelog', compact('version'));
    }

    public function getLogout(){
        Auth::logout();
        return view('logout');
    }

    public function getLogFile($path, $fileName){
        return "<html><body>" . str_replace("\n", "<br>", Storage::get('log/' . $path . '/' . $fileName)) . "</body></html>";
    }

    /* for debug
    public function versionUpdate(){
        (new App\ApplicationVersion())->fetchAllVersion();
    }

    public function testTweet($s){
        $res = (new App\AdminTweet())->tweet($s . rand());
    }
    */

}
