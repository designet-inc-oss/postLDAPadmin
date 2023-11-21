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
 * 管理者用ユーザ追加画面
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
 * 各ページ毎の設定
 ********************************************************/

define("OPERATION", "Adding user account");
define("TMPLFILE_LDAP",  "admin_user_add.tmpl");
define("TMPLFILE_WEBAUTH",  "admin_user_add_webauth.tmpl");
define("FORWARD_ON", "1");

/*********************************************************
 * set_tag_data()
 *
 * タグ情報セット関数
 *
 * [引数]
 *  	$post		入力された値
 *
 * [返り値]
 *	なし
 ********************************************************/

function set_tag_data($post, &$tag)
{
    global $mode;
    global $err_msg;
    global $url_data;
    global $web_conf;

    /* JavaScript 設定 */
    $java_script = <<<EOD

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
    /* 基本タグ 設定 */
    set_tag_common($tag, $java_script);

    /* メールボックス容量 設定 */
    if ($mode == ADD_MODE) {
        $post['quota'] = $web_conf[$url_data["script"]]["diskquotadefault"];
    }

    /* ユーザ情報タグ 設定 */
    set_admin_form_tag($mode, $post, array(), $tag);

}

/***********************************************************
 * 初期処理
 **********************************************************/

/* タグ初期化 */
$tag["<<TITLE>>"]      = "";
$tag["<<JAVASCRIPT>>"] = "";
$tag["<<SK>>"]         = "";
$tag["<<TOPIC>>"]      = "";
$tag["<<MESSAGE>>"]    = "";
$tag["<<TAB>>"]        = "";
$tag["<<UID>>"]        = "";
$tag["<<QUOTA>>"]      = "";
$tag["<<UNIT>>"]       = "";
$tag["<<ALIAS>>"]      = "";
$tag["<<TRANS>>"]      = "";
$tag["<<SAVEON>>"]     = "";
$tag["<<SAVEOFF>>"]    = "";
$tag["<<MAXPASSLEN>>"] = "";
$tag["<<FORWARD_START>>"] = "";
$tag["<<FORWARD_END>>"] = "";


/* 設定ファイルタブ管理ファイル読込、セッションのチェック */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit (1);
}

/***********************************************************
 * main処理
 **********************************************************/

/* 処理の分岐 */
if (isset($_POST["add"])) {
    $mode = POST_ADD_MODE;

    /* 入力データのチェック */
    $ret = check_add_data($_POST);

    /* システムエラー */
    if ($ret == FUNC_SYSERR) {
        result_log(OPERATION . ":NG:" . $log_msg);
        syserr_display();
        exit (1);

    /* 入力エラー */
    } elseif ($ret == FUNC_FALSE) {
        result_log(OPERATION . ":NG:" . $log_msg);
    } else {

        /* LDAP 登録 */
        if (add_user($_POST) === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg);
            syserr_display();
            exit (1);

        } else {
            $dispattr = $web_conf[$url_data['script']]['displayuser'];
            if (isset($attr[$dispattr])) {
                $dispusr = $attr[$dispattr];
            } else {
                $dispusr = "";
            }

            $err_msg = sprintf($msgarr['13001'][SCREEN_MSG], $dispusr);
            $log_msg = sprintf($msgarr['13001'][LOG_MSG], $dispusr);
            result_log(OPERATION . ":OK:" . $log_msg);
 
            /* ユーザ管理メニュー画面へ */
            dgp_location("../index.php", $err_msg);
            exit;
        }
    }
} elseif(isset($_POST["cancel"])) {

    /* ユーザ管理メニュー画面へ */
    dgp_location("../index.php", $err_msg);
    exit;

} else {
    $mode = ADD_MODE;
}

/***********************************************************
 * 表示処理
 **********************************************************/
// ForwardConfがONの場合は記入欄表示消す
if ($web_conf[$url_data['script']]['forwardconf'] === FORWARD_ON) {
    $tag["<<FORWARD_START>>"] = "<!--";
    $tag["<<FORWARD_END>>"] = "-->";
}

/* タグ情報 セット */
set_tag_data($_POST, $tag);

/* ページの出力 */
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
