<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class community_maintain extends PluginMaintain
{
  private $installed = false;

  function __construct($plugin_id)
  {
    parent::__construct($plugin_id);
  }

  function install($plugin_version, &$errors=array())
  {
    global $conf, $prefixeTable;
    
    $query = '
CREATE TABLE IF NOT EXISTS '.$prefixeTable.'community_permissions (
  id int(11) NOT NULL AUTO_INCREMENT,
  type varchar(255) NOT NULL,
  group_id smallint(5) unsigned DEFAULT NULL,
  user_id mediumint(8) unsigned DEFAULT NULL,
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
  validated_by mediumint(8) unsigned DEFAULT NULL
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
      pwg_query('ALTER TABLE `'.$prefixeTable .'categories` ADD `community_user` mediumint unsigned DEFAULT NULL;');
    }

    // Piwigo 2.7 enlarges user ids, from smallint to mediumint
    $to_enlarge_ids = array(
      $prefixeTable.'community_permissions.user_id',
      $prefixeTable.'community_pendings.validated_by',
      $prefixeTable.'categories.community_user',
      );

    foreach ($to_enlarge_ids as $to_enlarge_id)
    {
      list($table, $column) = explode('.', $to_enlarge_id);

      $row = pwg_db_fetch_assoc(pwg_query('SHOW COLUMNS FROM `'.$table.'` LIKE "'.$column.'";'));
      if (!preg_match('/^mediumint/i', $row['Type']))
      {
        $query = 'ALTER TABLE '.$table.' CHANGE '.$column.' '.$column.' MEDIUMINT UNSIGNED DEFAULT NULL;';
        pwg_query($query);
      }
    }

    if (!isset($conf['community']))
    {
      $community_default_config = array(
        'user_albums' => false,
        );
      
      conf_update_param('community', $community_default_config, true);
    }
    
    $this->installed = true;
  }

  function activate($plugin_version, &$errors=array())
  {
    global $prefixeTable;
    
    if (!$this->installed)
    {
      $this->install($plugin_version, $errors);
    }
    
    $query = '
SELECT
    COUNT(*)
  FROM '.$prefixeTable.'community_permissions
;';
    list($counter) = pwg_db_fetch_row(pwg_query($query));
    if (0 == $counter)
    {
      // is there a "Community" album?
      $query = '
SELECT
    id
  FROM '.CATEGORIES_TABLE.'
  WHERE name = \'Community\'
;';
      $result = pwg_query($query);
      while ($row = pwg_db_fetch_assoc($result))
      {
        $category_id = $row['id'];
        break;
      }

      if (!isset($category_id))
      {
        // create an album "Community"
        include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
        $category_info = create_virtual_category('Community');
        $category_id = $category_info['id'];
      }

      single_insert(
        $prefixeTable.'community_permissions',
        array(
          'type' => 'any_registered_user',
          'category_id' => $category_id,
          'recursive' => 'true',
          'create_subcategories' => 'true',
          'moderated' => 'true',
          )
        );
    }

    include_once(dirname(__FILE__).'/include/functions_community.inc.php');
    community_update_cache_key();
  }

  function update($old_version, $new_version, &$errors=array())
  {
    $this->install($new_version, $errors);
  }
  
  function deactivate()
  {
  }

  function uninstall()
  {
    global $prefixeTable;
  
    $query = 'DROP TABLE '.$prefixeTable.'community_permissions;';
    pwg_query($query);
    
    $query = 'DROP TABLE '.$prefixeTable.'community_pendings;';
    pwg_query($query);
    
    $query = 'ALTER TABLE '.$prefixeTable.'categories drop column community_user;';
    pwg_query($query);
    
    // delete configuration
    pwg_query('DELETE FROM `'. CONFIG_TABLE .'` WHERE param IN ("community", "community_cache_key");');
  }
}
?>
