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
 * CSV��������̲���
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
�ƥڡ����������
*********************************************************/
define("OPERATION", "Registering CSV at once");

/* CSV���������֤��� */
define("CSV_BATCH_OK", 0);             # ��������
define("CSV_BATCH_NG_ERR_IGNORE", 1);  # ��������(���顼̵��)
define("CSV_BATCH_NG_END", 2);         # ��������(�������)

/* CSV������κ����� */
define("CSV_ADDCOLUMN_MAX", 6);
define("CSV_ADDCOLUMN_FORWARD_MAX", 8);

/* ʸ����Υ��󥳡��ǥ��� */
define("USERNAME_DISP_ENCODING", "EUC-JP");
define("READ_CSVFILE_ENCODING",  "SJIS");

/***********************************************************
 * mk_ldap_array
 *
 * LDAP�ѹ�����������
 *
 * [����]
 *        $csvdata   CSV�ե����뤫���ɤ߹��������
 *
 *        $csvdata[0] = (�桼��̾)
 *        $csvdata[1] = (�ѥ����)
 *        $csvdata[2] = (�᡼��ܥå�������)
 *        $csvdata[3] = (�᡼�륨���ꥢ��)
 *        $csvdata[4] = (ž�����ɥ쥹)
 *        $csvdata[5] = (�����Ф˥᡼���Ĥ�/�Ĥ��ʤ�)
 *        $csvdata[6] = (mailFilterOrder��ForwardConf��ON�λ��Τ�)
 *        $csvdata[7] = (mailFilterArticle��ForwardConf��ON�λ��Τ�)
 * [�֤���]
 *        �Ѵ����줿����
 *        $userdata["uid"] = (�桼��̾)
 *        $userdata["pass"] = (�ѥ����)
 *        $userdata["re_pass"] = (�ѥ����)
 *        $userdata["quota"] = (�᡼��ܥå�������)
 *        $userdata["alias"] = (�᡼�륨���ꥢ��)
 *        $userdata["transes"][0] = (�᡼��ž�����ɥ쥹)
 *        $userdata["save"] = (�����Ф˥᡼���Ĥ�/�Ĥ��ʤ�)
 *        $userdata["order"] = (mailFilterOrder��ForwardConf��ON�λ��Τ�)
 *        $userdata["article"] = (mailFilterArticle��ForwardConf��ON�λ��Τ�)
 *
 **********************************************************/
function mk_ldap_array($csvdata)
{
    global $web_conf;
    global $url_data;

    /* ����η����Ѵ� */

    # �桼��̾�ʷ��ɽ���Ѥ�EUC���Ѵ���
    if (isset($csvdata[0])) {
        $userdata["uid"] = mb_convert_encoding($csvdata[0], 
                                USERNAME_DISP_ENCODING, READ_CSVFILE_ENCODING);
    }

    if (isset($csvdata[1])) {
        # �ѥ����
        $userdata["pass"] = $csvdata[1];

        # �ƥѥ����(�ѥ���ɤ�Ʊ���������)
        $userdata["re_pass"] = $csvdata[1];
    }

    # �᡼��ܥå�������
    if (isset($csvdata[2])) {
        $userdata["quota"] = $csvdata[2];
    }

    # �᡼�륨���ꥢ��
    if (isset($csvdata[3])) {
        $userdata["alias"] = $csvdata[3];
    }

    # �᡼��ž�����ɥ쥹
    if (isset($csvdata[4])) {
        $userdata["transes"][0] = $csvdata[4];
    }

    # �����Ф˥᡼���Ĥ�/�Ĥ��ʤ�
    if (isset($csvdata[5])) {
        $userdata["save"] = $csvdata[5];
    }

    // forwardconf��on�ξ��ϥ��������ȥ����ƥ���������
    if ($web_conf[$url_data['script']]['forwardconf'] === FORWARD_ON) {
        // ����������¸�ߤ���г�Ǽ
        if (isset($csvdata[6])) {
            $userdata["order"] = $csvdata[6];
        }

        // �����ƥ����뤬¸�ߤ���г�Ǽ
        if (isset($csvdata[7])) {
            $userdata["article"] = $csvdata[7];
        }
    }

    return $userdata;

}

/***********************************************************
 * fcheck_add_user_duplicate
 *
 * �ե���������å��ѥ桼����ʣ�����å�
 *
 * [����]
 *        $userdata   CSV�ե�����Υǡ�������Ǽ����Ƥ���Ϣ������
 *        $fcheckuser �ե���������å�����Ͽ�桼����Ǽ����
 *
 * [�֤���]
 *        TRUE        ̤��Ͽ�桼��
 *        FALSE       ������Ͽ����Ƥ���桼��
 *
 **********************************************************/
function fcheck_add_user_duplicate($userdata, $fcheckuser)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;

    $max = count($fcheckuser);
    for ($i = 0; $i < $max; $i++) {
        /* �桼��̾�Υ����å� */
        if ($fcheckuser[$i]["uid"] == $userdata["uid"] ||
            $fcheckuser[$i]["alias"] == $userdata["uid"]) {
            $err_msg = $msgarr['14001'][SCREEN_MSG];
            $log_msg = $msgarr['14001'][LOG_MSG];
            return FALSE;
        }

        /* �����ꥢ���ν�ʣ�����å� */
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
 * �ե���������å��ѥ桼��¸�ߥ����å�
 *
 * [����]
 *        $userdata   CSV�ե�����Υǡ�������Ǽ����Ƥ���Ϣ������
 *        $fcheckuser �ե���������å�����Ͽ�桼����Ǽ����
 *
 * [�֤���]
 *        TRUE        �������Ƥ��ʤ�
 *        FALSE       ���˺������Ƥ���
 *
 **********************************************************/
function is_fcheck_del_user($userdata, $fcheckuser)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;

    $max = count($fcheckuser);
    for ($i = 0; $i < $max; $i++) {
        /* �桼��̾�Υ����å� */
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
 * CSV���桼����Ͽ��Ԥ�
 *
 * [����]
 *        $userdata   CSV�ե�����Υǡ�������Ǽ����Ƥ���Ϣ������
 *        $ds         LDAP���ID
 *        $fcheckuser �ե���������å�����Ͽ�桼����Ǽ����
 *        $logstr     �����Ϥ˻��Ѥ���ʸ����
 *        $line       �Կ�
 *
 * [�֤���]
 *        CSV_BATCH_OK               ����
 *        CSV_BATCH_NG_ERR_IGNORE    ���顼̵��
 *        CSV_BATCH_NG_END           ������λ
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

    // CSV�ե�����η��������å�
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

    /* �ե���������å��ѽ�ʣ�����å� */
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

    /* �᡼�륢�ɥ쥹 */
    $userdata["mail"] = $userdata["uid"] . "@" . $domain;

    $userdn = sprintf(ADD_DN, $userdata["mail"], $web_conf[$url_data["script"]]["ldapusersuffix"],
                      $web_conf[$url_data["script"]]["ldapbasedn"]);


    /* �桼���ν�ʣ�����å� */
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

    /* ��ʣ����Ǿ�񤭤˥����å���������ϥǡ����ѹ� */
    if (isset($_POST["change"]) && $ret == LDAP_FOUNDUSER) {
        $type = MOD_MODE;
    }

    /* ��ʣ���Ƥ�����ϥ��顼 */
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

    /* �����ꥢ���ν�ʣ�����å� */
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

    /* �ǥ��������̥����å� */
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

    /* ž�����ɥ쥹�Υ����å� */
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

    /* �ɲû� */
    if ($type == ADD_MODE) {
        /* LDAP�ɲ� */
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

            // forwardconf��on�ξ���sieve�ե�����κ���
            if ($web_conf[$url_data['script']]['forwardconf'] === FORWARD_ON) {
                $ldapdata[0]['mailFilterArticle'] = explode(":", $userdata['article']);
                $ldapdata[0]['mailFilterOrder'][0] = $userdata['order'];
                $ldapdata[0]['mailDirectory'][0] = $web_conf[$url_data["script"]]["basemaildir"] . "/" . $userdata['uid'] . "/";

                // sieve�ե��������
                $ret = make_sievefile();
                // sieve�ե�����κ����˼��Ԥ������ϲ��̺�ɽ��
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
        /* �ե���������å� */
        } else {
            $err_msg = $msgarr['14014'][SCREEN_MSG];
            $log_msg = $msgarr['14014'][LOG_MSG];
        }
        printf(CSV_OK_MSG, $line, escape_html($userdata["uid"]), $err_msg);
        ob_flush();
        flush();
        result_log($logstr . ":OK:" . $userdata["uid"] .
                   ":$log_msg(line ${line})");
    /* �ѹ��� */
    } else {
        // LDAP�ѹ�
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

            // forwardconf��on�ξ���sieve�ե�����κ���
            if ($web_conf[$url_data['script']]['forwardconf'] === FORWARD_ON) {
                $ldapdata[0]['mailFilterArticle'] = explode(":", $userdata['article']);
                $ldapdata[0]['mailFilterOrder'][0] = $userdata['order'];
                $ldapdata[0]['mailDirectory'][0] = $web_conf[$url_data["script"]]["basemaildir"] . "/" . $userdata['uid'] . "/";

                // sieve�ե��������
                $ret = make_sievefile();
                // sieve�ե�����κ����˼��Ԥ������ϲ��̺�ɽ��
                if ($ret === FALSE) {
                    $err_msg = $msgarr['26011'][SCREEN_MSG];
                    if (isset($_POST["err"])) {
                        return CSV_BATCH_NG_ERR_IGNORE;
                    } else {
                        return CSV_BATCH_NG_END;
                    }
                }
            }
        /* �ե���������å� */
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
 * CSV���桼�������Ԥ�
 *
 * [����]
 *        $userdata   CSV�ե�����Υǡ�������Ǽ����Ƥ���Ϣ������
 *        $ds         LDAP���ID
 *        $fcheckuser �ե���������å�����Ͽ�桼����Ǽ����
 *        $logstr     �����Ϥ˻��Ѥ���ʸ����
 *        $line      �Կ�
 *
 * [�֤���]
 *        CSV_BATCH_OK               ����
 *        CSV_BATCH_NG_ERR_IGNORE    ���顼̵��
 *        CSV_BATCH_NG_END           ������λ
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

    /* CSV�η��������å� */
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

    /* �ե���������å���¸�ߥ����å� */
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

    /* �оݤȤʤ�DN��ʸ������� */
    $userdn = sprintf(ADD_DN, $userdata["uid"] . "@" .  $domain,
                      $web_conf[$url_data["script"]]["ldapusersuffix"], 
                      $web_conf[$url_data["script"]]["ldapbasedn"]);

    /* �桼��̾��¸�ߥ����å� */
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

    /* LDAP��� */
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
    /* ��������å� */
    } else {
        $err_msg = $msgarr['14016'][SCREEN_MSG];
        $log_msg = $msgarr['14016'][LOG_MSG];
    }

    /* ������ */
    printf(CSV_OK_MSG, $line, escape_html($userdata["uid"]), $err_msg);
    ob_flush();
    flush();
    result_log($logstr . ":OK:" . $userdata["uid"] . ":$log_msg(line ${line})");

    return CSV_BATCH_OK;

}

/***********************************************************
 * csv_column_check
 *
 * CSV�Υ�����������å�����
 *
 * [����]
 *        $csvdata    CSV�ե�������ʬ�Υǡ���
 *        $num        ���Ĥ��륫�����κ�����
 *        $line       �Կ��ʥ������ѡ�
 *        $logstr     ��������ʸ����
 *
 * [�֤���]
 *        CSV_BATCH_OK               ����
 *        CSV_BATCH_NG_ERR_IGNORE    ���顼̵��
 *        CSV_BATCH_NG_END           ������λ
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
 * CSV�ե�������ɤ߹���
 *
 * [����]
 *        $fp         �ե�����ݥ���
 *        $logstr     �����Ϥ˻��Ѥ���ʸ����
 *
 * [�֤���]
 *        TRUE              ����
 *        FALSE             �۾� 
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

    /* LDAP��³ */
    $ds = LDAP_connect_server();
    if ($ds == LDAP_ERR_BIND) {
        return FALSE;
    }

    while (($tmpline = fgets($fp)) !== FALSE) {

        /* ����ޤǶ��ڤ� */
        $tmpline = rtrim($tmpline);
        $csvdata = parse_csv($tmpline);

        /* �ͤν���� */
        $err_msg = "";
        $userdata = array();
        $dupuser = FALSE;

        /* �Կ��Υ������ */
        $line++;

        $colum_num = CSV_ADDCOLUMN_MAX;
        // ������
        if ($web_conf[$url_data['script']]['forwardconf'] === FORWARD_ON) {
            $colum_num = CSV_ADDCOLUMN_FORWARD_MAX;
        }

        /* ����Υ����å� */
        if ($_POST["runtype"] == "add") {
            $ret = csv_column_check($csvdata, $colum_num, $line,
                                    $logstr);
            if ($ret === CSV_BATCH_NG_END) {
                break;
            } elseif ($ret === CSV_BATCH_NG_ERR_IGNORE) {
                continue;
            }
        }

        // LDAP����Ͽ����������Ѵ�
        $userdata = mk_ldap_array($csvdata);

        if ($_POST["runtype"] == "add") {
            // ��������Ȱ����Ͽ�ʷ��������å���ԤäƤ����
            $ret = csv_add_user($userdata, $ds, $fcheckuser, $logstr, $line);
            if ($ret === CSV_BATCH_NG_END) {
                break;
            } elseif ($ret === CSV_BATCH_OK && isset($_POST["fcheck"])) {
                /* �ե���������å��Ѳ���Ͽ�桼���Υǡ������� */
                $fcheckuser[$fchecknum]["uid"] = $userdata["uid"];
                $fcheckuser[$fchecknum]["alias"] = $userdata["alias"];
                $fchecknum++;
            }
        } elseif ($_POST["runtype"] == "del") {
            /* ��������Ȱ���� */
            $ret = csv_del_user($userdata, $ds, $fcheckuser, $logstr, $line);
            if ($ret === CSV_BATCH_NG_END) {
                break;
            } elseif ($ret === CSV_BATCH_OK && isset($_POST["fcheck"])) {
                /* �ե���������å��Ѳ�����桼���Υǡ������� */
                $fcheckuser[$fchecknum]["uid"] = $userdata["uid"];
                $fchecknum++;
            }
        }
    }

    /* ���� */
    ldap_unbind($ds);

    /* ���ե�����ξ�票�顼 */
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
 * �ѿ��˳�Ǽ���줿CSV�ǡ�����ѡ�������
 *
 * [����]
 *        $line        CSV�ǡ���
 *
 * [�֤���]
 *        $parse       �ѡ�����������
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
 * CSV���������̤η�̲��̤�ɽ������
 *
 * [����]
 *        $postdata   POST���Ϥ��줿�ǡ�����Ϣ������
 *        $filedata   �ե�����ǡ�����Ϣ������
 *
 * [�֤���]
 *        �ʤ�
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
     * �������
     **********************************************************/
    /* �إå����� */
    output_http_header();
    display_header();

    /* $basedir��$topdir�Υ��å� */
    url_search();

    /* ���å��������å� */
    if (isset($_POST["sk"])) {
        $sesskey = $_POST["sk"];
    }

    /* �ɥᥤ����� */
    $domain = $_SERVER['DOMAIN'];

    /* ����ե������ɹ� */
    $ret = read_web_conf($url_data["script"]);
    if ($ret === FALSE) {
        print($err_msg);
        display_footer();
        exit(1);    
    }

    /* ���֥ե������ɹ� */
    $ret = read_tab_conf(ADMINTABCONF);
    if ($ret === FALSE) {
        print($err_msg);
        display_footer();
        exit(1);    
    }

    /* ��å������ե�������ɹ� */
    $ret = make_msgarr(MESSAGEFILE);
    if ($ret === FALSE) {
        print($err_msg);
        display_footer();
        exit(1);    
    }

    /* ���������å� */
    if (isset($sesskey) === FALSE) {
        print("����������ˡ�������Ǥ���");
        display_footer();
        exit(1);    
    }

    /* ���å��������å� */
    if (is_sysadm($sesskey) !== TRUE) {
        print("���å����̵���Ǥ���");
        display_footer();
        exit(1);    
    }

    /* ���顼��å���������� */
    $err_msg = "";

    /***********************************************************
     * main����
     **********************************************************/
    if (isset($_POST["fcheck"]) || isset($_POST["upload"])) {
        /* ������ʸ�������� */
        $logstr = OPERATION;

        /* ư����פ����ꤵ��Ƥ��뤫�����å� */
        if (isset($_POST["runtype"]) === FALSE) {
            $err_msg = $msgarr['14013'][SCREEN_MSG];
            $log_msg = $msgarr['14013'][LOG_MSG];
            print($err_msg);
            display_footer();
            result_log($logstr . ":NG:" .  $log_msg);
            exit(1);    
        }

        /* CSV�ե����뤬���ꤵ��Ƥ��뤫�ɤ��������å� */
        if (($_FILES["uploadfile"]["tmp_name"]) == "") {
            $err_msg = $msgarr['14011'][SCREEN_MSG];
            $log_msg = $msgarr['14011'][LOG_MSG];
            print($err_msg);
            display_footer();
            result_log($logstr . ":NG:" .  $log_msg);
            exit(1);    
        } else {
            /* ���åץ��ɥե����륪���ץ� */
            $fp = fopen($_FILES["uploadfile"]["tmp_name"], 'r');
            if ($fp === FALSE) {
                $err_msg = $msgarr['14012'][SCREEN_MSG];
                $log_msg = $msgarr['14012'][LOG_MSG];
                print($err_msg);
                display_footer();
                result_log($logstr . ":NG:" .  $log_msg);
                exit(1);    
            } else {
                /* CSV�ե������ɹ��� */
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
 * ɽ������
 **********************************************************/

print_csv_result();
