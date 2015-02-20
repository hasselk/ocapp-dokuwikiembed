<?php

$this->create('dokuwikiembed_root', '/')
  ->actionInclude('dokuwikiembed/index.php');

$this->create('dokuwikiembed_index', 'index.php')
  ->actionInclude('dokuwikiembed/index.php');

$this->create('dokuwikiembed_ajax_admin-settings', 'ajax/admin-settings.php')
  ->actionInclude('dokuwikiembed/ajax/admin-settings.php');

$this->create('dokuwikiembed_ajax_dokuwikiframe', 'ajax/dokuwikiframe.php')
  ->actionInclude('dokuwikiembed/ajax/dokuwikiframe.php');

OC::$CLASSPATH['DWEMBED\AuthHooks'] = OC_App::getAppPath("dokuwikiembed") . '/lib/auth.php';

$this->create('dokuwikirefresh', '/refresh')->post()->action('DWEMBED\AuthHooks', 'refresh');

?>
