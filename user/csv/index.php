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
 * CSV一括処理画面
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

define("TMPLFILE", "admin_user_csv.tmpl");

/*********************************************************
 * set_tag_data()
 *
 * タグ情報セット関数
 *
 * [引数]
 *      $post           入力された値
 *      $tag            タグ
 *
 * [返り値]
 *      なし
 ********************************************************/
function set_tag_data($post, &$tag) {

    global $web_conf;
    global $sesskey;

    $tag["<<JAVASCRIPT>>"] = <<<EOD
<script type="text/javascript">
<!--
  function resultwindow() {
      window.open("", "resultwindow", "width=500,height=400,toolbar=no,scrollbars=yes");
      document.form.target = "resultwindow";
  }

  function uploadconfirm(msg){
      if (msgConfirm(msg)) {
          return resultwindow();
      } else {
          return false;
      }
  }
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

}

/***********************************************************
 * 初期処理
 **********************************************************/
/* タグ初期化 */
$tag = array();

/* 設定ファイル、タブ管理ファイルの読込、セッションのチェック */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit (1);
}

/***********************************************************
 * 表示処理
 **********************************************************/
/* 共通のタグ設定 */
set_tag_common($tag);

/* この画面のタグを設定 */
set_tag_data($_POST, $tag);

$ret = display(TMPLFILE, $tag, array(), "", "");
if ($ret === FALSE) {
    syserr_display();
    result_log($log_msg, LOG_ERR);
    exit(1);
}

?>
