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
 * index.php
 * 管理者ログイン画面
 *
 * $RCSfile$
 * $Revision$
 * $Date$
 *
 **********************************************************/
include_once("lib/dglibcommon");
include_once("lib/dglibpage");
include_once("lib/dglibsess");
include_once("lib/dglibpostldapadmin");

/********************************************************
各ページ毎の設定
*********************************************************/

define("OPERATION_LOGIN", "Log in");
define("TMPLFILE", "admin_login.tmpl");

/***********************************************************
 * 初期処理
 **********************************************************/

/* タグ初期化 */
$tag["<<TITLE>>"]      = "";
$tag["<<JAVASCRIPT>>"] = "";
$tag["<<MESSAGE>>"]    = "";

/* $basedirと$topdirのセット */
url_search();

/* ドメイン取得 */
$domain = $_SERVER['DOMAIN'];

/* 設定ファイルの読み込み */
if (read_web_conf(NULL) === FALSE) {
    syserr_display();
    exit(1);
}

/* メッセージファイルの読込 */
if (make_msgarr(MESSAGEFILE) === FALSE) {
    syserr_display();
    exit(1);
}

/***********************************************************
 * main処理
 **********************************************************/

/* 処理の分岐 */
if (isset($_POST["login"]) === TRUE) {

    /* 管理者名のチェック */
    if (check_admin_uname($_POST["admin"], MAXADMINNAME) === TRUE &&
        check_passwd($_POST["passwd"], MINADMINPASSWD, MAXADMINPASSWD) === TRUE) {
        /* セッションチェック&開始 */
        if (sess_start($_POST["admin"], $_POST["passwd"], $sesskey) === TRUE) {
            result_log(OPERATION_LOGIN . ":OK:" . $msgarr['05006'][LOG_MSG]);
            /* タブファイル読込 */
            if (read_tab_conf(ADMINTABCONF) === FALSE) {
                result_log($log_msg, LOG_ERR);
                syserr_display();
                exit (1);
            }
            $script = key($tab_conf);
            $tab = key($tab_conf[$script][0]);
            dgp_location($script . "/" . $tab . "/index.php");
            exit (0);
        } else {
            $err_msg = $msgarr['05002'][SCREEN_MSG];
            $log_msg = $msgarr['05002'][LOG_MSG];
        }
    } else {
        $err_msg = $msgarr['05002'][SCREEN_MSG];
        $log_msg = $msgarr['05002'][LOG_MSG];
    }
}

if (isset($_GET["e"]) === TRUE) {
    if ($_GET["e"] == 1) {
        $err_msg = $msgarr['05003'][SCREEN_MSG];
        $log_msg = $msgarr['05003'][LOG_MSG];
    }
    if ($_GET["e"] == 2) {
        $err_msg = $msgarr['05004'][SCREEN_MSG];
        $log_msg = $msgarr['05004'][LOG_MSG];
    }
    if ($_GET["e"] == 3) {
        $err_msg = $msgarr['05005'][SCREEN_MSG];
        $log_msg = $msgarr['05005'][LOG_MSG];
    }
}

/***********************************************************
 * 表示処理
 **********************************************************/

/* タイトルタグ 設定 */
$tag["<<TITLE>>"] = $web_conf["global"]["titlename"];

/* メッセージタグ 設定 */
if (empty($err_msg)) {
    $tag["<<MESSAGE>>"] = "&nbsp;";
} else {
    $tag["<<MESSAGE>>"] = $err_msg;
}

/* JavaScriptタグ 設定 */
$tag["<<JAVASCRIPT>>"] = <<<EOD
<script type="text/javascript">
<!--
function msgConfirm(msg) {
        return(window.confirm(msg));
}

function dgpSubmit(url) {
    document.common.action = url;
    document.common.submit();
}
// -->
</script>
EOD;

/* ページの出力 */
$ret = display(TMPLFILE, $tag, array(), "", "");
if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

?>
