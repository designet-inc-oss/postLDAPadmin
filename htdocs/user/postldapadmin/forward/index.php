<?php

/*
 * postLDAPadmin
 *
 * Copyright (C) 2006,2007 DesigNET, INC.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

/***********************************************************
 * ユーザ用ユーザ修正画面
 *
 * $RCSfile$
 * $Revision$
 * $Date$
 **********************************************************/
include_once("../../initial");
include_once("lib/dglibldap");
include_once("lib/dglibpostldapadmin");
include_once("lib/dglibcommon");
include_once("lib/dglibpage");
include_once("lib/dglibsess");
include_once("lib/dglibdovecot");
include_once("lib/dglibforward");

/********************************************************
 *各ページ毎の設定
 *********************************************************/
define("OPERATION", "Modifying user");
define("MODE_LDAPDATA", 0);
define("MODE_POSTDATA", 1);
define("TMPLFILE", "user_forward_mod.tmpl");

/*********************************************************
 * check_user_data
 *
 * 入力フォームの形式チェック
 *
 * [引数]
 *         $mail     メールアドレス
 *         $passwd   パスワード
 *         $repasswd パスワード(確認)
 *         $trans    転送先アドレス
 *         $save     メール保存設定
 *         &$attrs   修正する属性を格納した配列
 * [返り値]
 *         TRUE      正常
 *         FALSE     異常
 *
 **********************************************************/
function check_user_data($mail, $passwd, $repasswd, $trans, $save, &$attrs)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;
    global $user;
    global $ldapdata;
    global $url_data;
    
    $enpass = "";

    if (isset($ldapdata[0]['mailAlias'][0])) {
        $alias = $ldapdata[0]['mailAlias'][0];
    }
    $transes = array();

    /* パスワード入力チェック */
    if ($passwd != "" || $repasswd != "") {
        $ret = check_passwd($passwd, (int)$web_conf["global"]["minpasswordlength"], 
                            (int)$web_conf["global"]["maxpasswordlength"]);
        if ($ret === FALSE) {
            return FALSE;
        }
        /* パスワードの一致を確認 */
        if ($passwd != $repasswd) {
            $err_msg = $msgarr['21001'][SCREEN_MSG];
            $log_msg = $msgarr['21001'][LOG_MSG];
            return FALSE;
        }

        /* パスワードを格納 */
        $enpass = make_passwd($passwd);
        if ($enpass === FALSE) {
            return FALSE;
        }
        $attrs['userPassword'] = $enpass;
    }

    if ($trans != "") {
        /* メール転送アドレス入力チェック */
        if (check_mail ($trans) === FALSE) {
            $err_msg = $msgarr['21002'][SCREEN_MSG];
            $log_msg = $msgarr['21002'][LOG_MSG];
            return FALSE;
        }
        array_push ($transes, $trans);
        /* メール保存設定チェック */
        if ($save == "") {
            $err_msg = $msgarr['21003'][SCREEN_MSG];
            $log_msg = $msgarr['21003'][LOG_MSG];
            return FALSE;
        }
        if (check_flg($save) === FALSE) {
            $err_msg = $msgarr['21004'][SCREEN_MSG];
            $log_msg = $msgarr['21004'][LOG_MSG];
            return FALSE;
        }
        /* 転送アドレス重複チェック */
        if ($trans == $mail) {
            $err_msg = $msgarr['21005'][SCREEN_MSG];
            $log_msg = $msgarr['21005'][LOG_MSG];
            return FALSE;
        }
        if (isset ($alias) && $trans == $alias) {
            $err_msg = $msgarr['21006'][SCREEN_MSG];
            $log_msg = $msgarr['21006'][LOG_MSG];
            return FALSE;
        }
        if ($save == 0) {
            /* メールを残す設定の場合は転送アドレスに自メールアドレスを追加 */
            array_push($transes, $mail);
        }

        /* 転送アドレスを格納 */
        $attrs['mailForwardingAddr'] = $transes;
    } else {
        /* 転送先アドレスが空であれば、削除対象 */
        $attrs['mailForwardingAddr'] = array();
    }

    /* メールディレクトリ属性を持たなければ作成 */
    if (!isset($ldapdata[0]["mailDirectory"][0])) {
        $attrs["mailDirectory"] = $web_conf[$url_data["script"]]["basemaildir"] . 
                                  "/" . $user . "/";
    }

    return TRUE;
}

/*********************************************************
 * mod_user_data
 *
 * ユーザデータの変更を行う
 *
 * [引数]
 *         $attrs   修正する属性を格納した配列
 * [返り値]
 *         TRUE     正常
 *         FALSE    異常
 *
 **********************************************************/
function mod_user_data($attrs)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $env;
    global $user;
    global $sesskey;

    /* 管理者DNで変更 */
    $env['user_self'] = FALSE;

    /* LDAPデータの更新 */
    $dn = $env['user_selfdn'];
    $ret = LDAP_mod_entry($dn, $attrs);
    if ($ret !== LDAP_OK) {
        return FALSE;
    } else {
        $err_msg = $msgarr['21007'][SCREEN_MSG];
        $log_msg = $msgarr['21007'][LOG_MSG];
        result_log(OPERATION . ":OK:" . $log_msg);
        if (isset ($_POST['passwd1']) && $_POST['passwd1'] != "") {
           /* ログアウト用にセッションキーを再生成 */
           sess_key_make($user, $_POST['passwd1'], $sesskey);
        }
    }
    return TRUE;
}

/***********************************************************
 * 初期処理
 **********************************************************/

/* タグ初期化 */
$tag["<<TITLE>>"] = "";
$tag["<<JAVASCRIPT>>"] = "";
$tag["<<SK>>"] = "";
$tag["<<TOPIC>>"] = "";
$tag["<<MESSAGE>>"] = "";
$tag["<<TAB>>"] = "";
$tag["<<UID>>"] = "";
$tag["<<TRANSFERADDR>>"] = "";
$tag["<<SAVEMAILENABLED>>"] = "";
$tag["<<SAVEMAILDISABLED>>"] = "";
$tag["<<MAXPASSLEN>>"] = "";

// looptag
// filter id
$tag_loop["<<FILTER_ID>>"] = "";
// 全て転送ラジオ
$tag_loop["<<ALL_FORWARD>>"] = "checked";
// 詳細設定ラジオ
$tag_loop["<<DETAIL_FORWARD>>"] = "";
// 送信者設定
$tag_loop["<<FORWARD_CHECK>>"] = "";
$tag_loop["<<FORWARD_TEXT>>"] = "";
$tag_loop["<<FORWARD_MATCH>>"] = "selected";
$tag_loop["<<FORWARD_INCLUDE>>"] = "";
$tag_loop["<<FORWARD_NOT_INC>>"] = "";
$tag_loop["<<FORWARD_EMPTY>>"] = "";
// 件名設定 
$tag_loop["<<SUBJECT_CHECK>>"] = "";
$tag_loop["<<SUBJECT_TEXT>>"] = "";
$tag_loop["<<SUBJECT_MATCH>>"] = "selected";
$tag_loop["<<SUBJECT_INCLUDE>>"] = "";
$tag_loop["<<SUBJECT_NOT_INC>>"] = "";
$tag_loop["<<SUBJECT_EMPTY>>"] = "";
// 宛先設定 
$tag_loop["<<RECIPT_CHECK>>"] = "";
$tag_loop["<<RECIPT_TEXT>>"] = "";
$tag_loop["<<RECIPT_MATCH>>"] = "selected";
$tag_loop["<<RECIPT_INCLUDE>>"] = "";
$tag_loop["<<RECIPT_NOT_INC>>"] = "";
$tag_loop["<<RECIPT_EMPTY>>"] = "";
// 転送先、メールの処理
$tag_loop["<<TRANSFER_ADDR>>"] = "";
$tag_loop["<<MAIL_LEAVE>>"] = "selected";
$tag_loop["<<MAIL_DEL>>"] = "";

// loopタグ作成
$loop = array();

/* 設定ファイル、タブ管理ファイル読込、セッションチェック */
$ret = user_init();
if ($ret === FALSE) {
    $sys_err = TRUE;
    syserr_display();
    exit (1);
}

/***********************************************************
 * main処理
 **********************************************************/
/* 処理の分岐 */

/* ユーザ名格納 */
$user = $env['loginuser'];
$userdn = $env['user_selfdn'];
$del_flag = "";

/* ユーザ情報の取得 */
$ret = get_userdata ($userdn);
if ($ret !== TRUE) {
    $err_msg = $msgarr['21008'][SCREEN_MSG];
    $log_msg = $msgarr['21008'][LOG_MSG];
    $sys_err = TRUE;
    result_log(OPERATION . ":NG:" . $log_msg);
    syserr_display();
    exit (1);
}

$dispusr = $web_conf[$url_data['script']]['displayuser'];
$dispusr = escape_html($ldapdata[0][$dispusr][0]);

$mode = MODE_LDAPDATA;
if (isset($_POST['modify'])) {

    $mode = MODE_POSTDATA;

    // 入力データのチェック
    $ret = check_forward_data($_POST, $attrs);
    if ($ret === FALSE) {
        result_log(OPERATION . ":NG:" . $log_msg);
        $del_flag = "on";
    } else {

        // 登録処理
        $ret = mod_user_data($attrs);
        if ($ret === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg);
        } else {
            // 更新情報を上書き
            $ldapdata[0]['mailFilterArticle'] = $attrs['mailFilterArticle'];
            $ldapdata[0]['mailFilterOrder'] = $attrs['mailFilterOrder'];

            // sieveファイル作成
            $ret = make_sievefile();
            // sieveファイルの作成に失敗した場合は画面再表示
            if ($ret === FALSE) {
                $err_msg = $msgarr['26011'][SCREEN_MSG];
                dgp_location("./index.php", $err_msg);
                exit(0);
            }
            $err_msg = $msgarr['25007'][SCREEN_MSG];
            $log_msg = $msgarr['25007'][LOG_MSG];
            result_log(OPERATION . ":OK:" . $log_msg);
        }
    }
} elseif (isset($_POST['buttonName']) && $_POST['buttonName'] === "delete") {

    // ポスト用画面表示処理に入る為のフラグを付加する
    $mode = MODE_POSTDATA;
    $del_flag = "on";
}

/***********************************************************
 * 表示処理
 **********************************************************/

/* メール転送アドレス表示処理 */
$trans = "";
$save_mail_check = "";
$unsave_mail_check = "";
if ($mode == MODE_POSTDATA) {
    // 更新モード（更新ボタン,削除ボタンが押された場合）
    keep_post_forward_data($_POST, $loop, $del_flag);

} else {
    // LDAPデータ反映（データあった場合の初期表示）
    if (isset($ldapdata[0]['mailFilterOrder'][0]) === TRUE &&
        isset($ldapdata[0]['mailFilterArticle']) === TRUE) {

        // フィルターオーダー取得
        $filterorder = array();
        order_analysis($ldapdata[0]['mailFilterOrder'][0], $filterorder);

        // フィルターアーティクル取得
        $filterarticle = array();
        $ret = article_analysis($ldapdata[0]['mailFilterArticle'], $filterarticle);
        if ($ret === FALSE) {
            $err_msg = $msgarr['25005'][SCREEN_MSG];
        }

        // LDAPの登録データを画面に反映
        $ret = reflect_filter_data($filterorder, $filterarticle, $loop);

        // orderの数と許可lineが一致しない場合は追加で入力欄作成
        for ($i = count($filterorder); 
             $i < $web_conf[$url_data['script']]['forwardnum']; $i++) {
            // デフォルトのidをセットしてループタグを作成する
            $tag_loop["<<FILTER_ID>>"] = $i+1;
            array_push($loop, $tag_loop);
        }

    } else {
        $i = 1;
        // mailForwardingAddrが存在した場合は全て転送で表示させる
        if (isset($ldapdata[0]['mailForwardingAddr'])) {
            convert_forward_value($i, $loop);
            $i++;
        }

        // order、articleが存在しない場合は初期値
        for ($i ; $i <= $web_conf[$url_data['script']]['forwardnum']; $i++) {
            // デフォルトのidをセットしてループタグを作成する
            $tag_loop["<<FILTER_ID>>"] = $i;
            array_push($loop, $tag_loop);
        }
    }

} 

/* タグの値をセット */
$java_script = <<<EOD
function dgpSubmitMulti(buttonValue, id) {
    document.getElementById('filterId').value = id;
    document.getElementById('buttonName').value = buttonValue;
    document.forms['filter_form'].submit();
}
function confirmDelete(buttonValue, filterValue) {
    if(confirm('本当に削除してよろしいですか？')) {
            dgpSubmitMulti(buttonValue, filterValue);
    }
}
EOD;
set_tag_common($tag, $java_script);
$tag["<<UID>>"] = $dispusr;
$tag["<<TRANSFERADDR>>"] = $trans;
$tag["<<SAVEMAILENABLED>>"] = $save_mail_check;
$tag["<<SAVEMAILDISABLED>>"] = $unsave_mail_check;
$tag["<<MAXPASSLEN>>"] = $web_conf["global"]["maxpasswordlength"];

/* ページの出力 */
$ret = display(TMPLFILE, $tag, $loop, "<<LOOP_START>>", "<<LOOP_END>>");
if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

?>
