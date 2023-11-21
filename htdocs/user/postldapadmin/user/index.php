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

/********************************************************
 *各ページ毎の設定
 *********************************************************/
define("OPERATION", "Modifying user");
define("MODE_LDAPDATA", 0);
define("MODE_POSTDATA", 1);
define("TMPLFILE_LDAP", "user_user_mod.tmpl");
define("TMPLFILE_WEBAUTH", "user_user_mod_webauth.tmpl");
define("FORWARD_ON", "1");

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

    // forwardconfがoffの場合に設定
    if($web_conf[$url_data['script']]['forwardconf'] === FORWARD_OFF) {
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

        // mailFilterOrder、mailFilterArticleが存在すれば削除する
        if (isset($ldapdata[0]['mailFilterOrder'])) {
            $attrs["mailFilterOrder"][0] = "";
        }
        if (isset($ldapdata[0]['mailFilterArticle'])) {
            $attrs["mailFilterArticle"] = array();
        }

    // forwardconfがonの状態でmailForwardingAddrがある場合は要素をキープ
    } else {
        if (isset($ldapdata[0]['mailForwardingAddr'])) {
            $attrs['mailForwardingAddr'] = $ldapdata[0]['mailForwardingAddr'];
        }
    }

    /* メールディレクトリ属性を持たなければ作成 */
    if (!isset($ldapdata[0]["mailDirectory"][0])) {
        $attrs["mailDirectory"] = $web_conf[$url_data["script"]]["basemaildir"] . 
                                  "/" . $user . "/";
    }

    // mailFilterArticle, Orderが存在した場合は削除対象
    if (isset($ldapdata[0]['mailFilterOrder'])) {
        $attrs['mailFilterOrder'] = array();
    }
    if (isset($ldapdata[0]['mailFilterArticle'])) {
        $attrs['mailFilterArticle'] = array();
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
$tag["<<FORWARD_START>>"] = "";
$tag["<<FORWARD_END>>"] = "";

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
$trans = "";
$save = "";

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

    $mail = $ldapdata[0]['mail'][0];
    $passwd = $_POST['passwd1'];
    $repasswd = $_POST['passwd2'];
    if (isset($_POST['trans'])) {
        $trans = $_POST['trans'];
        if (isset($_POST['save'])) { 
            $save = $_POST['save'];
        } else {
            $save = NULL;
        }
    }

    /* 入力データのチェック */
    $ret = check_user_data($mail, $passwd, $repasswd, $trans, $save, $attrs);
    if ($ret === FALSE) {
        result_log(OPERATION . ":NG:" . $log_msg);
    } else {

        $ret = mod_user_data($attrs);
        if ($ret === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg);
        } else {
            /* 成功した場合は表示用に再度LDAPからデータを取得 */
            unset($ldapdata);
            $ret = get_userdata($userdn);
            if ($ret !== TRUE) {
                $err_msg = $msgarr['21008'][SCREEN_MSG];
                $log_msg = $msgarr['21008'][LOG_MSG];
                $sys_err = TRUE;
                result_log(OPERATION . ":NG:" . $log_msg);
                syserr_display();
                exit (1);
            }
        }
    }
}

/***********************************************************
 * 表示処理
 **********************************************************/

/* メール転送アドレス表示処理 */
$trans = "";
$save_mail_check = "";
$unsave_mail_check = "";
if ($mode == MODE_POSTDATA) {
    /* 更新モード（更新ボタンが押された場合） */
    if (isset($_POST['trans'])) {
        $trans = escape_html($_POST['trans']);
    }
    if (isset($_POST['save']) && $_POST['save'] == "0") {
        $save_mail_check = "checked";
    } else {
        $unsave_mail_check = "checked";
    }
} else {
    /* LDAPデータ取得モード（初期表示） */
    $count = 0;
    if (isset($ldapdata[0]['mailForwardingAddr'])) {
        $count = count($ldapdata[0]['mailForwardingAddr']);
    }

    if ($count == 2) {
        /* mailForwardingAddrが2つ設定されている場合はメールを残す設定 */
        $save_mail_check = "checked";
        /* 自分のメールアドレスでない方が転送先アドレスである */
        if ($ldapdata[0]['mailForwardingAddr'][0] == $ldapdata[0]['mail'][0]) {
            $trans = $ldapdata[0]['mailForwardingAddr'][1];
        } else {
            $trans = $ldapdata[0]['mailForwardingAddr'][0];
        }
    } elseif ($count == 1) {
        /* mailForwardingAddrが1つ設定されている場合はメールを残さない設定 */
        $unsave_mail_check = "checked";
        /* この場合は、かならず転送先アドレスである */
        $trans = $ldapdata[0]['mailForwardingAddr'][0];
    }
    $trans = escape_html($trans);
} 

/* タグの値をセット */
$java_script = <<<EOD
window.onload = function() {
    var i;
    var len = document.mod_form.save.length;
    if(document.mod_form.trans.value == "") {
        for(i=0;i<len;i++) {
          document.mod_form.save[i].disabled = true;
        }
    } else {
        for(i=0;i<len;i++) {
            document.mod_form.save[i].disabled = false;
        }
    }
}
function check(n) {
    var i;
    var len = document.mod_form.save.length;
    if(n == "") {
        for(i=0;i<len;i++) {
            document.mod_form.save[i].disabled = true;
        }
    } else {
        for(i=0;i<len;i++) {
            document.mod_form.save[i].disabled = false;
        }
    }
}
EOD;
set_tag_common($tag, $java_script);
$tag["<<UID>>"] = $dispusr;
$tag["<<TRANSFERADDR>>"] = $trans;
$tag["<<SAVEMAILENABLED>>"] = $save_mail_check;
$tag["<<SAVEMAILDISABLED>>"] = $unsave_mail_check;
$tag["<<MAXPASSLEN>>"] = $web_conf["global"]["maxpasswordlength"];

// ForwardConfがONの場合は記入欄表示消す
if ($web_conf[$url_data['script']]['forwardconf'] === FORWARD_ON) {
    $tag["<<FORWARD_START>>"] = "<!--";
    $tag["<<FORWARD_END>>"] = "-->";
}

/* ページの出力 */
if ($web_conf['global']['webauthmode'] === WEBAUTHMODE_OFF){
    $ret = display(TMPLFILE_LDAP, $tag, array(), "", "");
}
else if ($web_conf['global']['webauthmode'] === WEBAUTHMODE_ON) {
    $ret = display(TMPLFILE_WEBAUTH, $tag, array(), "", "");
}

if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

?>
