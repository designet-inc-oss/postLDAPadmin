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
 * メーリングリスト編集画面
 *
 * $RCSfile$
 * $Revision$
 * $Date$
 **********************************************************/
include_once("../../initial");
include_once("lib/dglibpostldapadmin");
include_once("lib/dglibcommon");
include_once("lib/dglibpage");
include_once("lib/dglibldap");
include_once("lib/dglibsess");

/***********************************************************
各ページ毎の設定
*********************************************************/
define("OPERATION", "Modifying mailinglist");
define("TMPLFILE", "admin_ml_mod_mod.tmpl");

/*********************************************************
 * mk_addr_list
 *
 * アドレスのリストを作成する
 *
 * [引数]
 *           $nowlist	現在のリスト
 *           $input	入力されたリスト
 *           $list      アドレスの格納先
 *           $bool      リストに加える条件(TRUE:存在する| FALSE:しない)
 *
 * [返り値]
 *	     int	アドレスの数
 **********************************************************/
function mk_addr_list($nowlist, $input, &$list, $bool)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $logstr;

    $list = array();

    $i = 0;
    /* 現在のリストが一つも無い場合 */
    if (!is_array($nowlist)) {
        if ($bool === TRUE) {
            return 0; 
        }
        $list = $input;
        return count($list);
    }

    $j = 0;
    $err = 0;
    foreach($input as $addr) {
        $j++;
        reset($nowlist);
        if ((array_search($addr, $nowlist) !== FALSE) === $bool) {
            $list[] = $addr;
            $i++;
        } else {
            $err++;
            $err_msg .= $msgarr['12006'][SCREEN_MSG] . "(" . $j . "行目)<BR>";
            $log_msg .= $msgarr['12006'][LOG_MSG]    . "(line" . $j . ")<BR>";
        }
    }

    /*  エラー */
    if ($err != 0) {
        $logstr = OPERATION . ":NG:" . $log_msg;
        return -1;
    }

    return $i;
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
$tag["<<MLADDR>>"] = "";
$tag["<<HIDDEN>>"] = "";
$tag["<<NEWADDR>>"] = "";
$tag["<<MAILADDRS>>"] = "";

/* 設定ファイル、タブ管理ファイル読込、セッションチェック */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit (1);
}

/***********************************************************
 * main処理
 **********************************************************/

$mladdr = $_POST["mladdr"];

/* LDAPの検索用DN */
if (isset($_POST["myadd"]) || isset($_POST["myaddlist"]) || isset($_POST["mydel"]) ) {
    /* メーリングリスト管理画面からの遷移以外は複合化 */
    $mladdr = str_rot13($mladdr);
    $mladdr = base64_decode($mladdr);
}

$search_dn = sprintf(ADD_DN, $mladdr, $web_conf[$url_data['script']]['ldaplistsuffix'],
                     $web_conf[$url_data["script"]]["ldapbasedn"]);

$dispmlatt = $web_conf[$url_data['script']]['displayml'];

/* LDAP検索フィルター作成 */
$filter = "(&(objectClass=" . PLAOC . ")" .
          "(&" . $web_conf[$url_data["script"]]["ldapmlfilter"] .
          "(" . $dispmlatt . "=*)" .
          "))";

/* 個別登録 */
if (isset($_POST["myadd"])) {

    /* アドレスの書式チェック */
    if (check_mail($_POST["newaddr"]) === FALSE) {
        $err_msg = $msgarr['12002'][SCREEN_MSG];
        $log_msg = $msgarr['12002'][LOG_MSG];
        $logstr = OPERATION . ":NG:" . $log_msg;
    } else {
        /* メールアドレスの取得 */
        $result = array();
        $ret = main_get_entry($search_dn, $filter, array(), 
                              $web_conf[$url_data["script"]]["ldapscope"], $result);
        /* LDAPサーチに失敗 */
        if ($ret != LDAP_OK) {
            $sys_err = TRUE;
            result_log(OPERATION . ":NG:" . $log_msg);
            syserr_display();
            exit (1);
        }
        $dn = $result[0]["dn"];

        /*メーリングリストアドレスとの重複チェック */
        if ($_POST["newaddr"] == $mladdr) {
            $err_msg = $msgarr['12003'][SCREEN_MSG];
            $log_msg = $msgarr['12003'][LOG_MSG];
            $logstr = OPERATION . ":NG:" . $log_msg;
        } else {
            $attr = array("mailForwardingAddr" => $_POST["newaddr"]);

            /* 属性値追加 */
            $ret = LDAP_add_attribute($dn, $attr);
            if ($ret == LDAP_ERR_DUPLICATE) {
                    $err_msg = sprintf($msgarr['12004'][SCREEN_MSG],
                                       $_POST["newaddr"]);
                    $log_msg = sprintf($msgarr['12004'][LOG_MSG],
                                       $_POST["newaddr"]);
                    $logstr = OPERATION . ":NG:" . $log_msg;
            } elseif ($ret != LDAP_OK) {
                $logstr = OPERATION . ":NG:" . $log_msg;
            } else {
                $err_msg = sprintf($msgarr['12005'][SCREEN_MSG],
                                   $_POST["newaddr"]);
                $log_msg = sprintf($msgarr['12005'][LOG_MSG],
                                   $_POST["newaddr"]);
                $logstr = OPERATION . ":OK:" . $log_msg;
                $_POST["newaddr"] = "";
            }
        }
    }
    result_log($logstr);

/* 一括登録 */
} else if (isset($_POST["myaddlist"])) {

    if (get_addr_list($_FILES["filename"]["tmp_name"], $fwaddrs) === FALSE) {
        $logstr = OPERATION . ":NG:" . $log_msg;

    } else {

        /* メールアドレスの取得 */
        $result = array();

        $ret = main_get_entry($search_dn, $filter, array(), 
                              $web_conf[$url_data["script"]]["ldapscope"], $result);
        /* LDAPサーチに失敗 */
        if ($ret != LDAP_OK) {
            $sys_err = TRUE;
            result_log(OPERATION . ":NG:" . $log_msg);
            syserr_display();
            exit (1);
        }
        $dn = $result[0]["dn"];

        if (!isset($result[0]["mailForwardingAddr"])) {
            $num = 0;
            $result[0]["mailForwardingAddr"] = array();
        }

        $num = mk_addr_list($result[0]["mailForwardingAddr"], $fwaddrs,
                                $addlist, FALSE);

        if ($num > 0) {
            /* メーリングリストアドレスとの重複チェック */
            foreach ($addlist as $eachmail) {
                if ($mladdr == $eachmail) {
                    $err_msg = $msgarr['12003'][SCREEN_MSG];
                    $log_msg = $msgarr['12003'][LOG_MSG];
                    $logstr = OPERATION . ":NG:" . $log_msg;
                    $dup_flg = 1;
                    break;
                }
            }

            if (!isset($dup_flg)) {
                /* 属性値追加 */
                if ($num != 0) {
                    $attr = array("mailForwardingAddr" => $addlist);
                    $ret = LDAP_add_attribute($dn, $attr);
                } else {
                    $ret = LDAP_OK;
                }

                if ($ret != LDAP_OK) {
                    if ($ret == LDAP_ERR_DUPLICATE) {
                        $err_msg = $msgarr['12006'][SCREEN_MSG];
                        $log_msg = $msgarr['12006'][LOG_MSG];
                    }
                    $logstr = OPERATION . ":NG:" . $log_msg;
                } else {
                    $err_msg = $msgarr['12007'][SCREEN_MSG];
                    $log_msg = $msgarr['12007'][LOG_MSG];
                    $logstr = OPERATION . ":OK:" . $log_msg;
                }
            }
        }
    }
    $logstr = preg_replace("/<br>/i", " / ", $logstr);
    result_log($logstr);

/* 削除 */
} else if (isset($_POST["mydel"])) {

    if (isset($_POST["addr"])) {

        /* 現在のメールアドレスの取得 */
        $result = array();
        $ret = main_get_entry($search_dn, $filter, array(), 
                              $web_conf[$url_data["script"]]["ldapscope"], $result);
        /* LDAPサーチに失敗 */
        if ($ret != LDAP_OK) {
            $sys_err = TRUE;
            result_log(OPERATION . ":NG:" . $log_msg);
            syserr_display();
            exit (1);
        }
        $dn = $result[0]["dn"];

        if (!isset($result[0]["mailForwardingAddr"])) {
            $result[0]["mailForwardingAddr"] = array();
        }
        $num = mk_addr_list($result[0]["mailForwardingAddr"], $_POST["addr"],
                            $dellist, TRUE);

        /* LDAP属性削除 */
        if ($num > 0) {
            $ret = LDAP_del_attribute($dn, array("mailForwardingAddr" =>
                                                 $dellist));
        } else {
            $ret = LDAP_OK;
        }

        if ($ret != LDAP_OK) {
            if ($ret == LDAP_ERR_NOATTR) {
                $err_msg = sprintf($msgarr['12008'][SCREEN_MSG], $dn);
                $log_msg = sprintf($msgarr['12008'][LOG_MSG], $dn);
            }
            $logstr = OPERATION . ":NG:" . $log_msg;
        } else {
            $dispaddrs = $_POST["addr"][0];
            for ($i = 1; $i < count($_POST["addr"]); $i++) {
                $dispaddrs .= ", " . $_POST["addr"][$i];
            }
            $err_msg = sprintf($msgarr['12009'][SCREEN_MSG], $dispaddrs);
            $log_msg = sprintf($msgarr['12009'][LOG_MSG], $dispaddrs);
            $logstr = OPERATION . ":OK:" . $log_msg;
        }

    } else {
        $err_msg = $msgarr['12010'][SCREEN_MSG];
        $log_msg = $msgarr['12010'][LOG_MSG];
        $logstr = OPERATION . ":NG:" . $log_msg;
    }
    result_log($logstr);

/* キャンセル */
} else if (isset($_POST["cancel"])) {

    /* ユーザ管理メニュー画面へ */
    dgp_location("./index.php");
    exit;
}

/* メールアドレスの取得 */
$result = array();

$ret = main_get_entry($search_dn, $filter, array(), 
                      $web_conf[$url_data["script"]]["ldapscope"], $result);
/* LDAPサーチに失敗 */
if ($ret != LDAP_OK) {
    $sys_err = TRUE;
    $err_msg = escape_html($err_msg);
    result_log(OPERATION . ":NG:" . $log_msg);
    syserr_display();
    exit (1);
}
$dn = $result[0]["dn"];

/* メーリングリストアドレスを暗号化 */
$enc_ml = base64_encode($mladdr);
$enc_ml = str_rot13($enc_ml);

/* 表示用の属性を格納 */
$dispml = $result[0]["$dispmlatt"][0];

/***********************************************************
 * 表示処理
 **********************************************************/

/* メールアドレス入力値保持 */
$newaddr = "";
if (isset($_POST["newaddr"])) {
    $newaddr = escape_html($_POST["newaddr"]);
}

$mailaddrs = "";
/* メールアドレスのエントリがある場合 */
if (isset($result[0]["mailForwardingAddr"])) {
    /* メールアドレスのエントリをソート */
    sort($result[0]["mailForwardingAddr"]);
    reset($result[0]["mailForwardingAddr"]);

    /* メールアドレスのエントリを１つずつ取得し表示用に加工 */
    foreach($result[0]["mailForwardingAddr"] as $i) {
        $addr = $i;
        $addr = escape_html($addr);
        $mailaddrs .= "<option value=\"$addr\">$addr" . "\n";
    }
}

/* タグの値をセット */
set_tag_common($tag, "");
$tag["<<MLADDR>>"] = $dispml = escape_html($dispml);
$tag["<<HIDDEN>>"] = "<input type='hidden' name='mladdr' value='$enc_ml'>";
$tag["<<NEWADDR>>"] = $newaddr;
$tag["<<MAILADDRS>>"] = $mailaddrs;

/* ページの出力 */
$ret = display(TMPLFILE, $tag, array(), "", "");
if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

?>
