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

$admin_base_url = get_root_url().'admin.php?page=plugin-community-config';

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                                actions                                |
// +-----------------------------------------------------------------------+

if (!empty($_POST))
{
  check_input_parameter('user_albums_parent', $_POST, false, PATTERN_ID);

  $conf['community'] = array(
    'user_albums' => !empty($_POST['user_albums']),
    'user_albums_parent' => $_POST['user_albums_parent'],
    );

  conf_update_param('community', serialize($conf['community']));
  
  array_push($page['infos'], l10n('Information data registered in database'));
}

// +-----------------------------------------------------------------------+
// | template init                                                         |
// +-----------------------------------------------------------------------+

$template->set_filename('plugin_admin_content', dirname(__FILE__).'/admin_config.tpl');

// +-----------------------------------------------------------------------+
// | form options                                                          |
// +-----------------------------------------------------------------------+

$template->assign('user_albums', $conf['community']['user_albums']);

if (isset($conf['community']['user_albums_parent']))
{
  $category_options_selected = $conf['community']['user_albums_parent'];
}
else
{
  // is there a "Community" album?
  $query = '
SELECT
    id
  FROM '.CATEGORIES_TABLE.'
  WHERE name = \'Community\'
;';
  $result = pwg_query($query);
  while ($row = pwg_db_fetch_assoc($result))
  {
    $category_options_selected = $row['id'];
    break;
  }
}

// list of albums
$query = '
SELECT id,name,uppercats,global_rank
  FROM '.CATEGORIES_TABLE.'
;';

display_select_cat_wrapper(
  $query,
  isset($category_options_selected) ? $category_options_selected : array(),
  'category_options'
  );

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