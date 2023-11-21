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
 * �������ѥ桼���ɲò���
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
 * �ƥڡ����������
 ********************************************************/

define("OPERATION", "Adding user account");
define("TMPLFILE_LDAP",  "admin_user_add.tmpl");
define("TMPLFILE_WEBAUTH",  "admin_user_add_webauth.tmpl");
define("FORWARD_ON", "1");

/*********************************************************
 * set_tag_data()
 *
 * �������󥻥åȴؿ�
 *
 * [����]
 *  	$post		���Ϥ��줿��
 *
 * [�֤���]
 *	�ʤ�
 ********************************************************/

function set_tag_data($post, &$tag)
{
    global $mode;
    global $err_msg;
    global $url_data;
    global $web_conf;

    /* JavaScript ���� */
    $java_script = <<<EOD

  window.onload = function() {
    var i;
    var len = document.data_form.save.length;
    if(document.data_form.trans.value == "") {
      for(i=0;i<len;i++) {
        document.data_form.save[i].disabled = true;
      }
    } else {
      for(i=0;i<len;i++) {
        document.data_form.save[i].disabled = false;
      }
    }
  }

  function check(n) {
    var i;
    var len = document.data_form.save.length;
    if(n == "") {
      for(i=0;i<len;i++) {
        document.data_form.save[i].disabled = true;
      }
    } else {
      for(i=0;i<len;i++) {
        document.data_form.save[i].disabled = false;
      }
    }
  }
EOD;
    /* ���ܥ��� ���� */
    set_tag_common($tag, $java_script);

    /* �᡼��ܥå������� ���� */
    if ($mode == ADD_MODE) {
        $post['quota'] = $web_conf[$url_data["script"]]["diskquotadefault"];
    }

    /* �桼�����󥿥� ���� */
    set_admin_form_tag($mode, $post, array(), $tag);

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
$tag["<<UID>>"]        = "";
$tag["<<QUOTA>>"]      = "";
$tag["<<UNIT>>"]       = "";
$tag["<<ALIAS>>"]      = "";
$tag["<<TRANS>>"]      = "";
$tag["<<SAVEON>>"]     = "";
$tag["<<SAVEOFF>>"]    = "";
$tag["<<MAXPASSLEN>>"] = "";
$tag["<<FORWARD_START>>"] = "";
$tag["<<FORWARD_END>>"] = "";


/* ����ե����륿�ִ����ե������ɹ������å����Υ����å� */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit (1);
}

/***********************************************************
 * main����
 **********************************************************/

/* ������ʬ�� */
if (isset($_POST["add"])) {
    $mode = POST_ADD_MODE;

    /* ���ϥǡ����Υ����å� */
    $ret = check_add_data($_POST);

    /* �����ƥ२�顼 */
    if ($ret == FUNC_SYSERR) {
        result_log(OPERATION . ":NG:" . $log_msg);
        syserr_display();
        exit (1);

    /* ���ϥ��顼 */
    } elseif ($ret == FUNC_FALSE) {
        result_log(OPERATION . ":NG:" . $log_msg);
    } else {

        /* LDAP ��Ͽ */
        if (add_user($_POST) === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg);
            syserr_display();
            exit (1);

        } else {
            $dispattr = $web_conf[$url_data['script']]['displayuser'];
            if (isset($attr[$dispattr])) {
                $dispusr = $attr[$dispattr];
            } else {
                $dispusr = "";
            }

            $err_msg = sprintf($msgarr['13001'][SCREEN_MSG], $dispusr);
            $log_msg = sprintf($msgarr['13001'][LOG_MSG], $dispusr);
            result_log(OPERATION . ":OK:" . $log_msg);
 
            /* �桼��������˥塼���̤� */
            dgp_location("../index.php", $err_msg);
            exit;
        }
    }
} elseif(isset($_POST["cancel"])) {

    /* �桼��������˥塼���̤� */
    dgp_location("../index.php", $err_msg);
    exit;

} else {
    $mode = ADD_MODE;
}

/***********************************************************
 * ɽ������
 **********************************************************/
// ForwardConf��ON�ξ��ϵ�����ɽ���ä�
if ($web_conf[$url_data['script']]['forwardconf'] === FORWARD_ON) {
    $tag["<<FORWARD_START>>"] = "<!--";
    $tag["<<FORWARD_END>>"] = "-->";
}

/* �������� ���å� */
set_tag_data($_POST, $tag);

/* �ڡ����ν��� */
if ($web_conf['global']['webauthmode'] === WEBAUTHMODE_OFF) {
    $ret = display(TMPLFILE_LDAP, $tag, array(), "", "");
} else if ($web_conf['global']['webauthmode'] === WEBAUTHMODE_ON) {
    $ret = display(TMPLFILE_WEBAUTH, $tag, array(), "", "");
}

if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}
?>
