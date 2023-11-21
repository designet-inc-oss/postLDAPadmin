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
 * �᡼��󥰥ꥹ���Խ�
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

/********************************************************
 *�ƥڡ����������
 *********************************************************/
define("OPERATION", "Modifying mailinglist");
define("TMPLFILE", "admin_ml_mod.tmpl");

/*********************************************************
 * add_ml
 *
 * �᡼��󥰥ꥹ�ȥ���ȥ���ɲ�
 *
 * [����]
 *        $newm    ML���ɥ쥹
 *
 * [�֤���]
 *        TRUE     ����
 *        FALSE    �۾� 
 **********************************************************/
function add_ml($newml)
{
    global $web_conf;
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $domain;
    global $url_data;

    $mail = $newml . "@" . $domain;

    /* �᡼��󥰥ꥹ�ȥ��ɥ쥹�ν�ʣ�����å� */
    $ret = check_duplicate($mail);
    if ($ret == LDAP_FOUNDUSER || $ret == LDAP_FOUNDALIAS ||
        $ret == LDAP_FOUNDOTHER) {
        $err_msg = $msgarr['11001'][SCREEN_MSG];
        $log_msg = $msgarr['11001'][LOG_MSG];
        return FALSE;
    } elseif ($ret == LDAP_ERRUSER) {
        result_log(OPERATION . ":NG:" . $log_msg);
        syserr_display();
        exit (1);
    }

    /* LDAP�ɲ�°�� */
    $add_dn = sprintf(ADD_DN, $mail, 
                      $web_conf[$url_data["script"]]["ldaplistsuffix"], 
                      $web_conf[$url_data["script"]]["ldapbasedn"]);
    $objectclass = mk_oc_list($web_conf[$url_data["script"]]['ldapobjectclass']);
    $attr = array("objectClass" => $objectclass,
                  "mail" => $mail,
                  "uid" => $newml);

    /* LDAP����ȥ��ɲ� */
    $ret = LDAP_add_entry($add_dn, $attr);
    if ($ret != LDAP_OK) {
        result_log(OPERATION . ":NG:" . $log_msg);
        syserr_display();
        exit (1);
    }

    $dispml = $web_conf[$url_data["script"]]["displayml"];
    $dispval = "";
    if (isset($attr["$dispml"])) {
        $dispval = $attr["$dispml"];
    }

    $err_msg = sprintf($msgarr['11002'][SCREEN_MSG], $dispval);
    $log_msg = sprintf($msgarr['11002'][LOG_MSG], $dispval);
    return TRUE;
}

/*********************************************************
 * del_ml
 *
 * �᡼��󥰥ꥹ�ȥ���ȥ�κ��
 *
 * [����]
 *        $mladdr    ML���ɥ쥹
 *
 * [�֤���]
 *        TRUE       ����
 *        FALSE      �۾� 
 **********************************************************/
function del_ml($mladdr)
{
    global $web_conf;
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $url_data;

    $dispml = $web_conf[$url_data["script"]]["displayml"];

    /* �᡼��󥰥ꥹ�ȥ���ȥ�μ��� */
    $dn = sprintf(SEARCH_DN, $web_conf[$url_data['script']]['ldaplistsuffix'], 
                  $web_conf[$url_data['script']]['ldapbasedn']);
    $result = array();
    $filter = "(&(mail=" . $mladdr . ")(objectClass=" . PLAOC . "))";
    $ret = main_get_entry($dn, $filter, array($dispml),
                          $web_conf[$url_data["script"]]["ldapscope"], $result);
    /* ���˺������Ƥ��� */
    if ($ret == LDAP_ERR_NODATA) {
        $err_msg = $msgarr['11003'][SCREEN_MSG];
        $log_msg = $msgarr['11003'][LOG_MSG];
        return TRUE;
    }
    /* LDAP�������˼��� */
    if ($ret != LDAP_OK) {
        result_log(OPERATION . ":NG:" . $log_msg);
        syserr_display();
        exit (1);
    }

    $del_dn = $result[0]["dn"];
    $dispval = $result[0]["$dispml"][0];

    /* LDAP����ȥ��� */
    $ret = LDAP_del_entry($del_dn);
    if ($ret != LDAP_OK && $ret != LDAP_ERR_NODATA) {
        result_log(OPERATION . ":NG:" . $log_msg);
        syserr_display();
        exit (1);
    }

    $err_msg = sprintf($msgarr['11004'][SCREEN_MSG], $dispval);
    $log_msg = sprintf($msgarr['11004'][LOG_MSG], $dispval);
    return TRUE;
}

/*********************************************************
 * mlmod_location
 *
 * �᡼�륢�ɥ쥹�������̤ؤ�����
 *
 * [����]
 *        �ʤ�
 *
 * [�֤���]
 *        �ʤ�
 **********************************************************/
function mlmod_location()
{
    global $sesskey;

    /* ���å���� */
    $hidden  = "<input type=\"hidden\" name=\"sk\" value=\"" .
                $sesskey . "\">";
    $hidden .= "<input type=\"hidden\" name=\"mladdr\" value=\"" .
                $_POST["mladdr"] . "\">";

    /* HTML���� */
    display_header();
    print <<<EOD
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
<body onload="dgpSubmit('./mod.php')">
������...
<form method="post" name="common">
    $hidden
</form>
</body>
</html>
EOD;
    exit;
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
$tag["<<MLADDRS>>"] = "";
$tag["<<NEWML>>"] = "";
$tag["<<MLDOMAIN>>"] = "";

/* ����ե����롢���ִ����ե������ɹ������å��������å� */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit (1);
}

/***********************************************************
 * main����
 **********************************************************/
/* ������Ͽ */
if (isset($_POST["add"])) {

    /* ���ɥ쥹�ν񼰥����å� */
    if ($_POST["newml"] == "") {
        $err_msg = $msgarr['11005'][SCREEN_MSG];
        $log_msg = $msgarr['11005'][LOG_MSG];
        result_log(OPERATION . ":NG:" . $log_msg);
    } else {
        if (check_name($_POST["newml"], MAXNAME) === FALSE) {
            $err_msg = $msgarr['11006'][SCREEN_MSG];
            $log_msg = $msgarr['11006'][LOG_MSG];
            result_log(OPERATION . ":NG:" . $log_msg);
        } else {
            if (add_ml($_POST["newml"]) === FALSE) {
                result_log(OPERATION . ":NG:" . $log_msg);
            } else {
                result_log(OPERATION . ":OK:" . $log_msg);
                $_POST["newml"] = "";
            }
        }
    }

/* ��� */
} else if (isset($_POST["del"])) {

    if (isset($_POST["mladdr"])) {
        if (del_ml($_POST["mladdr"]) === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg);
        } else {
            result_log(OPERATION . ":OK:" . $log_msg);
            $err_msg = escape_html($err_msg);
        }

    } else {
        $err_msg = $msgarr['11007'][SCREEN_MSG];
        $log_msg = $msgarr['11007'][LOG_MSG];
        result_log(OPERATION . ":NG:" . $log_msg);
    }

/* �᡼�륢�ɥ쥹���ɲá����� */
} else if (isset($_POST["ml_add"])) {

    if (isset($_POST["mladdr"])) {
        /* �᡼��󥰥ꥹ�Ȥ����򤵤�Ƥ����� */
        mlmod_location();
    } else {
        /* �᡼��󥰥ꥹ�Ȥ����򤵤�Ƥ��ʤ���� */
        $err_msg = $msgarr['12001'][SCREEN_MSG];
        $log_msg = $msgarr['12001'][LOG_MSG];
        result_log(OPERATION . ":NG:" . $log_msg);
    }
}

$err_tmp = $err_msg;

/* �᡼��󥰥ꥹ�ȥ���ȥ�μ��� */
$dn = sprintf(SEARCH_DN, $web_conf[$url_data['script']]['ldaplistsuffix'], 
              $web_conf[$url_data['script']]['ldapbasedn']);
$result = array();
$filter = "(&(objectClass=" . PLAOC . ")" .
          "(&(" . $web_conf[$url_data['script']]['displayml'] . "=*)" . 
          $web_conf[$url_data['script']]['ldapmlfilter'] . "))";

$ret = main_get_entry($dn, $filter, array(), $web_conf[$url_data["script"]]["ldapscope"], $result);

/* LDAP�������˼��� */
if ($ret != LDAP_OK && $ret != LDAP_ERR_NODATA) {
    result_log(OPERATION . ":NG:" . $log_msg);
    if ($err_tmp != "" && $err_tmp != $err_msg) {
        $err_msg = $err_tmp . "<BR>" . $err_msg;
    }
    $sys_err = TRUE;
    syserr_display();
    exit (1);
}
if ($ret == LDAP_ERR_NODATA) {
    $err_msg = $err_tmp;
}

/***********************************************************
 * ɽ������
 **********************************************************/

/* �᡼��󥰥ꥹ�ȥ��ɥ쥹���������ݻ� */
$newml = "";
if (isset($_POST["newml"])) {
    $newml = escape_html($_POST["newml"]);
}

/* ɽ���Ѥ�LDAP°�� */
$displayml = $web_conf[$url_data["script"]]["displayml"];

/* �᡼��󥰥ꥹ�ȤΥ���ȥ�򥽡��� */
usort($result, "ml_sort");
reset($result);

/* �᡼��󥰥ꥹ�ȤΥ���ȥ�򣱤Ĥ��ļ�����ɽ���Ѥ˲ù� */
$mladdrs = "";
foreach($result as $i) {
    $mldisp = $i["$displayml"][0];
    $mldisp = escape_html($mldisp);
    $mladdr = $i["mail"][0];
    $mladdr = escape_html($mladdr);
    $mladdrs .= "<option value=\"$mladdr\">$mldisp" . "\n";
}

/* �������ͤ򥻥å� */
set_tag_common($tag, "");
$tag["<<MLADDRS>>"] = $mladdrs;
$tag["<<NEWML>>"] = $newml;
$tag["<<MLDOMAIN>>"] = escape_html($domain);

/* �ڡ����ν��� */
$ret = display(TMPLFILE, $tag, array(), "", "");
if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

?>
