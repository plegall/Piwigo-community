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
include_once(PHPWG_ROOT_PATH.'admin/include/tabsheet.class.php');
load_language('plugin.lang', COMMUNITY_PATH);

check_input_parameter('cat_id', $_GET, false, PATTERN_ID);
$cat_id = $_GET['cat_id'];

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

if (!isset($conf['community']['user_albums']) or !$conf['community']['user_albums'])
{
  die('community user albums is not active');
}

// +-----------------------------------------------------------------------+
// |                                actions                                |
// +-----------------------------------------------------------------------+

if (!empty($_POST))
{
  check_input_parameter('community_user', $_POST, false, PATTERN_ID);

  if (!empty($_POST['community_user']))
  {
    // only one album for each user, first we remove ownership on any other album
    single_update(
      CATEGORIES_TABLE,
      array('community_user' => null),
      array('community_user' => $_POST['community_user'])
      );
  }

  // then we give the album to the user
  single_update(
    CATEGORIES_TABLE,
    array('community_user' => $_POST['community_user'] ?? null),
    array('id' => $cat_id)
    );

  array_push($page['infos'], l10n('Information data registered in database'));
}

// +-----------------------------------------------------------------------+
// | template init                                                         |
// +-----------------------------------------------------------------------+

$template->set_filename('plugin_admin_content', dirname(__FILE__).'/template/admin_album.tpl');

// +-----------------------------------------------------------------------+
// | Tabs                                                                  |
// +-----------------------------------------------------------------------+

include_once(PHPWG_ROOT_PATH.'admin/include/tabsheet.class.php');

$page['tab'] = 'community';
$admin_album_base_url = get_root_url().'admin.php?page=album-'.$cat_id;

$query = '
SELECT *
  FROM '.CATEGORIES_TABLE.'
  WHERE id = '.$cat_id.'
;';
$category = pwg_db_fetch_assoc(pwg_query($query));

if (!isset($category['id']))
{
  die("unknown album");
}

$tabsheet = new tabsheet();
$tabsheet->set_id('album');
$tabsheet->select('community');
$tabsheet->assign();

// +-----------------------------------------------------------------------+
// | form options                                                          |
// +-----------------------------------------------------------------------+

$query = '
SELECT
    id,
    name,
    uppercats,
    global_rank,
    community_user
  FROM '.CATEGORIES_TABLE.'
  WHERE community_user IS NOT NULL
;';
$album_of_user = query2array($query, 'community_user');

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
  $value = $row['username'];

  if (isset($album_of_user[ $row['id'] ]))
  {
    if ($album_of_user[ $row['id'] ]['id'] == $category['id'])
    {
      $value .= l10n(' (owns this album)');
    }
    else
    {
      $album_fullname = strip_tags(
        get_cat_display_name_cache(
          $album_of_user[ $row['id'] ]['uppercats'],
          null
        )
      );
      $value .= l10n(' (already owns album "%s")', $album_fullname);
    }
  }

  $users[$row['id']] = $value;
}

// TODO add a * next to users with an associated album and warn about the automatic change

$template->assign(
  array(
    'ADMIN_PAGE_TITLE' => l10n('Edit album').' <strong>'.$category['name'].'</strong>',
    'ADMIN_PAGE_OBJECT_ID' => '#'.$category['id'],
    'community_user_options' => $users,
    'community_user_selected' => $category['community_user'],
    )
  );

// +-----------------------------------------------------------------------+
// | sending html code                                                     |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
?>