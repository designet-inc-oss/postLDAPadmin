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
 * 管理者パスワード変更画面
 *
 * $RCSfile$
 * $Revision$
 * $Date$
 **********************************************************/

include_once("lib/dglibcommon");
include_once("lib/dglibpage");
include_once("lib/dglibsess");
include_once("lib/dglibldap");

/********************************************************
各ページ毎の設定
*********************************************************/

define("OPERATION", "Modifying administrator account");

/***********************************************************
 * クラス: メニュー画面
 **********************************************************/
Class my_page extends page {

    /***********************************************************
     * ボディー部の表示（オーバーライド）
     **********************************************************/
    function display_body() {

        global $sesskey;

print <<<EOD
<form method="post" action="index.php">
<table class="table">
  <tr>
    <td class="key1">パスワード</td>
    <td class="value">
      <input type="password" maxlength="8" name="newpasswd">
    </td>
  </tr>
  <tr>
    <td class="key1">パスワード（確認）</td>
    <td class="value">
      <input type="password" maxlength="8" name="re_newpasswd">
    </td>
  </tr>
</table>
<br>
<input type="submit" name="update" value="" class="mod_btn">
<input type="hidden" name="sk" value="$sesskey">
</form>

EOD;
    }

};

/*********************************************************
 * mod_passwd
 *
 * パスワードをチェックし、ファイルに書き込む
 *
 * [引数]
 * $data                POSTで渡されたデータ
 *
 * [返り値]
 * TRUE                 正常
 * FALSE                異常  
 **********************************************************/
function mod_passwd ($data)
{
    global $err_msg;
    global $web_conf;
    global $domain;
    global $basedir;

    /* 入力文字のチェック */
    if (check_passwd($data["newpasswd"], (int)$web_conf["global"]["minpasswordlength"], 
        (int)$web_conf["global"]["maxpasswordlength"]) === FALSE) {
        return FALSE;
    }

    /* パスワードの一致チェック */
    if ($data["newpasswd"] != $data["re_newpasswd"]) {
        $err_msg = "パスワードが一致しません。";
        return FALSE;
    }

    $old_passwd = $web_conf["adminpasswd"];

    /* 変更前のパスワードと一致しないかチェック */
    $new_passwd = md5($data["newpasswd"]);
    if ($new_passwd == $old_passwd) {
        $err_msg = "パスワードが変更前と一致します。";
        return FALSE;
    }

    /* ドメイン毎の設定ファイル */
    $conf_file = $basedir . ETCDIR . $domain . "/" . WEBCONF;

    /* 変更するデータをセット */
    $moddata["adminpasswd"] = $new_passwd;

    /* 変更 */
    if (write_web_conf($conf_file, $moddata) === FALSE) {
        return FALSE;
    }
   
    $err_msg = "管理者パスワードを更新しました。";
    return TRUE; 
}

/***********************************************************
 * 初期処理
 **********************************************************/
/* インスタンス作成 */
$pg = new my_page();

/* 設定ファイル、タブ管理ファイル読込、セッションチェック */
$ret = init();
if ($ret === FALSE) {
    $sys_err = TRUE;
    $pg->display(NULL);
    exit (1);
}

/***********************************************************
 * main処理
 **********************************************************/

/* パスワード変更 */
if (isset($_POST["update"])) {
    if (mod_passwd($_POST) === FALSE) {
        result_log(OPERATION . ":NG:" . $err_msg);
        $err_msg = escape_html($err_msg);
    } else {
        result_log(OPERATION . ":OK:" . $err_msg);

        /* ログアウト用にセッションキーを再生成 */
        sess_key_make($web_conf["adminname"], $_POST["newpasswd"], $sesskey);

        /* ユーザ管理メニュー画面へ */
        dgp_location("../index.php", $err_msg);
        exit;
    }
}

/***********************************************************
 * 表示処理
 **********************************************************/

/* ページの出力 */
$pg->display(CONTENT);

?>
