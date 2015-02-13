CREATE TABLE IF NOT EXISTS "piwigo_community_permissions"
(
  "id" INTEGER  NOT NULL,
  "type" VARCHAR(255) NOT NULL,
  "group_id" INTEGER DEFAULT NULL,
  "user_id" INTEGER DEFAULT NULL,
  "category_id" smallint(5) unsigned DEFAULT NULL,
  "user_album" BOOLEAN DEFAULT false,
  "recursive" BOOLEAN DEFAULT true,
  "create_subcategories" BOOLEAN DEFAULT false,
  "moderated" BOOLEAN DEFAULT true,
  "nb_photos" INTEGER DEFAULT NULL,
  "storage" INTEGER DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS "piwigo_community_pendings" (
  image_id INTEGER NOT NULL,
  state VARCHAR(255) NOT NULL,
  added_on DATETIME NOT NULL,
  validated_by INTEGER DEFAULT NULL
);
