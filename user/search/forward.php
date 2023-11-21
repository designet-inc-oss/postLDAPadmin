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
 * �������ѥ桼��ž���������
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
include_once("lib/dglibdovecot");
include_once("lib/dglibforward");

/********************************************************
�ƥڡ����������
*********************************************************/

define("TMPLFILE", "admin_forward_mod.tmpl");

define("OPERATION", "Modify user forward");

define("MODE_LDAPDATA", 0);
define("MODE_POSTDATA", 1);

/*********************************************************
 * set_tag_data
 *
 * �����Υ��å�
 *
 * [����]
 *        $post      POST���Ϥ��줿��
 *        $tag       �������Ǽ
 *
 * [�֤���]
 *        �ʤ�
 *
 **********************************************************/
function set_tag_data($post, &$tag)
{
    global $user;
    global $dispusr;
    global $mode;
    global $ldapdata;
    global $userdn;
    global $form_name;

    /* DN�ΰŹ沽 */
    $userdn = base64_encode($userdn);
    $userdn = str_rot13($userdn);

    /* hidden���Ϥ��ǡ������Ǽ */
    $hiddendata['dn'] = $userdn;
    $hiddendata['page'] = $_POST["page"];
    $hiddendata['filter'] = $_POST["filter"];
    $hiddendata['form_name'] = $form_name;
    $hiddendata['name_match'] = $_POST['name_match'];
    $hiddendata['uid'] = $dispusr;

    // hidden�����Υ��å�
    $tag["<<HIDDEN>>"] = "";
    foreach($hiddendata as $hidkey => $hidval) {
        $hidval = escape_html($hidval);
        $tag["<<HIDDEN>>"] .= "<input type=\"hidden\" name=\"{$hidkey}\" value=\"{$hidval}\">\n";
    }

}

/***********************************************************
 * �������
 **********************************************************/
// �����
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

/* �桼�������Ǽ */
if (isset($_POST["dn"])) {
    $userdn = $_POST["dn"];
    $userdn = str_rot13($userdn);
    $userdn = base64_decode($userdn);
}
if (isset($_POST["page"])) {
    $page = $_POST["page"];
}
if (isset($_POST["filter"])) {
    $filter = $_POST["filter"];
}

/* ����ե����롢���ִ����ե������ɹ������å��������å� */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit (1);
}

/***********************************************************
 * main����
 **********************************************************/
/* ������ʬ�� */
/* �ڡ����η��������å� */
if (is_num_check($page) === FALSE) {
    $err_msg = $msgarr['16001'][SCREEN_MSG];
    $log_msg = $msgarr['16001'][LOG_MSG];
    result_log(OPERATION . ":NG:" . $log_msg);
    syserr_display();
    exit (1);
}

/* �ե��륿��ʣ�粽 */
if (sess_key_decode($filter, $dec_filter) === FALSE) {
    result_log(OPERATION . ":NG:" . $log_msg);
    syserr_display();
    exit (1);
}

/* �ե��륿�η��������å� */
$fdata = explode(':', $dec_filter);
if (count($fdata) != 3) {
    $err_msg = $msgarr['16002'][SCREEN_MSG];
    $log_msg = $msgarr['16002'][LOG_MSG];
    result_log(OPERATION . ":NG:" . $log_msg);
    syserr_display();
    exit (1);
}

/* DN�η��������å� */
$len = (-1) * strlen($web_conf[$url_data['script']]['ldapbasedn']);
$cmpdn = substr($userdn, $len);
if (strcmp($cmpdn, $web_conf[$url_data['script']]['ldapbasedn']) != 0) {
    $err_msg = $msgarr['16003'][SCREEN_MSG];
    $log_msg = $msgarr['16003'][LOG_MSG];
    result_log(OPERATION . ":NG:" . $log_msg);
    syserr_display();
    exit (1);
}

/* �桼������μ��� */
$ret = get_userdata($userdn);
if ($ret !== TRUE) {
    if ($ret == LDAP_ERR_NODATA) {
        $err_msg = $msgarr['16004'][SCREEN_MSG];
        $log_msg = $msgarr['16004'][LOG_MSG];
        result_log(OPERATION . ":OK:" . $log_msg);
        dgp_location_search("index.php", $err_msg);
    } else {
        result_log(OPERATION . ":NG:" . $log_msg);
        syserr_display();
        exit (1);
    }
}

$user = $ldapdata[0]["uid"][0];

$dispattr = $web_conf[$url_data['script']]['displayuser'];
$dispusr = $ldapdata[0][$dispattr][0];

/* �ե���������Ǽ */
$form_name = $_POST["form_name"];
$name_match = $_POST["name_match"];
$mode = "";

if (isset($_POST['modify'])) {

    // �����⡼��
    $mode = POST_MOD_MODE;

    // ���ϥǡ����Υ����å�
    $ret = check_forward_data($_POST, $attrs);
    if ($ret === FALSE) {
        result_log(OPERATION . ":NG:" . $log_msg);
        $del_flag = "on";
    } else {

        // ��Ͽ����
        $ret = mod_user_forward($attrs);
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

// ���ܥ������
} elseif (isset($_POST["cancel"])) {

    /* �������̤����� */
    dgp_location_search("index.php", $err_msg);
    exit;

} elseif (isset($_POST['buttonName']) && $_POST['buttonName'] === "delete") {

    // �ݥ����Ѳ���ɽ������������٤Υե饰���ղä���
    $mode = MODE_POSTDATA;
    $del_flag = "on";
}

/***********************************************************
 * ɽ������
 **********************************************************/
// �᡼��ž�����ɥ쥹ɽ������
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

// JAVASCRIPT
$javascript = <<<EOD
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

/* ���̥��� */
set_tag_common($tag, $javascript);
$tag["<<UID>>"] = $dispusr;
$tag["<<TRANSFERADDR>>"] = $trans;
$tag["<<SAVEMAILENABLED>>"] = $save_mail_check;
$tag["<<SAVEMAILDISABLED>>"] = $unsave_mail_check;
$tag["<<MAXPASSLEN>>"] = $web_conf["global"]["maxpasswordlength"];

/* �������å� */
set_tag_data($_POST, $tag);

$ret = display(TMPLFILE, $tag, $loop, "<<LOOP_START>>", "<<LOOP_END>>");
if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

?>
