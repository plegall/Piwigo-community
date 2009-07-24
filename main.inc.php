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
include_once (COMMUNITY_PATH.'/include/constants.php');

/* Plugin admin */
add_event_handler('get_admin_plugin_menu_links', 'community_admin_menu');

function community_admin_menu($menu)
{
  array_push(
    $menu,
    array(
      'NAME' => 'Community',
      'URL'  => get_admin_plugin_menu_link(dirname(__FILE__).'/admin.php')
      )
    );

  return $menu;
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
  FROM '.COMMUNITY_TABLE.'
  WHERE user_id = '.$user_id.'
;';
  pwg_query($query);
}

?>
