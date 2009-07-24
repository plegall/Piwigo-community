<?php

if (!defined("COMMUNITY_PATH"))
{
  define('COMMUNITY_PATH', PHPWG_PLUGINS_PATH.basename(dirname(__FILE__)));
}

include_once (COMMUNITY_PATH.'/include/constants.php');

function plugin_install()
{
  $query = "
CREATE TABLE IF NOT EXISTS ".COMMUNITY_TABLE." (
  user_id smallint(5) NOT NULL default '0',
  permission_level tinyint NOT NULL default 1,
  PRIMARY KEY  (user_id)
)
;";
  pwg_query($query);
}

function plugin_uninstall()
{
  $query = 'DROP TABLE '.COMMUNITY_TABLE.';';
  pwg_query($query);
}
?>