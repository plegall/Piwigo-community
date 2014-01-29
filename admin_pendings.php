<?php
// +-----------------------------------------------------------------------+
// | Piwigo - a PHP based picture gallery                                  |
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

if( !defined("PHPWG_ROOT_PATH") )
{
  die ("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH.'include/functions_picture.inc.php');
load_language('plugin.lang', COMMUNITY_PATH);

$admin_base_url = get_root_url().'admin.php?page=plugin-community-pendings';

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                                actions                                |
// +-----------------------------------------------------------------------+

if (!empty($_POST))
{
  if (empty($_POST['photos']))
  {
    array_push(
      $page['errors'],
      l10n('Select at least one photo')
      );
  }
  else
  {
    check_input_parameter('photos', $_POST, true, PATTERN_ID);
    check_input_parameter('level', $_POST, false, PATTERN_ID);
    
    if (isset($_POST['validate']))
    {
      $query = '
UPDATE '.COMMUNITY_PENDINGS_TABLE.'
  SET state = \'validated\',
      validated_by = '.$user['id'].'
  WHERE image_id IN ('.implode(',', $_POST['photos']).')
;';
      pwg_query($query);

      $query = '
UPDATE '.IMAGES_TABLE.'
  SET level = '.$_POST['level'].',
      date_available = NOW()
  WHERE id IN ('.implode(',', $_POST['photos']).')
;';
      pwg_query($query);

      array_push(
        $page['infos'],
        sprintf(
          l10n('%d photos validated'),
          count($_POST['photos'])
          )
        );
    }

    if (isset($_POST['reject']))
    {
      $query = '
DELETE
  FROM '.COMMUNITY_PENDINGS_TABLE.'
  WHERE image_id IN ('.implode(',', $_POST['photos']).')
;';
      pwg_query($query);

      delete_elements($_POST['photos'], true);

      array_push(
        $page['infos'],
        sprintf(
          l10n('%d photos rejected'),
          count($_POST['photos'])
          )
        );
    }

    invalidate_user_cache();
  }
}

// +-----------------------------------------------------------------------+
// | template init                                                         |
// +-----------------------------------------------------------------------+

$template->set_filenames(
  array(
    'plugin_admin_content' => dirname(__FILE__).'/admin_pendings.tpl'
    )
  );

// +-----------------------------------------------------------------------+
// | pending photos list                                                   |
// +-----------------------------------------------------------------------+

// just in case (because we had a bug in Community plugin up to version
// 2.5.c) let's remove rows in community_pendings table if related photos
// has been deleted
$query = '
SELECT
    image_id
  FROM '.COMMUNITY_PENDINGS_TABLE.'
    LEFT JOIN '.IMAGES_TABLE.' ON id = image_id
  WHERE id IS NULL
;';
$to_delete = array_from_query($query, 'image_id');

if (count($to_delete) > 0)
{
  $query = '
DELETE
  FROM '.COMMUNITY_PENDINGS_TABLE.'
  WHERE image_id IN ('.implode(',', $to_delete).')
;';
  pwg_query($query);
}

$list = array();

$query = '
SELECT
    image_id,
    added_on,

    i.id,
    path,
    date_creation,
    name,
    comment,
    added_by,
    file,
    name,
    filesize,
    width,
    height,
    rotation,
    representative_ext,

    '.$conf['user_fields']['username'].' AS username

  FROM '.COMMUNITY_PENDINGS_TABLE.' AS cp
    INNER JOIN '.IMAGES_TABLE.' AS i ON i.id = cp.image_id
    LEFT JOIN '.USERS_TABLE.' AS u ON u.'.$conf['user_fields']['id'].' = i.added_by

  WHERE state = \'moderation_pending\'

  ORDER BY image_id DESC
;';
$result = pwg_query($query);
$rows = array();
$image_ids = array();
while ($row = pwg_db_fetch_assoc($result))
{
  array_push($rows, $row);
  array_push($image_ids, $row['id']);
}

$category_for_image = array();

if (count($image_ids) > 0)
{
  $query = '
SELECT
    id,
    image_id,
    uppercats
  FROM '.IMAGE_CATEGORY_TABLE.'
    JOIN '.CATEGORIES_TABLE.' ON id = category_id
  WHERE image_id IN ('.implode(',', $image_ids).')
;';
  $result = pwg_query($query);

  while ($row = pwg_db_fetch_assoc($result))
  {
    $category_for_image[ $row['image_id'] ] = get_cat_display_name_cache(
      $row['uppercats'],
      'admin.php?page=album-',
      false,
      true,
      'externalLink'
      );
  }
}

foreach ($rows as $row)
{
  $src_image = new SrcImage($row);
  $thumb_url = DerivativeImage::url(IMG_THUMB, $src_image);
  $medium_url = DerivativeImage::url(IMG_MEDIUM, $src_image);
  
  // file properties
  $dimensions = null;
  $websize_props = $row['width'].'x'.$row['height'].' '.l10n('pixels').', '.sprintf(l10n('%d Kb'), $row['filesize']);
  if (!empty($row['has_high']) and get_boolean($row['has_high']))
  {
    $high_path = get_high_path($row);
    list($high_width, $high_height) = getimagesize($high_path);
    $high_props = $high_width.'x'.$high_height.' '.l10n('pixels').', '.sprintf(l10n('%d Kb'), $row['high_filesize']);
    
    $dimensions = $high_props.' ('.l10n('web size').' '.$websize_props.')';
  }
  else
  {
    $dimensions = $websize_props;
  }

  $album = null;
  if (isset($category_for_image[ $row['id'] ]))
  {
    $album = $category_for_image[ $row['id'] ];
  }
  else
  {
    $album = '<em>'.l10n('No album, this photo is orphan').'</em>';
  }
  
  $template->append(
    'photos',
    array(
      'U_EDIT' => get_root_url().'admin.php?page=photo-'.$row['image_id'],
      'ID' => $row['image_id'],
      'TN_SRC' => $thumb_url,
      'MEDIUM_SRC' => $medium_url,
      'ADDED_BY' => $row['username'],
      'ADDED_ON' => format_date($row['added_on'], true),
      'NAME' => $row['name'],
      'DIMENSIONS' => $dimensions,
      'FILE' => $row['file'],
      'DATE_CREATION' => empty($row['date_creation']) ? l10n('N/A') : format_date($row['date_creation']),
      'ALBUM' => $album,
      )
    );
}

// +-----------------------------------------------------------------------+
// | form options                                                          |
// +-----------------------------------------------------------------------+

// image level options
$selected_level = isset($_POST['level']) ? $_POST['level'] : 0;
$template->assign(
    array(
      'level_options'=> get_privacy_level_options(),
      'level_options_selected' => array($selected_level)
    )
  );


// +-----------------------------------------------------------------------+
// | sending html code                                                     |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
?>