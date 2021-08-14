<?php
/*
Plugin Name: Community
Version: auto
Description: Non admin users can add photos
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=303
Author: plg
Author URI: http://piwigo.wordpress.com
Has Settings: true
*/

if (!defined('PHPWG_ROOT_PATH'))
{
  die('Hacking attempt!');
}

global $prefixeTable, $conf;

// +-----------------------------------------------------------------------+
// | Define plugin constants                                               |
// +-----------------------------------------------------------------------+

defined('COMMUNITY_ID') or define('COMMUNITY_ID', basename(dirname(__FILE__)));
define('COMMUNITY_PATH' , PHPWG_PLUGINS_PATH.basename(dirname(__FILE__)).'/');
define('COMMUNITY_PERMISSIONS_TABLE', $prefixeTable.'community_permissions');
define('COMMUNITY_PENDINGS_TABLE', $prefixeTable.'community_pendings');
define('COMMUNITY_DOWNLOAD_LOCAL',   PHPWG_ROOT_PATH . $conf['data_location'] . 'community_downloads/'); // path for zip download action

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
  global $conf, $user;

  // prepare plugin configuration
  $conf['community'] = safe_unserialize($conf['community']);

  // TODO: generate permissions in $user['community_permissions'] if ws.php
  // + remove all calls of community_get_user_permissions related to webservices
  if (!defined('IN_ADMIN') or !IN_ADMIN)
  {
    $user['community_permissions'] = community_get_user_permissions($user['id']);
  }
}

/* Plugin admin */
add_event_handler('loc_begin_admin_page', 'community_loc_begin_admin_page');
function community_loc_begin_admin_page()
{
  global $page;

  $query = '
SELECT
    COUNT(*)
  FROM '.COMMUNITY_PENDINGS_TABLE.'
    JOIN '.IMAGES_TABLE.' ON image_id = id
  WHERE state = \'moderation_pending\'
;';
  $result = pwg_query($query);
  list($page['community_nb_pendings']) = pwg_db_fetch_row($result);
}

add_event_handler('loc_end_intro', 'community_loc_end_intro');
function community_loc_end_intro()
{
  global $page;

  if ($page['community_nb_pendings'] > 0)
  {
    $message = sprintf(
      'Community <i class="icon-picture"></i> <a href="%s">'.l10n('%u pending photos').' <i class="icon-right"></i></a>',
      get_root_url().'admin.php?page=plugin-community-pendings',
      $page['community_nb_pendings']
    );
  
    $page['messages'][] = $message;
  }
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
  
  if (in_array($tokens[0], array('add_photos', 'edit_photos')))
  {
    $page['section'] = $tokens[0];
    $page['is_homepage'] = false;
  }
}

add_event_handler('loc_begin_page_header', 'community_loc_begin_page_header');
function community_loc_begin_page_header()
{
  global $tokens, $page;

  if (isset($tokens[0]) and in_array($tokens[0], array('add_photos', 'edit_photos')))
  {
    $page['body_id'] = 'community_'.$tokens[0];
  }
}

add_event_handler('loc_end_index', 'community_index');
function community_index()
{
  global $page;
  
  if (isset($page['section']) and in_array($page['section'], array('add_photos', 'edit_photos')))
  {
    include(COMMUNITY_PATH.$page['section'].'.php');
  }
}

add_event_handler('blockmanager_apply' , 'community_gallery_menu', EVENT_HANDLER_PRIORITY_NEUTRAL+10);
function community_gallery_menu($menu_ref_arr)
{
  global $conf, $user, $page;

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

    $add_url = make_index_url(array('section' => 'add_photos'));

    $images_added = 0;

    if (isset($page['category']))
    {
      $url_suffix = '&amp;category_id='.$page['category']['id'];
      $add_url.= $url_suffix;
    }

    array_splice(
      $block->data,
      count($block->data),
      0,
      array(
        '' => array(
          'URL' => $add_url,
          'TITLE' => l10n('Upload your own photos'),
          'NAME' => l10n('Upload Photos')
          )
        )
      );

    // edit photos link

    // only admins and normal users can use the edit photos feature
    if (!is_autorize_status(ACCESS_CLASSIC))
    {
      return;
    }

    $edit_url = make_index_url(array('section' => 'edit_photos'));
    $images_added = 0;

    // query suffixes for additional filters: prefilter, tags, qsearch
    // only add suffixes if filter is enabled
    global $prefixeTable;
    if ($user_permissions['filters']['enable']) {

      // prefilters query
      if ($user_permissions['filters']['prefilter']['value']) {
        if (isset($page['section']) && $page['section']=='favorites') {
          $url_suffix = '&amp;favorites';
        } elseif (isset($page['section']) && $page['section']=='recent_pics') {
          $url_suffix = '&amp;recent_pics';
        } elseif (isset($page['col_id'])) {
//            $query = '
//                SELECT image_id
//                FROM '.$prefixeTable.'collections
//                JOIN '.$prefixeTable.'collection_images ON id=col_id
//                WHERE col_id='.$page["col_id"].'
//            ;';
//            $col_ids = query2array($query);
//            $col_ids = array_map(function ($arr) { return $arr['image_id']; }, $col_ids);
//            $s = base64_encode(serialize($col_ids)); // serialize user collection ids for POST retrieval
            $url_suffix = '&amp;col_id='.$page["col_id"];
        }
//add_event_handler('loc_end_index','myfunc'); // User Collections $page variables defined
//function myfunc() {
//    global $prefixeTable, $conf, $page;
//    if (isset($page['col_id'])) {
//        $query = '
//            SELECT image_id 
//            FROM '.$prefixeTable.'collections 
//            JOIN '.$prefixeTable.'collection_images ON id=col_id
//            WHERE col_id='.$page["col_id"].'
//        ;';
//        print_r(query2array($query));
//        echo "<br><br>";
//        print_r(count(query2array($query)));
//    }
//}
      }

      // tags query
      if ($user_permissions['filters']['tags']['value'] and isset($page['tag_ids'])) {
        $s = base64_encode(serialize($page['tag_ids'])); // serialize tag ids for POST retrieval
        $url_suffix = '&amp;tag_ids='.$s;
      }

      // qsearch query
      if ($user_permissions['filters']['q']['value'] and isset($page['qsearch_details'])) {
        $s = base64_encode(serialize($page['qsearch_details']['q'])); // serialize qsearch details for POST retrieval
        $url_suffix = '&amp;q='.$s;
      }

      // search.php query
      if (($user_permissions['filters']['album']['value'] or
        $user_permissions['filters']['q']['value'] or
        $user_permissions['filters']['tags']['value']) and
        isset($page['search'])
      ){
        $url_suffix = '&amp;search_id='.$page['search'];
//        $res = query2array('SELECT * FROM '.SEARCH_TABLE.' WHERE id='.$page["search"].';');
//        print_r(safe_unserialize($res[0]['rules']));
      }
    }

    if (isset($page['category']))
    {
      clearFilters(); // clear all filters when moving to different page
                      // so that user won't have to constantly delete previous filters

      // are there photos added by the current user in this album?
      $query = '
SELECT
    COUNT(*) AS images_count
  FROM '.IMAGES_TABLE.'
    JOIN '.IMAGE_CATEGORY_TABLE.' ON image_id=id
  WHERE `category_id` = '.$page['category']['id'].'
    AND `added_by` = '.$user['id'].'
;';
      $results = query2array($query);
      $images_added = $results[0]['images_count'];

      // $url_suffix generation conditions modified
      // if album filter is enabled, album query will be added even if $images_added=0
      // album filter allows for inclusion of child albums; user may want to edit photos in subalbums
      if ($images_added > 0  or $user_permissions['filters']['album']['value'])
      {
        $edit_url.= $url_suffix;
      }
    }
    elseif (isset($page['tag_ids']) or isset($page['qsearch_details']) or  // tag and qsearch filters
              (isset($page['section']) && $page['section']=='favorites') or //favorites page
              (isset($page['section']) && $page['section']=='recent_pics') or //recent pics page
              (isset($page['search'])) or // search.php
              (isset($page['col_id'])) // user collections page
    ){
      clearFilters();
      $images_added = count($page['items']);
      $edit_url.= isset($url_suffix) ? $url_suffix : ''; // url suffix will not be generated if filter not enabled
    }
    else
    {
      // also clear filters when user navigates to homepage
      if (isset($page['section']) && $page['section']=='categories') {
        clearFilters();
      }

      $query = '
SELECT
    COUNT(*) AS images_count
  FROM '.IMAGES_TABLE.'
  WHERE `added_by` = '.$user['id'].'
;';
      $results = query2array($query);
      $images_added = $results[0]['images_count'];
    }

    // $edit_url shown if user can perform at least one action for the whole gallery (scope filter enabled)
    // or album filter enabled (user may wish to include child albums in the filter)
    if ($images_added > 0 or $user_permissions['filters']['scope']['value'] or $user_permissions['filters']['album']['value']) {
      array_splice(
        $block->data,
        count($block->data),
        0,
        array(
          '' => array(
          'URL' => $edit_url,
          'TITLE' => l10n('Edit your photos'),
          'NAME' => l10n('Edit photos')
          )
        )
      );
    }
  }
}

/**
 * Clears all existing prefilters
 * so that user won't have to constantly delete previous filters
 */
function clearFilters() {
  unset($_SESSION['bulk_manager_filter']['scope']);
  unset($_SESSION['bulk_manager_filter']['prefilter']);
  unset($_SESSION['bulk_manager_filter']['category']);
  unset($_SESSION['bulk_manager_filter']['tags']);
  unset($_SESSION['bulk_manager_filter']['search']);
}


add_event_handler('ws_add_methods', 'community_switch_user_to_admin', EVENT_HANDLER_PRIORITY_NEUTRAL+5);
function community_switch_user_to_admin($arr)
{
  global $user, $community, $conf;

  $service = &$arr[0];

  if (is_admin())
  {
    return;
  }
  
  $community = array('method' => $_REQUEST['method']);

  if ('pwg.images.addSimple' == $community['method'])
  {
    $community['category'] = $_REQUEST['category'];
  }
  elseif ('pwg.images.upload' == $community['method'])
  {
    $community['category'] = $_REQUEST['category'];
  }
  elseif ('pwg.images.add' == $community['method'])
  {
    $community['category'] = $_REQUEST['categories'];
    $community['md5sum'] = $_REQUEST['original_sum'];
  }

  if ('pwg.images.setInfo' == $community['method'])
  {
    // prevent Community users to validate photos with setting level to 0
    unset($_POST['level']);

    // prevent HTML in photo properties
    $infos = array(
      'name',
      'author',
      'comment',
      'date_creation',
      );

    foreach ($infos as $info)
    {
      if (isset($_POST[$info]))
      {
        $_POST[$info] = strip_tags($_POST[$info], '<b><strong><em><i>');
      }
    }

    // security level 2 : deactivate HTML description
    $conf['allow_html_descriptions'] = false;
  }

  // $print_params = $params;
  // unset($print_params['data']);
  // file_put_contents('/tmp/community.log', '['.$methodName.'] '.json_encode($print_params)."\n" ,FILE_APPEND);

  // conditional : depending on community permissions, display the "Add
  // photos" link in the gallery menu
  $user_permissions = community_get_user_permissions($user['id']);

  if (count($user_permissions['upload_categories']) == 0 and !$user_permissions ['create_whole_gallery'])
  {
    return;
  }

  // if level of trust is low, then we have to set level to 16

  $methods = array();
  $methods[] = 'pwg.tags.add';
  $methods[] = 'pwg.images.exist';
  $methods[] = 'pwg.images.add';
  $methods[] = 'pwg.images.addSimple';
  $methods[] = 'pwg.images.addChunk';
  $methods[] = 'pwg.images.upload';
  $methods[] = 'pwg.images.checkUpload';
  $methods[] = 'pwg.images.checkFiles';
  $methods[] = 'pwg.session.getStatus';

  if (in_array($community['method'], array('pwg.images.delete', 'pwg.images.setInfo')))
  {
    $image_ids = $_POST['image_id'];
    if (!is_array($image_ids))
    {
      $image_ids = preg_split(
        '/[\s,;\|]/',
        $_POST['image_id'],
        -1,
        PREG_SPLIT_NO_EMPTY
      );
    }

    $image_ids = array_map('intval', $image_ids);

    $query = '
SELECT
    `id`
  FROM '.IMAGES_TABLE.'
  WHERE `added_by` = '.$user['id'].'
    AND `id` IN ('.join(',', $image_ids).')
;';
    $image_ids = query2array($query, null, 'id');

    if (!is_autorize_status(ACCESS_CLASSIC))
    {
      // in this specific case (ie a user with status guest/generic) we only allow the user
      // to edit/delete photos if they were added in the current session
      if (version_compare(PHPWG_VERSION, '2.10', '>='))
      {
        $query = '
SELECT
    `object_id`
  FROM '.ACTIVITY_TABLE.'
  WHERE `object` = \'photo\'
    AND `action` = \'add\'
    AND `object_id` IN ('.join(',', $image_ids).')
    AND `session_idx` = \''.session_id().'\'
;';
        $image_ids = query2array($query, null, 'object_id');
      }
      else
      {
        $image_ids = array();
      }
    }

    if (count($image_ids) > 0)
    {
      $_POST['image_id'] = join(',', $image_ids);
      $methods[] = $community['method'];
    }
  }

  if (in_array($community['method'], $methods))
  {
    $user['status'] = 'admin';
  }

  if ('pwg.categories.add' == $community['method'])
  {
    if (in_array($_REQUEST['parent'], $user_permissions['create_categories'])
        or $user_permissions['create_whole_gallery'])
    {
      $user['status'] = 'admin';
    }
  }

  return;
}

add_event_handler('ws_add_methods', 'community_add_methods', EVENT_HANDLER_PRIORITY_NEUTRAL+5);
function community_add_methods($arr)
{
  $service = &$arr[0];

  $service->addMethod(
    'community.categories.getList',
    'community_ws_categories_getList',
    array(
      'cat_id' =>       array('default'=>0),
      'recursive' =>    array('default'=>false),
      'public' =>       array('default'=>false),
      'tree_output' =>  array('default'=>false),
      'fullname' =>     array('default'=>false),
      ),
    'retrieves the list of categories where the user has upload permission'
    );

  $service->addMethod(
    'community.session.getStatus',
    'community_ws_session_getStatus',
    array(),
    'Gets information about the current session, related to Community.'
    );

  $service->addMethod(
    'community.images.uploadCompleted',
    'community_ws_images_uploadCompleted',
    array(
      'image_id' => array('flags'=>WS_PARAM_ACCEPT_ARRAY),
      'pwg_token' => array(),
      'category_id' => array('type'=>WS_TYPE_ID),
      ),
    'Notify Piwigo the upload of several photos is completed. Tells if some photos are under moderation.'
    );
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

  if (isset($_REQUEST['faked_by_community']) and $_REQUEST['faked_by_community'] == 'false')
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
        trigger_change(
          'render_category_name',
          $row['name'],
          'ws_categories_getList'
          )
        );
    }
    
    $row['comment'] = strip_tags(
      trigger_change(
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

/**
 * API method
 * Returns info about the current user, related to Community
 * @param mixed[] $params
 */
function community_ws_session_getStatus($params, &$service)
{
  global $user, $conf;

  $res['real_user_status'] = $user['status']; // this is the real user status, not the faked one

  if (is_admin())
  {
    $res['upload_categories_getList_method'] = 'pwg.categories.getAdminList';
  }
  else
  {
    $res['upload_categories_getList_method'] = 'community.categories.getList';
  }

  return $res;
}

/**
 * notify the admins some photos have been uploaded
 * returns the list of photos waiting for moderation
 */
function community_ws_images_uploadCompleted($params, &$service)
{
  global $user, $conf;

  if (get_pwg_token() != $params['pwg_token'])
  {
    return new PwgError(403, 'Invalid security token');
  }

  if (!is_array($params['image_id']))
  {
    $params['image_id'] = preg_split(
      '/[\s,;\|]/',
      $params['image_id'],
      -1,
      PREG_SPLIT_NO_EMPTY
      );
  }
  $params['image_id'] = array_map('intval', $params['image_id']);

  $image_ids = array();
  foreach ($params['image_id'] as $image_id)
  {
    if ($image_id > 0)
    {
      $image_ids[] = $image_id;
    }
  }

  if (count($image_ids) == 0)
  {
    return;
  }

  $query = '
SELECT
    id,
    level,
    added_by,
    state,
    notified_on
  FROM '.IMAGES_TABLE.'
    LEFT JOIN '.COMMUNITY_PENDINGS_TABLE.' ON image_id = id
  WHERE id IN ('.implode(',', $image_ids).')
;';

  $images = query2array($query);

  $to_notify = array();
  $to_notify_ids = array();
  $pending = array();
  foreach ($images as $image)
  {
    if (empty($image['notified_on']))
    {
      $to_notify[] = $image;
      $to_notify_ids[] = $image['id'];
    }

    if ('moderation_pending' == $image['state'])
    {
      $pending[] = $image;
    }
  }

  if (count($to_notify) > 0 and (!isset($conf['community_notify_admins']) or $conf['community_notify_admins']))
  {
    global $logger;
    $logger->debug(__FUNCTION__." : enter notification part");
    // time to notify admins
    include_once(PHPWG_ROOT_PATH.'include/functions_mail.inc.php');

    $category_infos = get_cat_info($params['category_id']);

    $keyargs_content = array(
      get_l10n_args('Hi administrators,', ''),
      get_l10n_args('', ''),
      get_l10n_args('Album: %s', get_cat_display_name($category_infos['upper_names'], null, false)),
      get_l10n_args('User: %s', $user['username']),
      get_l10n_args('Email: %s', $user['email']),
      );

    if (count($pending))
    {
      $keyargs_content[] = get_l10n_args('', '');

      array_push(
        $keyargs_content,
        get_l10n_args(
          'Validation page: %s',
          get_absolute_root_url().'admin.php?page=plugin-community-pendings'
          )
        );
    }

    pwg_mail_notification_admins(
      get_l10n_args('%d photos uploaded by %s', array(count($to_notify), $user['username'])),
      $keyargs_content,
      false
      );

    $query = '
UPDATE '.COMMUNITY_PENDINGS_TABLE.'
  SET notified_on = NOW()
  WHERE image_id IN ('.implode(',', $to_notify_ids).')
;';
    pwg_query($query);
  }

  return array('pending' => $pending);
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
  elseif ('pwg.images.upload' == $community['method'])
  {
    $response = json_decode($encodedResponse);

    if (isset($response->result->image_id))
    {
      $image_id = $response->result->image_id;
    }
    else
    {
      return;
    }
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
        'state' =>   ($moderate ? 'moderation_pending' : 'validated'),
        )
      );
  }

  mass_inserts(
    COMMUNITY_PENDINGS_TABLE,
    array_keys($inserts[0]),
    $inserts
    );

  $query = '
UPDATE '.IMAGES_TABLE.'
  SET level = '.($moderate ? 16 : 0).'
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

add_event_handler('delete_elements', 'community_delete_elements');
function community_delete_elements($image_ids)
{
  $query = '
DELETE
  FROM '.COMMUNITY_PENDINGS_TABLE.'
  WHERE image_id IN ('.implode(',', $image_ids).')
;';
  pwg_query($query);
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

// +-----------------------------------------------------------------------+
// | User Albums                                                           |
// +-----------------------------------------------------------------------+

add_event_handler('loc_end_cat_modify', 'community_set_prefilter_cat_modify', 50);
// add_event_handler('loc_begin_admin_page', 'community_cat_modify_submit', 45);

// Change the variables used by the function that changes the template
// add_event_handler('loc_begin_admin_page', 'community_cat_modify_add_vars_to_template');

function community_set_prefilter_cat_modify()
{
	global $template, $conf, $category;

  if (!isset($conf['community']['user_albums']) or !$conf['community']['user_albums'])
  {
    return;
  }
  
  $template->set_prefilter('album_properties', 'community_cat_modify_prefilter');

  $query = '
SELECT
    '.$conf['user_fields']['id'].' AS id,
    '.$conf['user_fields']['username'].' AS username
  FROM '.USERS_TABLE.' AS u
    INNER JOIN '.USER_INFOS_TABLE.' AS uf ON uf.user_id = u.'.$conf['user_fields']['id'].'
  WHERE uf.status IN (\'normal\',\'generic\')
;';
  $result = pwg_query($query);
  $users = array();
  while ($row = pwg_db_fetch_assoc($result))
  {
    $users[$row['id']] = $row['username'];
  }

  $template->assign(
    array(
      'community_user_options' => $users,
      'community_user_selected' => $category['community_user'],
      )
    );
}

function community_cat_modify_prefilter($content, &$smarty)
{
	$search = "#<strong>{'Name'#";

	// We use the <tr> from the Creation date, and give them a new <tr>
	$replacement = '<strong>(Community) {\'Album of user\'|@translate}</strong>
		<br>
			<select name="community_user">
				<option value="">--</option>
				{html_options options=$community_user_options selected=$community_user_selected}
			</select>
      <em>{\'a user can own only one album\'|@translate}</em>
		</p>
	
	</p>
  <p>
		<strong>{\'Name\'';

  return preg_replace($search, $replacement, $content);
}

add_event_handler('loc_begin_cat_modify', 'community_cat_modify_submit');
function community_cat_modify_submit()
{
  global $category, $conf;

  if (!isset($conf['community']['user_albums']) or !$conf['community']['user_albums'])
  {
    return;
  }
  
  if (isset($_POST['community_user']))
  {
    // echo '<pre>'; print_r($_POST); echo '</pre>'; exit();
    // only one album for each user, first we remove ownership on any other album
    single_update(
      CATEGORIES_TABLE,
      array('community_user' => null),
      array('community_user' => $_POST['community_user'])
      );

    // then we give the album to the user
    single_update(
      CATEGORIES_TABLE,
      array('community_user' => $_POST['community_user']),
      array('id' => $category['id'])
      );
  }
}
?>
