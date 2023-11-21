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
 * �桼���ѥ桼����������
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
include_once("lib/dglibdovecot");
include_once("lib/dglibforward");

/********************************************************
 *�ƥڡ����������
 *********************************************************/
define("OPERATION", "Modifying user");
define("MODE_LDAPDATA", 0);
define("MODE_POSTDATA", 1);
define("TMPLFILE", "user_forward_mod.tmpl");

/*********************************************************
 * check_user_data
 *
 * ���ϥե�����η��������å�
 *
 * [����]
 *         $mail     �᡼�륢�ɥ쥹
 *         $passwd   �ѥ����
 *         $repasswd �ѥ����(��ǧ)
 *         $trans    ž���襢�ɥ쥹
 *         $save     �᡼����¸����
 *         &$attrs   ��������°�����Ǽ��������
 * [�֤���]
 *         TRUE      ����
 *         FALSE     �۾�
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

    /* �ѥ�������ϥ����å� */
    if ($passwd != "" || $repasswd != "") {
        $ret = check_passwd($passwd, (int)$web_conf["global"]["minpasswordlength"], 
                            (int)$web_conf["global"]["maxpasswordlength"]);
        if ($ret === FALSE) {
            return FALSE;
        }
        /* �ѥ���ɤΰ��פ��ǧ */
        if ($passwd != $repasswd) {
            $err_msg = $msgarr['21001'][SCREEN_MSG];
            $log_msg = $msgarr['21001'][LOG_MSG];
            return FALSE;
        }

        /* �ѥ���ɤ��Ǽ */
        $enpass = make_passwd($passwd);
        if ($enpass === FALSE) {
            return FALSE;
        }
        $attrs['userPassword'] = $enpass;
    }

    if ($trans != "") {
        /* �᡼��ž�����ɥ쥹���ϥ����å� */
        if (check_mail ($trans) === FALSE) {
            $err_msg = $msgarr['21002'][SCREEN_MSG];
            $log_msg = $msgarr['21002'][LOG_MSG];
            return FALSE;
        }
        array_push ($transes, $trans);
        /* �᡼����¸��������å� */
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
        /* ž�����ɥ쥹��ʣ�����å� */
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
            /* �᡼���Ĥ�����ξ���ž�����ɥ쥹�˼��᡼�륢�ɥ쥹���ɲ� */
            array_push($transes, $mail);
        }

        /* ž�����ɥ쥹���Ǽ */
        $attrs['mailForwardingAddr'] = $transes;
    } else {
        /* ž���襢�ɥ쥹�����Ǥ���С�����о� */
        $attrs['mailForwardingAddr'] = array();
    }

    /* �᡼��ǥ��쥯�ȥ�°��������ʤ���к��� */
    if (!isset($ldapdata[0]["mailDirectory"][0])) {
        $attrs["mailDirectory"] = $web_conf[$url_data["script"]]["basemaildir"] . 
                                  "/" . $user . "/";
    }

    return TRUE;
}

/*********************************************************
 * mod_user_data
 *
 * �桼���ǡ������ѹ���Ԥ�
 *
 * [����]
 *         $attrs   ��������°�����Ǽ��������
 * [�֤���]
 *         TRUE     ����
 *         FALSE    �۾�
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

    /* ������DN���ѹ� */
    $env['user_self'] = FALSE;

    /* LDAP�ǡ����ι��� */
    $dn = $env['user_selfdn'];
    $ret = LDAP_mod_entry($dn, $attrs);
    if ($ret !== LDAP_OK) {
        return FALSE;
    } else {
        $err_msg = $msgarr['21007'][SCREEN_MSG];
        $log_msg = $msgarr['21007'][LOG_MSG];
        result_log(OPERATION . ":OK:" . $log_msg);
        if (isset ($_POST['passwd1']) && $_POST['passwd1'] != "") {
           /* ���������Ѥ˥��å���󥭡�������� */
           sess_key_make($user, $_POST['passwd1'], $sesskey);
        }
    }
    return TRUE;
}

/***********************************************************
 * �������
 **********************************************************/

/* ��������� */
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

// looptag
// filter id
$tag_loop["<<FILTER_ID>>"] = "";
// ����ž���饸��
$tag_loop["<<ALL_FORWARD>>"] = "checked";
// �ܺ�����饸��
$tag_loop["<<DETAIL_FORWARD>>"] = "";
// ����������
$tag_loop["<<FORWARD_CHECK>>"] = "";
$tag_loop["<<FORWARD_TEXT>>"] = "";
$tag_loop["<<FORWARD_MATCH>>"] = "selected";
$tag_loop["<<FORWARD_INCLUDE>>"] = "";
$tag_loop["<<FORWARD_NOT_INC>>"] = "";
$tag_loop["<<FORWARD_EMPTY>>"] = "";
// ��̾���� 
$tag_loop["<<SUBJECT_CHECK>>"] = "";
$tag_loop["<<SUBJECT_TEXT>>"] = "";
$tag_loop["<<SUBJECT_MATCH>>"] = "selected";
$tag_loop["<<SUBJECT_INCLUDE>>"] = "";
$tag_loop["<<SUBJECT_NOT_INC>>"] = "";
$tag_loop["<<SUBJECT_EMPTY>>"] = "";
// �������� 
$tag_loop["<<RECIPT_CHECK>>"] = "";
$tag_loop["<<RECIPT_TEXT>>"] = "";
$tag_loop["<<RECIPT_MATCH>>"] = "selected";
$tag_loop["<<RECIPT_INCLUDE>>"] = "";
$tag_loop["<<RECIPT_NOT_INC>>"] = "";
$tag_loop["<<RECIPT_EMPTY>>"] = "";
// ž���衢�᡼��ν���
$tag_loop["<<TRANSFER_ADDR>>"] = "";
$tag_loop["<<MAIL_LEAVE>>"] = "selected";
$tag_loop["<<MAIL_DEL>>"] = "";

// loop��������
$loop = array();

/* ����ե����롢���ִ����ե������ɹ������å��������å� */
$ret = user_init();
if ($ret === FALSE) {
    $sys_err = TRUE;
    syserr_display();
    exit (1);
}

/***********************************************************
 * main����
 **********************************************************/
/* ������ʬ�� */

/* �桼��̾��Ǽ */
$user = $env['loginuser'];
$userdn = $env['user_selfdn'];
$del_flag = "";

/* �桼������μ��� */
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

    // ���ϥǡ����Υ����å�
    $ret = check_forward_data($_POST, $attrs);
    if ($ret === FALSE) {
        result_log(OPERATION . ":NG:" . $log_msg);
        $del_flag = "on";
    } else {

        // ��Ͽ����
        $ret = mod_user_data($attrs);
        if ($ret === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg);
        } else {
            // �����������
            $ldapdata[0]['mailFilterArticle'] = $attrs['mailFilterArticle'];
            $ldapdata[0]['mailFilterOrder'] = $attrs['mailFilterOrder'];

            // sieve�ե��������
            $ret = make_sievefile();
            // sieve�ե�����κ����˼��Ԥ������ϲ��̺�ɽ��
            if ($ret === FALSE) {
                $err_msg = $msgarr['26011'][SCREEN_MSG];
                dgp_location("./index.php", $err_msg);
                exit(0);
            }
            $err_msg = $msgarr['25007'][SCREEN_MSG];
            $log_msg = $msgarr['25007'][LOG_MSG];
            result_log(OPERATION . ":OK:" . $log_msg);
        }
    }
} elseif (isset($_POST['buttonName']) && $_POST['buttonName'] === "delete") {

    // �ݥ����Ѳ���ɽ������������٤Υե饰���ղä���
    $mode = MODE_POSTDATA;
    $del_flag = "on";
}

/***********************************************************
 * ɽ������
 **********************************************************/

/* �᡼��ž�����ɥ쥹ɽ������ */
$trans = "";
$save_mail_check = "";
$unsave_mail_check = "";
if ($mode == MODE_POSTDATA) {
    // �����⡼�ɡʹ����ܥ���,����ܥ��󤬲����줿����
    keep_post_forward_data($_POST, $loop, $del_flag);

} else {
    // LDAP�ǡ���ȿ�ǡʥǡ������ä����ν��ɽ����
    if (isset($ldapdata[0]['mailFilterOrder'][0]) === TRUE &&
        isset($ldapdata[0]['mailFilterArticle']) === TRUE) {

        // �ե��륿��������������
        $filterorder = array();
        order_analysis($ldapdata[0]['mailFilterOrder'][0], $filterorder);

        // �ե��륿�������ƥ��������
        $filterarticle = array();
        $ret = article_analysis($ldapdata[0]['mailFilterArticle'], $filterarticle);
        if ($ret === FALSE) {
            $err_msg = $msgarr['25005'][SCREEN_MSG];
        }

        // LDAP����Ͽ�ǡ�������̤�ȿ��
        $ret = reflect_filter_data($filterorder, $filterarticle, $loop);

        // order�ο��ȵ���line�����פ��ʤ������ɲä����������
        for ($i = count($filterorder); 
             $i < $web_conf[$url_data['script']]['forwardnum']; $i++) {
            // �ǥե���Ȥ�id�򥻥åȤ��ƥ롼�ץ������������
            $tag_loop["<<FILTER_ID>>"] = $i+1;
            array_push($loop, $tag_loop);
        }

    } else {
        $i = 1;
        // mailForwardingAddr��¸�ߤ�����������ž����ɽ��������
        if (isset($ldapdata[0]['mailForwardingAddr'])) {
            convert_forward_value($i, $loop);
            $i++;
        }

        // order��article��¸�ߤ��ʤ����Ͻ����
        for ($i ; $i <= $web_conf[$url_data['script']]['forwardnum']; $i++) {
            // �ǥե���Ȥ�id�򥻥åȤ��ƥ롼�ץ������������
            $tag_loop["<<FILTER_ID>>"] = $i;
            array_push($loop, $tag_loop);
        }
    }

} 

/* �������ͤ򥻥å� */
$java_script = <<<EOD
function dgpSubmitMulti(buttonValue, id) {
    document.getElementById('filterId').value = id;
    document.getElementById('buttonName').value = buttonValue;
    document.forms['filter_form'].submit();
}
function confirmDelete(buttonValue, filterValue) {
    if(confirm('�����˺�����Ƥ�����Ǥ�����')) {
            dgpSubmitMulti(buttonValue, filterValue);
    }
}
EOD;
set_tag_common($tag, $java_script);
$tag["<<UID>>"] = $dispusr;
$tag["<<TRANSFERADDR>>"] = $trans;
$tag["<<SAVEMAILENABLED>>"] = $save_mail_check;
$tag["<<SAVEMAILDISABLED>>"] = $unsave_mail_check;
$tag["<<MAXPASSLEN>>"] = $web_conf["global"]["maxpasswordlength"];

/* �ڡ����ν��� */
$ret = display(TMPLFILE, $tag, $loop, "<<LOOP_START>>", "<<LOOP_END>>");
if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

?>
