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

global $template, $conf, $user;

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH.'admin/include/functions_upload.inc.php');
include_once(COMMUNITY_PATH.'include/functions_community.inc.php');

define('PHOTOS_ADD_BASE_URL', make_index_url(array('section' => 'add_photos')));

$user_permissions = $user['community_permissions'];

if (!$user_permissions['community_enabled'])
{
  redirect(make_index_url());
}

// +-----------------------------------------------------------------------+
// |                             process form                              |
// +-----------------------------------------------------------------------+

$page['errors'] = array();
$page['infos'] = array();

// this is for "browser uploader", for Flash Uploader the problem is solved
// with function community_uploadify_privacy_level (see main.inc.php)
$_POST['level'] = 16;

if (isset($_GET['processed']))
{
  $hacking_attempt = false;
  
  // is the user authorized to upload in this album?
  if (!in_array($_POST['category'], $user_permissions['upload_categories']))
  {
    echo 'Hacking attempt, you have no permission to upload in this album';
    $hacking_attempt = true;
  }

  if ($hacking_attempt)
  {
    if (isset($_SESSION['uploads'][ $_POST['upload_id'] ]))
    {
      delete_elements($_SESSION['uploads'][ $_POST['upload_id'] ], true);
    }
    exit();
  }
}

include_once(COMMUNITY_PATH.'include/photos_add_direct_process.inc.php');

// +-----------------------------------------------------------------------+
// | limits                                                                |
// +-----------------------------------------------------------------------+

// has the user reached its limits?
$user['community_usage'] = community_get_user_limits($user['id']);
// echo '<pre>'; print_r($user['community_usage']); echo '</pre>';

// +-----------------------------------------------------------------------+
// | set properties, moderate, notify                                      |
// +-----------------------------------------------------------------------+

if (isset($image_ids) and count($image_ids) > 0)
{
  $query = '
SELECT
    id,
    file,
    filesize
  FROM '.IMAGES_TABLE.'
  WHERE id IN ('.implode(',', $image_ids).')
  ORDER BY id DESC
;';
  $images = array_from_query($query);

  $nb_images_deleted = 0;
  
  // upload has just happened, maybe the user is over quota
  if ($user_permissions['storage'] > 0 and $user['community_usage']['storage'] > $user_permissions['storage'])
  {
    foreach ($images as $image)
    {
      array_push(
        $page['errors'],
        sprintf(l10n('Photo %s rejected.'), $image['file'])
        .' '.sprintf(l10n('Disk usage quota reached (%uMB)'), $user_permissions['storage'])
        );
      
      delete_elements(array($image['id']), true);
      foreach ($page['thumbnails'] as $tn_idx => $thumbnail)
      {
        if ($thumbnail['file'] == $image['file'])
        {
          unset($page['thumbnails'][$idx]);
        }
      }

      $user['community_usage'] = community_get_user_limits($user['id']);
      
      if ($user['community_usage']['storage'] <= $user_permissions['storage'])
      {
        // we stop the deletions
        break;
      }
    }
  }

  if ($user_permissions['nb_photos'] > 0 and $user['community_usage']['nb_photos'] > $user_permissions['nb_photos'])
  {
    foreach ($images as $image)
    {
      array_push(
        $page['errors'],
        sprintf(l10n('Photo %s rejected.'), $image['file'])
        .' '.sprintf(l10n('Maximum number of photos reached (%u)'), $user_permissions['nb_photos'])
        );
      
      delete_elements(array($image['id']), true);
      foreach ($page['thumbnails'] as $tn_idx => $thumbnail)
      {
        if ($thumbnail['file'] == $image['file'])
        {
          unset($page['thumbnails'][$idx]);
        }
      }

      $user['community_usage'] = community_get_user_limits($user['id']);
      
      if ($user['community_usage']['nb_photos'] <= $user_permissions['nb_photos'])
      {
        // we stop the deletions
        break;
      }
    }
  }
     
  
  // reinitialize the informations to display on the result page
  $page['infos'] = array();

  if (isset($_POST['set_photo_properties']))
  {
    $data = array();
    
    $data['name'] = $_POST['name'];
    $data['author'] = $_POST['author'];
    
    if ($conf['allow_html_descriptions'])
    {
      $data['comment'] = @$_POST['description'];
    }
    else
    {
      $data['comment'] = strip_tags(@$_POST['description']);
    }

    $updates = array();
    foreach ($image_ids as $image_id)
    {
      $update = $data;
      $update['id'] = $image_id;

      array_push($updates, $update);
    }

    mass_updates(
      IMAGES_TABLE,
      array(
        'primary' => array('id'),
        'update' => array_diff(array_keys($updates[0]), array('id'))
        ),
      $updates
      );
  }

  if (count($page['thumbnails']) > 0)
  {
    // $category_id is set in the photos_add_direct_process.inc.php included script
    $category_infos = get_cat_info($category_id);
    $category_name = get_cat_display_name($category_infos['upper_names']);

    array_push(
      $page['infos'],
      sprintf(
        l10n('%d photos uploaded into album "%s"'),
        count($page['thumbnails']),
        '<em>'.$category_name.'</em>'
        )
      );
  }

  // should the photos be moderated?
  //
  // if one of the user community permissions is not moderated on the path
  // to gallery root, then the upload is not moderated. For example, if the
  // user is allowed to upload to events/parties with no admin moderation,
  // then he's not moderated when uploading in
  // events/parties/happyNewYear2011
  $moderate = true;
  if (is_admin())
  {
    $moderate = false;
  }
  else
  {  
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

    if (count($inserts) > 0)
    {
      mass_inserts(
        COMMUNITY_PENDINGS_TABLE,
        array_keys($inserts[0]),
        $inserts
        );
      
      // find the url to the medium size
      $page['thumbnails'] = array();

      $query = '
SELECT *
  FROM '.IMAGES_TABLE.'
  WHERE id IN ('.implode(',', $image_ids).')
;';
      $result = pwg_query($query);
      while ($row = pwg_db_fetch_assoc($result))
      {
        $src_image = new SrcImage($row);
        
        $page['thumbnails'][] = array(
          'file' => $row['file'],
          'src' => DerivativeImage::url(IMG_THUMB, $src_image),
          'title' => $row['name'],
          'link' => $image_url = DerivativeImage::url(IMG_MEDIUM, $src_image),
          'lightbox' => true,
          );
      }
      
      array_push(
        $page['infos'],
        l10n('Your photos are waiting for validation, administrators have been notified')
        );
    }
  }
  else
  {
    // the level of a user upload photo with no moderation is 0
    $query = '
UPDATE '.IMAGES_TABLE.'
  SET level = 0
  WHERE id IN ('.implode(',', $image_ids).')
;';
    pwg_query($query);

    // the link on thumbnail must go to picture.php
    foreach ($page['thumbnails'] as $idx => $thumbnail)
    {
      if (preg_match('/page=photo-(\d+)/', $thumbnail['link'], $matches))
      {
        $page['thumbnails'][$idx]['link'] = make_picture_url(
          array(
            'image_id' => $matches[1],
            'image_file' => $thumbnail['file'],
            'category' => $category_infos,
            )
          );
      }
    }
  }

  invalidate_user_cache();
  
  if (count($page['thumbnails']))
  {
    // let's notify administrators
    include_once(PHPWG_ROOT_PATH.'include/functions_mail.inc.php');

    $keyargs_content = array(
      get_l10n_args('Hi administrators,', ''),
      get_l10n_args('', ''),
      get_l10n_args('Album: %s', get_cat_display_name($category_infos['upper_names'], null, false)),
      get_l10n_args('User: %s', $user['username']),
      get_l10n_args('Email: %s', $user['email']),
      );

    if ($moderate)
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
      get_l10n_args('%d photos uploaded by %s', array(count($image_ids), $user['username'])),
      $keyargs_content,
      false
      );
  }
}

// +-----------------------------------------------------------------------+
// |                             prepare form                              |
// +-----------------------------------------------------------------------+

$template->set_filenames(array('add_photos' => dirname(__FILE__).'/add_photos.tpl'));

// +-----------------------------------------------------------------------+
// | Uploaded photos                                                       |
// +-----------------------------------------------------------------------+

if (isset($page['thumbnails']))
{
  $template->assign(
    array(
      'thumbnails' => $page['thumbnails'],
      )
    );

  // only display the batch link if we have more than 1 photo
  if (count($page['thumbnails']) > 1)
  {
    $template->assign(
      array(
        'batch_link' => $page['batch_link'],
        'batch_label' => sprintf(
          l10n('Manage this set of %d photos'),
          count($page['thumbnails'])
          ),
        )
      );
  }
}

$upload_max_filesize = min(
  get_ini_size('upload_max_filesize'),
  get_ini_size('post_max_size')
  );

if ($upload_max_filesize == get_ini_size('upload_max_filesize'))
{
  $upload_max_filesize_shorthand = get_ini_size('upload_max_filesize', false);
}
else
{
  $upload_max_filesize_shorthand = get_ini_size('post_max_filesize', false);
}

$template->assign(
    array(
      'upload_max_filesize' => $upload_max_filesize,
      'upload_max_filesize_shorthand' => $upload_max_filesize_shorthand,
    )
  );

include_once(PHPWG_ROOT_PATH.'admin/include/photos_add_direct_prepare.inc.php');

if (isset($conf['upload_form_all_types']) and $conf['upload_form_all_types'])
{
  $upload_file_types = $conf['file_ext'];
}
else
{
  $upload_file_types = $conf['picture_ext'];
}

$unique_exts = array_unique(array_map('strtolower', $upload_file_types));

$is_windows = true;
if (stripos($_SERVER['HTTP_USER_AGENT'], 'Win') === false)
{
  $is_windows = false;
}

$uploadify_exts = array();
foreach ($unique_exts as $ext)
{
  $uploadify_exts[] = $ext;

  // Windows is not case sensitive and there is a bug with Firefox on
  // Windows: the list of extensions is truncated and last extensions are
  // not taken into account, so we have to make it as short as possible.
  if (!$is_windows)
  {
    $uploadify_exts[] = strtoupper($ext);
  }
}

$upload_modes = array('html', 'multiple');
$upload_mode = isset($conf['upload_mode']) ? $conf['upload_mode'] : 'multiple';

if (isset($_GET['upload_mode']) and $upload_mode != $_GET['upload_mode'] and in_array($_GET['upload_mode'], $upload_modes))
{
  $upload_mode = $_GET['upload_mode'];
  conf_update_param('upload_mode', $upload_mode);
}

// what is the upload switch mode
$index_of_upload_mode = array_flip($upload_modes);
$upload_mode_index = $index_of_upload_mode[$upload_mode];
$upload_switch = $upload_modes[ ($upload_mode_index + 1) % 2 ];

$template->assign(
  array(
    'uploadify_path' => COMMUNITY_PATH.'uploadify',
    'upload_file_types' => implode(', ', $unique_exts),
    'uploadify_fileTypeExts' => implode(';', prepend_append_array_items($uploadify_exts, '*.', '')),
    'upload_mode' => $upload_mode,
    'form_action' => PHOTOS_ADD_BASE_URL.'&amp;upload_mode='.$upload_mode.'&amp;processed=1',
    'switch_url' => PHOTOS_ADD_BASE_URL.'&amp;upload_mode='.$upload_switch,
    'upload_id' => md5(rand()),
    'session_id' => session_id(),
    'another_upload_link' => PHOTOS_ADD_BASE_URL.'&amp;upload_mode='.$upload_mode,
    )
  );

$quota_available = array(
  'summary' => array(),
  'details' => array(),
  );

// there is a limit on storage for this user
if ($user_permissions['storage'] > 0)
{
  $remaining_storage = $user_permissions['storage'] - $user['community_usage']['storage'];
  
  if ($remaining_storage <= 0)
  {
    // limit reached
    $setup_errors[] = sprintf(
      l10n('Disk usage quota reached (%uMB)'),
      $user_permissions['storage']
      );
  }
  else
  {
    $quota_available['summary'][] = $remaining_storage.'MB';
    
    $quota_available['details'][] = sprintf(
      l10n('%s out of %s'),
      $remaining_storage.'MB',
      $user_permissions['storage']
      );
    
    $template->assign(
      array(
        'limit_storage' => $remaining_storage*1024*1024,
        'limit_storage_total_mb' => $user_permissions['storage'],
        )
      );
  }
}

// there is a limit on number of photos for this user
if ($user_permissions['nb_photos'] > 0)
{
  $remaining_nb_photos = $user_permissions['nb_photos'] - $user['community_usage']['nb_photos'];
  
  if ($remaining_nb_photos <= 0)
  {
    // limit reached
    $setup_errors[] = sprintf(
      l10n('Maximum number of photos reached (%u)'),
      $user_permissions['nb_photos']
      );
  }
  else
  {
    $quota_available['summary'][] = l10n_dec('%d photo', '%d photos', $remaining_nb_photos);
    
    $quota_available['details'][] = sprintf(
      l10n('%s out of %s'),
      l10n_dec('%d photo', '%d photos', $remaining_nb_photos),
      $user_permissions['nb_photos']
      );
    
    $template->assign('limit_nb_photos', $remaining_nb_photos);
  }
}

if (count($quota_available['details']) > 0)
{
  $template->assign(
    array(
      'quota_summary' => sprintf(
        l10n('Available %s.'),
        implode(', ', $quota_available['summary'])
        ),
      'quota_details' => sprintf(
        l10n('Available quota %s.'),
        implode(', ', $quota_available['details'])
        ),
      )
    );
}

$template->assign(
  array(
    'setup_errors'=> $setup_errors,
    )
  );

// we have to change the list of uploadable albums
$upload_categories = $user_permissions['upload_categories'];
if (count($upload_categories) == 0)
{
  $upload_categories = array(-1);
}

$query = '
SELECT id,name,uppercats,global_rank
  FROM '.CATEGORIES_TABLE.'
  WHERE id IN ('.implode(',', $upload_categories).')
;';

display_select_cat_wrapper(
  $query,
  $selected_category,
  'category_options'
  );

$create_subcategories = false;
if ($user_permissions['create_whole_gallery'] or count($user_permissions['create_categories']) > 0)
{
  $create_subcategories = true;
}

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
  $selected_category,
  'category_parent_options'
  );

$template->assign(
  array(
    'create_subcategories' => $create_subcategories,
    'create_whole_gallery' => $user_permissions['create_whole_gallery'],
    )
  );

if (isset($conf['community_ask_for_properties']) and $conf['community_ask_for_properties'])
{
  $template->assign(
    array(
      'community_ask_for_properties' => true,
      )
    );
}

// +-----------------------------------------------------------------------+
// |                             display page                              |
// +-----------------------------------------------------------------------+

if (count($page['errors']) != 0)
{
  $template->assign('errors', $page['errors']);
}

if (count($page['infos']) != 0)
{
  $template->assign('infos', $page['infos']);
}

$title = l10n('Upload Photos');
$page['body_id'] = 'theUploadPage';

$template->assign_var_from_handle('PLUGIN_INDEX_CONTENT_BEGIN', 'add_photos');

$template->clear_assign(array('U_MODE_POSTED', 'U_MODE_CREATED'));

$template->assign(
  array(
    'TITLE' => '<a href="'.get_gallery_home_url().'">'.l10n('Home').'</a>'.$conf['level_separator'].$title,
    )
  );
?>
