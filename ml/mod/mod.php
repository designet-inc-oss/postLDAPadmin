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
 * �᡼��󥰥ꥹ���Խ�����
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

/***********************************************************
�ƥڡ����������
*********************************************************/
define("OPERATION", "Modifying mailinglist");
define("TMPLFILE", "admin_ml_mod_mod.tmpl");

/*********************************************************
 * mk_addr_list
 *
 * ���ɥ쥹�Υꥹ�Ȥ��������
 *
 * [����]
 *           $nowlist	���ߤΥꥹ��
 *           $input	���Ϥ��줿�ꥹ��
 *           $list      ���ɥ쥹�γ�Ǽ��
 *           $bool      �ꥹ�Ȥ˲ä�����(TRUE:¸�ߤ���| FALSE:���ʤ�)
 *
 * [�֤���]
 *	     int	���ɥ쥹�ο�
 **********************************************************/
function mk_addr_list($nowlist, $input, &$list, $bool)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $logstr;

    $list = array();

    $i = 0;
    /* ���ߤΥꥹ�Ȥ���Ĥ�̵����� */
    if (!is_array($nowlist)) {
        if ($bool === TRUE) {
            return 0; 
        }
        $list = $input;
        return count($list);
    }

    $j = 0;
    $err = 0;
    foreach($input as $addr) {
        $j++;
        reset($nowlist);
        if ((array_search($addr, $nowlist) !== FALSE) === $bool) {
            $list[] = $addr;
            $i++;
        } else {
            $err++;
            $err_msg .= $msgarr['12006'][SCREEN_MSG] . "(" . $j . "����)<BR>";
            $log_msg .= $msgarr['12006'][LOG_MSG]    . "(line" . $j . ")<BR>";
        }
    }

    /*  ���顼 */
    if ($err != 0) {
        $logstr = OPERATION . ":NG:" . $log_msg;
        return -1;
    }

    return $i;
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
$tag["<<MLADDR>>"] = "";
$tag["<<HIDDEN>>"] = "";
$tag["<<NEWADDR>>"] = "";
$tag["<<MAILADDRS>>"] = "";

/* ����ե����롢���ִ����ե������ɹ������å��������å� */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit (1);
}

/***********************************************************
 * main����
 **********************************************************/

$mladdr = $_POST["mladdr"];

/* LDAP�θ�����DN */
if (isset($_POST["myadd"]) || isset($_POST["myaddlist"]) || isset($_POST["mydel"]) ) {
    /* �᡼��󥰥ꥹ�ȴ������̤�������ܰʳ���ʣ�粽 */
    $mladdr = str_rot13($mladdr);
    $mladdr = base64_decode($mladdr);
}

$search_dn = sprintf(ADD_DN, $mladdr, $web_conf[$url_data['script']]['ldaplistsuffix'],
                     $web_conf[$url_data["script"]]["ldapbasedn"]);

$dispmlatt = $web_conf[$url_data['script']]['displayml'];

/* LDAP�����ե��륿������ */
$filter = "(&(objectClass=" . PLAOC . ")" .
          "(&" . $web_conf[$url_data["script"]]["ldapmlfilter"] .
          "(" . $dispmlatt . "=*)" .
          "))";

/* ������Ͽ */
if (isset($_POST["myadd"])) {

    /* ���ɥ쥹�ν񼰥����å� */
    if (check_mail($_POST["newaddr"]) === FALSE) {
        $err_msg = $msgarr['12002'][SCREEN_MSG];
        $log_msg = $msgarr['12002'][LOG_MSG];
        $logstr = OPERATION . ":NG:" . $log_msg;
    } else {
        /* �᡼�륢�ɥ쥹�μ��� */
        $result = array();
        $ret = main_get_entry($search_dn, $filter, array(), 
                              $web_conf[$url_data["script"]]["ldapscope"], $result);
        /* LDAP�������˼��� */
        if ($ret != LDAP_OK) {
            $sys_err = TRUE;
            result_log(OPERATION . ":NG:" . $log_msg);
            syserr_display();
            exit (1);
        }
        $dn = $result[0]["dn"];

        /*�᡼��󥰥ꥹ�ȥ��ɥ쥹�Ȥν�ʣ�����å� */
        if ($_POST["newaddr"] == $mladdr) {
            $err_msg = $msgarr['12003'][SCREEN_MSG];
            $log_msg = $msgarr['12003'][LOG_MSG];
            $logstr = OPERATION . ":NG:" . $log_msg;
        } else {
            $attr = array("mailForwardingAddr" => $_POST["newaddr"]);

            /* °�����ɲ� */
            $ret = LDAP_add_attribute($dn, $attr);
            if ($ret == LDAP_ERR_DUPLICATE) {
                    $err_msg = sprintf($msgarr['12004'][SCREEN_MSG],
                                       $_POST["newaddr"]);
                    $log_msg = sprintf($msgarr['12004'][LOG_MSG],
                                       $_POST["newaddr"]);
                    $logstr = OPERATION . ":NG:" . $log_msg;
            } elseif ($ret != LDAP_OK) {
                $logstr = OPERATION . ":NG:" . $log_msg;
            } else {
                $err_msg = sprintf($msgarr['12005'][SCREEN_MSG],
                                   $_POST["newaddr"]);
                $log_msg = sprintf($msgarr['12005'][LOG_MSG],
                                   $_POST["newaddr"]);
                $logstr = OPERATION . ":OK:" . $log_msg;
                $_POST["newaddr"] = "";
            }
        }
    }
    result_log($logstr);

/* �����Ͽ */
} else if (isset($_POST["myaddlist"])) {

    if (get_addr_list($_FILES["filename"]["tmp_name"], $fwaddrs) === FALSE) {
        $logstr = OPERATION . ":NG:" . $log_msg;

    } else {

        /* �᡼�륢�ɥ쥹�μ��� */
        $result = array();

        $ret = main_get_entry($search_dn, $filter, array(), 
                              $web_conf[$url_data["script"]]["ldapscope"], $result);
        /* LDAP�������˼��� */
        if ($ret != LDAP_OK) {
            $sys_err = TRUE;
            result_log(OPERATION . ":NG:" . $log_msg);
            syserr_display();
            exit (1);
        }
        $dn = $result[0]["dn"];

        if (!isset($result[0]["mailForwardingAddr"])) {
            $num = 0;
            $result[0]["mailForwardingAddr"] = array();
        }

        $num = mk_addr_list($result[0]["mailForwardingAddr"], $fwaddrs,
                                $addlist, FALSE);

        if ($num > 0) {
            /* �᡼��󥰥ꥹ�ȥ��ɥ쥹�Ȥν�ʣ�����å� */
            foreach ($addlist as $eachmail) {
                if ($mladdr == $eachmail) {
                    $err_msg = $msgarr['12003'][SCREEN_MSG];
                    $log_msg = $msgarr['12003'][LOG_MSG];
                    $logstr = OPERATION . ":NG:" . $log_msg;
                    $dup_flg = 1;
                    break;
                }
            }

            if (!isset($dup_flg)) {
                /* °�����ɲ� */
                if ($num != 0) {
                    $attr = array("mailForwardingAddr" => $addlist);
                    $ret = LDAP_add_attribute($dn, $attr);
                } else {
                    $ret = LDAP_OK;
                }

                if ($ret != LDAP_OK) {
                    if ($ret == LDAP_ERR_DUPLICATE) {
                        $err_msg = $msgarr['12006'][SCREEN_MSG];
                        $log_msg = $msgarr['12006'][LOG_MSG];
                    }
                    $logstr = OPERATION . ":NG:" . $log_msg;
                } else {
                    $err_msg = $msgarr['12007'][SCREEN_MSG];
                    $log_msg = $msgarr['12007'][LOG_MSG];
                    $logstr = OPERATION . ":OK:" . $log_msg;
                }
            }
        }
    }
    $logstr = preg_replace("/<br>/i", " / ", $logstr);
    result_log($logstr);

/* ��� */
} else if (isset($_POST["mydel"])) {

    if (isset($_POST["addr"])) {

        /* ���ߤΥ᡼�륢�ɥ쥹�μ��� */
        $result = array();
        $ret = main_get_entry($search_dn, $filter, array(), 
                              $web_conf[$url_data["script"]]["ldapscope"], $result);
        /* LDAP�������˼��� */
        if ($ret != LDAP_OK) {
            $sys_err = TRUE;
            result_log(OPERATION . ":NG:" . $log_msg);
            syserr_display();
            exit (1);
        }
        $dn = $result[0]["dn"];

        if (!isset($result[0]["mailForwardingAddr"])) {
            $result[0]["mailForwardingAddr"] = array();
        }
        $num = mk_addr_list($result[0]["mailForwardingAddr"], $_POST["addr"],
                            $dellist, TRUE);

        /* LDAP°����� */
        if ($num > 0) {
            $ret = LDAP_del_attribute($dn, array("mailForwardingAddr" =>
                                                 $dellist));
        } else {
            $ret = LDAP_OK;
        }

        if ($ret != LDAP_OK) {
            if ($ret == LDAP_ERR_NOATTR) {
                $err_msg = sprintf($msgarr['12008'][SCREEN_MSG], $dn);
                $log_msg = sprintf($msgarr['12008'][LOG_MSG], $dn);
            }
            $logstr = OPERATION . ":NG:" . $log_msg;
        } else {
            $dispaddrs = $_POST["addr"][0];
            for ($i = 1; $i < count($_POST["addr"]); $i++) {
                $dispaddrs .= ", " . $_POST["addr"][$i];
            }
            $err_msg = sprintf($msgarr['12009'][SCREEN_MSG], $dispaddrs);
            $log_msg = sprintf($msgarr['12009'][LOG_MSG], $dispaddrs);
            $logstr = OPERATION . ":OK:" . $log_msg;
        }

    } else {
        $err_msg = $msgarr['12010'][SCREEN_MSG];
        $log_msg = $msgarr['12010'][LOG_MSG];
        $logstr = OPERATION . ":NG:" . $log_msg;
    }
    result_log($logstr);

/* ����󥻥� */
} else if (isset($_POST["cancel"])) {

    /* �桼��������˥塼���̤� */
    dgp_location("./index.php");
    exit;
}

/* �᡼�륢�ɥ쥹�μ��� */
$result = array();

$ret = main_get_entry($search_dn, $filter, array(), 
                      $web_conf[$url_data["script"]]["ldapscope"], $result);
/* LDAP�������˼��� */
if ($ret != LDAP_OK) {
    $sys_err = TRUE;
    $err_msg = escape_html($err_msg);
    result_log(OPERATION . ":NG:" . $log_msg);
    syserr_display();
    exit (1);
}
$dn = $result[0]["dn"];

/* �᡼��󥰥ꥹ�ȥ��ɥ쥹��Ź沽 */
$enc_ml = base64_encode($mladdr);
$enc_ml = str_rot13($enc_ml);

/* ɽ���Ѥ�°�����Ǽ */
$dispml = $result[0]["$dispmlatt"][0];

/***********************************************************
 * ɽ������
 **********************************************************/

/* �᡼�륢�ɥ쥹�������ݻ� */
$newaddr = "";
if (isset($_POST["newaddr"])) {
    $newaddr = escape_html($_POST["newaddr"]);
}

$mailaddrs = "";
/* �᡼�륢�ɥ쥹�Υ���ȥ꤬������ */
if (isset($result[0]["mailForwardingAddr"])) {
    /* �᡼�륢�ɥ쥹�Υ���ȥ�򥽡��� */
    sort($result[0]["mailForwardingAddr"]);
    reset($result[0]["mailForwardingAddr"]);

    /* �᡼�륢�ɥ쥹�Υ���ȥ�򣱤Ĥ��ļ�����ɽ���Ѥ˲ù� */
    foreach($result[0]["mailForwardingAddr"] as $i) {
        $addr = $i;
        $addr = escape_html($addr);
        $mailaddrs .= "<option value=\"$addr\">$addr" . "\n";
    }
}

/* �������ͤ򥻥å� */
set_tag_common($tag, "");
$tag["<<MLADDR>>"] = $dispml = escape_html($dispml);
$tag["<<HIDDEN>>"] = "<input type='hidden' name='mladdr' value='$enc_ml'>";
$tag["<<NEWADDR>>"] = $newaddr;
$tag["<<MAILADDRS>>"] = $mailaddrs;

/* �ڡ����ν��� */
$ret = display(TMPLFILE, $tag, array(), "", "");
if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

?>
