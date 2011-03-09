<?php
/*
Plugin Name: Community
Version: auto
Description: Non admin users can add photos
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=303
Author: plg
Author URI: http://piwigo.wordpress.com
*/

if (!defined('PHPWG_ROOT_PATH'))
{
  die('Hacking attempt!');
}

define('COMMUNITY_PATH' , PHPWG_PLUGINS_PATH.basename(dirname(__FILE__)).'/');

global $prefixeTable;
define('COMMUNITY_PERMISSIONS_TABLE', $prefixeTable.'community_permissions');
define('COMMUNITY_PENDINGS_TABLE', $prefixeTable.'community_pendings');

include_once(COMMUNITY_PATH.'include/functions_community.inc.php');

/* Plugin admin */
add_event_handler('get_admin_plugin_menu_links', 'community_admin_menu');
function community_admin_menu($menu)
{
  global $page;
  
  $query = '
SELECT
    COUNT(*)
  FROM '.COMMUNITY_PENDINGS_TABLE.'
  WHERE state = \'moderation_pending\'
;';
  $result = pwg_query($query);
  list($page['community_nb_pendings']) = pwg_db_fetch_row($result);

  $name = 'Community';
  if ($page['community_nb_pendings'] > 0)
  {
    $style = 'background-color:#666;';
    $style.= 'color:white;';
    $style.= 'padding:1px 5px;';
    $style.= '-moz-border-radius:10px;';
    $style.= '-webkit-border-radius:10px;';
    $style.= '-border-radius:10px;';
    $style.= 'margin-left:5px;';
    
    $name.= '<span style="'.$style.'">'.$page['community_nb_pendings'].'</span>';

    if (defined('IN_ADMIN') and IN_ADMIN and $page['page'] == 'intro')
    {
      global $template;
      
      $template->set_prefilter('intro', 'community_pendings_on_intro');
      $template->assign(
        array(
          'COMMUNITY_PENDINGS' => sprintf(
            '<a href="%s">'.l10n('%u pending photos').'</a>',
            get_root_url().'admin.php?page=plugin-community-pendings',
            $page['community_nb_pendings']
            ),
          )
        );
    }
  }

  array_push(
    $menu,
    array(
      'NAME' => $name,
      'URL'  => get_root_url().'admin.php?page=plugin-community'
      )
    );

  return $menu;
}

function community_pendings_on_intro($content, &$smarty)
{
  $pattern = '#<li>\s*{\$DB_ELEMENTS\}#ms';
  $replacement = '<li>{$COMMUNITY_PENDINGS}</li><li>{$DB_ELEMENTS}';
  return preg_replace($pattern, $replacement, $content);
}

add_event_handler('init', 'community_load_language');
function community_load_language()
{
  if (!defined('IN_ADMIN') or !IN_ADMIN)
  {
    load_language('admin.lang');
  }
  
  load_language('plugin.lang', COMMUNITY_PATH);
}


add_event_handler('loc_end_section_init', 'community_section_init');
function community_section_init()
{
  global $tokens, $page;
  
  if ($tokens[0] == 'add_photos')
  {
    $page['section'] = 'add_photos';
  }
}

add_event_handler('loc_end_index', 'community_index');
function community_index()
{
  global $page;
  
  if (isset($page['section']) and $page['section'] == 'add_photos')
  {
    include(COMMUNITY_PATH.'add_photos.php');
  }
}

add_event_handler('blockmanager_apply' , 'community_gallery_menu');
function community_gallery_menu($menu_ref_arr)
{
  global $conf, $user;

  // conditional : depending on community permissions, display the "Add
  // photos" link in the gallery menu
  $user_permissions = community_get_user_permissions($user['id']);

  if (count($user_permissions['upload_categories']) == 0 and !$user_permissions ['create_whole_gallery'])
  {
    return;
  }

  $menu = & $menu_ref_arr[0];

  if (($block = $menu->get_block('mbMenu')) != null )
  {
    load_language('plugin.lang', COMMUNITY_PATH);

    array_splice(
      $block->data,
      count($block->data),
      0,
      array(
        '' => array(
          'URL' => make_index_url(array('section' => 'add_photos')),
          'TITLE' => l10n('Upload your own photos'),
          'NAME' => l10n('Upload Photos')
          )
        )
      );
  }
}


add_event_handler('ws_invoke_allowed', 'community_switch_user_to_admin', EVENT_HANDLER_PRIORITY_NEUTRAL, 3);

function community_switch_user_to_admin($res, $methodName, $params)
{
  global $user;

  $methods_of_permission_level[1] = array(
    'pwg.categories.getList',
    'pwg.tags.getAdminList',
    'pwg.tags.add',
    'pwg.images.exist',
    'pwg.images.add',
    'pwg.images.setInfo',
    'pwg.images.addChunk',
    'pwg.images.checkUpload',
    );

  // permission_level 2 has all methods of level 1 + others
  $methods_of_permission_level[2] = array_merge(
    $methods_of_permission_level[1],
    array(
      'pwg.categories.add',
      'pwg.categories.setInfo',
      )
    );
    
  $query = '
SELECT
    permission_level
  FROM '.COMMUNITY_TABLE.'
  WHERE user_id = '.$user['id'].'
;';
  $result = pwg_query($query);
  if (1 == mysql_num_rows($result))
  {
    list($permission_level) = mysql_fetch_row($result);

    if (in_array($methodName, $methods_of_permission_level[$permission_level]))
    {
      $user['status'] = 'admin';
    }
  }

  return $res;
}

add_event_handler('delete_user', 'community_delete_user');
function community_delete_user($user_id)
{
  $query = '
DELETE
  FROM '.COMMUNITY_PERMISSIONS_TABLE.'
  WHERE user_id = '.$user_id.'
;';
  pwg_query($query);

  community_reject_user_pendings($user_id);
}

add_event_handler('invalidate_user_cache', 'community_refresh_cache_update_time');
function community_refresh_cache_update_time()
{
  community_update_cache_key();
}
?>
