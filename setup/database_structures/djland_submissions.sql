CREATE TABLE `submissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `format_id` tinyint(3) DEFAULT NULL,
  `catalog` tinytext,
  `crtc` int(11) DEFAULT NULL,
  `cancon` tinyint(1) DEFAULT NULL,
  `femcon` tinyint(1) DEFAULT NULL,
  `local` int(11) DEFAULT NULL,
  `playlist` tinyint(1) DEFAULT NULL,
  `compilation` tinyint(1) DEFAULT NULL,
  `digitized` tinyint(1) DEFAULT NULL,
  `status` tinytext,
  `is_trashed` tinyint(1) DEFAULT 0,
  `artist` tinytext,
  `title` tinytext,
  `label` tinytext,
  `genre` tinytext,
  `tags` tinytext,
  `submitted` date DEFAULT NULL,
  `releasedate` date DEFAULT NULL,
  `assignee` int(10) unsigned DEFAULT NULL,
  `reviewed` int(10) DEFAULT NULL,
  `approved` tinyint(1) DEFAULT NULL,
  `description` longtext,
  `location` tinytext,
  `email` tinytext,
  `credit` tinytext,
  `art_url` tinytext,
  `review_comments` mediumtext,
  `staff_comment` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;;
