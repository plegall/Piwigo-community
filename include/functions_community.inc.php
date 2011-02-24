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

function community_get_user_permissions($user_id)
{
  global $conf;

  $return = array(
    'upload_whole_gallery' => false,
    'create_whole_gallery' => false,
    'create_categories' => array(),
    'upload_categories' => array(),
    'permission_ids' => array(),
    );

  $user_permissions = array();
  
  // what are the user groups?
  $query = '
SELECT
    group_id
  FROM '.USER_GROUP_TABLE.'
  WHERE user_id = '.$user_id.'
;';
  $user_group_ids = array_from_query($query, 'group_id');

  $query = '
SELECT
    id,
    category_id,
    create_subcategories
  FROM '.COMMUNITY_PERMISSIONS_TABLE.'
  WHERE (type = \'any_visitor\')';

  if ($user_id != $conf['guest_id'])
  {
  $query.= '
    OR (type = \'any_registered_user\')
    OR (type = \'user\' AND user_id = '.$user_id.')
    OR (type = \'group\' AND group_id IN ('.implode(',', $user_group_ids).'))
';
  }
    
  $query.= '
;';

  $result = pwg_query($query);
  while ($row = pwg_db_fetch_assoc($result))
  {
    array_push($return['permission_ids'], $row['id']);
    
    if (empty($row['category_id']))
    {
      $return ['upload_whole_gallery'] = true;
    }
    else
    {
      array_push($return['upload_categories'], $row['category_id']);
    }

    if ('true' == $row['create_subcategories'])
    {
      if (empty($row['category_id']))
      {
        $return ['create_whole_gallery'] = true;
      }
      else
      {
        array_push($return['create_categories'], $row['category_id']);
      }
    }
  }

  if (!$return['upload_whole_gallery'])
  {
    $return['upload_categories'] = get_subcat_ids($return['upload_categories']);
  }

  if (!$return ['create_whole_gallery'] and count($return['create_categories']) > 0)
  {
    $return['create_categories'] = get_subcat_ids($return['create_categories']);
  }

  return $return;
}

?>