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

    include_once(PHPWG_ROOT_PATH.'admin/include/functions_install.inc.php');
    execute_sqlfile(dirname(__FILE__).'/sql/community-structure-'.$conf['dblayer'].'.sql',
                    'piwigo_', // DEFAULT_PREFIX_TABLE is not easily available from here
                    $prefixeTable,
                    $conf['dblayer']
    );

    // dblayer specific installation
    $dblayer_install = 'install_'.$conf['dblayer'];
    if (method_exists($this, $dblayer_install)) {
        $this->$dblayer_install();
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

  // protected to avoid external calls
  protected function install_mysql()
  {
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
          if ($row['Type'] != 'mediumint')
          {
              $query = 'ALTER TABLE '.$table.' CHANGE '.$column.' '.$column.' MEDIUMINT UNSIGNED DEFAULT NULL;';
              pwg_query($query);
          }
      }
  }

  protected function install_mysqli()
  {
      $this->install_mysql();
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
    conf_delete_param(array('community', 'community_cache_key'));
  }
}
?>
