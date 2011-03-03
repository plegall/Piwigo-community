<?php

if (!defined("COMMUNITY_PATH"))
{
  define('COMMUNITY_PATH', PHPWG_PLUGINS_PATH.basename(dirname(__FILE__)));
}

function plugin_install()
{
  global $conf, $prefixeTable;

  if ('mysql' == $conf['dblayer'])
  {
    $query = '
CREATE TABLE '.$prefixeTable.'community_permissions (
  id int(11) NOT NULL AUTO_INCREMENT,
  type varchar(255) NOT NULL,
  group_id smallint(5) unsigned DEFAULT NULL,
  user_id smallint(5) DEFAULT NULL,
  category_id smallint(5) unsigned DEFAULT NULL,
  recursive enum(\'true\',\'false\') NOT NULL DEFAULT \'true\',
  create_subcategories enum(\'true\',\'false\') NOT NULL DEFAULT \'false\',
  moderated enum(\'true\',\'false\') NOT NULL DEFAULT \'true\',
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARACTER SET utf8
;';
    pwg_query($query);

    $query = '
CREATE TABLE '.$prefixeTable.'community_pendings (
  image_id mediumint(8) unsigned NOT NULL,
  state varchar(255) NOT NULL,
  added_on datetime NOT NULL,
  validated_by smallint(5) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARACTER SET utf8
;';
    pwg_query($query);
  }
  elseif ('pgsql' == $conf['dblayer'])
  {
    $query = '
CREATE TABLE "'.$prefixeTable.'community_permissions" (
  "id" serial NOT NULL,
  "type" VARCHAR(255) NOT NULL,
  "group_id" INTEGER,
  "user_id" INTEGER,
  "category_id" INTEGER,
  "recursive" BOOLEAN default true,
  "create_subcategories" BOOLEAN default false,
  "moderated" BOOLEAN default true,
  PRIMARY KEY ("id")
)
;';
    pwg_query($query);

    $query = '
CREATE TABLE "'.$prefixeTable.'community_pendings" (
  image_id INTEGER NOT NULL,
  state VARCHAR(255) NOT NULL,
  added_on TIMESTAMP NOT NULL,
  validated_by INTEGER
)
;';
    pwg_query($query);
  }
  else
  {
    $query = '
CREATE TABLE "'.$prefixeTable.'community_permissions" (
  "id" INTEGER NOT NULL,
  "type" VARCHAR(255) NOT NULL,
  "group_id" INTEGER,
  "user_id" INTEGER,
  "category_id" INTEGER,
  "recursive" BOOLEAN default true,
  "create_subcategories" BOOLEAN default false,
  "moderated" BOOLEAN default true,
  PRIMARY KEY ("id")
)
;';
    pwg_query($query);

    $query = '
CREATE TABLE "'.$prefixeTable.'community_pendings" (
  image_id INTEGER NOT NULL,
  state VARCHAR(255) NOT NULL,
  added_on TIMESTAMP NOT NULL,
  validated_by INTEGER
)
;';
    pwg_query($query);
  }
}

function plugin_uninstall()
{
  global $prefixeTable;
  
  $query = 'DROP TABLE '.$prefixeTable.'community_permissions;';
  pwg_query($query);

  $query = 'DROP TABLE '.$prefixeTable.'community_pendings;';
  pwg_query($query);
}
?>
