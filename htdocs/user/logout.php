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
 * logout.php
 * ��������
 *
 * $RCSfile$
 * $Revision$
 * $Date$
 **********************************************************/

include_once("initial");
include_once("lib/dglibpostldapadmin");
include_once("lib/dglibcommon");
include_once("lib/dglibsess");
include_once("lib/dglibpage");
include_once("lib/dglibldap");

define("OPERATION_LOGOUT", "Log out");

/***********************************************************
 * main����
 **********************************************************/
/* ���å���󥭡� */
if (isset($_POST["sk"])) {
    $sesskey = $_POST["sk"];
}

/* $basedir��$topdir�Υ��å� */
url_search();
$url_data["script"] = "postldapadmin";

/* �ɥᥤ����� */
$domain = $_SERVER['DOMAIN'];

/* �Ź沽�����Υѥ��򥻥å� */
$admkey_file = $basedir . ETCDIR . $domain . "/" . ADMKEY;

/* ���������å� */
if (isset($sesskey) === FALSE) {
    header("Location: index.php?e=2");
    exit (1);
}

/* ����ե������ɹ� */
if (read_web_conf($url_data["script"]) === TRUE) {
    /* ��å������ե�������ɹ� */
    if (make_msgarr(MESSAGEFILE) === FALSE) {
        return FALSE;
    }
    sess_logout($sesskey);
    result_log(OPERATION_LOGOUT . ":OK:" . $msgarr['24001'][LOG_MSG]);
}

/* ���å��������å� */
if (is_user($sesskey) !== TRUE) {
    /* ��������̤����� */
    if (isset ($env["ldap_server_down"])) {
        result_log("LDAP Connection:NG:" . $log_msg);
        header("Location: index.php?e=3");
    } else {
        header("Location: index.php?e=1");
    }
    exit (1);
}

dgp_location($web_conf['global']['logoutredirecturl']);

?>
