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

prepare_upload_configuration();

$user_permissions = community_get_user_permissions($user['id']);

if (count($user_permissions['upload_categories']) == 0 and !$user_permissions ['create_whole_gallery'])
{
  redirect(make_index_url());
}

// +-----------------------------------------------------------------------+
// |                             process form                              |
// +-----------------------------------------------------------------------+

$page['errors'] = array();
$page['infos'] = array();
$_POST['level'] = 16;

if (isset($_GET['processed']))
{
  $hacking_attempt = false;
  
  if ('existing' == $_POST['category_type'])
  {
    // is the user authorized to upload in this album?
    if (!in_array($_POST['category'], $user_permissions['upload_categories']))
    {
      echo 'Hacking attempt, you have no permission to upload in this album';
      $hacking_attempt = true;
    }
  }
  elseif ('new' == $_POST['category_type'])
  {
    if (!in_array($_POST['category_parent'], $user_permissions['create_categories']))
    {
      echo 'Hacking attempt, you have no permission to create this album';
      $hacking_attempt = true;
    }
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

include_once(PHPWG_ROOT_PATH.'admin/include/photos_add_direct_process.inc.php');

if (isset($image_ids) and count($image_ids) > 0)
{
  // reinitialize the informations to display on the result page
  $page['infos'] = array();

  if (isset($conf['community_ask_for_properties']) and $conf['community_ask_for_properties'])
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
        'update' => array_keys($updates[0])
        ),
      $updates
      );
  }
  
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
    
    mass_inserts(
      COMMUNITY_PENDINGS_TABLE,
      array_keys($inserts[0]),
      $inserts
      );

    // the link on thumbnail must go to the websize photo
    foreach ($page['thumbnails'] as $idx => $thumbnail)
    {
      $page['thumbnails'][$idx]['link'] = str_replace(
        'thumbnail/'.$conf['prefix_thumbnail'],
        '',
        $thumbnail['src']
        );
    }

    array_push(
      $page['infos'],
      l10n('Your photos are waiting for validation, administrators have been notified')
      );
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
      if (preg_match('/image_id=(\d+)/', $thumbnail['link'], $matches))
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

// +-----------------------------------------------------------------------+
// |                             prepare form                              |
// +-----------------------------------------------------------------------+

$template->set_filenames(array('add_photos' => dirname(__FILE__).'/add_photos.tpl'));

include_once(PHPWG_ROOT_PATH.'admin/include/photos_add_direct_prepare.inc.php');

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
