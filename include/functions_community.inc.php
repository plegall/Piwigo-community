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
  global $conf, $user;

  $cache_key = community_get_cache_key();
  if (!isset($cache_key))
  {
    $cache_key = community_update_cache_key();
  }

  // I (plg) don't understand why, but when you connect, you keep the
  // permissions calculated for the "guest" : the session "inherits"
  // variables from guest to the connected user, so I add a
  // $_SESSION['community_user_id'] to force refresh if the permissions were
  // not calculated for the right user
  if (
    isset($_SESSION['community_user_id'])
    and $_SESSION['community_user_id'] == $user_id
    and $_SESSION['community_cache_key'] == $cache_key
    )
  {
    return $_SESSION['community_user_permissions'];
  }

  $return = array(
    'upload_whole_gallery' => false,
    'create_whole_gallery' => false,
    'create_categories' => array(),
    'upload_categories' => array(),
    'permission_ids' => array(),
    );
  
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
    recursive,
    create_subcategories
  FROM '.COMMUNITY_PERMISSIONS_TABLE.'
  WHERE (type = \'any_visitor\')';

  if ($user_id != $conf['guest_id'])
  {
    $query.= '
    OR (type = \'any_registered_user\')
    OR (type = \'user\' AND user_id = '.$user_id.')';

    if (count($user_group_ids) > 0)
    {
      $query.= '
    OR (type = \'group\' AND group_id IN ('.implode(',', $user_group_ids).'))';
    }
  }
    
  $query.= '
;';

  $recursive_categories = array();

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

      if ('true' == $row['recursive'])
      {
        array_push($recursive_categories, $row['category_id']);
      }
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

  if (is_admin())
  {
    $return ['upload_whole_gallery'] = true;
    $return ['create_whole_gallery'] = true;
  }

  // these are categories with access permission but considering the user
  // has a level 8 (maximum level). We want to keep categories with no
  // photos inside (for nobody)
  $forbidden_categories = calculate_permissions($user['id'], $user['status']);
  
  $empty_categories = array_diff(
    explode(',', $user['forbidden_categories']),
    explode(',', $forbidden_categories)
    );

  if (count($empty_categories) > 0)
  {
    $query = '
SELECT
    category_id
  FROM '.IMAGE_CATEGORY_TABLE.'
    JOIN '.IMAGES_TABLE.' ON image_id = id
  WHERE category_id IN ('.implode(',', $empty_categories).')
    AND level > '.$user['level'].'
    AND level <= 8
  GROUP BY category_id
;';
    $not_really_empty_categories = array_keys(hash_from_query($query, 'category_id'));
    $forbidden_categories.= ','.implode(',', $not_really_empty_categories);
  }

  $query = '
SELECT
    id
  FROM '.CATEGORIES_TABLE.'
;';
  $all_categories = array_keys(hash_from_query($query, 'id'));

  if ($return['upload_whole_gallery'])
  {
    $return['upload_categories'] = array_diff(
      $all_categories,
      explode(',', $forbidden_categories)
      );
  }
  elseif (count($return['upload_categories']) > 0)
  {
    if (count($recursive_categories) > 0)
    {
      $return['upload_categories'] = array_unique(
        array_merge(
          $return['upload_categories'],
          get_subcat_ids($recursive_categories)
          )
        );
    }

    $return['upload_categories'] = array_diff(
      $return['upload_categories'],
      explode(',', $forbidden_categories)
      );
  }

  if ($return ['create_whole_gallery'])
  {
    $return['create_categories'] = array_diff(
      $all_categories,
      explode(',', $forbidden_categories)
      );
  }
  elseif (count($return['create_categories']) > 0)
  {
    // no need to check for "recursive", an upload permission can't be
    // "create_subcategories" without being "recursive"
    $return['create_categories'] = get_subcat_ids($return['create_categories']);

    $return['create_categories'] = array_diff(
      $return['create_categories'],
      explode(',', $forbidden_categories)
      );
  }

  $_SESSION['community_user_permissions'] = $return;
  $_SESSION['community_cache_key'] = $cache_key;
  $_SESSION['community_user_id'] = $user_id;

  return $_SESSION['community_user_permissions'];
}

function community_reject_pendings($image_ids)
{
  if (count($image_ids) == 0)
  {
    return;
  }
  
  $query = '
DELETE
  FROM '.COMMUNITY_PENDINGS_TABLE.'
  WHERE image_id IN ('.implode(',', $image_ids).')
;';
  pwg_query($query);

  // needs to be in administration panel
  delete_elements($image_ids, true);
}

function community_reject_user_pendings($user_id)
{
  $query = '
SELECT
    image_id
  FROM '.COMMUNITY_PENDINGS_TABLE.' AS cp
    INNER JOIN '.IMAGES_TABLE.' AS i ON i.id = cp.image_id
  WHERE state != \'validated\'
    AND added_by = '.$user_id.'
;';
  $result = pwg_query($query);
  $image_ids = array();
  while ($row = pwg_db_fetch_assoc($result))
  {
    array_push($image_ids, $row['image_id']);
  }

  community_reject_pendings($image_ids);
}

function community_update_cache_key()
{
  $cache_key = generate_key(20);
  conf_update_param('community_cache_key', $cache_key);
  return $cache_key;
}

function community_get_cache_key()
{
  global $conf;

  if (isset($conf['community_cache_key']))
  {
    return $conf['community_cache_key'];
  }
  else
  {
    return null;
  }
}
?>