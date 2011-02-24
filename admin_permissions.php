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
load_language('plugin.lang', COMMUNITY_PATH);

$admin_base_url = get_root_url().'admin.php?page=plugin-community-permissions';

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                            add permissions                            |
// +-----------------------------------------------------------------------+

if (isset($_POST['submit_add']))
{
  $who_options = array('any_visitor', 'any_registered_user', 'user', 'group');
  
  if (!in_array($_POST['who'], $who_options))
  {
    die('hacking attempt: invalid "who" option');
  }
  
  if ('user' == $_POST['who'])
  {
    check_input_parameter('who_user', $_POST, false, PATTERN_ID);
  }

  if ('group' == $_POST['who'])
  {
    check_input_parameter('who_group', $_POST, false, PATTERN_ID);
  }

  if (-1 != $_POST['category'])
  {
    check_input_parameter('category', $_POST, false, PATTERN_ID);
  }

  check_input_parameter('moderate', $_POST, false, '/^(true|false)$/');

  // creating the permission
  $insert = array(
    'type' => $_POST['who'],
    'group_id' => ('group' == $_POST['who']) ? $_POST['who_group'] : null,
    'user_id' => ('user' == $_POST['who']) ? $_POST['who_user'] : null,
    'category_id' => ($_POST['category'] > 0) ? $_POST['category'] : null,
    'create_subcategories' => isset($_POST['create_subcategories']) ? 'true' : 'false',
    'moderated' => $_POST['moderate'],
    );
  mass_inserts(
    COMMUNITY_PERMISSIONS_TABLE,
    array_keys($insert),
    array($insert)
    );
  
  array_push(
    $page['infos'],
    l10n('Permission added')
    );
}

// +-----------------------------------------------------------------------+
// |                           remove permissions                          |
// +-----------------------------------------------------------------------+

if (isset($_GET['delete']))
{
  check_input_parameter('delete', $_GET, false, PATTERN_ID);
  
  $query = '
DELETE
  FROM '.COMMUNITY_PERMISSIONS_TABLE.'
  WHERE id = '.$_GET['delete'].'
;';
  pwg_query($query);

  $_SESSION['page_infos'] = array(l10n('Permission removed'));
  redirect($admin_base_url);
}

// +-----------------------------------------------------------------------+
// | template init                                                         |
// +-----------------------------------------------------------------------+

$template->set_filenames(
  array(
    'plugin_admin_content' => dirname(__FILE__).'/admin_permissions.tpl'
    )
  );

// +-----------------------------------------------------------------------+
// | prepare form                                                          |
// +-----------------------------------------------------------------------+


// list of users
$users = array();

$query = '
SELECT
    '.$conf['user_fields']['id'].' AS id,
    '.$conf['user_fields']['username'].' AS username
  FROM '.USERS_TABLE.' AS u
    INNER JOIN '.USER_INFOS_TABLE.' AS uf ON uf.user_id = id
  WHERE uf.status IN (\'normal\',\'generic\')
;';
$result = pwg_query($query);
while ($row = pwg_db_fetch_assoc($result))
{
  $users[$row['id']] = $row['username'];
}

natcasesort($users);

$template->assign(
  array(
    'user_options' => $users,
    )
  );

// list of groups
$query = '
SELECT
    id,
    name
  FROM '.GROUPS_TABLE.'
;';
$result = pwg_query($query);
while ($row = pwg_db_fetch_assoc($result))
{
  $groups[$row['id']] = $row['name'];
}

natcasesort($groups);

$template->assign(
  array(
    'group_options' => $groups,
    )
  );


$template->assign(
  array(
    'F_ADD_ACTION' => COMMUNITY_BASE_URL.'-'.$page['tab'],
    )
  );

// list of albums
$query = '
SELECT id,name,uppercats,global_rank
  FROM '.CATEGORIES_TABLE.'
;';

display_select_cat_wrapper(
  $query,
  array(),
  'category_options'
  );

// +-----------------------------------------------------------------------+
// | permission list                                                       |
// +-----------------------------------------------------------------------+

// user with community permissions
$query = '
SELECT
    *
  FROM '.COMMUNITY_PERMISSIONS_TABLE.'
  ORDER BY id DESC
;';
$result = pwg_query($query);

$permissions = array();
$user_ids = array();
$group_ids = array();
$category_ids = array();

while ($row = mysql_fetch_assoc($result))
{
  array_push($permissions, $row);

  if (!empty($row['user_id']))
  {
    array_push($user_ids, $row['user_id']);
  }

  if (!empty($row['group_id']))
  {
    array_push($group_ids, $row['group_id']);
  }

  if (!empty($row['category_id']))
  {
    array_push($category_ids, $row['category_id']);
  }
}

if (!empty($user_ids))
{
  $query = '
SELECT
    '.$conf['user_fields']['id'].' AS id,
    '.$conf['user_fields']['username'].' AS username
  FROM '.USERS_TABLE.'
  WHERE '.$conf['user_fields']['id'].' IN ('.implode(',', $user_ids).')
;';
  $result = pwg_query($query);
  while ($row = pwg_db_fetch_assoc($result))
  {
    $name_of_user[ $row['id'] ] = $row['username'];
  }
}

if (!empty($group_ids))
{
  $query = '
SELECT
    id,
    name
  FROM '.GROUPS_TABLE.'
  WHERE id IN ('.implode(',', $group_ids).')
;';
  $result = pwg_query($query);
  while ($row = pwg_db_fetch_assoc($result))
  {
    $name_of_group[ $row['id'] ] = $row['name'];
  }
}

if (!empty($category_ids))
{
  $query = '
SELECT
    id,
    uppercats
  FROM '.CATEGORIES_TABLE.'
  WHERE id IN ('.implode(',', $category_ids).')
;';
  $result = pwg_query($query);

  while ($row = pwg_db_fetch_assoc($result))
  {
    $name_of_category[ $row['id'] ] = get_cat_display_name_cache(
      $row['uppercats'],
      null,
      false
      );
  }
}

foreach ($permissions as $permission)
{
  $where = l10n('The whole gallery');
  if (isset($permission['category_id']))
  {
    $where = $name_of_category[ $permission['category_id'] ];
  }

  $who = l10n('any visitor');
  if ('any_registered_user' == $permission['type'])
  {
    $who = l10n('any registered user');
  }
  elseif ('user' == $permission['type'])
  {
    $who = sprintf(
      l10n('%s (the user)'),
      $name_of_user[$permission['user_id']]
      );
  }
  elseif ('group' == $permission['type'])
  {
    $who = sprintf(
      l10n('%s (the group)'),
      $name_of_group[$permission['group_id']]
      );
  }

  $trust = l10n('low trust');
  $trust_tooltip = l10n('uploaded photos must be validated by an administrator');
  if ('false' == $permission['moderated'])
  {
    $trust = l10n('high trust');
    $trust_tooltip = l10n('uploaded photos are directly displayed in the gallery');
  }
  
  $template->append(
    'permissions',
    array(
      'WHO' => $who,
      'WHERE' => $where,
      'TRUST' => $trust,
      'TRUST_TOOLTIP' => $trust_tooltip,
      'CREATE_SUBCATEGORIES' => get_boolean($permission['create_subcategories']),
      'U_DELETE' => $admin_base_url.'&amp;delete='.$permission['id']
      )
    );
}

// +-----------------------------------------------------------------------+
// | sending html code                                                     |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
?>