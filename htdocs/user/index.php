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
 * index.php
 * �桼�����������
 *
 * $RCSfile$
 * $Revision$
 * $Date$
 *
 **********************************************************/

include_once("initial");
include_once("lib/dglibpostldapadmin");
include_once("lib/dglibcommon");
include_once("lib/dglibpage");
include_once("lib/dglibsess");
include_once("lib/dglibldap");

/********************************************************
 * �ƥڡ������Ȥ�����
*********************************************************/
define("OPERATION_LOGIN", "Login");
define("TMPLFILE", "user_login.tmpl");

/********************************************************
 * ldap_auth_login
 *
 * LDAPǧ�ڤ�Ԥ�
 *
 * [�֤���]
 *         TRUE     ����
 *         FALSE    �۾�
 * 
*********************************************************/
function ldap_auth_login() {
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;
    global $tab_conf;
    global $sesskey;

    /* ������ʬ�� */
    if (isset($_POST["login"]) === TRUE) {
    
        /* �桼��̾�Υ����å� */
        if ($_POST["user"] != "" &&
            check_search_name($_POST["user"]) === TRUE) {
            /* ���å��������å�&���� */
            $chk_mode = GENUSER;
    
            if (sess_start($_POST["user"], $_POST["passwd"], $sesskey, $chk_mode) === TRUE) {
                result_log(OPERATION_LOGIN . ":OK:" . $msgarr['05006'][LOG_MSG]);
                if (read_tab_conf(USERTABCONF) === FALSE) {
                    syserr_display();
                    return FALSE;
                }
                $script = key($tab_conf);
                $tab = key($tab_conf[$script][0]);
                dgp_location($script . "/" . $tab . "/index.php");
                return TRUE;
            }
            if (isset($env["ldap_server_down"])) {
                result_log(OPERATION_LDAP_CONNECTION . ":NG:" . $log_msg);
                $err_msg = $msgarr['06002'][SCREEN_MSG];
                $log_msg = $msgarr['06002'][LOG_MSG];
            } else {
                $err_msg = $msgarr['06003'][SCREEN_MSG];
                $log_msg = $msgarr['06003'][LOG_MSG];
            }
    
        } else {
            $err_msg = $msgarr['06004'][SCREEN_MSG];
            $log_msg = $msgarr['06004'][LOG_MSG];
        }
    }
    if (isset($_GET["e"]) === TRUE) {
        if ($_GET["e"] == 1) {
            $err_msg = $msgarr['06005'][SCREEN_MSG];
            $log_msg = $msgarr['06005'][LOG_MSG];
        }
        if ($_GET["e"] == 2) {
            $err_msg = $msgarr['06006'][SCREEN_MSG];
            $log_msg = $msgarr['06006'][LOG_MSG];
        }
        if ($_GET["e"] == 3) {
            $err_msg = $msgarr['06002'][SCREEN_MSG];
            $log_msg = $msgarr['06002'][LOG_MSG];
        }
    }
    /***********************************************************
    * ɽ������
    **********************************************************/
    /* ���� ���� */
    $tag["<<TITLE>>"] = $web_conf["global"]["titlename"];
    if (empty($err_msg)) {
    $tag["<<MESSAGE>>"] = "&nbsp;";
    } else {
        $tag["<<MESSAGE>>"] = $err_msg;
    }
    $tag["<<JAVASCRIPT>>"] = <<<EOD
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
    EOD;

    /* �ڡ����ν��� */
    $ret = display(TMPLFILE, $tag, array(), "", "");
    if ($ret === FALSE) {
        result_log($log_msg, LOG_ERR);
        syserr_display();
        return FALSE;
    }
}

/********************************************************
 * web_auth_login
 *
 * WEBǧ�ڤ�Ԥ�
 *
 * [�֤���]
 *         TRUE     ����
 *         FALSE    �۾�
 *
*********************************************************/
function web_auth_login(){
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;
    global $tab_conf;
    global $sesskey;
    global $domain;
 

    /* ������ʬ�� */
    if (isset($_SERVER[$web_conf['global']['webauthusername']]) !== TRUE) {
        $err_msg = $msgarr['26012'][SCREEN_MSG];       
        $log_msg = sprintf($msgarr['26015'][LOG_MSG], $web_conf['global']['webauthusername']);

        result_log($log_msg, LOG_ERR);
        syserr_display();
        return FALSE;
    }

    /* �桼��̾�Υ����å� */
    if ($_SERVER[$web_conf['global']['webauthusername']] != "" &&
        check_search_name($_SERVER[$web_conf['global']['webauthusername']]) === TRUE) {
        /* ���å��������å�&���� */
        $chk_mode = GENUSER;

        /* �ѥ���ɤ϶��ˤ��� */
        $webauth_passwd = '';
        $webauth_user = $_SERVER[$web_conf['global']['webauthusername']];

        /* �桼��̾���᡼�륢�ɥ쥹�ξ�� */
        if (filter_var($webauth_user, FILTER_VALIDATE_EMAIL)) {
            $ret = explode('@', $_SERVER[$web_conf['global']['webauthusername']]);

            /* �ɥᥤ��Υ����å� */
            if ($ret[1] !== $domain) {
                $err_msg = $msgarr['26012'][SCREEN_MSG];
                $log_msg = $msgarr['26014'][LOG_MSG];
  
                result_log($log_msg, LOG_ERR);
                syserr_display();

                return FALSE;
            }

            $webauth_user = $ret[0];
        }
 
        if (sess_start($webauth_user, $webauth_passwd, $sesskey, $chk_mode) === TRUE) {
            result_log(OPERATION_LOGIN . ":OK:" . $msgarr['05006'][LOG_MSG]);
            if (read_tab_conf(USERTABCONF) === FALSE) {
                result_log($log_msg, LOG_ERR);
                syserr_display();
                return FALSE;
            }
            $script = key($tab_conf);
            $tab = key($tab_conf[$script][0]);
            dgp_location($script . "/" . $tab . "/index.php");
            return TRUE;
        }
        if (isset($env["ldap_server_down"])) {
        result_log(OPERATION_LDAP_CONNECTION . ":NG:" . $log_msg);
            $err_msg = $msgarr['06002'][SCREEN_MSG];
            $log_msg = $msgarr['06002'][LOG_MSG];
        }     
    } else {
            $err_msg = $msgarr['26012'][SCREEN_MSG];
            $log_msg = sprintf($msgarr['26016'][LOG_MSG], $web_conf['global']['webauthusername']);
    }

    result_log($log_msg, LOG_ERR);
    syserr_display();
    return FALSE;
}

/***********************************************************
 * �������
 **********************************************************/

/* ��������� */
$tag["<<TITLE>>"]      = "";
$tag["<<JAVASCRIPT>>"] = "";
$tag["<<MESSAGE>>"]    = "";

/* $topdir��$basedir�Υ��å� */
url_search();

/* �ɥᥤ����� */
$domain = $_SERVER['DOMAIN'];

$url_data["script"] = "postldapadmin";

/* �����ɹ� */
if (read_web_conf($url_data["script"]) === FALSE) {
    syserr_display();
    exit (1);
}

/* ��å������ե�������ɹ� */
if (make_msgarr(MESSAGEFILE) === FALSE) {
    syserr_display();
    exit (1);
}

/* �Ź沽�����Υѥ��򥻥å� */
$admkey_file = $basedir . ETCDIR . $domain . "/" . ADMKEY;


/***********************************************************
 * main����
 **********************************************************/
if ($web_conf['global']['webauthmode'] === WEBAUTHMODE_ON){
    $ret = web_auth_login();
    if ($ret === TRUE) {
        exit (0);
    }
    exit (1);
}
$ret = ldap_auth_login();
if ($ret === TRUE) {
    exit (0);
}
exit (1);
?>
