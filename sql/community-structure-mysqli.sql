CREATE TABLE IF NOT EXISTS `piwigo_community_permissions` (
  id int(11) NOT NULL AUTO_INCREMENT,
  type varchar(255) NOT NULL,
  group_id smallint(5) unsigned DEFAULT NULL,
  user_id mediumint(8) unsigned DEFAULT NULL,
  category_id smallint(5) unsigned DEFAULT NULL,
  user_album enum('true','false') NOT NULL DEFAULT 'false',
  recursive enum('true','false') NOT NULL DEFAULT 'true',
  create_subcategories enum('true','false') NOT NULL DEFAULT 'false',
  moderated enum('true','false') NOT NULL DEFAULT 'true',
  nb_photos int DEFAULT NULL,
  storage int DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `piwigo_community_pendings` (
  image_id mediumint(8) unsigned NOT NULL,
  state varchar(255) NOT NULL,
  added_on datetime NOT NULL,
  validated_by mediumint(8) unsigned DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
