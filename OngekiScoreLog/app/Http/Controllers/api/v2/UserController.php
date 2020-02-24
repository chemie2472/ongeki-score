<?php

namespace App\Http\Controllers\api\v2;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Facades\Slack;
use Carbon\Carbon;
use RuntimeException;

class UserController extends Controller{
    public function GetUpdateStatus(Request $request){
        $result = [];
        $result['id'] = null;
        $result['name'] = null;
        $result['message'] = [];
        $result['hash'] = '';

        if(Auth::user() === null){
            $result['message'][] = "ログイン情報が取得できませんでした。ブックマークレットの再生成をお試しください。";
            return $result;
        }

        $result['id'] = Auth::id();
        $result['name'] = Auth::user()->name;

        if(config('env.is-maintenance-api-user-update')){
            $result['message'][] = "<p>只今メンテナンスを行っています。スコアデータの登録は行なえません。</p><p>詳細は<a href='https://twitter.com/ongeki_score' target='_blank' style='color:#222'>Twitter@ongeki_score</a>にてお知らせします。</p>";

            $user = Auth::user();
            if($request->input('PlayerData') !== null){
                $name = $request->input('PlayerData')['name'];
            }else{
                $name = "<Unknown>";
            }
            $content = "スコア登録: " . $name . "(" . $user->id . ")\n" . url("/user/" . $user->id);
            $fileContent = "ip: " . \Request::ip() . "\nUser agent: " . $_SERVER['HTTP_USER_AGENT'] . "\n\nUser:\nid: " . $user->id . "\nemail: " . $user->email . "\nrole: " . $user->role . "\n\nCookie:\n" . var_export(Cookie::get(), true) . "\n\nRequest:\n" . var_export($request, true);
            $fields = ["IP Address" => \Request::ip(), "User id" => $user->id];
            Slack::Warning($content, $fileContent, $fields, "success");
            return $result;
        }
        $result['message'] = 'ok';
        $result['hash'] = hash('sha256', $result['id'] . '_' . microtime(true));

        $generation = (new \App\ScoreData())->getMaxGeneration(Auth::id()) + 1;
        \App\UserUpdateStatus::create([
            'user_id' => $result['id'],
            'hash' => $result['hash'],
            'begin_at' => Carbon::now(),
            'generation' => $generation,
        ]);
        return $result;
    }

    public function PostUpdate(Request $request){
        $result = [];
        $result['id'] = null;
        $result['isError'] = false;
        $result['message'] = [];

        $hash = $request->input('hash', null);
        $data = $request->input('data', null);
        $methodType = $request->input('methodType', null);

        if(Auth::id() === null){
            $result['message'][] = "認証に失敗しました。";
            $result['isError'] = true;
            return $result;
        }
        if($hash === null || $data === null || $methodType === null){
            $result['message'][] = "送信内容を確認できませんでした。";
            $result['isError'] = true;
            return $result;
        }

        $userStatus = \App\UserUpdateStatus::where('hash', $hash)->first();

        if($userStatus === null){
            $result['message'][] = "送信内容が正しくありません。";
            $result['isError'] = true;
            return $result;
        }

        $uniqueID = md5(uniqid(rand(),1));
        \App\UniqueIDForRequest::create([
            'ip_address' => \Request::ip(),
            'unique_id' => $uniqueID,
        ]);

        if($methodType === "0"){
            $this->setPlayer($data, $userStatus->begin_at, $uniqueID);
        }else if($methodType === "1"){
            if(Auth::user()->role >= 7){
                $result['message'] = array_merge($result['message'], $this->addMusic($data, $uniqueID));
            }
            $result['message'] = array_merge($result['message'], $this->setScore($data, $userStatus->begin_at, $uniqueID, $userStatus->generation));

        }else if($methodType === "2"){
            $this->setTrophy($data, $userStatus->begin_at, $uniqueID);
        }else if($methodType === "3"){
            $this->setCharacterFriendly($data, $userStatus->begin_at, $uniqueID);
        }else if($methodType === "4"){
            $this->setRatingRecentMusic($data, $userStatus->begin_at, $uniqueID);
        }else if($methodType === "5"){
            $this->setPaymentStatus($data, $userStatus->begin_at, $uniqueID);
        }else{
            $result['message'][] = "未知のtypeが渡されました。 type:" . $methodType;
            $result['isError'] = true;
            return $result;
        }

        $result['data'] = $data;
        return $result;
    }

    private function setPlayer($data, $dateTime, $uniqueID){
        $userId = Auth::id();
        \App\UserStatus::create([
            'user_id' => $userId,
            'trophy' => $data['trophy'],
            'level' => $data['level'],
            'name' => $data['name'],
            'battle_point' => $data['battle_point'],
            'rating' => $data['rating'],
            'rating_max' => $data['rating_max'],
            'money' => $data['money'],
            'total_money' => $data['total_money'],
            'total_play' => $data['total_play'],
            'comment' => $data['comment'],
            'friend_code' => $data['friend_code'],
            'unique_id' => $uniqueID,
            'created_at' => $dateTime,
            'updated_at' => $dateTime,
        ]);
    }

    private function setScore($data, $dateTime, $uniqueID, $generation){
        $message = [];
        foreach ($data as $key => $value) {
            if($value['difficulty'] !== "0" && $value['difficulty'] !== "1" && $value['difficulty'] !== "2" && $value['difficulty'] !== "3" && $value['difficulty'] !== "10"){
                throw new RuntimeException("未知の難易度が送信されました。(" . $value['difficulty'] . ")");
            }
            $music = \App\MusicData::where("title", "=", $value['title'])->first();
            if($music === null){
                $message[] = "未知の曲: " . $value['title'];
                continue;
            }
            $recentScore = (new \App\ScoreData())->getRecentGenerationOfScoreData(Auth::id(), $music->id, $value['difficulty'])->getValue();
            $isUpdate = false;

            $full_bell = ($value['full_bell'] === "true" ? 1 : 0);
            $full_combo = ($value['full_combo'] === "true" ? 1 : 0);
            $all_break = ($value['all_break'] === "true" ? 1 : 0);
            if((bool)$recentScore === false){
                $isUpdate = true;
            }else{
                if($value['over_damage_high_score'] > $recentScore->over_damage_high_score){
                    $isUpdate = true;
                }else{
                    $value['over_damage_high_score'] = $recentScore->over_damage_high_score;
                }

                if($value['battle_high_score'] > $recentScore->battle_high_score){
                    $isUpdate = true;
                }else{
                    $value['battle_high_score'] = $recentScore->battle_high_score;
                }

                if($value['technical_high_score'] > $recentScore->technical_high_score){
                    $isUpdate = true;
                }else{
                    $value['technical_high_score'] = $recentScore->technical_high_score;
                }

                if($full_bell > $recentScore->full_bell){
                    $isUpdate = true;
                }else{
                    $full_bell = $recentScore->full_bell;
                }

                if($full_combo > $recentScore->full_combo){
                    $isUpdate = true;
                }else{
                    $full_combo = $recentScore->full_combo;
                }

                if($all_break > $recentScore->all_break){
                    $isUpdate = true;
                }else{
                    $all_break = $recentScore->all_break;
                }
            }
            if($isUpdate){
                \App\ScoreData::create([
                    'user_id' => Auth::id(),
                    'generation' => $generation,
                    'song_id' => $music->id,
                    'difficulty' => $value['difficulty'],
                    'over_damage_high_score' => $value['over_damage_high_score'],
                    'battle_high_score' => $value['battle_high_score'],
                    'technical_high_score' => $value['technical_high_score'],
                    'full_bell' => $full_bell,
                    'full_combo' => $full_combo,
                    'all_break' => $all_break,
                    'unique_id' => $uniqueID,
                    'created_at' => $dateTime,
                    'updated_at' => $dateTime,
                ]);
            }
        }
        return $message;
    }

    private function addMusic($data, $uniqueID){
        $message = [];
        $titles = [];

        $currentVersion = config('env.ongeki-version', null);
        if($currentVersion === null){
            throw new RuntimeException("現在のバージョンが取得できませんでした。");
        }

        foreach ($data as $v) {
            $musicData = \App\MusicData::where("title", $v['title'])->first();
            if(is_null($musicData)){
                $musicData = new \App\MusicData();
                $musicData->title = $v['title'];
                $message[] =  "[楽曲データ追加] ". $v['title'];
                $titles[] = $v['title'];
            }
            $musicData->genre = $v['genre'];
            $difficulty = "";

            if($v['difficulty'] === "0"){
                $difficulty = "basic";
                $musicData->basic_level = $v['level'];
            }else if($v['difficulty'] === "1"){
                $difficulty = "advanced";
                $musicData->advanced_level = $v['level'];
            }else if($v['difficulty'] === "2"){
                $difficulty = "expert";
                $musicData->expert_level = $v['level'];
            }else if($v['difficulty'] === "3"){
                $difficulty = "master";
                $musicData->master_level = $v['level'];
            }else if($v['difficulty'] === "10"){
                $difficulty = "lunatic";
                $musicData->lunatic_level = $v['level'];
            }else{
                throw new RuntimeException("未知の難易度が送信されました。(" . $v['difficulty'] . ")");
            }
            if($difficulty === "lunatic" && is_null($musicData->lunatic_added_version)){
                $musicData->lunatic_added_version = $currentVersion;
            }else if($difficulty !== "lunatic" && is_null($musicData->normal_added_version)){
                $musicData->normal_added_version = $currentVersion;
            }
            $musicData->unique_id = $uniqueID;
            $musicData->save();
        }
        if(count($titles) !== 0){
            $message[] = "楽曲情報の追加を行いました。";
            (new \App\AdminTweet())->tweetMusicUpdate($titles);
        }

        return $message;
    }

    private $trophyGrade = ["Normal" => 0, "Silver" => 1, "Gold" => 2, "Platinum" => 3, "Rainbow" => 4];
    private function setTrophy($data, $dateTime, $uniqueID){
        foreach ($data as $k => $v) {
            $record = \App\UserTrophy::where([
                ['user_id', '=', Auth::id()],
                ['name', '=', $v['name']],
            ])->get();
            if(count($record) > 0){
                continue;
            }

            \App\UserTrophy::create([
                'user_id' => Auth::id(),
                'grade' => $this->trophyGrade[$v['rank']],
                'name' => $v['name'],
                'detail' => $v['detail'],
                'unique_id' => $uniqueID,
                'created_at' => $dateTime,
                'updated_at' => $dateTime,
            ]);
        }
    }

    private function setCharacterFriendly($data, $dateTime, $uniqueID){
        foreach ($data['friendly'] as $key => $value) {
            \App\CharacterFriendly::create([
                'user_id' => Auth::id(),
                'character_id' => $key,
                'value' => $value,
                'unique_id' => $uniqueID,
                'created_at' => $dateTime,
                'updated_at' => $dateTime,
            ]);
        }
    }

    private function setRatingRecentMusic($data, $dateTime, $uniqueID){
        \App\RatingRecentMusic::where('user_id', Auth::id())->delete();
        foreach ($data['ratingRecentMusicObject'] as $key => $value) {
            \App\RatingRecentMusic::create([
                'user_id' => Auth::id(),
                'rank' => $key,
                'title' => $value['title'],
                'difficulty' => $value['difficulty'],
                'technical_score' => $value['technicalScore'],
                'unique_id' => $uniqueID,
                'created_at' => $dateTime,
                'updated_at' => $dateTime,
            ]);
        }
    }

    private function setPaymentStatus($data, $dateTime, $uniqueID){
        \App\UserInformation ::updateOrCreate(
            ['user_id' => Auth::id()], [
                'is_standard_plan' => $data['isStandardPlan'] === "true" ? 1 : 0,
                'is_premium_plan' => $data['isPremiumPlan'] === "true" ? 1 : 0,
                'unique_id' => $uniqueID,
                'created_at' => $dateTime,
                'updated_at' => $dateTime
            ]
        );
    }
}