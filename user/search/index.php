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
 * �������ѥ桼����������
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
�ƥڡ����������
*********************************************************/

define("TMPLFILE", "admin_user_search.tmpl");

define("OPERATION",  "Searching user account");

define("STATUS_INPUT",  0);
define("STATUS_SEARCH", 1);

define("FORWARD_ON", "1");
/*********************************************************
 * set_tag_data
 *
 * �����Υ��å�
 *
 * [����]
 *        $post              POST���Ϥ��줿��
 *        $form_name         �ե���������Ϥ���̾��
 *        $ldap_result       LDAP�������
 *        $filter_disp       LDAP�ե��륿
 *        $form_name_encode  ���󥳡��ɤ��줿�ե��������
 *        $tag               �������Ǽ
 *
 * [�֤���]
 *        TRUE               ����
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

    /* ���̤ǻȤ����� */
    set_tag_common($tag, $javascript);

    /* ��������UID */
    $tag["<<SEARCH_UID>>"] ="";
    if (isset($form_name)) {
        $tag["<<SEARCH_UID>>"] = escape_html($form_name);
    }

    /* �ޥå���� */
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

    /* ���ڡ��������ڡ��� */
    $tag["<<PRE>>"] = "";
    $tag["<<NEXT>>"] = "";
    $tag["<<MAXPASSLEN>>"] = "";

    get_page($ldap_result, $page, $form_name_encode, $tag);

    return TRUE;

}

/*********************************************************
 * get_page
 *
 * ���ڡ��������ڡ����μ���
 *
 * [����]
 *       $ldap_result       LDAP�θ������
 *       $page              �ڡ���
 *       $form_name_encode  ���󥳡��ɤ��줿������
 *       $tag               ����
 * [�֤���]
 *       TRUE               ����
 **********************************************************/
function get_page($ldap_result, &$page, $form_name_encode, &$tag)
{
    global $web_conf;
    global $url_data;

    $sum = count($ldap_result);

    /* ������ */
    $tag["<<NUM>>"] = $sum;

    /* �ڡ����ֹ��0����ϤޤäƤ��� */
    $all_page = (int) ceil(($sum / $web_conf[$url_data["script"]]["lineperpage"]));
    if ($all_page == 0) {
        $all_page = 1;
    }

    /* ���ڡ����ʾ�ο������ϤäƤ�����Ǹ�Υڡ�����ɽ�� */
    if ($all_page < $page + 1) {
        $page = $all_page - 1;
    }

    /* ɽ������Ƥ���Ǹ�ΰ�����������硢���Υڡ�����ɽ�� */
    $ret = $sum % $all_page;
    if ($ret == 0 && $all_page < $page + 1) {
        $page = $page - 1;
    } 

    /* �ǽ�Υڡ����Ǥʤ�������ڡ�����ɽ�� */
    if ($page != 0) {
        $tmp = $page - 1;
        $tag["<<PRE>>"] = "<a href=\"#\" onClick=\"allSubmit('index.php', '$tmp', '', '$form_name_encode')\">���ڡ���</a>";
    }

    /* �Ǹ�Υڡ����Ǥʤ���м��ڡ�����ɽ�� */
    if ($page != $all_page - 1) {
        $tmp = $page + 1;
        $tag["<<NEXT>>"] = "<a href=\"#\" onClick=\"allSubmit('index.php', '$tmp', '', '$form_name_encode')\">���ڡ���</a>";
    }

    return TRUE;
}

/*********************************************************
 * set_loop_tag
 *
 * ������̽���
 *
 * [����]
 *       $ldap_result        LDAP�������
 *       $filter             �ե��륿
 *       $page               �ڡ���
 *       $form_name_encode   ���󥳡��ɤ�������
 *       $looptag            �롼�ץ���
 *
 * [�֤���]
 *       �ʤ�
 **********************************************************/
function set_loop_tag($ldap_result, $filter, $page, $form_name_encode, &$looptag)
{
    global $web_conf;
    global $url_data;
    global $plugindata;

    /* �桼��̾��ɽ������°�� */
    $dispusr = $web_conf[$url_data['script']]['displayuser'];

    /* ������̤�ɽ��°��̾�ǥ����� */
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

    /* ɽ��������� */
    $max = ($tmp < $sum) ? $tmp : $sum;
    while ($i < $max) {
        /* dn���Ǽ */
        $userdn = $ldap_result[$i]["dn"];
        $userdn = base64_encode($userdn);
        $userdn = str_rot13($userdn);
        $userdn = escape_html($userdn);

        /* ����� */
        $alias = "";
        $trans = "";
        $quota = "";

        /* �桼��̾���Ǽ */
        $name = escape_html($ldap_result[$i][$dispusr][0]);

        /* �����ꥢ�����Ǽ */
        if (isset($ldap_result[$i]["mailAlias"])) {
            $mailalias = escape_html($ldap_result[$i]["mailAlias"][0]);
            $part = explode("@", $mailalias, 2);
            $alias = $part[0];
        }

        /* ž�����ɥ쥹���Ǽ */
        if (isset($ldap_result[$i]['mailForwardingAddr'][0])) {
            if ($ldap_result[$i]['mailForwardingAddr'][0] !=
                $ldap_result[$i]['mail'][0]) {
                $trans = $ldap_result[$i]['mailForwardingAddr'][0];
            } else {
                $trans = $ldap_result[$i]['mailForwardingAddr'][1];
            }
        }

         /* �᡼�����̤��Ǽ */
        if (isset($ldap_result[$i]["quotaSize"][0])) {

            /* ����ե�����Υ�������ñ�̤�ʸ�����Ѵ����� */
            $conf_quota = strtolower($web_conf[$url_data["script"]]["quotaunit"]);

            /* ����ե���������Ƥˤ�ä�ñ�̤��Ѳ������� */
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
<input type="button" class="list_mod_btn" onClick="allSubmit('mod.php', '$page', '$userdn', '$form_name_encode')" title="�桼��
�Խ�">

EOD;

        // forwardconf��on�ξ���ž�������󥯤��������
        if ($web_conf[$url_data["script"]]["forwardconf"] === FORWARD_ON) {
            $looptag[$k]["<<FORWARD>>"] = <<<EOD
<a href="#" onClick="allSubmit('forward.php', '$page', '$userdn', '$form_name_encode')">ž������</a>

EOD;
        }

        /* �ץ饰�������Խ����̤ؤΥ��ɽ�� */
        $count = count($plugindata);
        if ($count > 0) {
            for ($j = 0; $j < $count; $j++) {
                /* ����� */
                $looptag[$k]["<<PLUGIN$j>>"] = "";

                $p_name = $plugindata[$j]["name"];
                $file = $plugindata[$j]["file"];
                $image = $plugindata[$j]["image"];
                $looptag[$k]["<<PLUGIN$j>>"] .=<<<EOD
        <input type="button" class="plugin_btn" style="background:url(images/${image});" onClick="allSubmit('$file', '$page', '$userdn', '$form_name_encode')" title="${p_name}�Խ�">
EOD;
            }
        }

        /* ɽ���������Ǥ���Υ롼�� */
        $i++;

        /* 0����Υ롼�� */
        $k++;
    }

}


/*********************************************************
 * csv_output
 *
 * CSV�ե�����ν��Ϥ򤪤��ʤ�
 *
 * [����]
 *
 * [�֤���]
 *           TRUE     ����
 *           FALSE    �۾�
 *
 **********************************************************/
function csv_output()
{

    global $dispusr;
    global $ldap_result;
    global $domain;
    global $web_conf;
    global $url_data;

    /* ������̤�ɽ��°��̾�ǥ����� */
    usort($ldap_result, "user_sort");
    reset($ldap_result);

    header("Content-Disposition: attachment; filename=userdata_$domain.csv");
    header("Content-Type: application/octet-stream");

    foreach ($ldap_result as $userdata) {

        /* �������� */
        $csvdata = array();

        /* �桼��̾���Ǽ */
        array_push($csvdata, $userdata[$dispusr][0]);

        /* �᡼�����̤��Ǽ */
        if (isset($userdata["quotaSize"])) {
            $quota = $userdata["quotaSize"][0];
            array_push($csvdata, $quota);
        } else {
            array_push($csvdata, "");
        }

        /* �����ꥢ�����Ǽ */
        if (isset($userdata["mailAlias"])) {
            $part = explode("@", $userdata["mailAlias"][0], 2);
            array_push($csvdata, $part[0]);
        } else {
            array_push($csvdata, "");
        }

        /* �᡼��ž�����ɥ쥹���Ǽ */
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

        // forwardconf��on�ξ��ϥ��������ȥ����ƥ�������Ǽ
        if ($web_conf[$url_data['script']]['forwardconf'] === FORWARD_ON) {
            // ��������������
            if (isset($userdata["mailFilterOrder"])) {
                array_push($csvdata, $userdata["mailFilterOrder"][0]);
            } else {
                array_push($csvdata, "");
            }

            // �����ƥ����������
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
 * �������Ϥ��줿���󤫤�CSV�ե�����ΰ�Ԥ����
 *
 * [����]
 *           $csvdata CSV�������Ѵ���������
 *
 * [�֤���]
 *           $csvline CSV�������Ѵ����줿ʸ����
 *
 **********************************************************/
function mk_csv_line($csvdata) {

    $csvline = implode(",", $csvdata);
    
    return $csvline;
}

/*********************************************************
 * form_check
 *
 * ���ϥե�����η��������å�
 *
 * [����]
 *           $data    �����å�����ǡ�����Ϣ������
 *
 * [�֤���]
 *           TRUE     ����
 *           FALSE    ���顼
 *
 **********************************************************/
function form_check($data)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;

    /* �桼��̾�η��������å� */
    $ret = check_search_name($data['form_name']);
    if ($ret === FALSE) {
        $err_msg = $msgarr['15001'][SCREEN_MSG];
        $log_msg = $msgarr['15001'][LOG_MSG];
        return FALSE;
    }	

    /* �桼���������Υ����å� */
    $ret = check_flg($data['name_match']);
    if ($ret === FALSE) {
        $err_msg = $msgarr['15002'][SCREEN_MSG];
        $log_msg = $msgarr['15002'][LOG_MSG];
        return FALSE;
    }	

    return TRUE;
}

/***********************************************************
 * �������
 **********************************************************/
/* �ͤν���� */
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

/* �ե���������Ϥ��줿�� */
if (isset($_POST['form_name'])) {
    $form_name = $_POST['form_name'];
}

/* �������ơ����� */
if (isset($_POST["search"]) || isset($_POST["filter"]) ||
    isset($_POST["csvdownload"])) {
    $status = STATUS_SEARCH;
} else {
    $status = STATUS_INPUT;
}

/* ����ե����롦���ִ����ե������ɹ������å��������å� */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit (1);
}

/* �ץ饰����ǡ������å� */
set_plugindata();

/***********************************************************
 * main����
 **********************************************************/
/* ɽ��°������� */
$dispusr = $web_conf[$url_data['script']]['displayuser'];
/* ������ʬ�� */
if ($status == STATUS_SEARCH) {
    if ((isset($_POST["search"]) || isset($_POST["csvdownload"])) &&
         form_check($_POST) === FALSE) {
        /* �����ܥ��󤬲����졢���������ϤǤ���С����Ϥ��ʤ��� */
        $status = STATUS_INPUT;
        $logstr = OPERATION . ":NG:" . $log_msg;
        result_log($logstr);
    } else {
        /* �����Υե��륿�������� */
        if ((!isset($_POST["search"]) && !isset($_POST["csvdownload"])) &&
             $_POST["filter"] != "") {

            /* �ڡ������ͥ����å� */
            if (is_num_check($_POST["page"]) === FALSE) {
                $err_msg = $msgarr['15003'][SCREEN_MSG];
                $log_msg = $msgarr['15003'][LOG_MSG];
                result_log(OPERATION . ":NG:" . $log_msg);
                syserr_display();
                exit (1);
            }
            $page = $_POST["page"];

            /* �ե���������Ϥ��줿�ͤ�ʣ�粽 */
            if (isset($_POST['form_name'])) {
                $form_name = str_rot13($_POST['form_name']);
                $form_name = base64_decode($form_name);
            }

            /* �ե��륿��ʣ�粽 */
            if (sess_key_decode($_POST["filter"], $filter) === FALSE) {
                result_log($log_msg, LOG_ERR);
                syserr_display();
                exit (1);
            }

            /* �ե��륿�η��������å� */
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
            /* �ե��륿����� */
            $filter = mk_filter($_POST["form_name"], $_POST["name_match"]);

            /* ��������Τϡ�ɽ��°�����ĥ���ȥ�Τ� */
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

        /* �ե��륿�ΰŹ沽 */
        if (sess_key_make($filter, "", $filter_disp) === FALSE) {
            result_log($log_msg, LOG_ERR);
            syserr_display();
            exit (1);
        }

        /* ¾�Υڡ�����������ۤ���å�������ɽ�� */
        if (isset($_POST["msg"])) {
            $err_msg = escape_html($_POST["msg"]);
        }
    }
}

/* �������ΰŹ沽 */
if (isset($_POST['form_name'])) {
    $form_name_encode = base64_encode($form_name);
    $form_name_encode = str_rot13($form_name_encode);
}


/***********************************************************
 * ɽ������
 **********************************************************/
// ž�����ꥫ���ɽ������
if ($web_conf[$url_data['script']]['forwardconf'] === FORWARD_ON) {
    $tag["<<FORWARD_START>>"] = "";
    $tag["<<FORWARD_END>>"] = "";
}
/* �ڡ����ν��� */
if (isset($_POST['csvdownload']) && count($ldap_result) > 0) {
    $err_msg = $msgarr['15006'][SCREEN_MSG];
    $log_msg = $msgarr['15006'][LOG_MSG];
    $logstr = OPERATION . ":OK:" . $log_msg;
    result_log($logstr);
    csv_output();
    exit(0);
} else {
    /* �������� */
    set_tag_data($_POST, $form_name, $ldap_result, $filter_disp, $form_name_encode, $page, $tag);

    /* �롼�ץ����κ��� */
    set_loop_tag($ldap_result, $filter, $page, $form_name_encode,
                 $looptag);
    /* ɽ�� */
    $ret = display(TMPLFILE, $tag, $looptag, "<<STARTLOOP>>", "<<ENDLOOP>>");
    if ($ret === FALSE) {
        result_log($log_msg, LOG_ERR);
        syserr_display();
        exit(1);
    }
}
?>
