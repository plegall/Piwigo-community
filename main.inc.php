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

global $prefixeTable;

// +-----------------------------------------------------------------------+
// | Define plugin constants                                               |
// +-----------------------------------------------------------------------+

defined('COMMUNITY_ID') or define('COMMUNITY_ID', basename(dirname(__FILE__)));
define('COMMUNITY_PATH' , PHPWG_PLUGINS_PATH.basename(dirname(__FILE__)).'/');
define('COMMUNITY_PERMISSIONS_TABLE', $prefixeTable.'community_permissions');
define('COMMUNITY_PENDINGS_TABLE', $prefixeTable.'community_pendings');
define('COMMUNITY_VERSION', 'auto');

include_once(COMMUNITY_PATH.'include/functions_community.inc.php');

// init the plugin
add_event_handler('init', 'community_init');
/**
 * plugin initialization
 *   - check for upgrades
 *   - unserialize configuration
 *   - load language
 */
function community_init()
{
  global $conf, $user, $pwg_loaded_plugins;

  // apply upgrade if needed
  if (
    COMMUNITY_VERSION == 'auto' or
    $pwg_loaded_plugins[COMMUNITY_ID]['version'] == 'auto' or
    version_compare($pwg_loaded_plugins[COMMUNITY_ID]['version'], COMMUNITY_VERSION, '<')
  )
  {
    // call install function
    include_once(COMMUNITY_PATH.'include/install.inc.php');
    community_install();

    // update plugin version in database
    if ( $pwg_loaded_plugins[COMMUNITY_ID]['version'] != 'auto' and COMMUNITY_VERSION != 'auto' )
    {
      $query = '
UPDATE '. PLUGINS_TABLE .'
SET version = "'. COMMUNITY_VERSION .'"
WHERE id = "'. COMMUNITY_ID .'"';
      pwg_query($query);

      $pwg_loaded_plugins[COMMUNITY_ID]['version'] = COMMUNITY_VERSION;
    }
  }

  // prepare plugin configuration
  $conf['community'] = unserialize($conf['community']);

  // TODO: generate permissions in $user['community_permissions'] if ws.php
  // + remove all calls of community_get_user_permissions related to webservices
  if (!defined('IN_ADMIN') or !IN_ADMIN)
  {
    $user['community_permissions'] = community_get_user_permissions($user['id']);
  }
}

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
    $style.= 'border-radius:10px;';
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

add_event_handler('blockmanager_apply' , 'community_gallery_menu', EVENT_HANDLER_PRIORITY_NEUTRAL+10);
function community_gallery_menu($menu_ref_arr)
{
  global $conf, $user;

  // conditional : depending on community permissions, display the "Add
  // photos" link in the gallery menu
  $user_permissions = $user['community_permissions'];

  if (!$user_permissions['community_enabled'])
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
  global $user, $community;

  if (is_admin())
  {
    return $res;
  }
  
  $community = array('method' => $methodName);

  if ('pwg.images.addSimple' == $community['method'])
  {
    $community['category'] = $params['category'];
  }
  elseif ('pwg.images.add' == $community['method'])
  {
    $community['category'] = $params['categories'];
    $community['md5sum'] = $params['original_sum'];
  }

  // $print_params = $params;
  // unset($print_params['data']);
  // file_put_contents('/tmp/community.log', '['.$methodName.'] '.json_encode($print_params)."\n" ,FILE_APPEND);

  // conditional : depending on community permissions, display the "Add
  // photos" link in the gallery menu
  $user_permissions = community_get_user_permissions($user['id']);

  if (count($user_permissions['upload_categories']) == 0 and !$user_permissions ['create_whole_gallery'])
  {
    return $res;
  }

  // if level of trust is low, then we have to set level to 16

  $methods = array();
  $methods[] = 'pwg.tags.add';
  $methods[] = 'pwg.images.exist';
  $methods[] = 'pwg.images.add';
  $methods[] = 'pwg.images.addSimple';
  $methods[] = 'pwg.images.addChunk';
  $methods[] = 'pwg.images.checkUpload';
  $methods[] = 'pwg.images.checkFiles';
  $methods[] = 'pwg.images.setInfo';

  if (in_array($methodName, $methods))
  {
    $user['status'] = 'admin';
  }

  if ('pwg.categories.add' == $methodName)
  {
    if (in_array($params['parent'], $user_permissions['create_categories'])
        or $user_permissions['create_whole_gallery'])
    {
      $user['status'] = 'admin';
    }
  }

  return $res;
}

add_event_handler('ws_add_methods', 'community_ws_replace_methods', EVENT_HANDLER_PRIORITY_NEUTRAL+5);
function community_ws_replace_methods($arr)
{
  global $conf, $user;
  
  $service = &$arr[0];

  if (is_admin())
  {
    return;
  }

  $user_permissions = community_get_user_permissions($user['id']);
  
  if (count($user_permissions['permission_ids']) == 0)
  {
    return;
  }
  
  // the plugin Community is activated, the user has upload permissions, we
  // use a specific function to list available categories, assuming the user
  // wants to list categories where upload is possible for him
  
  $service->addMethod(
    'pwg.categories.getList',
    'community_ws_categories_getList',
    array(
      'cat_id' =>       array('default'=>0),
      'recursive' =>    array('default'=>false),
      'public' =>       array('default'=>false),
      'tree_output' =>  array('default'=>false),
      'fullname' =>     array('default'=>false),
      ),
    'retrieves a list of categories'
    );
  
  $service->addMethod(
    'pwg.tags.getAdminList',
    'community_ws_tags_getAdminList',
    array(),
    'administration method only'
    );
}

/**
 * returns a list of categories (web service method)
 */
function community_ws_categories_getList($params, &$service)
{
  global $user, $conf;

  if ($params['tree_output'])
  {
    if (!isset($_GET['format']) or !in_array($_GET['format'], array('php', 'json')))
    {
      // the algorithm used to build a tree from a flat list of categories
      // keeps original array keys, which is not compatible with
      // PwgNamedArray.
      //
      // PwgNamedArray is useful to define which data is an attribute and
      // which is an element in the XML output. The "hierarchy" output is
      // only compatible with json/php output.

      return new PwgError(405, "The tree_output option is only compatible with json/php output formats");
    }
  }
  
  $where = array('1=1');
  $join_type = 'LEFT';
  $join_user = $user['id'];

  if (!$params['recursive'])
  {
    if ($params['cat_id']>0)
      $where[] = '(id_uppercat='.(int)($params['cat_id']).'
    OR id='.(int)($params['cat_id']).')';
    else
      $where[] = 'id_uppercat IS NULL';
  }
  else if ($params['cat_id']>0)
  {
    $where[] = 'uppercats '.DB_REGEX_OPERATOR.' \'(^|,)'.
      (int)($params['cat_id'])
      .'(,|$)\'';
  }

  if ($params['public'])
  {
    $where[] = 'status = "public"';
    $where[] = 'visible = "true"';
    
    $join_user = $conf['guest_id'];
  }

  $user_permissions = community_get_user_permissions($user['id']);
  $upload_categories = $user_permissions['upload_categories'];
  if (count($upload_categories) == 0)
  {
    $upload_categories = array(-1);
  }

  $where[] = 'id IN ('.implode(',', $upload_categories).')';

  $query = '
SELECT
    id,
    name,
    permalink,
    uppercats,
    global_rank,
    comment,
    nb_images,
    count_images AS total_nb_images,
    date_last,
    max_date_last,
    count_categories AS nb_categories
  FROM '.CATEGORIES_TABLE.'
   '.$join_type.' JOIN '.USER_CACHE_CATEGORIES_TABLE.' ON id=cat_id AND user_id='.$join_user.'
  WHERE '. implode('
    AND ', $where);

  $result = pwg_query($query);

  $cats = array();
  while ($row = pwg_db_fetch_assoc($result))
  {
    $row['url'] = make_index_url(
        array(
          'category' => $row
          )
      );
    foreach( array('id','nb_images','total_nb_images','nb_categories') as $key)
    {
      $row[$key] = (int)$row[$key];
    }

    if ($params['fullname'])
    {
      $row['name'] = strip_tags(get_cat_display_name_cache($row['uppercats'], null, false));
    }
    else
    {
      $row['name'] = strip_tags(
        trigger_event(
          'render_category_name',
          $row['name'],
          'ws_categories_getList'
          )
        );
    }
    
    $row['comment'] = strip_tags(
      trigger_event(
        'render_category_description',
        $row['comment'],
        'ws_categories_getList'
        )
      );
    
    array_push($cats, $row);
  }
  usort($cats, 'global_rank_compare');

  if ($params['tree_output'])
  {
    return categories_flatlist_to_tree($cats);
  }
  else
  {
    return array(
      'categories' => new PwgNamedArray(
        $cats,
        'category',
        array(
          'id',
          'url',
          'nb_images',
          'total_nb_images',
          'nb_categories',
          'date_last',
          'max_date_last',
          )
        )
      );
  }
}

function community_ws_tags_getAdminList($params, &$service)
{
  $tags = get_available_tags();

  // keep orphan tags
  include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
  $orphan_tags = get_orphan_tags();
  if (count($orphan_tags) > 0)
  {
    $orphan_tag_ids = array();
    foreach ($orphan_tags as $tag)
    {
      $orphan_tag_ids[] = $tag['id'];
    }
    
    $query = '
SELECT *
  FROM '.TAGS_TABLE.'
  WHERE id IN ('.implode(',', $orphan_tag_ids).')
;';
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result))
    {
      $tags[] = $row;
    }
  }

  usort($tags, 'tag_alpha_compare');
  
  return array(
    'tags' => new PwgNamedArray(
      $tags,
      'tag',
      array(
        'name',
        'id',
        'url_name',
        )
      )
    );
}

add_event_handler('sendResponse', 'community_sendResponse');
function community_sendResponse($encodedResponse)
{
  global $community, $user;

  if (!isset($community['method']))
  {
    return;
  }

  if ('pwg.images.addSimple' == $community['method'])
  {
    $response = json_decode($encodedResponse);
    $image_id = $response->result->image_id;
  }
  elseif ('pwg.images.add' == $community['method'])
  {    
    $query = '
SELECT
    id
  FROM '.IMAGES_TABLE.'
  WHERE md5sum = \''.$community['md5sum'].'\'
  ORDER BY id DESC
  LIMIT 1
;';
    list($image_id) = pwg_db_fetch_row(pwg_query($query));
  }
  else
  {
    return;
  }
  
  $image_ids = array($image_id);

  // $category_id is set in the photos_add_direct_process.inc.php included script
  $category_infos = get_cat_info($community['category']);

  // should the photos be moderated?
  //
  // if one of the user community permissions is not moderated on the path
  // to gallery root, then the upload is not moderated. For example, if the
  // user is allowed to upload to events/parties with no admin moderation,
  // then he's not moderated when uploading in
  // events/parties/happyNewYear2011
  $moderate = true;

  $user_permissions = community_get_user_permissions($user['id']);
  $query = '
SELECT
    cp.category_id,
    c.uppercats
  FROM '.COMMUNITY_PERMISSIONS_TABLE.' AS cp
    LEFT JOIN '.CATEGORIES_TABLE.' AS c ON category_id = c.id
  WHERE cp.id IN ('.implode(',', $user_permissions['permission_ids']).')
    AND cp.moderated = \'false\'
;';
  $result = pwg_query($query);
  while ($row = pwg_db_fetch_assoc($result))
  {
    if (empty($row['category_id']))
    {
      $moderate = false;
    }
    elseif (preg_match('/^'.$row['uppercats'].'(,|$)/', $category_infos['uppercats']))
    {
      $moderate = false;
    }
  }
  
  if ($moderate)
  {
    $inserts = array();

    $query = '
SELECT
    id,
    date_available
  FROM '.IMAGES_TABLE.'
  WHERE id IN ('.implode(',', $image_ids).')
;';
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result))
    {
      array_push(
        $inserts,
        array(
          'image_id' => $row['id'],
          'added_on' => $row['date_available'],
          'state' => 'moderation_pending',
          )
        );
    }
    
    mass_inserts(
      COMMUNITY_PENDINGS_TABLE,
      array_keys($inserts[0]),
      $inserts
      );
    
    // the level of a user upload photo with moderation is 16
    $level = 16;
  }
  else
  {
    // the level of a user upload photo with no moderation is 0
    $level = 0;
  }

  $query = '
UPDATE '.IMAGES_TABLE.'
  SET level = '.$level.'
  WHERE id IN ('.implode(',', $image_ids).')
;';
  pwg_query($query);

  invalidate_user_cache();
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

add_event_handler('delete_categories', 'community_delete_category');
function community_delete_category($category_ids)
{
  // $category_ids includes all the sub-category ids
  $query = '
DELETE
  FROM '.COMMUNITY_PERMISSIONS_TABLE.'
  WHERE category_id IN ('.implode(',', $category_ids).')
;';
  pwg_query($query);
  
  community_update_cache_key();
}

add_event_handler('invalidate_user_cache', 'community_refresh_cache_update_time');
function community_refresh_cache_update_time()
{
  community_update_cache_key();
}

add_event_handler('init', 'community_uploadify_privacy_level');
function community_uploadify_privacy_level()
{
  if (script_basename() == 'uploadify' and !is_admin())
  {
    $_POST['level'] = 16;
  }
}
?>
