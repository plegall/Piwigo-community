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

$query = '
SELECT
    id
  FROM '.IMAGES_TABLE.'
  WHERE added_by = '.$user['id'].'
;';
$page['cat_elements_id'] = query2array($query, null, 'id');

if (isset($_GET['category_id']))
{
  $query = '
SELECT
    image_id
  FROM '.IMAGE_CATEGORY_TABLE.'
  WHERE category_id = '.$_GET['category_id'].'
;';
  $page['cat_elements_id'] = array_intersect($page['cat_elements_id'], query2array($query, null, 'image_id'));
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
if (isset($_GET['category_id']))
{
  $base_url .= '&amp;category_id='.$_GET['category_id'];
}
$redirect_url = $base_url;

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
      $page['infos'] = 'Tags added';
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

      $page['infos'] = 'Tags removed';
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
  else
  {
    // we must be sure we don't use "rank" because it's in the image_category table only
    $conf['order_by'] = str_replace('`rank`', 'id', $conf['order_by']);
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
$template->set_filename('edit_photos','/template/edit_photos.tpl');

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

$template->assign(
  array(
    'TITLE' => '<a href="'.get_gallery_home_url().'">'.l10n('Home').'</a>'.$conf['level_separator'].$title,
    'selection' => $collection,
    'all_elements' => $page['cat_elements_id'],
    'START' => $page['start'],
    'PWG_TOKEN' => get_pwg_token(),
    'U_DISPLAY'=>$base_url.get_query_string_diff(array('display')),
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

$template->assign_var_from_handle('PLUGIN_INDEX_CONTENT_BEGIN', 'edit_photos');
?>