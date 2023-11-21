<?php

/*
 * postLdapAdmin
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
 * CSV一括処理結果画面
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
include_once("lib/dglibforward");
include_once("lib/dglibdovecot");

/********************************************************
各ページ毎の設定
*********************************************************/
define("OPERATION", "Registering CSV at once");

/* CSV一括処理の返り値 */
define("CSV_BATCH_OK", 0);             # 更新成功
define("CSV_BATCH_NG_ERR_IGNORE", 1);  # 更新失敗(エラー無視)
define("CSV_BATCH_NG_END", 2);         # 更新失敗(処理中止)

/* CSVの列数の最大値 */
define("CSV_ADDCOLUMN_MAX", 6);
define("CSV_ADDCOLUMN_FORWARD_MAX", 8);

/* 文字列のエンコーディング */
define("USERNAME_DISP_ENCODING", "EUC-JP");
define("READ_CSVFILE_ENCODING",  "SJIS");

/***********************************************************
 * mk_ldap_array
 *
 * LDAP変更用配列を作成
 *
 * [引数]
 *        $csvdata   CSVファイルから読み込んだ配列
 *
 *        $csvdata[0] = (ユーザ名)
 *        $csvdata[1] = (パスワード)
 *        $csvdata[2] = (メールボックス容量)
 *        $csvdata[3] = (メールエイリアス)
 *        $csvdata[4] = (転送アドレス)
 *        $csvdata[5] = (サーバにメールを残す/残さない)
 *        $csvdata[6] = (mailFilterOrder：ForwardConfがONの時のみ)
 *        $csvdata[7] = (mailFilterArticle：ForwardConfがONの時のみ)
 * [返り値]
 *        変換された配列
 *        $userdata["uid"] = (ユーザ名)
 *        $userdata["pass"] = (パスワード)
 *        $userdata["re_pass"] = (パスワード)
 *        $userdata["quota"] = (メールボックス容量)
 *        $userdata["alias"] = (メールエイリアス)
 *        $userdata["transes"][0] = (メール転送アドレス)
 *        $userdata["save"] = (サーバにメールを残す/残さない)
 *        $userdata["order"] = (mailFilterOrder：ForwardConfがONの時のみ)
 *        $userdata["article"] = (mailFilterArticle：ForwardConfがONの時のみ)
 *
 **********************************************************/
function mk_ldap_array($csvdata)
{
    global $web_conf;
    global $url_data;

    /* 配列の形式変換 */

    # ユーザ名（結果表示用にEUCに変換）
    if (isset($csvdata[0])) {
        $userdata["uid"] = mb_convert_encoding($csvdata[0], 
                                USERNAME_DISP_ENCODING, READ_CSVFILE_ENCODING);
    }

    if (isset($csvdata[1])) {
        # パスワード
        $userdata["pass"] = $csvdata[1];

        # 再パスワード(パスワードと同じもの入力)
        $userdata["re_pass"] = $csvdata[1];
    }

    # メールボックス容量
    if (isset($csvdata[2])) {
        $userdata["quota"] = $csvdata[2];
    }

    # メールエイリアス
    if (isset($csvdata[3])) {
        $userdata["alias"] = $csvdata[3];
    }

    # メール転送アドレス
    if (isset($csvdata[4])) {
        $userdata["transes"][0] = $csvdata[4];
    }

    # サーバにメールを残す/残さない
    if (isset($csvdata[5])) {
        $userdata["save"] = $csvdata[5];
    }

    // forwardconfがonの場合はオーダーとアーティクルも取得
    if ($web_conf[$url_data['script']]['forwardconf'] === FORWARD_ON) {
        // オーダーが存在すれば格納
        if (isset($csvdata[6])) {
            $userdata["order"] = $csvdata[6];
        }

        // アーティクルが存在すれば格納
        if (isset($csvdata[7])) {
            $userdata["article"] = $csvdata[7];
        }
    }

    return $userdata;

}

/***********************************************************
 * fcheck_add_user_duplicate
 *
 * ファイルチェック用ユーザ重複チェック
 *
 * [引数]
 *        $userdata   CSVファイルのデータが格納されている連想配列
 *        $fcheckuser ファイルチェック用登録ユーザ格納配列
 *
 * [返り値]
 *        TRUE        未登録ユーザ
 *        FALSE       既に登録されているユーザ
 *
 **********************************************************/
function fcheck_add_user_duplicate($userdata, $fcheckuser)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;

    $max = count($fcheckuser);
    for ($i = 0; $i < $max; $i++) {
        /* ユーザ名のチェック */
        if ($fcheckuser[$i]["uid"] == $userdata["uid"] ||
            $fcheckuser[$i]["alias"] == $userdata["uid"]) {
            $err_msg = $msgarr['14001'][SCREEN_MSG];
            $log_msg = $msgarr['14001'][LOG_MSG];
            return FALSE;
        }

        /* エイリアスの重複チェック */
        if ($userdata["alias"] != "") {
            if ($fcheckuser[$i]["uid"] == $userdata["alias"] ||
                $fcheckuser[$i]["alias"] == $userdata["alias"]) {
                $err_msg = $msgarr['14002'][SCREEN_MSG];
                $log_msg = $msgarr['14002'][LOG_MSG];
                return FALSE;
            }
        }
    }

    return TRUE;
}

/***********************************************************
 * is_fcheck_del_user
 *
 * ファイルチェック用ユーザ存在チェック
 *
 * [引数]
 *        $userdata   CSVファイルのデータが格納されている連想配列
 *        $fcheckuser ファイルチェック用登録ユーザ格納配列
 *
 * [返り値]
 *        TRUE        削除されていない
 *        FALSE       既に削除されている
 *
 **********************************************************/
function is_fcheck_del_user($userdata, $fcheckuser)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;

    $max = count($fcheckuser);
    for ($i = 0; $i < $max; $i++) {
        /* ユーザ名のチェック */
        if ($fcheckuser[$i]["uid"] == $userdata["uid"]) {
            $err_msg = $msgarr['14003'][SCREEN_MSG];
            $log_msg = $msgarr['14003'][LOG_MSG];
            return FALSE;
        }
    }
    return TRUE;

}
/***********************************************************
 * csv_add_user
 *
 * CSV一括ユーザ登録を行う
 *
 * [引数]
 *        $userdata   CSVファイルのデータが格納されている連想配列
 *        $ds         LDAPリンクID
 *        $fcheckuser ファイルチェック用登録ユーザ格納配列
 *        $logstr     ログ出力に使用する文字列
 *        $line       行数
 *
 * [返り値]
 *        CSV_BATCH_OK               正常
 *        CSV_BATCH_NG_ERR_IGNORE    エラー無視
 *        CSV_BATCH_NG_END           処理終了
 *
 **********************************************************/
function csv_add_user($userdata, $ds, $fcheckuser, $logstr, $line)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;
    global $domain;
    global $userdn;
    global $url_data;
    global $ldapdata;

    $type = ADD_MODE;

    // CSVファイルの形式チェック
    if (check_userdata($userdata) === FALSE) {
        printf(CSV_NG_MSG, $line, escape_html($userdata["uid"]), $err_msg);
        ob_flush();
        flush();
        result_log($logstr . ":NG:" . $userdata["uid"] .
                   ":$log_msg(line ${line})");
        if (isset($_POST["err"])) {
            return CSV_BATCH_NG_ERR_IGNORE;
        } else {
            return CSV_BATCH_NG_END;
        }
    }

    /* ファイルチェック用重複チェック */
    if (isset($_POST["fcheck"])) {
        if (fcheck_add_user_duplicate($userdata, $fcheckuser) === FALSE) {
            printf(CSV_NG_MSG, $line, escape_html($userdata["uid"]),
                   $err_msg);
            ob_flush();
            flush();
            result_log($logstr . ":NG:" . $userdata["uid"] .
                       ":$log_msg(line ${line})");
            if (isset($_POST["err"])) {
                return CSV_BATCH_NG_ERR_IGNORE;
            } else {
                return CSV_BATCH_NG_END;
            }
        }
    }

    /* メールアドレス */
    $userdata["mail"] = $userdata["uid"] . "@" . $domain;

    $userdn = sprintf(ADD_DN, $userdata["mail"], $web_conf[$url_data["script"]]["ldapusersuffix"],
                      $web_conf[$url_data["script"]]["ldapbasedn"]);


    /* ユーザの重複チェック */
    $ret = csv_check_duplicate($userdata["mail"], $ds);
    if ($ret == LDAP_ERRUSER) {
        printf(CSV_NG_MSG, $line, escape_html($userdata["uid"]), $err_msg);
        ob_flush();
        flush();
        result_log($logstr . ":NG:" . $userdata["uid"] .
                   ":$log_msg(line ${line})");
        if (isset($_POST["err"])) {
            return CSV_BATCH_NG_ERR_IGNORE;
        } else {
            return CSV_BATCH_NG_END;
        }
    }

    /* 重複ありで上書きにチェックがある場合はデータ変更 */
    if (isset($_POST["change"]) && $ret == LDAP_FOUNDUSER) {
        $type = MOD_MODE;
    }

    /* 重複している場合はエラー */
    if (($type == ADD_MODE && $ret == LDAP_FOUNDUSER) ||
        $ret == LDAP_FOUNDALIAS || $ret == LDAP_FOUNDOTHER) {
        $err_msg = $msgarr['14001'][SCREEN_MSG];
        $log_msg = $msgarr['14001'][LOG_MSG];
        printf(CSV_NG_MSG, $line, escape_html($userdata["uid"]), $err_msg);
        ob_flush();
        flush();
        result_log($logstr . ":NG:" . $userdata["uid"] .
                   ":$log_msg(line ${line})");
        if (isset($_POST["err"])) {
            return CSV_BATCH_NG_ERR_IGNORE;
        } else {
            return CSV_BATCH_NG_END;
        }
    }

    /* エイリアスの重複チェック */
    if ($userdata["alias"] != "") {
        $userdata["mailalias"] = $userdata["alias"] . "@" . $domain;
        $ret = csv_check_duplicate($userdata["mailalias"], $ds);
        if ($ret == LDAP_ERRUSER) {
            printf(CSV_NG_MSG, $line, escape_html($userdata["uid"]),
                   $err_msg);
            ob_flush();
            flush();
            result_log($logstr . ":NG:" . $userdata["uid"] .
                       ":$log_msg(line ${line})");
            if (isset($_POST["err"])) {
                return CSV_BATCH_NG_ERR_IGNORE;
            } else {
                return CSV_BATCH_NG_END;
            }
        }
        if ($ret == LDAP_FOUNDUSER || $ret == LDAP_FOUNDOTHER) {
            $err_msg = $msgarr['14002'][SCREEN_MSG];
            $log_msg = $msgarr['14002'][LOG_MSG];
            printf(CSV_NG_MSG, $line, escape_html($userdata["uid"]),
                   $err_msg);
            ob_flush();
            flush();
            result_log($logstr . ":NG:" . $userdata["uid"] .
                   ":$log_msg(line ${line})");
            if (isset($_POST["err"])) {
                return CSV_BATCH_NG_ERR_IGNORE;
            } else {
                return CSV_BATCH_NG_END;
            }
        }
    }

    /* ディスク容量チェック */
    $quotalen = strlen($userdata["quota"]);
    if ($quotalen > $web_conf[$url_data["script"]]["quotasize"]) {
        $err_msg = $msgarr['14004'][SCREEN_MSG];
        $log_msg = $msgarr['14004'][LOG_MSG];
        printf(CSV_NG_MSG, $line, escape_html($userdata["uid"]), $err_msg);
        ob_flush();
        flush();
        result_log($logstr . ":NG:" . $userdata["uid"] .
                   ":$log_msg(line ${line})");
        if (isset($_POST["err"])) {
            return CSV_BATCH_NG_ERR_IGNORE;
        } else {
            return CSV_BATCH_NG_END;
        }
    }

    /* 転送アドレスのチェック */
    if (check_csv_trans($userdata) === FALSE) {
        printf(CSV_NG_MSG, $line, escape_html($userdata["uid"]), $err_msg);
        ob_flush();
        flush();
        result_log($logstr . ":NG:" . $userdata["uid"] .
                   ":$log_msg(line ${line})");
        if (isset($_POST["err"])) {
            return CSV_BATCH_NG_ERR_IGNORE;
        } else {
            return CSV_BATCH_NG_END;
        }
    }

    /* 追加時 */
    if ($type == ADD_MODE) {
        /* LDAP追加 */
        if (isset($_POST["upload"])) {
            if (add_user_connect($userdata, $ds) === FALSE) {
                printf(CSV_NG_MSG, $line, escape_html($userdata["uid"]),
                       $err_msg);
                ob_flush();
                flush();
                result_log($logstr . ":NG:" .  $userdata["uid"] .
                           ":$log_msg(line ${line})");
                if (isset($_POST["err"])) {
                    return CSV_BATCH_NG_ERR_IGNORE;
                } else {
                    return CSV_BATCH_NG_END;
                }
            }
            $err_msg = $msgarr['14005'][SCREEN_MSG];
            $log_msg = $msgarr['14005'][LOG_MSG];

            // forwardconfがonの場合はsieveファイルの作成
            if ($web_conf[$url_data['script']]['forwardconf'] === FORWARD_ON) {
                $ldapdata[0]['mailFilterArticle'] = explode(":", $userdata['article']);
                $ldapdata[0]['mailFilterOrder'][0] = $userdata['order'];
                $ldapdata[0]['mailDirectory'][0] = $web_conf[$url_data["script"]]["basemaildir"] . "/" . $userdata['uid'] . "/";

                // sieveファイル作成
                $ret = make_sievefile();
                // sieveファイルの作成に失敗した場合は画面再表示
                if ($ret === FALSE) {
                    $err_msg = $msgarr['26011'][SCREEN_MSG];
                    printf(CSV_NG_MSG, $line, escape_html($userdata["uid"])                           , $err_msg);
                    ob_flush();
                    flush();
                    result_log($logstr . ":NG:" .  $userdata["uid"] .
                               ":$log_msg(line ${line})");
                    if (isset($_POST["err"])) {
                        return CSV_BATCH_NG_ERR_IGNORE;
                    } else {
                        return CSV_BATCH_NG_END;
                    }
                }
            }
        /* ファイルチェック */
        } else {
            $err_msg = $msgarr['14014'][SCREEN_MSG];
            $log_msg = $msgarr['14014'][LOG_MSG];
        }
        printf(CSV_OK_MSG, $line, escape_html($userdata["uid"]), $err_msg);
        ob_flush();
        flush();
        result_log($logstr . ":OK:" . $userdata["uid"] .
                   ":$log_msg(line ${line})");
    /* 変更時 */
    } else {
        // LDAP変更
        if (isset($_POST["upload"])) {
            if (mod_user_connect($userdata, $ds) === FALSE) {
                printf(CSV_NG_MSG, $line, escape_html($userdata["uid"]),
                       $err_msg);
                ob_flush();
                flush();
                result_log($logstr . ":NG:" .  $userdata["uid"] .
                           ":$log_msg(line ${line})");
                if (isset($_POST["err"])) {
                    return CSV_BATCH_NG_ERR_IGNORE;
                } else {
                    return CSV_BATCH_NG_END;
                }
            }
            $err_msg = $msgarr['14006'][SCREEN_MSG];
            $log_msg = $msgarr['14006'][LOG_MSG];

            // forwardconfがonの場合はsieveファイルの作成
            if ($web_conf[$url_data['script']]['forwardconf'] === FORWARD_ON) {
                $ldapdata[0]['mailFilterArticle'] = explode(":", $userdata['article']);
                $ldapdata[0]['mailFilterOrder'][0] = $userdata['order'];
                $ldapdata[0]['mailDirectory'][0] = $web_conf[$url_data["script"]]["basemaildir"] . "/" . $userdata['uid'] . "/";

                // sieveファイル作成
                $ret = make_sievefile();
                // sieveファイルの作成に失敗した場合は画面再表示
                if ($ret === FALSE) {
                    $err_msg = $msgarr['26011'][SCREEN_MSG];
                    if (isset($_POST["err"])) {
                        return CSV_BATCH_NG_ERR_IGNORE;
                    } else {
                        return CSV_BATCH_NG_END;
                    }
                }
            }
        /* ファイルチェック */
        } else {
            $err_msg = $msgarr['14015'][SCREEN_MSG];
            $log_msg = $msgarr['14015'][LOG_MSG];
        }
        printf(CSV_OK_MSG, $line, escape_html($userdata["uid"]), $err_msg);
        ob_flush();
        flush();
        result_log($logstr . ":OK:" . $userdata["uid"] .
                   ":$log_msg(line ${line})");
    }
    return CSV_BATCH_OK;

}

/***********************************************************
 * csv_del_user
 *
 * CSV一括ユーザ削除を行う
 *
 * [引数]
 *        $userdata   CSVファイルのデータが格納されている連想配列
 *        $ds         LDAPリンクID
 *        $fcheckuser ファイルチェック用登録ユーザ格納配列
 *        $logstr     ログ出力に使用する文字列
 *        $line      行数
 *
 * [返り値]
 *        CSV_BATCH_OK               正常
 *        CSV_BATCH_NG_ERR_IGNORE    エラー無視
 *        CSV_BATCH_NG_END           処理終了
 *
 **********************************************************/
function csv_del_user($userdata, $ds, $fcheckuser, $logstr, $line)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $domain;
    global $web_conf;
    global $userdn;
    global $url_data;

    /* CSVの形式チェック */
    if ($userdata["uid"] == "") {
        $err_msg = $msgarr['14007'][SCREEN_MSG];
        $log_msg = $msgarr['14007'][LOG_MSG];
        printf(CSV_NG_MSG, $line, escape_html($userdata["uid"]), $err_msg);
        ob_flush();
        flush();
        result_log($logstr . ":NG:" . $userdata["uid"] .
                   ":$log_msg(line ${line})");
        if (isset($_POST["err"])) {
            return CSV_BATCH_NG_ERR_IGNORE;
        } else {
            return CSV_BATCH_NG_END;
        }
    }

    /* ファイルチェック用存在チェック */
    if (isset($_POST["fcheck"])) {
        if (is_fcheck_del_user($userdata, $fcheckuser) === FALSE) {
            printf(CSV_NG_MSG, $line, escape_html($userdata["uid"]),
                   $err_msg);
            ob_flush();
            flush();
            result_log($logstr . ":NG:" . $userdata["uid"] .
                       ":$log_msg(line ${line})");
            if (isset($_POST["err"])) {
                return CSV_BATCH_NG_ERR_IGNORE;
            } else {
                return CSV_BATCH_NG_END;
            }
        }
    }

    /* 対象となるDNの文字列作成 */
    $userdn = sprintf(ADD_DN, $userdata["uid"] . "@" .  $domain,
                      $web_conf[$url_data["script"]]["ldapusersuffix"], 
                      $web_conf[$url_data["script"]]["ldapbasedn"]);

    /* ユーザ名の存在チェック */
    if (get_userdata_connect($userdn, $ds) === FALSE) {
        $err_msg = $msgarr['14003'][SCREEN_MSG];
        $log_msg = $msgarr['14003'][LOG_MSG];
        printf(CSV_NG_MSG, $line, escape_html($userdata["uid"]), $err_msg);
        ob_flush();
        flush();
        result_log($logstr . ":NG:" . $userdata["uid"] .
                   ":$log_msg(line ${line})");
        if (isset($_POST["err"])) {
            return CSV_BATCH_NG_ERR_IGNORE;
        } else {
            return CSV_BATCH_NG_END;
        }
    }

    /* LDAP削除 */
    if (isset($_POST["upload"])) {
        if (del_user_connect($userdata["uid"], $ds) === FALSE) {
            printf(CSV_NG_MSG, $line, escape_html($userdata["uid"]),
                   $err_msg);
            ob_flush();
            flush();
            result_log($logstr . ":NG:" . $userdata["uid"] .
                       ":$log_msg(line ${line})");
            if (isset($_POST["err"])) {
                return CSV_BATCH_NG_ERR_IGNORE;
            } else {
                return CSV_BATCH_NG_END;
            }
        }
        $err_msg = $msgarr['14008'][SCREEN_MSG];
        $log_msg = $msgarr['14008'][LOG_MSG];
    /* 削除チェック */
    } else {
        $err_msg = $msgarr['14016'][SCREEN_MSG];
        $log_msg = $msgarr['14016'][LOG_MSG];
    }

    /* ログ出力 */
    printf(CSV_OK_MSG, $line, escape_html($userdata["uid"]), $err_msg);
    ob_flush();
    flush();
    result_log($logstr . ":OK:" . $userdata["uid"] . ":$log_msg(line ${line})");

    return CSV_BATCH_OK;

}

/***********************************************************
 * csv_column_check
 *
 * CSVのカラム数をチェックする
 *
 * [引数]
 *        $csvdata    CSVファイル一行分のデータ
 *        $num        許可するカラム数の最大値
 *        $line       行数（ログ出力用）
 *        $logstr     ログ出力用文字列
 *
 * [返り値]
 *        CSV_BATCH_OK               正常
 *        CSV_BATCH_NG_ERR_IGNORE    エラー無視
 *        CSV_BATCH_NG_END           処理終了
 *
 **********************************************************/
function csv_column_check($csvdata, $num, $line, $logstr)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;

    if (count($csvdata) < $num) {
        $err_msg = $msgarr['14009'][SCREEN_MSG];
        $log_msg = $msgarr['14009'][LOG_MSG];
        printf(CSV_NG_MSG, $line, "", $err_msg);
        ob_flush();
        flush();
        result_log($logstr . ":NG:******:$log_msg(line ${line})");
        if (isset($_POST["err"])) {
            return CSV_BATCH_NG_ERR_IGNORE;
        } else {
            return CSV_BATCH_NG_END;
        }
    }
    return CSV_BATCH_OK;
}

/***********************************************************
 * read_csvfile
 *
 * CSVファイルを読み込む
 *
 * [引数]
 *        $fp         ファイルポインタ
 *        $logstr     ログ出力に使用する文字列
 *
 * [返り値]
 *        TRUE              正常
 *        FALSE             異常 
 *
 **********************************************************/
function read_csvfile($fp, $logstr)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;
    global $url_data;

    $line = 0;
    $fchecknum = 0;
    $fcheckuser = array();

    /* LDAP接続 */
    $ds = LDAP_connect_server();
    if ($ds == LDAP_ERR_BIND) {
        return FALSE;
    }

    while (($tmpline = fgets($fp)) !== FALSE) {

        /* カンマで区切る */
        $tmpline = rtrim($tmpline);
        $csvdata = parse_csv($tmpline);

        /* 値の初期化 */
        $err_msg = "";
        $userdata = array();
        $dupuser = FALSE;

        /* 行数のカウント */
        $line++;

        $colum_num = CSV_ADDCOLUMN_MAX;
        // カラム数
        if ($web_conf[$url_data['script']]['forwardconf'] === FORWARD_ON) {
            $colum_num = CSV_ADDCOLUMN_FORWARD_MAX;
        }

        /* 列数のチェック */
        if ($_POST["runtype"] == "add") {
            $ret = csv_column_check($csvdata, $colum_num, $line,
                                    $logstr);
            if ($ret === CSV_BATCH_NG_END) {
                break;
            } elseif ($ret === CSV_BATCH_NG_ERR_IGNORE) {
                continue;
            }
        }

        // LDAPに登録する形式に変換
        $userdata = mk_ldap_array($csvdata);

        if ($_POST["runtype"] == "add") {
            // アカウント一括登録（形式チェックも行っている）
            $ret = csv_add_user($userdata, $ds, $fcheckuser, $logstr, $line);
            if ($ret === CSV_BATCH_NG_END) {
                break;
            } elseif ($ret === CSV_BATCH_OK && isset($_POST["fcheck"])) {
                /* ファイルチェック用仮登録ユーザのデータ作成 */
                $fcheckuser[$fchecknum]["uid"] = $userdata["uid"];
                $fcheckuser[$fchecknum]["alias"] = $userdata["alias"];
                $fchecknum++;
            }
        } elseif ($_POST["runtype"] == "del") {
            /* アカウント一括削除 */
            $ret = csv_del_user($userdata, $ds, $fcheckuser, $logstr, $line);
            if ($ret === CSV_BATCH_NG_END) {
                break;
            } elseif ($ret === CSV_BATCH_OK && isset($_POST["fcheck"])) {
                /* ファイルチェック用仮削除ユーザのデータ作成 */
                $fcheckuser[$fchecknum]["uid"] = $userdata["uid"];
                $fchecknum++;
            }
        }
    }

    /* 切断 */
    ldap_unbind($ds);

    /* 空ファイルの場合エラー */
    if ($line == 0) {
        $err_msg = $msgarr['14010'][SCREEN_MSG];
        $log_msg = $msgarr['14010'][LOG_MSG];
        return FALSE;
    }

    return TRUE;
}

/***********************************************************
 * parse_csv
 *
 * 変数に格納されたCSVデータをパースする
 *
 * [引数]
 *        $line        CSVデータ
 *
 * [返り値]
 *        $parse       パースした配列
 *
 **********************************************************/
function parse_csv($line)
{
    $parse = str_getcsv($line);
    return $parse;
}

/***********************************************************
 * print_csv_result
 *
 * CSV一括処理画面の結果画面を表示する
 *
 * [引数]
 *        $postdata   POSTで渡されたデータの連想配列
 *        $filedata   ファイルデータの連想配列
 *
 * [返り値]
 *        なし
 *
 **********************************************************/
function print_csv_result()
{
    global $web_conf;
    global $domain;
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $admkey_file;
    global $userdn;
    global $basedir;
    global $url_data;

    /***********************************************************
     * 初期処理
     **********************************************************/
    /* ヘッダ出力 */
    output_http_header();
    display_header();

    /* $basedirと$topdirのセット */
    url_search();

    /* セッションチェック */
    if (isset($_POST["sk"])) {
        $sesskey = $_POST["sk"];
    }

    /* ドメイン取得 */
    $domain = $_SERVER['DOMAIN'];

    /* 設定ファイル読込 */
    $ret = read_web_conf($url_data["script"]);
    if ($ret === FALSE) {
        print($err_msg);
        display_footer();
        exit(1);    
    }

    /* タブファイル読込 */
    $ret = read_tab_conf(ADMINTABCONF);
    if ($ret === FALSE) {
        print($err_msg);
        display_footer();
        exit(1);    
    }

    /* メッセージファイルの読込 */
    $ret = make_msgarr(MESSAGEFILE);
    if ($ret === FALSE) {
        print($err_msg);
        display_footer();
        exit(1);    
    }

    /* 引数チェック */
    if (isset($sesskey) === FALSE) {
        print("アクセス方法が不当です。");
        display_footer();
        exit(1);    
    }

    /* セッションチェック */
    if (is_sysadm($sesskey) !== TRUE) {
        print("セッションが無効です。");
        display_footer();
        exit(1);    
    }

    /* エラーメッセージ初期化 */
    $err_msg = "";

    /***********************************************************
     * main処理
     **********************************************************/
    if (isset($_POST["fcheck"]) || isset($_POST["upload"])) {
        /* ログ出力文字列設定 */
        $logstr = OPERATION;

        /* 動作タイプが指定されているかチェック */
        if (isset($_POST["runtype"]) === FALSE) {
            $err_msg = $msgarr['14013'][SCREEN_MSG];
            $log_msg = $msgarr['14013'][LOG_MSG];
            print($err_msg);
            display_footer();
            result_log($logstr . ":NG:" .  $log_msg);
            exit(1);    
        }

        /* CSVファイルが指定されているかどうかチェック */
        if (($_FILES["uploadfile"]["tmp_name"]) == "") {
            $err_msg = $msgarr['14011'][SCREEN_MSG];
            $log_msg = $msgarr['14011'][LOG_MSG];
            print($err_msg);
            display_footer();
            result_log($logstr . ":NG:" .  $log_msg);
            exit(1);    
        } else {
            /* アップロードファイルオープン */
            $fp = fopen($_FILES["uploadfile"]["tmp_name"], 'r');
            if ($fp === FALSE) {
                $err_msg = $msgarr['14012'][SCREEN_MSG];
                $log_msg = $msgarr['14012'][LOG_MSG];
                print($err_msg);
                display_footer();
                result_log($logstr . ":NG:" .  $log_msg);
                exit(1);    
            } else {
                /* CSVファイル読込み */
                if (read_csvfile($fp, $logstr) === FALSE) {
                    fclose($fp);
                    print($err_msg);
                    display_footer();
                    result_log($logstr . ":NG:" .  $log_msg);
                    exit(1);    
                }
                fclose($fp);
            }
        }
    }
    display_footer();
}
/***********************************************************
 * 表示処理
 **********************************************************/

print_csv_result();
