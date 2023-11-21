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
 * 管理者用ユーザ修正画面
 *
 * $RCSfile$
 * $Revision$
 * $Date$
 **********************************************************/

include_once("../../initial");
include_once("lib/dglibpostldapadmin");
include_once("lib/dglibcommon");
include_once("lib/dglibpage");
include_once("lib/dglibsess");
include_once("lib/dglibldap");

/********************************************************
各ページ毎の設定
*********************************************************/

define("TMPLFILE_LDAP", "admin_user_mod.tmpl");
define("TMPLFILE_WEBAUTH", "admin_user_mod_webauth.tmpl");

define("OPERATION", "Searching user account");

define("MODE_LDAPDATA", 0);
define("MODE_POSTDATA", 1);
define("FORWARD_ON", "1");

/*********************************************************
 * set_tag_data
 *
 * タグのセット
 *
 * [引数]
 *        $post      POSTで渡された値
 *        $tag       タグを格納
 *
 * [返り値]
 *        なし
 *
 **********************************************************/
function set_tag_data($post, &$tag)
{
    global $user;
    global $dispusr;
    global $mode;
    global $ldapdata;
    global $userdn;
    global $form_name;

    /* DNの暗号化 */
    $userdn = base64_encode($userdn);
    $userdn = str_rot13($userdn);

    /* hiddenで渡すデータを格納 */
    $hiddendata['dn'] = $userdn;
    $hiddendata['page'] = $_POST["page"];
    $hiddendata['filter'] = $_POST["filter"];
    $hiddendata['form_name'] = $form_name;
    $hiddendata['name_match'] = $_POST['name_match'];
    $hiddendata['uid'] = $dispusr;

    if ($mode == MOD_MODE) {

        $postdata['uid'] = $dispusr;
        if (isset($ldapdata[0]["quotaSize"][0])) {
            $postdata['quota'] = $ldapdata[0]["quotaSize"][0];
        }
 
        /* 転送先アドレスに自分のアドレスがあれば、
        サーバにメールを残す設定、なければ残さない。 */
        $count = 0;
        if (isset($ldapdata[0]['mailForwardingAddr'])) {
            $count = count($ldapdata[0]['mailForwardingAddr']);
        }
       
        if ($count == 2) {
             /* サーバにメールを残す */
             $postdata['save'] = ON;
             if ($ldapdata[0]['mail'][0] == $ldapdata[0]['mailForwardingAddr'][0]) {
                 $postdata['trans'] = $ldapdata[0]['mailForwardingAddr'][1];
             } else {
                 $postdata['trans'] = $ldapdata[0]['mailForwardingAddr'][0];
             }
        } elseif ($count == 1) {
             $postdata['save'] = OFF;
             $postdata['trans'] = $ldapdata[0]['mailForwardingAddr'][0];
        }

        /* メールエイリアスの分割 */
        if (isset($ldapdata[0]['mailAlias'][0])) {
            $ldapalias = escape_html($ldapdata[0]['mailAlias'][0]);
            $part = explode("@", $ldapalias, 2);
            $postdata['alias'] = $part[0];
        }
    } elseif ($mode == POST_MOD_MODE) {
        $postdata = $_POST;
    }

    set_admin_form_tag($mode, $postdata, $hiddendata, $tag);

}

/***********************************************************
 * 初期処理
 **********************************************************/
/* 初期化 */
$tag["<<UID>>"] = "";
$tag["<<QUOTA>>"] = "";
$tag["<<ALIAS>>"] = "";
$tag["<<TRANS>>"] = "";
$tag["<<SAVEON>>"] = "";
$tag["<<SAVEOFF>>"] = "";
$tag["<<FORWARD_START>>"] = "";
$tag["<<FORWARD_END>>"] = "";

/* ユーザ情報格納 */
if (isset($_POST["dn"])) {
    $userdn = $_POST["dn"];
    $userdn = str_rot13($userdn);
    $userdn = base64_decode($userdn);
}
if (isset($_POST["page"])) {
    $page = $_POST["page"];
}
if (isset($_POST["filter"])) {
    $filter = $_POST["filter"];
}

/* 設定ファイル、タブ管理ファイル読込、セッションチェック */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit (1);
}

/***********************************************************
 * main処理
 **********************************************************/
/* 処理の分岐 */
/* ページの形式チェック */
if (is_num_check($page) === FALSE) {
    $err_msg = $msgarr['16001'][SCREEN_MSG];
    $log_msg = $msgarr['16001'][LOG_MSG];
    result_log(OPERATION . ":NG:" . $log_msg);
    syserr_display();
    exit (1);
}

/* フィルタの複合化 */
if (sess_key_decode($filter, $dec_filter) === FALSE) {
    result_log(OPERATION . ":NG:" . $log_msg);
    syserr_display();
    exit (1);
}

/* フィルタの形式チェック */
$fdata = explode(':', $dec_filter);
if (count($fdata) != 3) {
    $err_msg = $msgarr['16002'][SCREEN_MSG];
    $log_msg = $msgarr['16002'][LOG_MSG];
    result_log(OPERATION . ":NG:" . $log_msg);
    syserr_display();
    exit (1);
}

/* DNの形式チェック */
$len = (-1) * strlen($web_conf[$url_data['script']]['ldapbasedn']);
$cmpdn = substr($userdn, $len);
if (strcmp($cmpdn, $web_conf[$url_data['script']]['ldapbasedn']) != 0) {
    $err_msg = $msgarr['16003'][SCREEN_MSG];
    $log_msg = $msgarr['16003'][LOG_MSG];
    result_log(OPERATION . ":NG:" . $log_msg);
    syserr_display();
    exit (1);
}

/* ユーザ情報の取得 */
$ret = get_userdata($userdn);
if ($ret !== TRUE) {
    if ($ret == LDAP_ERR_NODATA) {
        $err_msg = $msgarr['16004'][SCREEN_MSG];
        $log_msg = $msgarr['16004'][LOG_MSG];
        result_log(OPERATION . ":OK:" . $log_msg);
        dgp_location_search("index.php", $err_msg);
    } else {
        result_log(OPERATION . ":NG:" . $log_msg);
        syserr_display();
        exit (1);
    }
}

$user = $ldapdata[0]["uid"][0];

$dispattr = $web_conf[$url_data['script']]['displayuser'];
$dispusr = $ldapdata[0][$dispattr][0];

/* フォーム情報格納 */
$form_name = $_POST["form_name"];
$name_match = $_POST["name_match"];

if (isset($_POST['modify'])) {

    /* 更新モード */
    $mode = POST_MOD_MODE;

    /* 変更用データ */
    $data = $_POST;
    $data["mail"] = $ldapdata[0]["mail"][0];
    $data["uid"] = $ldapdata[0]["uid"][0];

    /* 入力データのチェック */
    if (check_mod_data($data) === FALSE) {
        result_log(OPERATION . ":NG:" . $log_msg);
    } else {
        if (mod_user($data) === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg);
            syserr_display();
            exit (1);
        } else {
            $err_msg = sprintf($msgarr['16005'][SCREEN_MSG], $dispusr);
            $log_msg = sprintf($msgarr['16005'][LOG_MSG], $dispusr);
            result_log(OPERATION . ":OK:" . $log_msg);

            /* 検索画面に遷移 */
            dgp_location_search("index.php", $err_msg);
            exit;
        }
    }
} elseif (isset($_POST["delete"])) {

    $mode = POST_MOD_MODE;

    /* ユーザ削除 */
    if ($user != "") {
       if (del_user($user) === FALSE) {
           result_log(OPERATION . ":NG:" . $log_msg);
           syserr_display();
           exit (1);
       } else {
           $err_msg = sprintf($msgarr['16006'][SCREEN_MSG], $dispusr);
           $log_msg = sprintf($msgarr['16006'][LOG_MSG], $dispusr);
           result_log(OPERATION . ":OK:" . $log_msg);
       }
    }
    /* 検索画面に遷移 */
    dgp_location_search("index.php", $err_msg);
    exit;

} elseif (isset($_POST["cancel"])) {

    /* 検索画面に遷移 */
    dgp_location_search("index.php", $err_msg);
    exit;

} else {

    /* LDAPデータ取得モード */
    $mode = MOD_MODE;
}



/***********************************************************
 * 表示処理
 **********************************************************/
/* JAVASCRIPT */
$javascript = <<<EOD
window.onload = function() {
  var i;
  var len = document.data_form.save.length;
  if(document.data_form.trans.value == "") {
    for(i=0;i<len;i++) {
      document.data_form.save[i].disabled = true;
    }
  } else {
    for(i=0;i<len;i++) {
      document.data_form.save[i].disabled = false;
    }
  }
}
function check(n) {
  var i;
  var len = document.data_form.save.length;
  if(n == "") {
    for(i=0;i<len;i++) {
      document.data_form.save[i].disabled = true;
    }
  } else {
    for(i=0;i<len;i++) {
      document.data_form.save[i].disabled = false;
    }
  }
}

EOD;

/* 共通タグ */
set_tag_common($tag, $javascript);

/* タグセット */
set_tag_data($_POST, $tag);

// ForwardConfがONの場合は記入欄表示消す
if ($web_conf[$url_data['script']]['forwardconf'] === FORWARD_ON) {
    $tag["<<FORWARD_START>>"] = "<!--";
    $tag["<<FORWARD_END>>"] = "-->";
}

// ページの出力
if ($web_conf['global']['webauthmode'] === WEBAUTHMODE_OFF) {
    $ret = display(TMPLFILE_LDAP, $tag, array(), "", "");
} else if ($web_conf['global']['webauthmode'] === WEBAUTHMODE_ON) {
    $ret = display(TMPLFILE_WEBAUTH, $tag, array(), "", "");
}

if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

?>
