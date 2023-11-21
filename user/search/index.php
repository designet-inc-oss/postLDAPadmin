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
 * 管理者用ユーザ検索画面
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

define("TMPLFILE", "admin_user_search.tmpl");

define("OPERATION",  "Searching user account");

define("STATUS_INPUT",  0);
define("STATUS_SEARCH", 1);

define("FORWARD_ON", "1");
/*********************************************************
 * set_tag_data
 *
 * タグのセット
 *
 * [引数]
 *        $post              POSTで渡された値
 *        $form_name         フォームに入力した名前
 *        $ldap_result       LDAP検索結果
 *        $filter_disp       LDAPフィルタ
 *        $form_name_encode  エンコードされたフォームの値
 *        $tag               タグを格納
 *
 * [返り値]
 *        TRUE               正常
 **********************************************************/
function set_tag_data($post, $form_name, $ldap_result, $filter_disp, $form_name_encode, &$page, &$tag)
{
    global $web_conf;

    /* JavaScript */
    $javascript = <<<EOD
    function allSubmit(url, page, dn, form_name_encode) {
        document.form_main.action = url;
        document.form_main.page.value = page;
        document.form_main.dn.value = dn;
        document.form_main.form_name.value = form_name_encode;
        document.form_main.submit();
    }

EOD;

    /* 共通で使うタグ */
    set_tag_common($tag, $javascript);

    /* 検索するUID */
    $tag["<<SEARCH_UID>>"] ="";
    if (isset($form_name)) {
        $tag["<<SEARCH_UID>>"] = escape_html($form_name);
    }

    /* マッチ条件 */
    $tag["<<INCLUDE_ON>>"] = "selected";
    $tag["<<MATCH_ON>>"] = "";
    if (isset($post["name_match"]) && $post["name_match"] == 1) {
        $tag["<<MATCH_ON>>"] = "selected";
        $tag["<<INCLUDE_ON>>"] = "";
    }

    /* hidden */
    $tag["<<HIDDEN>>"] = "";
    if (isset($post["search"]) || isset($post["filter"])) {

        $tag["<<HIDDEN>>"] = <<<EOD
<input type="hidden" name="filter" value="{$filter_disp}">
<input type="hidden" name="dn">
<input type="hidden" name="page" value="${page}">

EOD;
    }

    /* 前ページ・次ページ */
    $tag["<<PRE>>"] = "";
    $tag["<<NEXT>>"] = "";
    $tag["<<MAXPASSLEN>>"] = "";

    get_page($ldap_result, $page, $form_name_encode, $tag);

    return TRUE;

}

/*********************************************************
 * get_page
 *
 * 前ページ・次ページの取得
 *
 * [引数]
 *       $ldap_result       LDAPの検索結果
 *       $page              ページ
 *       $form_name_encode  エンコードされた入力値
 *       $tag               タグ
 * [返り値]
 *       TRUE               正常
 **********************************************************/
function get_page($ldap_result, &$page, $form_name_encode, &$tag)
{
    global $web_conf;
    global $url_data;

    $sum = count($ldap_result);

    /* 検索数 */
    $tag["<<NUM>>"] = $sum;

    /* ページ番号は0から始まっている */
    $all_page = (int) ceil(($sum / $web_conf[$url_data["script"]]["lineperpage"]));
    if ($all_page == 0) {
        $all_page = 1;
    }

    /* 全ページ以上の数字が渡ってきたら最後のページを表示 */
    if ($all_page < $page + 1) {
        $page = $all_page - 1;
    }

    /* 表示されている最後の一件を削除した場合、前のページを表示 */
    $ret = $sum % $all_page;
    if ($ret == 0 && $all_page < $page + 1) {
        $page = $page - 1;
    } 

    /* 最初のページでなければ前ページを表示 */
    if ($page != 0) {
        $tmp = $page - 1;
        $tag["<<PRE>>"] = "<a href=\"#\" onClick=\"allSubmit('index.php', '$tmp', '', '$form_name_encode')\">前ページ</a>";
    }

    /* 最後のページでなければ次ページを表示 */
    if ($page != $all_page - 1) {
        $tmp = $page + 1;
        $tag["<<NEXT>>"] = "<a href=\"#\" onClick=\"allSubmit('index.php', '$tmp', '', '$form_name_encode')\">次ページ</a>";
    }

    return TRUE;
}

/*********************************************************
 * set_loop_tag
 *
 * 検索結果出力
 *
 * [引数]
 *       $ldap_result        LDAP検索結果
 *       $filter             フィルタ
 *       $page               ページ
 *       $form_name_encode   エンコードした入力
 *       $looptag            ループタグ
 *
 * [返り値]
 *       なし
 **********************************************************/
function set_loop_tag($ldap_result, $filter, $page, $form_name_encode, &$looptag)
{
    global $web_conf;
    global $url_data;
    global $plugindata;

    /* ユーザ名に表示する属性 */
    $dispusr = $web_conf[$url_data['script']]['displayuser'];

    /* 検索結果を表示属性名でソート */
    if (isset($ldap_result)) {
        usort($ldap_result, "user_sort");
        reset($ldap_result);
        $sum = count($ldap_result);
    } else {
        $sum = 0;
    }

    $i = $web_conf[$url_data["script"]]["lineperpage"] * $page;
    $tmp = $i + $web_conf[$url_data["script"]]["lineperpage"];
    $k = 0;

    /* 表示数を決定 */
    $max = ($tmp < $sum) ? $tmp : $sum;
    while ($i < $max) {
        /* dnを格納 */
        $userdn = $ldap_result[$i]["dn"];
        $userdn = base64_encode($userdn);
        $userdn = str_rot13($userdn);
        $userdn = escape_html($userdn);

        /* 初期化 */
        $alias = "";
        $trans = "";
        $quota = "";

        /* ユーザ名を格納 */
        $name = escape_html($ldap_result[$i][$dispusr][0]);

        /* エイリアスを格納 */
        if (isset($ldap_result[$i]["mailAlias"])) {
            $mailalias = escape_html($ldap_result[$i]["mailAlias"][0]);
            $part = explode("@", $mailalias, 2);
            $alias = $part[0];
        }

        /* 転送アドレスを格納 */
        if (isset($ldap_result[$i]['mailForwardingAddr'][0])) {
            if ($ldap_result[$i]['mailForwardingAddr'][0] !=
                $ldap_result[$i]['mail'][0]) {
                $trans = $ldap_result[$i]['mailForwardingAddr'][0];
            } else {
                $trans = $ldap_result[$i]['mailForwardingAddr'][1];
            }
        }

         /* メール容量を格納 */
        if (isset($ldap_result[$i]["quotaSize"][0])) {

            /* 設定ファイルのクォータ単位を小文字に変換する */
            $conf_quota = strtolower($web_conf[$url_data["script"]]["quotaunit"]);

            /* 設定ファイルの内容によって単位を変化させる */
            switch ($conf_quota) {
                case "b":
                    $quota = $ldap_result[$i]["quotaSize"][0] . "bytes";
                    break;
                case "kb":
                    $quota = $ldap_result[$i]["quotaSize"][0] . "Kbytes";
                    break;
                case "mb":
                    $quota = $ldap_result[$i]["quotaSize"][0] . "Mbytes";
                    break;
                case "gb":
                    $quota = $ldap_result[$i]["quotaSize"][0] . "Gbytes";
                    break;
            }
        }

        $looptag[$k]["<<UID>>"] = $name;
        $looptag[$k]["<<ALIAS>>"] = $alias;
        $looptag[$k]["<<TRANS>>"] = $trans;
        $looptag[$k]["<<QUOTA>>"] = $quota;
        $looptag[$k]["<<MOD>>"] = <<<EOD
<input type="button" class="list_mod_btn" onClick="allSubmit('mod.php', '$page', '$userdn', '$form_name_encode')" title="ユーザ
編集">

EOD;

        // forwardconfがonの場合は転送設定リンクを作成する
        if ($web_conf[$url_data["script"]]["forwardconf"] === FORWARD_ON) {
            $looptag[$k]["<<FORWARD>>"] = <<<EOD
<a href="#" onClick="allSubmit('forward.php', '$page', '$userdn', '$form_name_encode')">転送設定</a>

EOD;
        }

        /* プラグイン用編集画面へのリンク表示 */
        $count = count($plugindata);
        if ($count > 0) {
            for ($j = 0; $j < $count; $j++) {
                /* 初期化 */
                $looptag[$k]["<<PLUGIN$j>>"] = "";

                $p_name = $plugindata[$j]["name"];
                $file = $plugindata[$j]["file"];
                $image = $plugindata[$j]["image"];
                $looptag[$k]["<<PLUGIN$j>>"] .=<<<EOD
        <input type="button" class="plugin_btn" style="background:url(images/${image});" onClick="allSubmit('$file', '$page', '$userdn', '$form_name_encode')" title="${p_name}編集">
EOD;
            }
        }

        /* 表示する要素からのループ */
        $i++;

        /* 0からのループ */
        $k++;
    }

}


/*********************************************************
 * csv_output
 *
 * CSVファイルの出力をおこなう
 *
 * [引数]
 *
 * [返り値]
 *           TRUE     正常
 *           FALSE    異常
 *
 **********************************************************/
function csv_output()
{

    global $dispusr;
    global $ldap_result;
    global $domain;
    global $web_conf;
    global $url_data;

    /* 検索結果を表示属性名でソート */
    usort($ldap_result, "user_sort");
    reset($ldap_result);

    header("Content-Disposition: attachment; filename=userdata_$domain.csv");
    header("Content-Type: application/octet-stream");

    foreach ($ldap_result as $userdata) {

        /* 配列初期化 */
        $csvdata = array();

        /* ユーザ名を格納 */
        array_push($csvdata, $userdata[$dispusr][0]);

        /* メール容量を格納 */
        if (isset($userdata["quotaSize"])) {
            $quota = $userdata["quotaSize"][0];
            array_push($csvdata, $quota);
        } else {
            array_push($csvdata, "");
        }

        /* エイリアスを格納 */
        if (isset($userdata["mailAlias"])) {
            $part = explode("@", $userdata["mailAlias"][0], 2);
            array_push($csvdata, $part[0]);
        } else {
            array_push($csvdata, "");
        }

        /* メール転送アドレスを格納 */
        if (isset($userdata["mailForwardingAddr"])) {
            if (count($userdata["mailForwardingAddr"]) == 1) {
                $trans = $userdata["mailForwardingAddr"][0];
                $reserve = 1;
            } else {
                if ($userdata["mailForwardingAddr"][0] !=
                    $userdata["mail"][0]) {
                    $trans = $userdata["mailForwardingAddr"][0];
                } else {
                    $trans = $userdata["mailForwardingAddr"][1];
                }
                $reserve = 0;
            }

            array_push($csvdata, $trans);
            array_push($csvdata, $reserve);
        } else {
            array_push($csvdata, "");
            array_push($csvdata, "");
        }

        // forwardconfがonの場合はオーダーとアーティクルも格納
        if ($web_conf[$url_data['script']]['forwardconf'] === FORWARD_ON) {
            // オーダーを整形
            if (isset($userdata["mailFilterOrder"])) {
                array_push($csvdata, $userdata["mailFilterOrder"][0]);
            } else {
                array_push($csvdata, "");
            }

            // アーティクルを整形
            if (isset($userdata["mailFilterArticle"])) {
                $article = implode(":", $userdata["mailFilterArticle"]);
                array_push($csvdata, $article);
            } else {
                array_push($csvdata, "");
            }
        }

        $csvline = mk_csv_line($csvdata);
        print("$csvline\r\n");
    }
}

/*********************************************************
 * mk_csv_line
 *
 * 引数で渡された配列からCSVファイルの一行を作成
 *
 * [引数]
 *           $csvdata CSV形式に変換する配列
 *
 * [返り値]
 *           $csvline CSV形式に変換された文字列
 *
 **********************************************************/
function mk_csv_line($csvdata) {

    $csvline = implode(",", $csvdata);
    
    return $csvline;
}

/*********************************************************
 * form_check
 *
 * 入力フォームの形式チェック
 *
 * [引数]
 *           $data    チェックするデータの連想配列
 *
 * [返り値]
 *           TRUE     正常
 *           FALSE    エラー
 *
 **********************************************************/
function form_check($data)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;

    /* ユーザ名の形式チェック */
    $ret = check_search_name($data['form_name']);
    if ($ret === FALSE) {
        $err_msg = $msgarr['15001'][SCREEN_MSG];
        $log_msg = $msgarr['15001'][LOG_MSG];
        return FALSE;
    }	

    /* ユーザ検索条件のチェック */
    $ret = check_flg($data['name_match']);
    if ($ret === FALSE) {
        $err_msg = $msgarr['15002'][SCREEN_MSG];
        $log_msg = $msgarr['15002'][LOG_MSG];
        return FALSE;
    }	

    return TRUE;
}

/***********************************************************
 * 初期処理
 **********************************************************/
/* 値の初期化 */
$tag["<<QUOTASIZE>>"] = "";
$tag["<<UID>>"] = "";
$tag["<<QUOTA>>"] = "";
$tag["<<QUOTAUNIT>>"] = "";
$tag["<<ALIAS>>"] = "";
$tag["<<TRANS>>"] = "";
$tag["<<SAVEON>>"] = "";
$tag["<<SAVEOFF>>"] = "";
$tag["<<HIDDEN>>"] = "";
$tag["<<FORWARD_START>>"] = "<!--";
$tag["<<FORWARD_END>>"] = "-->";

$looptag = array();

$ldap_result = array();
$filter = "";
$filter_disp = "";
$form_name = "";
$form_name_encode = "";
$page = 0;

/* フォームに入力された値 */
if (isset($_POST['form_name'])) {
    $form_name = $_POST['form_name'];
}

/* 検索ステータス */
if (isset($_POST["search"]) || isset($_POST["filter"]) ||
    isset($_POST["csvdownload"])) {
    $status = STATUS_SEARCH;
} else {
    $status = STATUS_INPUT;
}

/* 設定ファイル・タブ管理ファイル読込、セッションチェック */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit (1);
}

/* プラグインデータセット */
set_plugindata();

/***********************************************************
 * main処理
 **********************************************************/
/* 表示属性を取得 */
$dispusr = $web_conf[$url_data['script']]['displayuser'];
/* 処理の分岐 */
if ($status == STATUS_SEARCH) {
    if ((isset($_POST["search"]) || isset($_POST["csvdownload"])) &&
         form_check($_POST) === FALSE) {
        /* 検索ボタンが押され、不正な入力であれば、入力やりなおし */
        $status = STATUS_INPUT;
        $logstr = OPERATION . ":NG:" . $log_msg;
        result_log($logstr);
    } else {
        /* 以前のフィルタがある場合 */
        if ((!isset($_POST["search"]) && !isset($_POST["csvdownload"])) &&
             $_POST["filter"] != "") {

            /* ページの値チェック */
            if (is_num_check($_POST["page"]) === FALSE) {
                $err_msg = $msgarr['15003'][SCREEN_MSG];
                $log_msg = $msgarr['15003'][LOG_MSG];
                result_log(OPERATION . ":NG:" . $log_msg);
                syserr_display();
                exit (1);
            }
            $page = $_POST["page"];

            /* フォームに入力された値の複合化 */
            if (isset($_POST['form_name'])) {
                $form_name = str_rot13($_POST['form_name']);
                $form_name = base64_decode($form_name);
            }

            /* フィルタの複合化 */
            if (sess_key_decode($_POST["filter"], $filter) === FALSE) {
                result_log($log_msg, LOG_ERR);
                syserr_display();
                exit (1);
            }

            /* フィルタの形式チェック */
            $fdata = explode(':', $filter);
            if (count($fdata) != 3) {
                $err_msg = $msgarr['15004'][SCREEN_MSG];
                $log_msg = $msgarr['15004'][LOG_MSG];
                result_log(OPERATION . ":NG:" . $log_msg);
                syserr_display();
                exit (1);
            }
            $filter = $fdata[1];

        } else {
            /* フィルタを作成 */
            $filter = mk_filter($_POST["form_name"], $_POST["name_match"]);

            /* 取得するのは、表示属性をもつエントリのみ */
            $filter = "(&($dispusr=*)$filter)";
            $page = 0;
        }
        $search_dn = sprintf(SEARCH_DN, $web_conf[$url_data["script"]]["ldapusersuffix"],
                                        $web_conf[$url_data["script"]]["ldapbasedn"]);
        $ret = main_get_entry($search_dn, $filter, array(),
                              $web_conf[$url_data['script']]['ldapscope'], $ldap_result);
        if ($ret == LDAP_ERR_NODATA ) {
            $err_msg = $msgarr['15005'][SCREEN_MSG];
            $log_msg = $msgarr['15005'][LOG_MSG];
            $logstr = OPERATION . ":NG:" . $log_msg;
            result_log($logstr);
        } elseif ($ret != LDAP_OK) {
            $status = STATUS_INPUT;
            $logstr = OPERATION . ":NG:" . $log_msg;
            result_log($logstr);
            syserr_display();
            exit(1);
        }

        /* フィルタの暗号化 */
        if (sess_key_make($filter, "", $filter_disp) === FALSE) {
            result_log($log_msg, LOG_ERR);
            syserr_display();
            exit (1);
        }

        /* 他のページから持ち越すメッセージを表示 */
        if (isset($_POST["msg"])) {
            $err_msg = escape_html($_POST["msg"]);
        }
    }
}

/* 検索条件の暗号化 */
if (isset($_POST['form_name'])) {
    $form_name_encode = base64_encode($form_name);
    $form_name_encode = str_rot13($form_name_encode);
}


/***********************************************************
 * 表示処理
 **********************************************************/
// 転送設定カラム表示処理
if ($web_conf[$url_data['script']]['forwardconf'] === FORWARD_ON) {
    $tag["<<FORWARD_START>>"] = "";
    $tag["<<FORWARD_END>>"] = "";
}
/* ページの出力 */
if (isset($_POST['csvdownload']) && count($ldap_result) > 0) {
    $err_msg = $msgarr['15006'][SCREEN_MSG];
    $log_msg = $msgarr['15006'][LOG_MSG];
    $logstr = OPERATION . ":OK:" . $log_msg;
    result_log($logstr);
    csv_output();
    exit(0);
} else {
    /* タグ作成 */
    set_tag_data($_POST, $form_name, $ldap_result, $filter_disp, $form_name_encode, $page, $tag);

    /* ループタグの作成 */
    set_loop_tag($ldap_result, $filter, $page, $form_name_encode,
                 $looptag);
    /* 表示 */
    $ret = display(TMPLFILE, $tag, $looptag, "<<STARTLOOP>>", "<<ENDLOOP>>");
    if ($ret === FALSE) {
        result_log($log_msg, LOG_ERR);
        syserr_display();
        exit(1);
    }
}
?>
