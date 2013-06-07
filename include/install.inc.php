<?php
// +-----------------------------------------------------------------------+
// | Piwigo - a PHP based photo gallery                                    |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2013 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery Team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

function community_install()
{
  global $conf, $prefixeTable;
    
  $query = '
CREATE TABLE IF NOT EXISTS '.$prefixeTable.'community_permissions (
  id int(11) NOT NULL AUTO_INCREMENT,
  type varchar(255) NOT NULL,
  group_id smallint(5) unsigned DEFAULT NULL,
  user_id smallint(5) DEFAULT NULL,
  category_id smallint(5) unsigned DEFAULT NULL,
  user_album enum(\'true\',\'false\') NOT NULL DEFAULT \'false\',
  recursive enum(\'true\',\'false\') NOT NULL DEFAULT \'true\',
  create_subcategories enum(\'true\',\'false\') NOT NULL DEFAULT \'false\',
  moderated enum(\'true\',\'false\') NOT NULL DEFAULT \'true\',
  nb_photos int DEFAULT NULL,
  storage int DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8
;';
  pwg_query($query);

  $query = '
CREATE TABLE IF NOT EXISTS '.$prefixeTable.'community_pendings (
  image_id mediumint(8) unsigned NOT NULL,
  state varchar(255) NOT NULL,
  added_on datetime NOT NULL,
  validated_by smallint(5) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8
;';
  pwg_query($query);

  // column community_permissions.nb_photos added for version 2.5.d
  $result = pwg_query('SHOW COLUMNS FROM `'.$prefixeTable.'community_permissions` LIKE "nb_photos";');
  if (!pwg_db_num_rows($result))
  {     
    pwg_query('ALTER TABLE `'.$prefixeTable .'community_permissions` ADD `nb_photos` INT DEFAULT NULL;');
  }
  
  // column community_permissions.storage added for version 2.5.d
  $result = pwg_query('SHOW COLUMNS FROM `'.$prefixeTable.'community_permissions` LIKE "storage";');
  if (!pwg_db_num_rows($result))
  {     
    pwg_query('ALTER TABLE `'.$prefixeTable .'community_permissions` ADD `storage` INT DEFAULT NULL;');
  }

  // column community_permissions.user_album added for version 2.5.d
  $result = pwg_query('SHOW COLUMNS FROM `'.$prefixeTable.'community_permissions` LIKE "user_album";');
  if (!pwg_db_num_rows($result))
  {     
    pwg_query('ALTER TABLE `'.$prefixeTable .'community_permissions` ADD `user_album` enum(\'true\',\'false\') NOT NULL DEFAULT \'false\' after `category_id`;');
  }

  // column categories.community_user added for version 2.5.d
  $result = pwg_query('SHOW COLUMNS FROM `'.$prefixeTable.'categories` LIKE "community_user";');
  if (!pwg_db_num_rows($result))
  {     
    pwg_query('ALTER TABLE `'.$prefixeTable .'categories` ADD `community_user`  smallint(5) DEFAULT NULL;');
  }

  if (!isset($conf['community']))
  {
    $community_default_config = serialize(
      array(
        'user_albums' => false,
        )
      );
    
    conf_update_param('community', $community_default_config);
    $conf['community'] = $community_default_config;
  }
}
?>