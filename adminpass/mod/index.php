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
include_once("lib/dglibldap");

/********************************************************
�ƥڡ����������
*********************************************************/

define("OPERATION", "Modifying administrator account");

/***********************************************************
 * ���饹: ��˥塼����
 **********************************************************/
Class my_page extends page {

    /***********************************************************
     * �ܥǥ�������ɽ���ʥ����С��饤�ɡ�
     **********************************************************/
    function display_body() {

        global $sesskey;

print <<<EOD
<form method="post" action="index.php">
<table class="table">
  <tr>
    <td class="key1">�ѥ����</td>
    <td class="value">
      <input type="password" maxlength="8" name="newpasswd">
    </td>
  </tr>
  <tr>
    <td class="key1">�ѥ���ɡʳ�ǧ��</td>
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
    global $err_msg;
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
        $err_msg = "�ѥ���ɤ����פ��ޤ���";
        return FALSE;
    }

    $old_passwd = $web_conf["adminpasswd"];

    /* �ѹ����Υѥ���ɤȰ��פ��ʤ��������å� */
    $new_passwd = md5($data["newpasswd"]);
    if ($new_passwd == $old_passwd) {
        $err_msg = "�ѥ���ɤ��ѹ����Ȱ��פ��ޤ���";
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
   
    $err_msg = "�����ԥѥ���ɤ򹹿����ޤ�����";
    return TRUE; 
}

/***********************************************************
 * �������
 **********************************************************/
/* ���󥹥��󥹺��� */
$pg = new my_page();

/* ����ե����롢���ִ����ե������ɹ������å��������å� */
$ret = init();
if ($ret === FALSE) {
    $sys_err = TRUE;
    $pg->display(NULL);
    exit (1);
}

/***********************************************************
 * main����
 **********************************************************/

/* �ѥ�����ѹ� */
if (isset($_POST["update"])) {
    if (mod_passwd($_POST) === FALSE) {
        result_log(OPERATION . ":NG:" . $err_msg);
        $err_msg = escape_html($err_msg);
    } else {
        result_log(OPERATION . ":OK:" . $err_msg);

        /* ���������Ѥ˥��å���󥭡�������� */
        sess_key_make($web_conf["adminname"], $_POST["newpasswd"], $sesskey);

        /* �桼��������˥塼���̤� */
        dgp_location("../index.php", $err_msg);
        exit;
    }
}

/***********************************************************
 * ɽ������
 **********************************************************/

/* �ڡ����ν��� */
$pg->display(CONTENT);

?>
