@extends('layouts.app')

@section('title', 'ログインページ')
@section('hero_subtitle', '')
@section('hero_title', '使い方')
@section('sidemark_howto', "is-active")

@section('content')
<div class="container">
    <article class="box">
        <p class="space-bottom">
            以下書きかけです。
        </p>

        <h3 class="title is-3">はじめに</h3>
        <p class="space-bottom">
            このサイトではオンゲキNETからデータを取得します。<br>
            まずはオンゲキNETへの会員登録を済ませ、カードの紐づけを行ってください。<br>
            <a href="http://otogame-net.com" target="_blank">詳しくはこちら</a>
        </p>
        <p class="space-bottom">
            オンゲキNETの情報を使用する為、有料プランに加入していないと得られる情報に制限が掛かります。<br>
            <strong>プレミアムコースに課金しましょう！！</strong>
        </p>

        <table class="table is-bordered is-striped">
            <tbody>
                <tr>
                    <th>無料コース</th>
                    <td>基本的なプレイヤー情報のみ</td>
                </tr>
                <tr>
                    <th>スタンダートコース</th>
                    <td>楽曲のスコア キャラクター情報など</td>
                </tr>
                <tr>
                    <th>プレミアムコース</th>
                    <td>レーティング・BP対象曲情報</td>
                </tr>
            </tbody>
        </table>

        <h3 class="title is-3">ブックマークレットの使い方</h3>
        <p class="space-bottom">
            どのブラウザでも以下のような流れになると思います。<br>
            詳しくは<strong>「ブックマークレット &lt;環境名&gt;」</strong>などで調べてみてください。<br>
            以下はPC版Google Chromeでの解説です。
            <ol>
                <li><a href="/bookmarklet" target="_blank">このページ</a>からブックマークレットを取得します。メモ帳などにコピペしておいてください。</li>
                <li>適当なサイトをブックマークに登録します</li>
                <li>右クリック→編集を押します</li>
                <img src="/img/howto_chrome_01.jpg"><br>
                <li>以下のように変更します(名前は何でもok わかりやすく設定しましょう)</li>
                <img src="/img/howto_chrome_02.jpg"><br>
                <li><a href="https://ongeki-net.com">オンゲキNET</a>にログインします</li>
                <li>ホーム画面でさっき登録したブックマークを実行します</li>
            </ol>
        </p>
    </article>
</div>
@endsection