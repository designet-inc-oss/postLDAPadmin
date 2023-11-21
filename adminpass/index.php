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
 * �����ԥѥ�����ѹ�����
 *
 * $RCSfile$
 * $Revision$
 * $Date$
 **********************************************************/

include_once("lib/dglibcommon");
include_once("lib/dglibpage");
include_once("lib/dglibsess");

/********************************************************
�ƥڡ����������
*********************************************************/

define("OPERATION", "Controlling administrator account");
define("TMPLFILE", "admin_adminpass_mod.tmpl");

/*********************************************************
 * mod_passwd
 *
 * �ѥ���ɤ�����å������ե�����˽񤭹���
 *
 * [����]
 * $data                POST���Ϥ��줿�ǡ���
 *
 * [�֤���]
 * TRUE                 ����
 * FALSE                �۾�  
 **********************************************************/
function mod_passwd ($data)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;
    global $domain;
    global $basedir;

    /* ����ʸ���Υ����å� */
    if (check_passwd($data["newpasswd"], (int)$web_conf["global"]["minpasswordlength"], 
        (int)$web_conf["global"]["maxpasswordlength"]) === FALSE) {
        return FALSE;
    }

    /* �ѥ���ɤΰ��ץ����å� */
    if ($data["newpasswd"] != $data["re_newpasswd"]) {
        $err_msg = $msgarr['07001'][SCREEN_MSG];
        $log_msg = $msgarr['07001'][LOG_MSG];
        return FALSE;
    }

    $old_passwd = $web_conf["global"]["adminpasswd"];

    /* �ѹ����Υѥ���ɤȰ��פ��ʤ��������å� */
    $new_passwd = md5($data["newpasswd"]);
    if ($new_passwd == $old_passwd) {
        $err_msg = $msgarr['07002'][SCREEN_MSG];
        $log_msg = $msgarr['07002'][LOG_MSG];
        return FALSE;
    }

    /* �ɥᥤ���������ե����� */
    $conf_file = $basedir . ETCDIR . $domain . "/" . WEBCONF;

    /* �ѹ�����ǡ����򥻥å� */
    $moddata["adminpasswd"] = $new_passwd;

    /* �ѹ� */
    if (write_web_conf($conf_file, $moddata) === FALSE) {
        return FALSE;
    }
   
    $err_msg = $msgarr['07003'][SCREEN_MSG];
    $log_msg = $msgarr['07003'][LOG_MSG];
    return TRUE; 
}

/***********************************************************
 * �������
 **********************************************************/

/* ��������� */
$tag["<<TITLE>>"]      = "";
$tag["<<JAVASCRIPT>>"] = "";
$tag["<<SK>>"]         = "";
$tag["<<TOPIC>>"]      = "";
$tag["<<MESSAGE>>"]    = "";
$tag["<<TAB>>"]        = "";

/* ����ե����롢���ִ����ե������ɹ������å��������å� */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit (1);
}

/***********************************************************
 * main����
 **********************************************************/

/* �ѥ�����ѹ� */
if (isset($_POST["update"])) {
    if (mod_passwd($_POST) === FALSE) {
        result_log(OPERATION . ":NG:" . $log_msg);
        $err_msg = escape_html($err_msg);
    } else {
        result_log(OPERATION . ":OK:" . $log_msg);

        /* ���������Ѥ˥��å���󥭡�������� */
        sess_key_make($web_conf["global"]["adminname"], $_POST["newpasswd"], $sesskey);

        /* �桼��������˥塼���̤� */
        dgp_location("index.php", $err_msg);
        exit;
    }
}

/***********************************************************
 * ɽ������
 **********************************************************/

/* ���� ���� */
set_tag_common($tag);

/* �ڡ����ν��� */
$ret = display(TMPLFILE, $tag, array(), "", "");
if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

?>
