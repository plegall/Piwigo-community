<?php
// +-----------------------------------------------------------------------+
// | Piwigo - a PHP based picture gallery                                  |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2009      Pierrick LE GALL             http://piwigo.org |
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

$conf['community_permission_levels'] = array(1,2);
$admin_base_url = get_root_url().'admin.php?page=plugin&section=community%2Fadmin.php';

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                               functions                               |
// +-----------------------------------------------------------------------+

function get_permission_level_label($level)
{
  return '('.$level.') '.l10n( sprintf('Community level %d', $level) );
}

// +-----------------------------------------------------------------------+
// |                            add permissions                            |
// +-----------------------------------------------------------------------+

if (isset($_POST['submit_add']) and !is_adviser())
{
  if (!is_numeric($_POST['user_options']))
  {
    array_push($page['errors'], 'invalid user');
  }
  if (!is_numeric($_POST['permission_level_options']))
  {
    array_push($page['errors'], 'invalid permission level');
  }

  if (count($page['errors']) == 0)
  {
    $query = '
SELECT
    '.$conf['user_fields']['username'].' AS username
  FROM '.USERS_TABLE.'
  WHERE '.$conf['user_fields']['id'].' = '.$_POST['user_options'].'
;';
    list($username) = mysql_fetch_row(pwg_query($query));
    // remove any existing permission for this user
    $query = '
DELETE
  FROM '.COMMUNITY_TABLE.'
  WHERE user_id = '.$_POST['user_options'].'
;';
    pwg_query($query);

    // creating the permission
    $query = '
INSERT INTO '.COMMUNITY_TABLE.'
  (user_id, permission_level)
  VALUES
  ('.$_POST['user_options'].', '.$_POST['permission_level_options'].')
;';
    pwg_query($query);

    array_push(
      $page['infos'],
      sprintf(
        l10n('community permissions "%s" added/updated for "%s"'),
        get_permission_level_label($_POST['permission_level_options']),
        $username
        )
      );
  }

}

// +-----------------------------------------------------------------------+
// |                           remove permissions                          |
// +-----------------------------------------------------------------------+

if (isset($_GET['delete']) and !is_adviser())
{
  if (is_numeric($_GET['delete']))
  {
    $query = '
SELECT
    community.user_id,
    community.permission_level,
    u.'.$conf['user_fields']['username'].' AS username
  FROM '.COMMUNITY_TABLE.' AS community
    INNER JOIN '.USERS_TABLE.' AS u
      ON u.'.$conf['user_fields']['id'].' = community.user_id
  WHERE community.user_id = '.$_GET['delete'].'
;';
    $result = pwg_query($query);
    if (mysql_num_rows($result) == 0)
    {
      array_push($page['errors'], 'this user has no community permission yet');
    }

    if (count($page['errors']) == 0)
    {
      list($user_id, $permission_level, $username) = mysql_fetch_row($result);

      $query = '
DELETE
  FROM '.COMMUNITY_TABLE.'
  WHERE user_id = '.$user_id.'
;';
      pwg_query($query);

      array_push(
        $page['infos'],
        sprintf(
          l10n('community permissions "%s" removed for "%s"'),
          get_permission_level_label($permission_level),
          $username
        )
      );
    }
  }
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames(
  array(
    'plugin_admin_content' => dirname(__FILE__).'/admin.tpl'
    )
  );

$template->assign(
    array(
      'F_ADD_ACTION'=> $admin_base_url,
    )
  );


// user options
$query = '
SELECT
    u.'.$conf['user_fields']['id'].' AS id,
    u.'.$conf['user_fields']['username'].' AS username
  FROM '.USERS_TABLE.' AS u
    INNER JOIN '.USER_INFOS_TABLE.' AS ui
      ON u.'.$conf['user_fields']['id'].' = ui.user_id
  WHERE ui.status = "normal"
  ORDER BY username
;';
$user_options = array();
$result = pwg_query($query);
while ($row = mysql_fetch_assoc($result))
{
  $user_options[ $row['id'] ] = $row['username'];
}
$template->assign(
    array(
      'user_options'=> $user_options,
    )
  );

  
// permission level options
$permission_level_options = array();
foreach ($conf['community_permission_levels'] as $level)
{
  $permission_level_options[$level] = get_permission_level_label($level);
}
$template->assign(
    array(
      'permission_level_options'=> $permission_level_options,
    )
  );

// user with community permissions
$query = '
SELECT
    community.user_id,
    community.permission_level,
    u.'.$conf['user_fields']['username'].' AS username
  FROM '.COMMUNITY_TABLE.' AS community
    INNER JOIN '.USERS_TABLE.' AS u
      ON u.'.$conf['user_fields']['id'].' = community.user_id
  ORDER BY username
;';
$result = pwg_query($query);

while ($row = mysql_fetch_assoc($result))
{
  $template->append(
    'users',
    array(
      'NAME' => $row['username'],
      'PERMISSION_LEVEL' => get_permission_level_label($row['permission_level']),
      'U_DELETE' => $admin_base_url.'&amp;delete='.$row['user_id']
      )
    );
}

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
?>