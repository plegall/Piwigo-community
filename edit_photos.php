<?php
// +-----------------------------------------------------------------------+
// | Piwigo - a PHP based photo gallery                                    |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2011 Piwigo Team                  http://piwigo.org |
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

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

global $template, $conf, $user, $page;

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

$user_permissions = $user['community_permissions'];

// +-----------------------------------------------------------------------+
// | Checks                                                                |
// +-----------------------------------------------------------------------+

check_status(ACCESS_CLASSIC);

if (!empty($_POST))
{
  check_pwg_token();
}

check_input_parameter('del_tags', $_POST, true, PATTERN_ID);
check_input_parameter('category_id', $_GET, false, PATTERN_ID);

// +-----------------------------------------------------------------------+
// | photo set                                                             |
// +-----------------------------------------------------------------------+

// if scope permission set to "whole gallery" for at least one action
// remove "added by user" condition (ie. allow user to view all photos)
if ($user_permissions['filters']['scope']['value']) {
  $query = '
  SELECT
      id
    FROM '.IMAGES_TABLE.'
      JOIN '.IMAGE_CATEGORY_TABLE.' ON image_id=id
    WHERE `category_id` NOT IN (' .$user["forbidden_categories"]. ')
  ;';
    $photo_set = query2array($query, null, 'id');
} else { // user only allowed to edit (and thus view) photos posted by user
  $query = '
  SELECT
      id
    FROM '.IMAGES_TABLE.'
    WHERE added_by = '.$user['id'].'
  ;';
    $photo_set = query2array($query, null, 'id');

  if (isset($_GET['category_id']))
  {
    $query = '
  SELECT
      image_id
    FROM '.IMAGE_CATEGORY_TABLE.'
    WHERE category_id = '.$_GET['category_id'].'
  ;';
    $photo_set = array_intersect($photo_set, query2array($query, null, 'image_id'));
  }
}


/* initialize current selection of filters */

// if filters present, retain selected values when changing page or display
$category_options_selected = isset($_SESSION['bulk_manager_filter']['category']) ? $_SESSION['bulk_manager_filter']['category'] : null;
$scope_selected = isset($_SESSION['bulk_manager_filter']['scope']) ? $_SESSION['bulk_manager_filter']['scope'] : 'all'; // default to show all photos

// enable scope filter if permitted
if ($user_permissions['filters']['scope']) {
  $_SESSION['bulk_manager_filter']['scope'] = 'all'; // default
}

// filters from url query
if ($user_permissions['filters']['enable']) {

    // init album filter
    if (isset($_GET['category_id']))
    {
      $_SESSION['bulk_manager_filter']['category'] = $_GET['category_id'];
      $category_options_selected = $_GET['category_id']; // set album filter option based on page
    }

    // init tag filter
    elseif (isset($_GET['tag_ids']))
    {
      $url_tag_ids = unserialize(base64_decode($_GET['tag_ids']));
      $_SESSION['bulk_manager_filter']['tags'] = $url_tag_ids;
      $_SESSION['bulk_manager_filter']['tag_mode'] = 'AND';  // default
    }

    // init qsearch filter
    elseif (isset($_GET['q']))
    {
      $url_q = unserialize(base64_decode($_GET['q']));
      $_SESSION['bulk_manager_filter']['search']['q'] = $url_q;
    }

    // init search filters: album, tag, and/or qsearch
    elseif (isset($_GET['search_id']))
    {
      $search_id = $_GET['search_id'];
      $res = query2array('SELECT * FROM '.SEARCH_TABLE.' WHERE id='.$search_id.';');
//      echo "<pre>"; print_r(safe_unserialize($res[0]['rules'])); echo "<post>";
//      $url_q = unserialize(base64_decode($_GET['q']));
//      $_SESSION['bulk_manager_filter']['search']['q'] = $url_q;
    }

    // init prefilter:favorites
    elseif (isset($_GET['favorites']))
    {
      $_SESSION['bulk_manager_filter']['prefilter'] = 'favorites';
    }

    // init prefilter:recent
    elseif (isset($_GET['recent_pics']))
    {
      $_SESSION['bulk_manager_filter']['prefilter'] = 'last_import';
    }

    $page['cat_elements_id'] = (getFilteredSet($photo_set) !== null) ? getFilteredSet($photo_set) : $photo_set; // get filtered set if not null

} else { // if filters not enabled
    $page['cat_elements_id'] = $photo_set;
}

// +-----------------------------------------------------------------------+
// |                            current selection                          |
// +-----------------------------------------------------------------------+

$collection = array();
if (isset($_POST['nb_photos_deleted']))
{
  check_input_parameter('nb_photos_deleted', $_POST, false, '/^\d+$/');

  // let's fake a collection (we don't know the image_ids so we use "null", we only
  // care about the number of items here)
  $collection = array_fill(0, $_POST['nb_photos_deleted'], null);
}
else if (isset($_POST['setSelected']))
{
  $collection = $page['cat_elements_id'];
}
else if (isset($_POST['selection']))
{
  $collection = $_POST['selection'];
}

// +-----------------------------------------------------------------------+
// | global mode form submission                                           |
// +-----------------------------------------------------------------------+

$base_url = make_index_url(array('section' => 'edit_photos'));
// only activate when filters not enabled so that album filter can be properly removed
if (isset($_GET['category_id']) and !$user_permissions['filters']['enable'])
{
  $base_url .= '&amp;category_id='.$_GET['category_id'];
}
$redirect_url = $base_url;

// filters from form submission
if (isset($_POST['submitFilter']))
{
//  echo '<pre>'; print_r($_POST); echo '</pre>';
  unset($_REQUEST['start']); // new photo set must reset the page

  // scope
  if (isset($_POST['filter_scope_use']))
  {
    $_SESSION['bulk_manager_filter']['scope'] = $_POST['filter_scope'];
    $scope_selected = $_POST['filter_scope'];
  }

  // prefilter
  if (isset($_POST['filter_prefilter_use']))
  {
    $_SESSION['bulk_manager_filter']['prefilter'] = $_POST['filter_prefilter'];
  }
  else unset($_SESSION['bulk_manager_filter']['prefilter']);

  // album
  if (isset($_POST['filter_category_use']))
  {
    check_input_parameter('filter_category', $_POST, false, PATTERN_ID);
    if (isset($_POST['filter_category'])) {
      $_SESSION['bulk_manager_filter']['category'] = $_POST['filter_category'];
      $category_options_selected = $_POST['filter_category'];
    }
    if (isset($_POST['filter_category_recursive']))
    {
      $_SESSION['bulk_manager_filter']['category_recursive'] = true;
    }
    else unset($_SESSION['bulk_manager_filter']['category_recursive']);
  }
  else unset($_SESSION['bulk_manager_filter']['category']);

  // tags
  if (isset($_POST['filter_tags_use']))
  {
    if (isset($_POST['filter_tags'])) {
        $_SESSION['bulk_manager_filter']['tags'] = get_tag_ids($_POST['filter_tags'], false);
    }
    if (isset($_POST['tag_mode']) and in_array($_POST['tag_mode'], array('AND', 'OR')))
    {
      $_SESSION['bulk_manager_filter']['tag_mode'] = $_POST['tag_mode'];
    }
  }
  else unset($_SESSION['bulk_manager_filter']['tags']);

  // qsearch
  if (isset($_POST['filter_search_use']))
  {
    $_SESSION['bulk_manager_filter']['search']['q'] = $_POST['q'];
  }
  else unset($_SESSION['bulk_manager_filter']['search']);

  $_SESSION['bulk_manager_filter'] = trigger_change('batch_manager_register_filters', $_SESSION['bulk_manager_filter']);
  $page['cat_elements_id'] = (getFilteredSet($photo_set) !== null) ? getFilteredSet($photo_set) : $photo_set; // get filtered set if not null

}


if (isset($_POST['submit']))
{
  // if the user tries to apply an action, it means that there is at least 1
  // photo in the selection
  if (count($collection) == 0)
  {
    $page['errors'][] = l10n('Select at least one photo');
  }

  $action = $_POST['selectAction'];
  $redirect = false;

  if ('add_tags' == $action)
  {
    if (empty($_POST['add_tags']))
    {
      $page['errors'][] = l10n('Select at least one tag');
    }
    else
    {
      $tag_ids = get_tag_ids($_POST['add_tags']);
      add_tags($tag_ids, $collection);
      $page['infos'][] = l10n('Tags added'); // fixed
    }
  }

  // TODO, finish to implement del_tags
  else if ('del_tags' == $action)
  {
    if (isset($_POST['del_tags']) and count($_POST['del_tags']) > 0)
    {
      $taglist_before = get_image_tag_ids($collection);

      $query = ' 
DELETE
  FROM '.IMAGE_TAG_TABLE.'
  WHERE image_id IN ('.implode(',', $collection).')
    AND tag_id IN ('.implode(',', $_POST['del_tags']).')
;';
      pwg_query($query);

      $taglist_after = get_image_tag_ids($collection);
      $images_to_update = compare_image_tag_lists($taglist_before, $taglist_after);
      update_images_lastmodified($images_to_update);

      $page['infos'][] = l10n('Tags removed'); // fixed
    }
    else
    {   
      $page['errors'][] = l10n('Select at least one tag');
    }   
  }

  // delete
  else if ('delete' == $action)
  {
    if (isset($_POST['confirm_deletion']) and 1 == $_POST['confirm_deletion'])
    {
      // now done with ajax calls, with blocks
      // $deleted_count = delete_elements($collection, true);
      if (count($collection) > 0)
      {
        $_SESSION['page_infos'][] = l10n_dec(
          '%d photo was deleted', '%d photos were deleted',
          count($collection)
          );

        $redirect = true;
      }
      else
      {
        $page['errors'][] = l10n('No photo can be deleted');
      }
    }
    else
    {
      $page['errors'][] = l10n('You need to confirm deletion');
    }
  }

  // move to album
  else if ('move' == $action)
  {
    move_images_to_categories($collection, array($_POST['associate']));

    $_SESSION['page_infos'] = array(
      l10n('Information data registered in database')
      );

    update_images_lastmodified($collection);

    if (isset($_SESSION['bulk_manager_filter']['category'])
        and $_POST['move'] != $_SESSION['bulk_manager_filter']['category'])
    {
      $redirect = true;
    }
  }

  // add to favourites
  else if ('add_fav' == $action)
  {
    foreach ($collection as $img_id) {
      if (!in_favorites($img_id)) {
        $query = '
    INSERT INTO '.FAVORITES_TABLE.' (image_id,user_id)
    VALUES ('.$img_id.','.$user['id'].')
  ;';
  pwg_query($query);
      }
    }
    $page['infos'] = [l10n('Photos added to favorites')];
  }

  // remove from favourites
  else if ('del_fav' == $action)
  {
    foreach ($collection as $img_id) {
      if (in_favorites($img_id)) {
  $query = '
    DELETE FROM '.FAVORITES_TABLE.'
    WHERE user_id=' .$user['id']. ' AND image_id='.$img_id.'
  ;';
  pwg_query($query);
      }
    }
    $_SESSION['page_infos'] = [l10n('Photos removed from favorites')];
    $redirect = true;
  }

  // download single or batch
  else if ('download' == $action)
  {
    // download files individually by clicking on img in $page[infos] banner
    if ($_POST['download_type'] == 'single') {
      $datas = array();
      $str_click_download = l10n('Click the image to download it');
      $str = '<style>img.download-single:hover { border: 1px solid rgb(231,231,231) !important; }</style>';
      $str .= '<div style="margin-bottom:1em">' .$str_click_download. '</div>';
      foreach ($collection as $img_id) {
        $query = 'SELECT * FROM ' . IMAGES_TABLE . ' WHERE id = ' . $img_id . ';';
        $img = pwg_db_fetch_assoc(pwg_query($query));

        //Check if the file path exists or not
        if (file_exists($img['path'])) {
          $str .= '<a style="background-color:transparent; padding:0;" 
                      title="' .$img["file"]. '" 
                      href="' .$img['path']. '" 
                      download="' .$img['file']
                  ;

          $src_image = new SrcImage($img);
          $img_url = DerivativeImage::url(IMG_XXSMALL,$src_image);
          $str .= '"><img class="download-single" 
                          style="margin:0 5px 5px 0; border:1px solid rgb(128,128,128); border-radius:4px;" 
                          height="70px" 
                          src="' .$img_url .'"
                    ></a></span>';
        }
      } // foreach $collection as $img_id

      array_push($page['infos'], $str);

    // download files in ZIP file. ZIP immediately deletes itself upon sending headers
    } elseif ($_POST['download_type'] == 'all') {

      // check directory write permissions
      if (!is_writable(COMMUNITY_DOWNLOAD_LOCAL)) {
        $page['errors'][] = l10n('Community download directory has no write access. Please contact your administrator.');
      } else {
        // Remove ALL print_r or echo statements
        // Create ZIP file
        $zip = new ZipArchive();
        $download_file_id = date("Y-m-d");
        $download_file_name = preg_replace('/[[:space:]]+/', '-', $conf['gallery_title']) . '-download-' . $download_file_id;
        $zip_file = COMMUNITY_DOWNLOAD_LOCAL .$download_file_name. '.zip';
        if ($zip->open($zip_file, ZipArchive::CREATE|ZipArchive::OVERWRITE)!==TRUE) {
          exit("cannot open <$zip_file>\n");
        }

        // Add files
        foreach ($collection as $img_id) {
          $query = 'SELECT * FROM ' . IMAGES_TABLE . ' WHERE id = ' . $img_id . ';';
          $img = pwg_db_fetch_assoc(pwg_query($query));
          $path = (string)($img['path']);
          $zip->addFile($path, $img['file']);
        }

        $zip->close();

        // Download zip
        if (file_exists($zip_file)) {
          $u_download = get_root_url().COMMUNITY_PATH . 'download.php?id=' . $download_file_id;
          $template->block_footer_script(null, 'setTimeout("document.location.href = \''.$u_download.'\';", 1000);');

          $page['infos'][] = l10n('The archive is downloading...');
        }

      } // end if directory writable
    } // end if download_type
  } // end elseif download==action

  invalidate_user_cache();

  if ($redirect)
  {
    redirect($redirect_url);
  }
}

// +-----------------------------------------------------------------------+
// | first element to display                                              |
// +-----------------------------------------------------------------------+

// $page['start'] contains the number of the first element in its
// category. For exampe, $page['start'] = 12 means we must show elements #12
// and $page['nb_images'] next elements

if (!isset($_REQUEST['start'])
    or !is_numeric($_REQUEST['start'])
    or $_REQUEST['start'] < 0
    or (isset($_REQUEST['display']) and 'all' == $_REQUEST['display']))
{
  $page['start'] = 0;
}
else
{
  $page['start'] = $_REQUEST['start'];
}

// +-----------------------------------------------------------------------+
// | global mode thumbnails                                                |
// +-----------------------------------------------------------------------+

// how many items to display on this page
if (!empty($_GET['display']))
{
  if ('all' == $_GET['display'])
  {
    $page['nb_images'] = count($page['cat_elements_id']);
  }
  else
  {
    $page['nb_images'] = intval($_GET['display']);
  }
}
elseif (in_array($conf['batch_manager_images_per_page_global'], array(20, 50, 100)))
{
  $page['nb_images'] = $conf['batch_manager_images_per_page_global'];
}
else
{
  $page['nb_images'] = 20;
}

$nb_thumbs_page = 0;

if (count($page['cat_elements_id']) > 0)
{
  $nav_bar = create_navigation_bar(
    $base_url,
    count($page['cat_elements_id']),
    $page['start'],
    $page['nb_images']
    );
  $template->assign('navbar', $nav_bar);

  $is_category = false;
  if (isset($_SESSION['bulk_manager_filter']['category'])
      and !isset($_SESSION['bulk_manager_filter']['category_recursive']))
  {
    $is_category = true;
  }

  $query = '
SELECT id,path,representative_ext,file,filesize,level,name,width,height,rotation
  FROM '.IMAGES_TABLE;

  if ($is_category)
  {
    $category_info = get_cat_info($_SESSION['bulk_manager_filter']['category']);

    $conf['order_by'] = $conf['order_by_inside_category'];
    if (!empty($category_info['image_order']))
    {
      $conf['order_by'] = ' ORDER BY '.$category_info['image_order'];
    }

    $query.= '
    JOIN '.IMAGE_CATEGORY_TABLE.' ON id = image_id';
  }

  $query.= '
  WHERE id IN ('.implode(',', $page['cat_elements_id']).')';

  if ($is_category)
  {
    $query.= '
    AND category_id = '.$_SESSION['bulk_manager_filter']['category'];
  }

  $query.= '
  '.$conf['order_by'].'
  LIMIT '.$page['nb_images'].' OFFSET '.$page['start'].'
;';
  $result = pwg_query($query);

  $thumb_params = ImageStdParams::get_by_type(IMG_SQUARE);
  // template thumbnail initialization
  while ($row = pwg_db_fetch_assoc($result))
  {
    $nb_thumbs_page++;
    $src_image = new SrcImage($row);

    $ttitle = render_element_name($row);
    if ($ttitle != get_name_from_file($row['file']))
    {
      $ttitle.= ' ('.$row['file'].')';
    }

    $ttitle.= ', '.$row['width'].'x'.$row['height'].' pixels, '.sprintf('%.2f', $row['filesize']/1024).'MB';

    $template->append(
      'thumbnails', array_merge($row,
      array(
        'thumb' => new DerivativeImage($thumb_params, $src_image),
        'TITLE' => $ttitle,
        'FILE_SRC' => DerivativeImage::url(IMG_LARGE, $src_image),
        'U_JUMPTO' => make_picture_url(array('image_id' => $row['id'])),
        )
      ));
  }
  $template->assign('thumb_params', $thumb_params);
}

// construct tag list
$query = '
SELECT
    id,
    name
  FROM '.TAGS_TABLE.'
;';
$tag_list = query2array($query, 'id', 'name');

$template->assign(array(
  'nb_thumbs_page' => $nb_thumbs_page,
  'nb_thumbs_set' => count($page['cat_elements_id']),
  'tag_list' => $tag_list,
  ));

// +-----------------------------------------------------------------------+
// | display page                                                          |
// +-----------------------------------------------------------------------+

$template->set_template_dir(realpath(dirname(__FILE__)));
$template->set_filename('edit_photos', 'edit_photos.tpl');

if (count($page['errors']) != 0)
{
  $template->assign('errors', $page['errors']);
}

if (count($page['infos']) != 0)
{
  $template->assign('infos', $page['infos']);
}

$title = l10n('Edit Photos');

// WARNING the 2 following instructions have no effect because we're too late
// compared to index.php and include/page_header.php. We need to change the
// execution order if we want to force the id of the html body.
$page['is_external'] = true;
$page['body_id'] = 'theCommunityEditPhotosPage';

// $base_url already contains query /edit_photos
// original U_DISPLAY appended an extra %2Fedit_photos query on top of the existing /edit_photos query
$template->assign(
  array(
    'TITLE' => '<a href="'.get_gallery_home_url().'">'.l10n('Home').'</a>'.$conf['level_separator'].$title,
    'selection' => $collection,
    'all_elements' => $page['cat_elements_id'],
    'START' => $page['start'],
    'PWG_TOKEN' => get_pwg_token(),
    'U_DISPLAY'=> get_root_url().get_query_string_diff(array('display')),
    'F_ACTION'=>$base_url,
    'CACHE_KEYS' => get_admin_client_cache_keys(array('tags')),
  )
);

if (count($page['cat_elements_id']) > 0)
{
  // remove tags
  $template->assign('associated_tags', get_common_tags($page['cat_elements_id'], -1));
}

if (isset($_GET['category_id']))
{
  $query = '
SELECT
    id,
    uppercats
  FROM '.CATEGORIES_TABLE.'
  WHERE id = '.$_GET['category_id'].'
;';
  $categories = query2array($query, 'id', 'uppercats');

  if (isset($categories[ $_GET['category_id'] ]))
  {
    $template->assign('EDIT_ALBUM', get_cat_display_name_cache($categories[ $_GET['category_id'] ], null));
  }
  else
  {
    $template->assign('EDIT_ALBUM', 'unknown album');
  }
}

// Set prefilters
$prefilters = array(
  array('ID' => 'favorites', 'NAME' => l10n('Your favorites')),
  array('ID' => 'last_import', 'NAME' => l10n('Last import')),
  array('ID' => 'no_album', 'NAME' => l10n('With no album').' ('.l10n('Orphans').')'),
  array('ID' => 'no_tag', 'NAME' => l10n('With no tag')),
);

$prefilters = trigger_change('get_batch_manager_prefilters', $prefilters);

// Sort prefilters by localized name.
usort($prefilters, function ($a, $b) {
  return strcmp(strtolower($a['NAME']), strtolower($b['NAME']));
});

if (!isset($_SESSION['bulk_manager_filter'])) {
    $_SESSION['bulk_manager_filter'] = array();
}

// Initialize list of albums for album filter
// Albums must be authorized to user
$query = '
SELECT id,name,uppercats,global_rank
  FROM ' .CATEGORIES_TABLE. '
  WHERE id NOT IN (' .$user["forbidden_categories"]. ')
;';

display_select_cat_wrapper(
  $query,
  array(),
  'category_options'
);

// Parent categories for album creation in "move to album" action
$create_categories = $user_permissions['create_categories'];
if (count($user_permissions['create_categories']) == 0)
{
  $create_categories = array(-1);
}

$query = '
SELECT id,name,uppercats,global_rank
  FROM '.CATEGORIES_TABLE.'
  WHERE id IN ('.implode(',', $create_categories).')
;';

display_select_cat_wrapper(
  $query,
  array(),
  'category_parent_options'
  );

// Tags filter values
$filter_tags = array();
if (!empty($_SESSION['bulk_manager_filter']['tags']))
{
  $query = '
SELECT
    id,
    name
  FROM '.TAGS_TABLE.'
  WHERE id IN ('.implode(',', $_SESSION['bulk_manager_filter']['tags']).')
;';
  $filter_tags = get_taglist($query);
}

// user ability to create albums
// referenced in "move to album" action
$create_subcategories = false;
if ($user_permissions['create_whole_gallery'] or count($user_permissions['create_categories']) > 0)
{
  $create_subcategories = true;
}

$template->assign(array(
  'scope_selected' => $scope_selected,
  'prefilters' => $prefilters,
  'category_options_selected' => $category_options_selected,
  'filter_tags' => $filter_tags,
  'filter' => $_SESSION['bulk_manager_filter'],
  'user_filters' => $user_permissions['filters'],
  'user_actions' => $user_permissions['actions'],
  'create_subcategories' => $create_subcategories,
  'create_whole_gallery' => $user_permissions['create_whole_gallery'],
  ));

$template->clear_assign(array('U_MODE_POSTED', 'U_MODE_CREATED')); // removed by rachung

$template->assign_var_from_handle('PLUGIN_INDEX_CONTENT_BEGIN', 'edit_photos');


// +-----------------------------------------------------------------------+
// | functions                                                             |
// +-----------------------------------------------------------------------+

/**
 * Get set based on applied filters.
 * Simplified from batch manager filter
 * @return  filtered set as array if filter exists else null
 */
function getFilteredSet($photo_set) {
    global $user;

    // echo '<pre>'; print_r($_SESSION['bulk_manager_filter']); echo '</pre>';

    // depending on the current filter (in session), we find the appropriate photos
    $filterExists = FALSE; // check if filter exists
    $filter_sets = array();

    // filter set by scope
    if (isset($_SESSION['bulk_manager_filter']['scope']))
    {
      $filterExists = TRUE;
      if ($_SESSION['bulk_manager_filter']['scope'] == 'user') {
          $query = '
      SELECT id
        FROM '.IMAGES_TABLE.'
        WHERE added_by = '.$user['id'].'
      ;';
      } else {
          $query = '
      SELECT id
        FROM '.IMAGES_TABLE.'
      ;';
      }
      $filter_sets[] = query2array($query, null, 'id');
    }

    // filter set by predefinitions
    if (isset($_SESSION['bulk_manager_filter']['prefilter']))
    {
      $filterExists = TRUE;
      switch ($_SESSION['bulk_manager_filter']['prefilter'])
      {
      case 'favorites':
        $query = '
    SELECT image_id
      FROM '.FAVORITES_TABLE.'
      WHERE user_id = '.$user['id'].'
    ;';
        $filter_sets[] = query2array($query, null, 'image_id');

        break;

      case 'last_import':
        $query = '
    SELECT MAX(date_available) AS date
      FROM '.IMAGES_TABLE.'
    ;';
        $row = pwg_db_fetch_assoc(pwg_query($query));
        if (!empty($row['date']))
        {
          // remove images in forbidden categories
          $query = '
    SELECT id
      FROM '.IMAGES_TABLE.'
        JOIN '.IMAGE_CATEGORY_TABLE.' ON image_id=id
      WHERE date_available BETWEEN '.pwg_db_get_recent_period_expression(1, $row['date']).' AND \''.$row['date'].'\'
        AND `category_id` NOT IN (' .$user["forbidden_categories"]. ')
    ;';
          $filter_sets[] = query2array($query, null, 'id');

        }

        break;

      case 'no_album':
        $filter_sets[] = get_orphans();
        break;

      case 'no_tag':
        $query = '
    SELECT
        id
      FROM '.IMAGES_TABLE.'
        LEFT JOIN '.IMAGE_TAG_TABLE.' ON id = image_id
        JOIN '.IMAGE_CATEGORY_TABLE.' ON '.IMAGE_CATEGORY_TABLE.'.image_id='.IMAGES_TABLE.'.id
      WHERE tag_id is null
        AND `category_id` NOT IN (' .$user["forbidden_categories"]. ')
    ;';
        $filter_sets[] = query2array($query, null, 'id');

        break;

      default:
        $filter_sets = trigger_change('perform_batch_manager_prefilters', $filter_sets, $_SESSION['bulk_manager_filter']['prefilter']);
        break;
      }
    }

    // filter set by album
    if (isset($_SESSION['bulk_manager_filter']['category']))
    {
      $filterExists = TRUE;
      $categories = array();

      // we need to check the category still exists (it may have been deleted since it was added in the session)
      $query = '
    SELECT COUNT(*)
      FROM '.CATEGORIES_TABLE.'
      WHERE id = '.$_SESSION['bulk_manager_filter']['category'].'
    ;';
      list($counter) = pwg_db_fetch_row(pwg_query($query));
      if (0 == $counter)
      {
        unset($_SESSION['bulk_manager_filter']);
        redirect(get_root_url().'admin.php?page='.$_GET['page']);
      }

      if (isset($_SESSION['bulk_manager_filter']['category_recursive']))
      {
        $categories = get_subcat_ids(array($_SESSION['bulk_manager_filter']['category']));

        // remove forbidden categories
        $forbidden_categories = array_map('intval',explode(',', $user['forbidden_categories']));
        $categories = array_diff($categories, $forbidden_categories);
      }
      else
      {
        $categories = array($_SESSION['bulk_manager_filter']['category']);
      }

      $query = '
     SELECT DISTINCT(image_id)
       FROM '.IMAGE_CATEGORY_TABLE.'
       WHERE category_id IN ('.implode(',', $categories).')
     ;';
      $filter_sets[] = query2array($query, null, 'image_id');
    }

    // filter set by tags
    if (!empty($_SESSION['bulk_manager_filter']['tags']))
    {
      $filterExists = TRUE;
      $filter_sets[] = get_image_ids_for_tags(
        $_SESSION['bulk_manager_filter']['tags'],
        $_SESSION['bulk_manager_filter']['tag_mode'],
        null,
        null,
        true // take user permissions into account
        );
    }

    // filter set by qsearch terms
    if (isset($_SESSION['bulk_manager_filter']['search']) &&
        strlen($_SESSION['bulk_manager_filter']['search']['q']))
    {
      $filterExists = TRUE;
      include_once( PHPWG_ROOT_PATH .'include/functions_search.inc.php' );
      $res = get_quick_search_results_no_cache($_SESSION['bulk_manager_filter']['search']['q'], array('permissions'=>true));
      if (!empty($res['items']) && !empty($res['qs']['unmatched_terms']))
      {
        $template->assign('no_search_results', array_map('htmlspecialchars', $res['qs']['unmatched_terms']) );
      }
      $filter_sets[] = $res['items'];
    }

    if (isset($_SESSION['bulk_manager_filter'])) {
        $filter_sets = trigger_change('batch_manager_perform_filters', $filter_sets, $_SESSION['bulk_manager_filter']);
    }

    $current_set = array_shift($filter_sets);
    foreach ($filter_sets as $set)
    {
      $current_set = array_intersect($current_set, $set);
    }

    // if scope permissions are not set to whole gallery for at least one action
    // user can only view photos uploaded by user
    if (!empty($current_set)) {
        $current_set = array_intersect($current_set, $photo_set);
    }

    return ($filterExists) ? $current_set : null;
}

/*
 * Check if image is in favourites
 * @param img_id image ID
 * @return true if image in favourites else false
 */
function in_favorites($img_id) {
    global $user;
    $query = '
  SELECT image_id
  FROM '. FAVORITES_TABLE .'
  WHERE image_id=' .$img_id. ' AND user_id=' .$user['id'] .'
    ;';
    $data = pwg_db_fetch_assoc(pwg_query($query));
//    console_log($data);
    return ($data == null) ? false : true;
}

?>
