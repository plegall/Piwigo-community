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

define('COMMUNITY_BASE_URL', get_root_url().'admin.php?page=plugin-community');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// | Tabs                                                                  |
// +-----------------------------------------------------------------------+

$page['tab'] = (isset($_GET['tab'])) ? $_GET['tab'] : 'permissions';

if ('album' == $page['tab'])
{
  include(COMMUNITY_PATH.'admin_album.php');
}
else
{
  $tabsheet = new tabsheet();
  $tabsheet->add('permissions', l10n('Upload Permissions'), COMMUNITY_BASE_URL.'-permissions');
  $tabsheet->add('pendings', l10n('Pending Photos').($page['community_nb_pendings'] > 0 ? ' ('.$page['community_nb_pendings'].')' : ''), COMMUNITY_BASE_URL.'-pendings');
  $tabsheet->add('config', l10n('Configuration'), COMMUNITY_BASE_URL.'-config');
  $tabsheet->select($page['tab']);
  $tabsheet->assign();

  $template->assign(
    array(
      'ADMIN_PAGE_TITLE' => 'Community',
    )
  );

  include(COMMUNITY_PATH.'admin_'.$page['tab'].'.php');
}
?>