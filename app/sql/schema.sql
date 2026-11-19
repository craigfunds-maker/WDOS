-- WODDI Institute, MySQL/MariaDB schema converted from Supabase (Postgres). Generated; do not edit by hand.

SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `academic_cycles`;
CREATE TABLE `academic_cycles` (
  `id` CHAR(36) NOT NULL,
  `name` VARCHAR(191) NULL,
  `year` INT NULL,
  `start_month` INT NULL,
  `end_month` INT NULL,
  `admission_open_day` INT NULL DEFAULT 1,
  `admission_close_day` INT NULL DEFAULT 14,
  `status` VARCHAR(191) NULL DEFAULT 'auto',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_academic_cycles_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `activation_events`;
CREATE TABLE `activation_events` (
  `id` CHAR(36) NOT NULL,
  `uid` VARCHAR(191) NULL,
  `event` MEDIUMTEXT NULL,
  `detail` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_activation_events_uid` (`uid`),
  KEY `ix_activation_events_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `activity_events`;
CREATE TABLE `activity_events` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `user_id` CHAR(36) NULL,
  `event_name` MEDIUMTEXT NULL,
  `entity_type` VARCHAR(191) NULL,
  `entity_id` VARCHAR(191) NULL,
  `pathway` VARCHAR(191) NULL,
  `course_id` VARCHAR(191) NULL,
  `phase_id` VARCHAR(191) NULL,
  `metadata` LONGTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_activity_events_user_id` (`user_id`),
  KEY `ix_activity_events_course_id` (`course_id`),
  KEY `ix_activity_events_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
  `id` CHAR(36) NOT NULL,
  `uid` VARCHAR(191) NULL,
  `category` VARCHAR(191) NULL,
  `action` MEDIUMTEXT NULL,
  `label` MEDIUMTEXT NULL,
  `ref_id` VARCHAR(191) NULL,
  `meta` LONGTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_activity_log_uid` (`uid`),
  KEY `ix_activity_log_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `admin_audit_logs`;
CREATE TABLE `admin_audit_logs` (
  `id` CHAR(36) NOT NULL,
  `admin_uid` VARCHAR(191) NULL,
  `action` MEDIUMTEXT NULL,
  `target_uid` VARCHAR(191) NULL,
  `target_id` VARCHAR(191) NULL,
  `meta` LONGTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `target_table` MEDIUMTEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_admin_audit_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `admin_roles`;
CREATE TABLE `admin_roles` (
  `uid` CHAR(36) NOT NULL,
  `email` VARCHAR(191) NULL,
  `role` VARCHAR(191) NULL DEFAULT 'learner',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`uid`),
  KEY `ix_admin_roles_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `admission_waitlist`;
CREATE TABLE `admission_waitlist` (
  `id` CHAR(36) NOT NULL,
  `contact` MEDIUMTEXT NULL,
  `network` VARCHAR(191) NULL,
  `note` MEDIUMTEXT NULL,
  `notified` TINYINT(1) NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `full_name` MEDIUMTEXT NULL,
  `country` MEDIUMTEXT NULL,
  `region` MEDIUMTEXT NULL,
  `lga` MEDIUMTEXT NULL,
  `join_community` TINYINT(1) NULL DEFAULT 0,
  `emailed_circle` TINYINT(1) NULL DEFAULT 0,
  `attribution` LONGTEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_admission_waitlist_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `admissions`;
CREATE TABLE `admissions` (
  `id` CHAR(36) NOT NULL,
  `uid` CHAR(36) NULL,
  `network` VARCHAR(191) NULL,
  `cycle_name` MEDIUMTEXT NULL,
  `cycle_year` INT NULL,
  `cycle_month` INT NULL,
  `admitted_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_admissions_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ai_mentor_failures`;
CREATE TABLE `ai_mentor_failures` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `uid` CHAR(36) NULL,
  `kind` VARCHAR(191) NULL,
  `message` MEDIUMTEXT NULL,
  `tried` LONGTEXT NULL,
  `ms` INT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ai_mentor_failures_uid` (`uid`),
  KEY `ix_ai_mentor_failures_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ai_mentor_messages`;
CREATE TABLE `ai_mentor_messages` (
  `id` CHAR(36) NOT NULL,
  `thread_id` CHAR(36) NULL,
  `uid` CHAR(36) NULL,
  `role` VARCHAR(191) NULL,
  `content` MEDIUMTEXT NULL,
  `engine` MEDIUMTEXT NULL,
  `model` MEDIUMTEXT NULL,
  `followups` LONGTEXT NULL,
  `flags` LONGTEXT NULL,
  `rating` INT NULL,
  `latency_ms` INT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ai_mentor_messages_thread_id` (`thread_id`),
  KEY `ix_ai_mentor_messages_uid` (`uid`),
  KEY `ix_ai_mentor_messages_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ai_mentor_threads`;
CREATE TABLE `ai_mentor_threads` (
  `id` CHAR(36) NOT NULL,
  `uid` CHAR(36) NULL,
  `title` MEDIUMTEXT NULL,
  `topic` MEDIUMTEXT NULL,
  `message_count` INT NULL DEFAULT 0,
  `archived` TINYINT(1) NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ai_mentor_threads_uid` (`uid`),
  KEY `ix_ai_mentor_threads_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ai_model_status`;
CREATE TABLE `ai_model_status` (
  `model` VARCHAR(191) NOT NULL,
  `provider` MEDIUMTEXT NULL,
  `ok_count` INT NULL DEFAULT 0,
  `fail_count` INT NULL DEFAULT 0,
  `last_ok_at` DATETIME NULL,
  `last_fail_at` DATETIME NULL,
  `avg_ms` INT NULL,
  `blocked_until` DATETIME NULL,
  `last_error` MEDIUMTEXT NULL,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `al_circle_members`;
CREATE TABLE `al_circle_members` (
  `circle_id` CHAR(36) NOT NULL,
  `uid` CHAR(36) NOT NULL,
  `joined_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`circle_id`,`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `al_circles`;
CREATE TABLE `al_circles` (
  `id` CHAR(36) NOT NULL,
  `slug` VARCHAR(191) NULL,
  `name` VARCHAR(191) NULL,
  `description` MEDIUMTEXT NULL,
  `icon` MEDIUMTEXT NULL,
  `kind` VARCHAR(191) NULL DEFAULT 'interest',
  `created_by` CHAR(36) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_al_circles_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `al_comments`;
CREATE TABLE `al_comments` (
  `id` CHAR(36) NOT NULL,
  `post_id` CHAR(36) NULL,
  `author` CHAR(36) NULL,
  `body` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'visible',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_al_comments_post_id` (`post_id`),
  KEY `ix_al_comments_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `al_events`;
CREATE TABLE `al_events` (
  `id` CHAR(36) NOT NULL,
  `host` CHAR(36) NULL,
  `title` MEDIUMTEXT NULL,
  `description` MEDIUMTEXT NULL,
  `starts_at` DATETIME NULL,
  `ends_at` DATETIME NULL,
  `mode` MEDIUMTEXT NULL,
  `location` MEDIUMTEXT NULL,
  `link` MEDIUMTEXT NULL,
  `capacity` INT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'scheduled',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_al_events_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `al_opportunities`;
CREATE TABLE `al_opportunities` (
  `id` CHAR(36) NOT NULL,
  `author` CHAR(36) NULL,
  `title` MEDIUMTEXT NULL,
  `kind` VARCHAR(191) NULL DEFAULT 'job',
  `organisation` MEDIUMTEXT NULL,
  `location` MEDIUMTEXT NULL,
  `remote` TINYINT(1) NULL DEFAULT 0,
  `deadline` DATE NULL,
  `link` MEDIUMTEXT NULL,
  `description` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'open',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_al_opportunities_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `al_posts`;
CREATE TABLE `al_posts` (
  `id` CHAR(36) NOT NULL,
  `author` CHAR(36) NULL,
  `circle_id` CHAR(36) NULL,
  `kind` VARCHAR(191) NULL DEFAULT 'update',
  `body` MEDIUMTEXT NULL,
  `link` MEDIUMTEXT NULL,
  `image_url` MEDIUMTEXT NULL,
  `pinned` TINYINT(1) NULL DEFAULT 0,
  `status` VARCHAR(191) NULL DEFAULT 'visible',
  `comment_count` INT NULL DEFAULT 0,
  `reaction_count` INT NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `edited_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_al_posts_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `al_profiles`;
CREATE TABLE `al_profiles` (
  `uid` CHAR(36) NOT NULL,
  `headline` MEDIUMTEXT NULL,
  `bio` MEDIUMTEXT NULL,
  `skills` LONGTEXT NULL,
  `open_to` LONGTEXT NULL,
  `location` MEDIUMTEXT NULL,
  `linkedin` MEDIUMTEXT NULL,
  `contact` MEDIUMTEXT NULL,
  `contact_visible` TINYINT(1) NULL DEFAULT 0,
  `show_in_directory` TINYINT(1) NULL DEFAULT 1,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `al_reactions`;
CREATE TABLE `al_reactions` (
  `post_id` CHAR(36) NOT NULL,
  `uid` CHAR(36) NOT NULL,
  `kind` VARCHAR(191) NULL DEFAULT 'like',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`post_id`,`uid`),
  KEY `ix_al_reactions_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `al_reports`;
CREATE TABLE `al_reports` (
  `id` CHAR(36) NOT NULL,
  `reporter` CHAR(36) NULL,
  `target_kind` MEDIUMTEXT NULL,
  `target_id` CHAR(36) NULL,
  `reason` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'open',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_al_reports_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `al_rsvps`;
CREATE TABLE `al_rsvps` (
  `event_id` CHAR(36) NOT NULL,
  `uid` CHAR(36) NOT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'going',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`event_id`,`uid`),
  KEY `ix_al_rsvps_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `alumni`;
CREATE TABLE `alumni` (
  `uid` CHAR(36) NOT NULL,
  `full_name` MEDIUMTEXT NULL,
  `reason` MEDIUMTEXT NULL,
  `since` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `analytics_events`;
CREATE TABLE `analytics_events` (
  `id` CHAR(36) NOT NULL,
  `uid` VARCHAR(191) NULL,
  `event` MEDIUMTEXT NULL,
  `meta` LONGTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `target_id` VARCHAR(191) NULL,
  `target_label` MEDIUMTEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_analytics_events_uid` (`uid`),
  KEY `ix_analytics_events_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `announcement_reads`;
CREATE TABLE `announcement_reads` (
  `announcement_id` CHAR(36) NOT NULL,
  `uid` VARCHAR(191) NOT NULL,
  `read_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`announcement_id`,`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
  `id` CHAR(36) NOT NULL,
  `uid` VARCHAR(191) NULL,
  `title` MEDIUMTEXT NULL,
  `body` MEDIUMTEXT NULL,
  `target` MEDIUMTEXT NULL,
  `pinned` TINYINT(1) NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `audience` MEDIUMTEXT NULL,
  `created_by` VARCHAR(191) NULL,
  `target_course_id` VARCHAR(191) NULL,
  `target_group` MEDIUMTEXT NULL,
  `scheduled_at` DATETIME NULL,
  `expires_at` DATETIME NULL,
  `status` VARCHAR(191) NULL DEFAULT 'published',
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `body_html` MEDIUMTEXT NULL,
  `media_url` MEDIUMTEXT NULL,
  `media_type` VARCHAR(191) NULL,
  `youtube_id` VARCHAR(191) NULL,
  `network` VARCHAR(191) NULL DEFAULT 'all',
  PRIMARY KEY (`id`),
  KEY `ix_announcements_uid` (`uid`),
  KEY `ix_announcements_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `assessments`;
CREATE TABLE `assessments` (
  `id` CHAR(36) NOT NULL,
  `course_id` CHAR(36) NULL,
  `uid` VARCHAR(191) NULL,
  `score` INT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'submitted',
  `submitted_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by` VARCHAR(191) NULL,
  `reviewed_at` DATETIME NULL,
  `feedback` MEDIUMTEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_assessments_course_id` (`course_id`),
  KEY `ix_assessments_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `assignment_submissions`;
CREATE TABLE `assignment_submissions` (
  `id` CHAR(36) NOT NULL,
  `assignment_id` CHAR(36) NULL,
  `uid` CHAR(36) NULL,
  `body` MEDIUMTEXT NULL,
  `link` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'submitted',
  `grade` MEDIUMTEXT NULL,
  `feedback` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_assignment_submissions_uid` (`uid`),
  KEY `ix_assignment_submissions_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `assignments`;
CREATE TABLE `assignments` (
  `id` CHAR(36) NOT NULL,
  `title` MEDIUMTEXT NULL,
  `course_id` VARCHAR(191) NULL,
  `instructions` MEDIUMTEXT NULL,
  `active` TINYINT(1) NULL DEFAULT 1,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_assignments_course_id` (`course_id`),
  KEY `ix_assignments_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` CHAR(36) NOT NULL,
  `actor` MEDIUMTEXT NULL,
  `action` MEDIUMTEXT NULL,
  `details` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_audit_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `certificate_audit`;
CREATE TABLE `certificate_audit` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `certificate_id` CHAR(36) NULL,
  `credential_code` VARCHAR(191) NULL,
  `uid` VARCHAR(191) NULL,
  `action` MEDIUMTEXT NULL,
  `old_value` LONGTEXT NULL,
  `new_value` LONGTEXT NULL,
  `reason` MEDIUMTEXT NULL,
  `by_uid` VARCHAR(191) NULL,
  `by_name` MEDIUMTEXT NULL,
  `at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_certificate_audit_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `certificates`;
CREATE TABLE `certificates` (
  `id` CHAR(36) NOT NULL,
  `uid` VARCHAR(191) NULL,
  `course_id` CHAR(36) NULL,
  `title` MEDIUMTEXT NULL,
  `issued_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `pdf_url` MEDIUMTEXT NULL,
  `score` INT NULL DEFAULT 100,
  `course_name` MEDIUMTEXT NULL,
  `language` VARCHAR(191) NULL DEFAULT 'en',
  `student_name` MEDIUMTEXT NULL,
  `pathway` VARCHAR(191) NULL,
  `revoked_at` DATETIME NULL,
  `revoked` TINYINT(1) NULL DEFAULT 0,
  `revoke_reason` MEDIUMTEXT NULL,
  `course_key` VARCHAR(191) NULL,
  `source` MEDIUMTEXT NULL,
  `credential_code` VARCHAR(191) NULL,
  `replaced_by` CHAR(36) NULL,
  `corrected_at` DATETIME NULL,
  `cohort` MEDIUMTEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_certificates_uid` (`uid`),
  KEY `ix_certificates_course_id` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE `chat_messages` (
  `id` CHAR(36) NOT NULL,
  `sender_uid` VARCHAR(191) NULL,
  `recipient_uid` VARCHAR(191) NULL,
  `content` MEDIUMTEXT NULL,
  `read` TINYINT(1) NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_chat_messages_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `community_members`;
CREATE TABLE `community_members` (
  `id` CHAR(36) NOT NULL,
  `country_code` VARCHAR(191) NULL,
  `uid` VARCHAR(191) NULL,
  `role` VARCHAR(191) NULL DEFAULT 'member',
  `status` VARCHAR(191) NULL DEFAULT 'active',
  `joined_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_community_members_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `community_post_comments`;
CREATE TABLE `community_post_comments` (
  `id` CHAR(36) NOT NULL,
  `post_id` CHAR(36) NULL,
  `uid` VARCHAR(191) NULL,
  `author_name` MEDIUMTEXT NULL,
  `author_avatar` MEDIUMTEXT NULL,
  `content` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'active',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_community_post_comments_post_id` (`post_id`),
  KEY `ix_community_post_comments_uid` (`uid`),
  KEY `ix_community_post_comments_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `community_post_likes`;
CREATE TABLE `community_post_likes` (
  `id` CHAR(36) NOT NULL,
  `post_id` CHAR(36) NULL,
  `uid` VARCHAR(191) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_community_post_likes_post_id` (`post_id`),
  KEY `ix_community_post_likes_uid` (`uid`),
  KEY `ix_community_post_likes_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `community_posts`;
CREATE TABLE `community_posts` (
  `id` CHAR(36) NOT NULL,
  `country_code` VARCHAR(191) NULL,
  `uid` VARCHAR(191) NULL,
  `author_name` MEDIUMTEXT NULL,
  `author_avatar` MEDIUMTEXT NULL,
  `content` MEDIUMTEXT NULL,
  `image_url` MEDIUMTEXT NULL,
  `pinned` TINYINT(1) NULL DEFAULT 0,
  `likes_count` INT NULL DEFAULT 0,
  `comments_count` INT NULL DEFAULT 0,
  `status` VARCHAR(191) NULL DEFAULT 'active',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_community_posts_uid` (`uid`),
  KEY `ix_community_posts_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `community_reports`;
CREATE TABLE `community_reports` (
  `id` CHAR(36) NOT NULL,
  `reporter_uid` VARCHAR(191) NULL,
  `post_id` CHAR(36) NULL,
  `comment_id` CHAR(36) NULL,
  `reason` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'open',
  `resolved_by` VARCHAR(191) NULL,
  `resolved_at` DATETIME NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_community_reports_post_id` (`post_id`),
  KEY `ix_community_reports_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `content_reports`;
CREATE TABLE `content_reports` (
  `id` CHAR(36) NOT NULL,
  `reporter_uid` VARCHAR(191) NULL,
  `content_type` VARCHAR(191) NULL,
  `content_id` VARCHAR(191) NULL,
  `reported_uid` VARCHAR(191) NULL,
  `reason` MEDIUMTEXT NULL,
  `details` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'open',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_content_reports_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `country_communities`;
CREATE TABLE `country_communities` (
  `code` VARCHAR(191) NOT NULL,
  `name` VARCHAR(191) NULL,
  `flag` MEDIUMTEXT NULL,
  `region` MEDIUMTEXT NULL,
  `member_count` INT NULL DEFAULT 0,
  `post_count` INT NULL DEFAULT 0,
  `is_active` TINYINT(1) NULL DEFAULT 1,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`code`),
  KEY `ix_country_communities_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `course_catalog`;
CREATE TABLE `course_catalog` (
  `course_key` VARCHAR(191) NOT NULL,
  `title` MEDIUMTEXT NULL,
  `category` VARCHAR(191) NULL,
  `first_seen` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`course_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `course_content_overrides`;
CREATE TABLE `course_content_overrides` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `course_key` VARCHAR(191) NULL,
  `field` MEDIUMTEXT NULL,
  `original_text` MEDIUMTEXT NULL,
  `edited_text` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL,
  `reviewer_name` MEDIUMTEXT NULL,
  `stream_key` VARCHAR(191) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_course_content_overrides_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `course_lessons`;
CREATE TABLE `course_lessons` (
  `id` CHAR(36) NOT NULL,
  `course_id` CHAR(36) NULL,
  `module_id` CHAR(36) NULL,
  `title` MEDIUMTEXT NULL,
  `content` MEDIUMTEXT NULL,
  `video_url` MEDIUMTEXT NULL,
  `duration_seconds` INT NULL DEFAULT 0,
  `sort_order` INT NULL DEFAULT 0,
  `status` VARCHAR(191) NULL DEFAULT 'published',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_course_lessons_course_id` (`course_id`),
  KEY `ix_course_lessons_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `course_module_counts`;
CREATE TABLE `course_module_counts` (
  `title_key` VARCHAR(191) NOT NULL,
  `modules` INT NULL,
  `network` VARCHAR(191) NULL,
  PRIMARY KEY (`title_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `course_modules`;
CREATE TABLE `course_modules` (
  `id` CHAR(36) NOT NULL,
  `course_id` CHAR(36) NULL,
  `title` MEDIUMTEXT NULL,
  `description` MEDIUMTEXT NULL,
  `sort_order` INT NULL DEFAULT 0,
  `status` VARCHAR(191) NULL DEFAULT 'published',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_course_modules_course_id` (`course_id`),
  KEY `ix_course_modules_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `course_progress`;
CREATE TABLE `course_progress` (
  `id` CHAR(36) NOT NULL,
  `uid` VARCHAR(191) NULL,
  `course_id` VARCHAR(191) NULL,
  `lesson_id` VARCHAR(191) NULL,
  `video_time` DECIMAL(20,4) NULL DEFAULT 0,
  `completed` TINYINT(1) NULL DEFAULT 0,
  `phase` MEDIUMTEXT NULL,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `language` VARCHAR(191) NULL DEFAULT 'en',
  `module_id` VARCHAR(191) NULL,
  `last_seen_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `capstone_completed` TINYINT(1) NULL DEFAULT 0,
  `capstone_completed_at` DATETIME NULL,
  `evidence_note` MEDIUMTEXT NULL,
  `final_quiz_passed` TINYINT(1) NULL DEFAULT 0,
  `progress_percent` DECIMAL(20,4) NULL DEFAULT 0,
  `note` MEDIUMTEXT NULL,
  `course_title` MEDIUMTEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_course_progress_uid` (`uid`),
  KEY `ix_course_progress_course_id` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `courses`;
CREATE TABLE `courses` (
  `id` CHAR(36) NOT NULL,
  `title` MEDIUMTEXT NULL,
  `description` MEDIUMTEXT NULL,
  `phase` MEDIUMTEXT NULL,
  `track` MEDIUMTEXT NULL,
  `level` MEDIUMTEXT NULL,
  `cover_url` MEDIUMTEXT NULL,
  `video_url` MEDIUMTEXT NULL,
  `audio_url` MEDIUMTEXT NULL,
  `content_html` MEDIUMTEXT NULL,
  `resources` LONGTEXT NULL,
  `translations` LONGTEXT NULL,
  `published` TINYINT(1) NULL DEFAULT 0,
  `order_index` INT NULL DEFAULT 0,
  `uploaded_by` VARCHAR(191) NULL,
  `assigned_tutor` MEDIUMTEXT NULL,
  `quiz_id` CHAR(36) NULL,
  `tags` LONGTEXT NULL,
  `duration_mins` INT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `type` VARCHAR(191) NULL DEFAULT 'video',
  `language` VARCHAR(191) NULL DEFAULT 'en',
  `pathway` VARCHAR(191) NULL DEFAULT 'gmn',
  `text_content` MEDIUMTEXT NULL,
  `file_url` MEDIUMTEXT NULL,
  `thumbnail_url` MEDIUMTEXT NULL,
  `duration` INT NULL DEFAULT 0,
  `slug` VARCHAR(191) NULL,
  `uploaded_by_uid` VARCHAR(191) NULL,
  `uploader_name` MEDIUMTEXT NULL,
  `builtin_id` VARCHAR(191) NULL,
  `view_count` INT NULL DEFAULT 0,
  `url` MEDIUMTEXT NULL,
  `curriculum_json` LONGTEXT NULL,
  `course_objectives` LONGTEXT NULL,
  `learning_outcomes` LONGTEXT NULL,
  `required_tools` LONGTEXT NULL,
  `target_audience` MEDIUMTEXT NULL,
  `practical_relevance` MEDIUMTEXT NULL,
  `global_best_practice_alignment` MEDIUMTEXT NULL,
  `pass_criteria` MEDIUMTEXT NULL,
  `certificate_eligibility_statement` MEDIUMTEXT NULL,
  `suggested_certificate_title` MEDIUMTEXT NULL,
  `recommended_app_display_format` LONGTEXT NULL,
  `capstone` LONGTEXT NULL,
  `final_assessment` LONGTEXT NULL,
  `module_count` INT NULL,
  `lesson_count` INT NULL,
  `curriculum_version` MEDIUMTEXT NULL,
  `category` VARCHAR(191) NULL,
  `status` VARCHAR(191) NULL DEFAULT 'draft',
  `archived_at` DATETIME NULL,
  `duplicate_of` MEDIUMTEXT NULL,
  `modules` LONGTEXT NULL,
  `lessons` LONGTEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_courses_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `data_governance_log`;
CREATE TABLE `data_governance_log` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `run_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `table_name` MEDIUMTEXT NULL,
  `row_ref` VARCHAR(191) NULL,
  `field` MEDIUMTEXT NULL,
  `old_value` MEDIUMTEXT NULL,
  `new_value` MEDIUMTEXT NULL,
  `rule` MEDIUMTEXT NULL,
  `actor` MEDIUMTEXT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `direct_messages`;
CREATE TABLE `direct_messages` (
  `id` CHAR(36) NOT NULL,
  `from_user_id` CHAR(36) NULL,
  `from_user_name` MEDIUMTEXT NULL,
  `from_user_avatar` MEDIUMTEXT NULL,
  `to_user_id` CHAR(36) NULL,
  `content` MEDIUMTEXT NULL,
  `read_at` DATETIME NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `from_name` VARCHAR(255) NULL,
  `from_avatar` MEDIUMTEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_direct_messages_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `email_log`;
CREATE TABLE `email_log` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `kind` VARCHAR(191) NULL,
  `to_email` MEDIUMTEXT NULL,
  `subject` MEDIUMTEXT NULL,
  `sender` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL,
  `provider_id` VARCHAR(191) NULL,
  `error` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_email_log_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `expert_mentors`;
CREATE TABLE `expert_mentors` (
  `id` CHAR(36) NOT NULL,
  `name` VARCHAR(191) NULL,
  `title` MEDIUMTEXT NULL,
  `gender` MEDIUMTEXT NULL,
  `expertise` LONGTEXT NULL,
  `phone` VARCHAR(191) NULL,
  `whatsapp_url` MEDIUMTEXT NULL,
  `call_url` MEDIUMTEXT NULL,
  `avatar_url` MEDIUMTEXT NULL,
  `active` TINYINT(1) NULL DEFAULT 1,
  `sort_order` INT NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_expert_mentors_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `feature_flags`;
CREATE TABLE `feature_flags` (
  `key` VARCHAR(191) NOT NULL,
  `enabled` TINYINT(1) NULL DEFAULT 1,
  `notes` MEDIUMTEXT NULL,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `forum_comments`;
CREATE TABLE `forum_comments` (
  `id` CHAR(36) NOT NULL,
  `post_id` CHAR(36) NULL,
  `user_id` CHAR(36) NULL,
  `author_name` MEDIUMTEXT NULL,
  `author_avatar` MEDIUMTEXT NULL,
  `content` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `uid` VARCHAR(191) NULL,
  `status` VARCHAR(191) NULL DEFAULT 'active',
  `hidden_at` DATETIME NULL,
  `hidden_by` VARCHAR(191) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_forum_comments_post_id` (`post_id`),
  KEY `ix_forum_comments_user_id` (`user_id`),
  KEY `ix_forum_comments_created_at` (`created_at`),
  KEY `ix_forum_comments_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `forum_likes`;
CREATE TABLE `forum_likes` (
  `id` CHAR(36) NOT NULL,
  `post_id` CHAR(36) NULL,
  `user_id` CHAR(36) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `uid` VARCHAR(191) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_forum_likes_post_id` (`post_id`),
  KEY `ix_forum_likes_user_id` (`user_id`),
  KEY `ix_forum_likes_created_at` (`created_at`),
  KEY `ix_forum_likes_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `forum_posts`;
CREATE TABLE `forum_posts` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NULL,
  `author_name` MEDIUMTEXT NULL,
  `author_avatar` MEDIUMTEXT NULL,
  `content` MEDIUMTEXT NULL,
  `rich_html` MEDIUMTEXT NULL,
  `image_url` MEDIUMTEXT NULL,
  `category` VARCHAR(191) NULL DEFAULT 'general',
  `likes_count` INT NULL DEFAULT 0,
  `comments_count` INT NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `status` VARCHAR(255) NULL DEFAULT 'published',
  `title` VARCHAR(255) NULL,
  `reply_count` INT NULL DEFAULT 0,
  `like_count` INT NULL DEFAULT 0,
  `uid` VARCHAR(191) NULL,
  `pinned` TINYINT(1) NULL DEFAULT 0,
  `locked` TINYINT(1) NULL DEFAULT 0,
  `hidden_at` DATETIME NULL,
  `hidden_by` VARCHAR(191) NULL,
  `comment_count` INT NULL DEFAULT 0,
  `channel` MEDIUMTEXT NULL,
  `body` MEDIUMTEXT NULL,
  `is_admin` TINYINT(1) NULL DEFAULT 0,
  `hidden` TINYINT(1) NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_forum_posts_user_id` (`user_id`),
  KEY `ix_forum_posts_created_at` (`created_at`),
  KEY `ix_forum_posts_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `forum_reports`;
CREATE TABLE `forum_reports` (
  `id` CHAR(36) NOT NULL,
  `post_id` CHAR(36) NULL,
  `comment_id` CHAR(36) NULL,
  `uid` VARCHAR(191) NULL,
  `reason` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'pending',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_forum_reports_post_id` (`post_id`),
  KEY `ix_forum_reports_uid` (`uid`),
  KEY `ix_forum_reports_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `friend_requests`;
CREATE TABLE `friend_requests` (
  `id` CHAR(36) NOT NULL,
  `from_user_id` CHAR(36) NULL,
  `from_user_name` MEDIUMTEXT NULL,
  `from_user_avatar` MEDIUMTEXT NULL,
  `to_user_id` CHAR(36) NULL,
  `status` VARCHAR(191) NULL DEFAULT 'pending',
  `message` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` DATETIME NULL,
  `from_name` VARCHAR(255) NULL,
  `from_avatar` MEDIUMTEXT NULL,
  `from_uid` VARCHAR(191) NULL,
  `to_uid` VARCHAR(191) NULL,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_friend_requests_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `friendships`;
CREATE TABLE `friendships` (
  `id` CHAR(36) NOT NULL,
  `user_a` CHAR(36) NULL,
  `user_b` CHAR(36) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_friendships_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `growth_profiles`;
CREATE TABLE `growth_profiles` (
  `uid` CHAR(36) NOT NULL,
  `points` INT NULL DEFAULT 0,
  `level_name` MEDIUMTEXT NULL,
  `badges` LONGTEXT NULL,
  `streak_days` INT NULL DEFAULT 0,
  `onboarding` INT NULL DEFAULT 0,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `growth_transactions`;
CREATE TABLE `growth_transactions` (
  `id` CHAR(36) NOT NULL,
  `uid` CHAR(36) NULL,
  `points` INT NULL,
  `reason` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_growth_transactions_uid` (`uid`),
  KEY `ix_growth_transactions_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `impact_metrics`;
CREATE TABLE `impact_metrics` (
  `id` INT NOT NULL DEFAULT 1,
  `women_trained` INT NULL DEFAULT 0,
  `young_women_reached` INT NULL DEFAULT 0,
  `children_reached` INT NULL DEFAULT 0,
  `countries_covered` INT NULL DEFAULT 0,
  `communities_reached` INT NULL DEFAULT 0,
  `notes` MEDIUMTEXT NULL,
  `updated_by` VARCHAR(191) NULL,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `invites`;
CREATE TABLE `invites` (
  `id` CHAR(36) NOT NULL,
  `email` VARCHAR(191) NULL,
  `token` VARCHAR(191) NULL,
  `status` VARCHAR(191) NULL DEFAULT 'pending',
  `invited_by` VARCHAR(191) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_invites_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `journals`;
CREATE TABLE `journals` (
  `id` CHAR(36) NOT NULL,
  `uid` CHAR(36) NULL,
  `title` MEDIUMTEXT NULL,
  `body` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_journals_uid` (`uid`),
  KEY `ix_journals_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `kpi_targets`;
CREATE TABLE `kpi_targets` (
  `kpi` VARCHAR(191) NOT NULL,
  `target` DECIMAL(20,4) NULL,
  `unit` MEDIUMTEXT NULL,
  `period` MEDIUMTEXT NULL,
  `approved_by` VARCHAR(191) NULL,
  `approved_at` DATETIME NULL,
  `note` MEDIUMTEXT NULL,
  PRIMARY KEY (`kpi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `leader_reports`;
CREATE TABLE `leader_reports` (
  `id` CHAR(36) NOT NULL,
  `title` MEDIUMTEXT NULL,
  `department` MEDIUMTEXT NULL,
  `leader_uid` VARCHAR(191) NULL,
  `leader_name` MEDIUMTEXT NULL,
  `period` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'pending',
  `body` MEDIUMTEXT NULL,
  `attachment_url` MEDIUMTEXT NULL,
  `due_date` DATE NULL,
  `submitted_at` DATETIME NULL,
  `reviewed_by` VARCHAR(191) NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_leader_reports_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `leadership_roles`;
CREATE TABLE `leadership_roles` (
  `id` CHAR(36) NOT NULL,
  `uid` CHAR(36) NULL,
  `full_name` MEDIUMTEXT NULL,
  `network` VARCHAR(191) NULL,
  `role` VARCHAR(191) NULL,
  `country` MEDIUMTEXT NULL,
  `state_region` MEDIUMTEXT NULL,
  `chapter` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'active',
  `appointed_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_leadership_roles_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `learner_attribution`;
CREATE TABLE `learner_attribution` (
  `uid` VARCHAR(191) NOT NULL,
  `source` MEDIUMTEXT NULL,
  `medium` MEDIUMTEXT NULL,
  `campaign` MEDIUMTEXT NULL,
  `content` MEDIUMTEXT NULL,
  `term` MEDIUMTEXT NULL,
  `referral_code_used` MEDIUMTEXT NULL,
  `referred_by` VARCHAR(191) NULL,
  `referrer` MEDIUMTEXT NULL,
  `landing_page` MEDIUMTEXT NULL,
  `country` MEDIUMTEXT NULL,
  `language` VARCHAR(191) NULL,
  `first_seen_at` DATETIME NULL,
  `captured_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `learner_followups`;
CREATE TABLE `learner_followups` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `uid` VARCHAR(191) NULL,
  `kind` VARCHAR(191) NULL,
  `course_key` VARCHAR(191) NULL,
  `answers` LONGTEXT NULL,
  `consent` TINYINT(1) NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_learner_followups_uid` (`uid`),
  KEY `ix_learner_followups_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `learning_events`;
CREATE TABLE `learning_events` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `uid` VARCHAR(191) NULL,
  `kind` VARCHAR(191) NULL,
  `ref` MEDIUMTEXT NULL,
  `meta` LONGTEXT NULL,
  `source` MEDIUMTEXT NULL,
  `approx` TINYINT(1) NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_learning_events_uid` (`uid`),
  KEY `ix_learning_events_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `lesson_completions`;
CREATE TABLE `lesson_completions` (
  `id` CHAR(36) NOT NULL,
  `uid` VARCHAR(191) NULL,
  `course_id` VARCHAR(191) NULL,
  `lesson_id` VARCHAR(191) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_lesson_completions_uid` (`uid`),
  KEY `ix_lesson_completions_course_id` (`course_id`),
  KEY `ix_lesson_completions_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `lesson_progress`;
CREATE TABLE `lesson_progress` (
  `id` CHAR(36) NOT NULL,
  `uid` VARCHAR(191) NULL,
  `course_id` CHAR(36) NULL,
  `completed` TINYINT(1) NULL DEFAULT 0,
  `completed_at` DATETIME NULL,
  `progress_pct` INT NULL DEFAULT 0,
  `time_spent_s` INT NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_lesson_progress_uid` (`uid`),
  KEY `ix_lesson_progress_course_id` (`course_id`),
  KEY `ix_lesson_progress_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `marketplace_items`;
CREATE TABLE `marketplace_items` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NULL,
  `seller_name` MEDIUMTEXT NULL,
  `seller_avatar` MEDIUMTEXT NULL,
  `title` MEDIUMTEXT NULL,
  `description` MEDIUMTEXT NULL,
  `price` DECIMAL(20,4) NULL,
  `currency` MEDIUMTEXT NULL,
  `category` VARCHAR(191) NULL DEFAULT 'general',
  `image_url` MEDIUMTEXT NULL,
  `contact_info` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'active',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `sold` TINYINT(1) NULL DEFAULT 0,
  `sold_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_marketplace_items_user_id` (`user_id`),
  KEY `ix_marketplace_items_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `marketplace_listings`;
CREATE TABLE `marketplace_listings` (
  `id` CHAR(36) NOT NULL,
  `seller_id` CHAR(36) NULL,
  `title` VARCHAR(255) NULL,
  `description` MEDIUMTEXT NULL,
  `price` DECIMAL(20,4) NULL,
  `image_url` MEDIUMTEXT NULL,
  `seller_name` VARCHAR(255) NULL,
  `category` VARCHAR(255) NULL,
  `status` VARCHAR(255) NULL DEFAULT 'active',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_marketplace_listings_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `member_actions`;
CREATE TABLE `member_actions` (
  `id` CHAR(36) NOT NULL,
  `uid` VARCHAR(191) NULL,
  `action_type` VARCHAR(191) NULL,
  `description` MEDIUMTEXT NULL,
  `contact_name` MEDIUMTEXT NULL,
  `proof_url` MEDIUMTEXT NULL,
  `completed_on` DATE NULL,
  `status` VARCHAR(191) NULL DEFAULT 'submitted',
  `reviewed_by` VARCHAR(191) NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_member_actions_uid` (`uid`),
  KEY `ix_member_actions_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `member_activation`;
CREATE TABLE `member_activation` (
  `uid` VARCHAR(191) NOT NULL,
  `programme` MEDIUMTEXT NULL,
  `application_completed` TINYINT(1) NULL DEFAULT 0,
  `app_registered` TINYINT(1) NULL DEFAULT 0,
  `profile_completed` TINYINT(1) NULL DEFAULT 0,
  `welcome_viewed` TINYINT(1) NULL DEFAULT 0,
  `orientation_attended` TINYINT(1) NULL DEFAULT 0,
  `country_community_joined` TINYINT(1) NULL DEFAULT 0,
  `foundation_course_started` TINYINT(1) NULL DEFAULT 0,
  `foundation_course_done` TINYINT(1) NULL DEFAULT 0,
  `reflection_submitted` TINYINT(1) NULL DEFAULT 0,
  `first_action_done` TINYINT(1) NULL DEFAULT 0,
  `current_phase` INT NULL DEFAULT 1,
  `progress_pct` INT NULL DEFAULT 0,
  `classification` MEDIUMTEXT NULL,
  `leadership_prospect` TINYINT(1) NULL DEFAULT 0,
  `leadership_stage` MEDIUMTEXT NULL,
  `admin_notes` MEDIUMTEXT NULL,
  `classified_by` VARCHAR(191) NULL,
  `classified_at` DATETIME NULL,
  `started_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `member_reflections`;
CREATE TABLE `member_reflections` (
  `uid` VARCHAR(191) NOT NULL,
  `why_joined` MEDIUMTEXT NULL,
  `service_area` MEDIUMTEXT NULL,
  `passion_issue` MEDIUMTEXT NULL,
  `contribution` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`uid`),
  KEY `ix_member_reflections_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `membership_interest`;
CREATE TABLE `membership_interest` (
  `id` CHAR(36) NOT NULL,
  `full_name` MEDIUMTEXT NULL,
  `email` VARCHAR(191) NULL,
  `phone` VARCHAR(191) NULL,
  `age_bracket` MEDIUMTEXT NULL,
  `country` MEDIUMTEXT NULL,
  `region` MEDIUMTEXT NULL,
  `city` MEDIUMTEXT NULL,
  `network` VARCHAR(191) NULL,
  `interest` MEDIUMTEXT NULL,
  `heard_from` MEDIUMTEXT NULL,
  `utm_source` MEDIUMTEXT NULL,
  `utm_medium` MEDIUMTEXT NULL,
  `utm_campaign` MEDIUMTEXT NULL,
  `utm_term` MEDIUMTEXT NULL,
  `referrer` MEDIUMTEXT NULL,
  `landing_page` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `dob` DATE NULL,
  `language` VARCHAR(191) NULL,
  `state` MEDIUMTEXT NULL,
  `lga` MEDIUMTEXT NULL,
  `current_status` VARCHAR(191) NULL,
  `lc_interest` MEDIUMTEXT NULL,
  `lc_preference` MEDIUMTEXT NULL,
  `consent_correct` TINYINT(1) NULL,
  `consent_updates` TINYINT(1) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_membership_interest_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mentor_assignments`;
CREATE TABLE `mentor_assignments` (
  `id` CHAR(36) NOT NULL,
  `mentor_uid` VARCHAR(191) NULL,
  `mentee_uid` VARCHAR(191) NULL,
  `programme` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'active',
  `notes` MEDIUMTEXT NULL,
  `assigned_by` VARCHAR(191) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_mentor_assignments_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mentor_checkins`;
CREATE TABLE `mentor_checkins` (
  `id` CHAR(36) NOT NULL,
  `uid` CHAR(36) NULL,
  `note` MEDIUMTEXT NULL,
  `mood` INT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_mentor_checkins_uid` (`uid`),
  KEY `ix_mentor_checkins_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mentor_memory`;
CREATE TABLE `mentor_memory` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NULL,
  `fact` MEDIUMTEXT NULL,
  `category` VARCHAR(191) NULL DEFAULT 'general',
  `importance` INT NULL DEFAULT 5,
  `source` MEDIUMTEXT NULL,
  `last_referenced` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_mentor_memory_user_id` (`user_id`),
  KEY `ix_mentor_memory_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mentor_messages`;
CREATE TABLE `mentor_messages` (
  `id` CHAR(36) NOT NULL,
  `request_id` CHAR(36) NULL,
  `sender_uid` VARCHAR(191) NULL,
  `body` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_mentor_messages_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mentor_profiles`;
CREATE TABLE `mentor_profiles` (
  `uid` CHAR(36) NOT NULL,
  `goal` MEDIUMTEXT NULL,
  `level` MEDIUMTEXT NULL,
  `hours_per_week` INT NULL,
  `style` MEDIUMTEXT NULL,
  `motivation` INT NULL DEFAULT 3,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `roadmap_json` MEDIUMTEXT NULL,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mentor_requests`;
CREATE TABLE `mentor_requests` (
  `id` CHAR(36) NOT NULL,
  `student_uid` VARCHAR(191) NULL,
  `mentor_uid` VARCHAR(191) NULL,
  `area` MEDIUMTEXT NULL,
  `message` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'pending',
  `notes` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `mentor_display_name` MEDIUMTEXT NULL,
  `mentor_avatar_url` MEDIUMTEXT NULL,
  `contact_phone` MEDIUMTEXT NULL,
  `whatsapp_url` MEDIUMTEXT NULL,
  `call_url` MEDIUMTEXT NULL,
  `ai_context` LONGTEXT NULL,
  `uid` CHAR(36) NULL,
  `name` VARCHAR(191) NULL,
  `topic` MEDIUMTEXT NULL,
  `note` MEDIUMTEXT NULL,
  `mentor` MEDIUMTEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_mentor_requests_student_uid` (`student_uid`),
  KEY `ix_mentor_requests_created_at` (`created_at`),
  KEY `ix_mentor_requests_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mentor_sessions`;
CREATE TABLE `mentor_sessions` (
  `user_id` CHAR(36) NOT NULL,
  `user_name` MEDIUMTEXT NULL,
  `user_email` MEDIUMTEXT NULL,
  `language` VARCHAR(191) NULL DEFAULT 'en',
  `last_message` MEDIUMTEXT NULL,
  `last_topic` MEDIUMTEXT NULL,
  `last_active` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `followup_sent_at` DATETIME NULL,
  `followup_count` INT NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `ix_mentor_sessions_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mentorship_log`;
CREATE TABLE `mentorship_log` (
  `id` CHAR(36) NOT NULL,
  `uid` CHAR(36) NULL,
  `kind` VARCHAR(191) NULL DEFAULT 'ai-mentor',
  `summary` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_mentorship_log_uid` (`uid`),
  KEY `ix_mentorship_log_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` CHAR(36) NOT NULL,
  `from_user_id` CHAR(36) NULL,
  `to_user_id` CHAR(36) NULL,
  `content` MEDIUMTEXT NULL,
  `read` TINYINT(1) NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `to_uid` CHAR(36) NULL,
  `from_uid` CHAR(36) NULL,
  `from_name` MEDIUMTEXT NULL,
  `body` MEDIUMTEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_messages_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mp_enquiries`;
CREATE TABLE `mp_enquiries` (
  `id` CHAR(36) NOT NULL,
  `item_id` CHAR(36) NULL,
  `seller_id` CHAR(36) NULL,
  `buyer_uid` CHAR(36) NULL,
  `buyer_name` MEDIUMTEXT NULL,
  `buyer_contact` MEDIUMTEXT NULL,
  `kind` VARCHAR(191) NULL DEFAULT 'hire',
  `quantity` INT NULL,
  `budget` MEDIUMTEXT NULL,
  `message` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'new',
  `seller_note` MEDIUMTEXT NULL,
  `review_token` CHAR(36) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_mp_enquiries_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mp_listings`;
CREATE TABLE `mp_listings` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NULL,
  `seller_name` MEDIUMTEXT NULL,
  `seller_avatar` MEDIUMTEXT NULL,
  `title` MEDIUMTEXT NULL,
  `description` MEDIUMTEXT NULL,
  `listing_type` VARCHAR(191) NULL DEFAULT 'service',
  `category` VARCHAR(191) NULL DEFAULT 'other',
  `price` DECIMAL(20,4) NULL,
  `currency` MEDIUMTEXT NULL,
  `price_type` VARCHAR(191) NULL DEFAULT 'fixed',
  `delivery` MEDIUMTEXT NULL,
  `country` MEDIUMTEXT NULL,
  `city` MEDIUMTEXT NULL,
  `images` LONGTEXT NULL,
  `image_url` MEDIUMTEXT NULL,
  `tags` LONGTEXT NULL,
  `whatsapp` MEDIUMTEXT NULL,
  `contact_info` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'active',
  `sold` TINYINT(1) NULL DEFAULT 0,
  `sold_at` DATETIME NULL,
  `views` INT NULL DEFAULT 0,
  `enquiries` INT NULL DEFAULT 0,
  `rating_avg` DECIMAL(20,4) NULL DEFAULT 0,
  `rating_count` INT NULL DEFAULT 0,
  `featured` TINYINT(1) NULL DEFAULT 0,
  `moderation_note` MEDIUMTEXT NULL,
  `legacy_id` CHAR(36) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `published_at` DATETIME NULL,
  `boost_until` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_mp_listings_user_id` (`user_id`),
  KEY `ix_mp_listings_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mp_reviews`;
CREATE TABLE `mp_reviews` (
  `id` CHAR(36) NOT NULL,
  `item_id` CHAR(36) NULL,
  `seller_id` CHAR(36) NULL,
  `enquiry_id` CHAR(36) NULL,
  `reviewer_uid` CHAR(36) NULL,
  `reviewer_name` MEDIUMTEXT NULL,
  `rating` INT NULL,
  `comment` MEDIUMTEXT NULL,
  `verified` TINYINT(1) NULL DEFAULT 0,
  `status` VARCHAR(191) NULL DEFAULT 'visible',
  `seller_reply` MEDIUMTEXT NULL,
  `seller_replied_at` DATETIME NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_mp_reviews_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `networks`;
CREATE TABLE `networks` (
  `id` CHAR(36) NOT NULL,
  `code` VARCHAR(191) NULL,
  `name` VARCHAR(191) NULL,
  `age_min` INT NULL,
  `age_max` INT NULL,
  `brand_color` MEDIUMTEXT NULL,
  `active` TINYINT(1) NULL DEFAULT 1,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_networks_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NULL,
  `kind` VARCHAR(191) NULL,
  `title` MEDIUMTEXT NULL,
  `body` MEDIUMTEXT NULL,
  `link_url` MEDIUMTEXT NULL,
  `ref_id` CHAR(36) NULL,
  `read_at` DATETIME NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `type` VARCHAR(255) NULL,
  `link` MEDIUMTEXT NULL,
  `unread` TINYINT(1) NULL DEFAULT 1,
  `from_user_name` VARCHAR(255) NULL,
  `from_user_avatar` MEDIUMTEXT NULL,
  `from_user_id` CHAR(36) NULL,
  `uid` VARCHAR(191) NULL,
  `text` MEDIUMTEXT NULL,
  `icon` MEDIUMTEXT NULL,
  `read` TINYINT(1) NULL DEFAULT 0,
  `from_uid` VARCHAR(191) NULL,
  `from_name` MEDIUMTEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_notifications_user_id` (`user_id`),
  KEY `ix_notifications_created_at` (`created_at`),
  KEY `ix_notifications_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ops_leaders`;
CREATE TABLE `ops_leaders` (
  `id` CHAR(36) NOT NULL,
  `full_name` MEDIUMTEXT NULL,
  `email` VARCHAR(191) NULL,
  `phone` VARCHAR(191) NULL,
  `network` VARCHAR(191) NULL,
  `role` VARCHAR(191) NULL,
  `country` MEDIUMTEXT NULL,
  `state_region` MEDIUMTEXT NULL,
  `source` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'active',
  `notes` MEDIUMTEXT NULL,
  `created_by` VARCHAR(191) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ops_leaders_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ops_message_recipients`;
CREATE TABLE `ops_message_recipients` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `message_id` CHAR(36) NULL,
  `leader_key` VARCHAR(191) NULL,
  `name` VARCHAR(191) NULL,
  `email` VARCHAR(191) NULL,
  `phone` VARCHAR(191) NULL,
  `role` VARCHAR(191) NULL,
  `country` MEDIUMTEXT NULL,
  `uid` CHAR(36) NULL,
  `email_status` VARCHAR(191) NULL,
  `inapp_status` VARCHAR(191) NULL,
  `push_status` VARCHAR(191) NULL,
  `error` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ops_message_recipients_uid` (`uid`),
  KEY `ix_ops_message_recipients_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ops_messages`;
CREATE TABLE `ops_messages` (
  `id` CHAR(36) NOT NULL,
  `subject` MEDIUMTEXT NULL,
  `body` MEDIUMTEXT NULL,
  `channels` LONGTEXT NULL,
  `audience` LONGTEXT NULL,
  `recipients` INT NULL DEFAULT 0,
  `email_sent` INT NULL DEFAULT 0,
  `inapp_sent` INT NULL DEFAULT 0,
  `push_sent` INT NULL DEFAULT 0,
  `failed` INT NULL DEFAULT 0,
  `is_test` TINYINT(1) NULL DEFAULT 0,
  `created_by` VARCHAR(191) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ops_messages_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `password_reset_log`;
CREATE TABLE `password_reset_log` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `identifier` MEDIUMTEXT NULL,
  `uid` CHAR(36) NULL,
  `method` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL,
  `ip` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_password_reset_log_uid` (`uid`),
  KEY `ix_password_reset_log_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `points_redemptions`;
CREATE TABLE `points_redemptions` (
  `id` CHAR(36) NOT NULL,
  `uid` CHAR(36) NULL,
  `kind` VARCHAR(191) NULL,
  `points` INT NULL,
  `listing_id` CHAR(36) NULL,
  `headline` MEDIUMTEXT NULL,
  `details` MEDIUMTEXT NULL,
  `platforms` LONGTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'pending',
  `starts_at` DATETIME NULL,
  `ends_at` DATETIME NULL,
  `admin_note` MEDIUMTEXT NULL,
  `post_url` MEDIUMTEXT NULL,
  `decided_by` CHAR(36) NULL,
  `decided_at` DATETIME NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_points_redemptions_uid` (`uid`),
  KEY `ix_points_redemptions_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `pokes`;
CREATE TABLE `pokes` (
  `id` CHAR(36) NOT NULL,
  `from_user_id` CHAR(36) NULL,
  `to_user_id` CHAR(36) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_pokes_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `push_subscriptions`;
CREATE TABLE `push_subscriptions` (
  `id` CHAR(36) NOT NULL,
  `uid` CHAR(36) NULL,
  `endpoint` MEDIUMTEXT NULL,
  `p256dh` MEDIUMTEXT NULL,
  `auth` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_push_subscriptions_uid` (`uid`),
  KEY `ix_push_subscriptions_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `quiz_attempts`;
CREATE TABLE `quiz_attempts` (
  `id` CHAR(36) NOT NULL,
  `uid` VARCHAR(191) NULL,
  `quiz_id` CHAR(36) NULL,
  `course_id` CHAR(36) NULL,
  `score` INT NULL,
  `passed` TINYINT(1) NULL,
  `answers` LONGTEXT NULL,
  `attempted_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_quiz_attempts_uid` (`uid`),
  KEY `ix_quiz_attempts_course_id` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `quiz_questions`;
CREATE TABLE `quiz_questions` (
  `id` CHAR(36) NOT NULL,
  `quiz_id` CHAR(36) NULL,
  `question` MEDIUMTEXT NULL,
  `options` LONGTEXT NULL,
  `correct_idx` INT NULL,
  `explanation` MEDIUMTEXT NULL,
  `order_index` INT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `quizzes`;
CREATE TABLE `quizzes` (
  `id` CHAR(36) NOT NULL,
  `course_id` CHAR(36) NULL,
  `title` MEDIUMTEXT NULL,
  `pass_score` INT NULL DEFAULT 70,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_quizzes_course_id` (`course_id`),
  KEY `ix_quizzes_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `radio`;
CREATE TABLE `radio` (
  `id` CHAR(36) NOT NULL,
  `title` MEDIUMTEXT NULL,
  `description` MEDIUMTEXT NULL,
  `category` VARCHAR(191) NULL,
  `audio_url` MEDIUMTEXT NULL,
  `thumbnail_url` MEDIUMTEXT NULL,
  `duration` MEDIUMTEXT NULL,
  `listen_count` INT NULL DEFAULT 0,
  `published` TINYINT(1) NULL DEFAULT 1,
  `uploaded_by` VARCHAR(191) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_radio_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `radio_programs`;
CREATE TABLE `radio_programs` (
  `id` CHAR(36) NOT NULL,
  `name` VARCHAR(191) NULL,
  `short_name` MEDIUMTEXT NULL,
  `tagline` MEDIUMTEXT NULL,
  `description` MEDIUMTEXT NULL,
  `stream_url` MEDIUMTEXT NULL,
  `cover_color` MEDIUMTEXT NULL,
  `icon_text` MEDIUMTEXT NULL,
  `category` VARCHAR(191) NULL DEFAULT 'talk',
  `published` TINYINT(1) NULL DEFAULT 1,
  `order_index` INT NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_radio_programs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `reflections`;
CREATE TABLE `reflections` (
  `id` CHAR(36) NOT NULL,
  `uid` CHAR(36) NULL,
  `course_id` VARCHAR(191) NULL,
  `context` MEDIUMTEXT NULL,
  `body` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_reflections_uid` (`uid`),
  KEY `ix_reflections_course_id` (`course_id`),
  KEY `ix_reflections_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `reminder_log`;
CREATE TABLE `reminder_log` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `uid` VARCHAR(191) NULL,
  `kind` VARCHAR(191) NULL,
  `email_status` VARCHAR(191) NULL,
  `push_status` VARCHAR(191) NULL,
  `inapp_status` VARCHAR(191) NULL,
  `detail` MEDIUMTEXT NULL,
  `run_id` VARCHAR(191) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `provider_id` VARCHAR(191) NULL,
  `push_detail` MEDIUMTEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_reminder_log_uid` (`uid`),
  KEY `ix_reminder_log_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `retention_audit`;
CREATE TABLE `retention_audit` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `uid` VARCHAR(191) NULL,
  `field` MEDIUMTEXT NULL,
  `old_value` MEDIUMTEXT NULL,
  `new_value` MEDIUMTEXT NULL,
  `note` MEDIUMTEXT NULL,
  `by_uid` VARCHAR(191) NULL,
  `by_name` MEDIUMTEXT NULL,
  `at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_retention_audit_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `retention_cases`;
CREATE TABLE `retention_cases` (
  `uid` VARCHAR(191) NOT NULL,
  `owner_name` MEDIUMTEXT NULL,
  `owner_email` MEDIUMTEXT NULL,
  `status_override` MEDIUMTEXT NULL,
  `override_note` MEDIUMTEXT NULL,
  `override_by` VARCHAR(191) NULL,
  `override_at` DATETIME NULL,
  `reason_code` VARCHAR(191) NULL,
  `technical_blocker` MEDIUMTEXT NULL,
  `next_action` MEDIUMTEXT NULL,
  `next_action_due` DATE NULL,
  `recovery_status` VARCHAR(191) NULL DEFAULT 'none',
  `recovery_plan` LONGTEXT NULL,
  `recovery_started_at` DATETIME NULL,
  `recovery_achieved_at` DATETIME NULL,
  `final_outcome` MEDIUMTEXT NULL,
  `updated_by` VARCHAR(191) NULL,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `retention_interventions`;
CREATE TABLE `retention_interventions` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `uid` VARCHAR(191) NULL,
  `risk_status` VARCHAR(191) NULL,
  `trigger_date` DATE NULL,
  `last_meaningful` DATETIME NULL,
  `reason_code` VARCHAR(191) NULL,
  `channel` MEDIUMTEXT NULL,
  `contact_result` MEDIUMTEXT NULL,
  `intervention` MEDIUMTEXT NULL,
  `owner_name` MEDIUMTEXT NULL,
  `next_action` MEDIUMTEXT NULL,
  `next_action_due` DATE NULL,
  `outcome` MEDIUMTEXT NULL,
  `created_by` VARCHAR(191) NULL,
  `created_by_name` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_retention_interventions_uid` (`uid`),
  KEY `ix_retention_interventions_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `retention_rules`;
CREATE TABLE `retention_rules` (
  `id` INT NOT NULL DEFAULT 1,
  `amber_days` INT NULL DEFAULT 3,
  `red_days` INT NULL DEFAULT 7,
  `recovered_days` INT NULL DEFAULT 14,
  `grace_days` INT NULL DEFAULT 3,
  `completion_rule` MEDIUMTEXT NULL,
  `note` MEDIUMTEXT NULL,
  `updated_by` VARCHAR(191) NULL,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `learner_card_on` TINYINT(1) NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `retention_status_history`;
CREATE TABLE `retention_status_history` (
  `uid` VARCHAR(191) NOT NULL,
  `day` DATE NOT NULL,
  `status` VARCHAR(191) NULL,
  PRIMARY KEY (`uid`,`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `review_activity`;
CREATE TABLE `review_activity` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `stream_key` VARCHAR(191) NULL,
  `course_id` VARCHAR(191) NULL,
  `field` MEDIUMTEXT NULL,
  `action` MEDIUMTEXT NULL,
  `reviewer_name` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_review_activity_course_id` (`course_id`),
  KEY `ix_review_activity_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `review_course_map`;
CREATE TABLE `review_course_map` (
  `stream_key` VARCHAR(191) NOT NULL,
  `course_key` VARCHAR(191) NOT NULL,
  PRIMARY KEY (`stream_key`,`course_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `review_streams`;
CREATE TABLE `review_streams` (
  `id` CHAR(36) NOT NULL,
  `stream_key` VARCHAR(191) NULL,
  `name` VARCHAR(191) NULL,
  `token` VARCHAR(191) NULL,
  `active` TINYINT(1) NULL DEFAULT 1,
  `expires_at` DATETIME NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_review_streams_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `reward_redemptions`;
CREATE TABLE `reward_redemptions` (
  `id` CHAR(36) NOT NULL,
  `uid` CHAR(36) NULL,
  `reward_id` CHAR(36) NULL,
  `reward_title` MEDIUMTEXT NULL,
  `cost_points` INT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'pending',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_reward_redemptions_uid` (`uid`),
  KEY `ix_reward_redemptions_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rewards`;
CREATE TABLE `rewards` (
  `id` CHAR(36) NOT NULL,
  `title` MEDIUMTEXT NULL,
  `description` MEDIUMTEXT NULL,
  `cost_points` INT NULL DEFAULT 100,
  `active` TINYINT(1) NULL DEFAULT 1,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_rewards_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `role` VARCHAR(191) NOT NULL,
  `permission` VARCHAR(191) NOT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role`,`permission`),
  KEY `ix_role_permissions_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `site_flags`;
CREATE TABLE `site_flags` (
  `key` VARCHAR(191) NOT NULL,
  `value` LONGTEXT NULL,
  `note` MEDIUMTEXT NULL,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `site_i18n`;
CREATE TABLE `site_i18n` (
  `lang` VARCHAR(191) NOT NULL,
  `src_hash` VARCHAR(191) NOT NULL,
  `src` MEDIUMTEXT NULL,
  `text` MEDIUMTEXT NULL,
  `scope` MEDIUMTEXT NULL,
  `engine` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`lang`,`src_hash`),
  KEY `ix_site_i18n_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `support_ai_sessions`;
CREATE TABLE `support_ai_sessions` (
  `id` CHAR(36) NOT NULL,
  `uid` CHAR(36) NULL,
  `messages` LONGTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'open',
  `category` VARCHAR(191) NULL,
  `language` VARCHAR(191) NULL,
  `device` LONGTEXT NULL,
  `escalated_thread` CHAR(36) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_support_ai_sessions_uid` (`uid`),
  KEY `ix_support_ai_sessions_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `support_messages`;
CREATE TABLE `support_messages` (
  `id` CHAR(36) NOT NULL,
  `thread_id` CHAR(36) NULL,
  `uid` VARCHAR(191) NULL,
  `sender_role` MEDIUMTEXT NULL,
  `body` MEDIUMTEXT NULL,
  `attachment_url` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `is_admin` TINYINT(1) NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_support_messages_thread_id` (`thread_id`),
  KEY `ix_support_messages_uid` (`uid`),
  KEY `ix_support_messages_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `support_threads`;
CREATE TABLE `support_threads` (
  `id` CHAR(36) NOT NULL,
  `uid` VARCHAR(191) NULL,
  `subject` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'open',
  `priority` MEDIUMTEXT NULL,
  `assigned_to` MEDIUMTEXT NULL,
  `unread_admin` TINYINT(1) NULL DEFAULT 1,
  `unread_student` TINYINT(1) NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `category` VARCHAR(191) NULL,
  `closed_at` DATETIME NULL,
  `source` MEDIUMTEXT NULL,
  `ai_summary` MEDIUMTEXT NULL,
  `ai_session` CHAR(36) NULL,
  `language` VARCHAR(191) NULL,
  `contact` MEDIUMTEXT NULL,
  `first_reply_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_support_threads_uid` (`uid`),
  KEY `ix_support_threads_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `key` VARCHAR(191) NOT NULL,
  `value` MEDIUMTEXT NULL,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_blocks`;
CREATE TABLE `user_blocks` (
  `blocker_uid` VARCHAR(191) NOT NULL,
  `blocked_uid` VARCHAR(191) NOT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`blocker_uid`,`blocked_uid`),
  KEY `ix_user_blocks_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_consents`;
CREATE TABLE `user_consents` (
  `uid` CHAR(36) NOT NULL,
  `terms` TINYINT(1) NULL DEFAULT 0,
  `privacy` TINYINT(1) NULL DEFAULT 0,
  `community` TINYINT(1) NULL DEFAULT 0,
  `conduct` TINYINT(1) NULL DEFAULT 0,
  `comms` TINYINT(1) NULL DEFAULT 0,
  `accepted_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_notifications`;
CREATE TABLE `user_notifications` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NULL,
  `title` VARCHAR(255) NULL,
  `message` MEDIUMTEXT NULL,
  `type` VARCHAR(255) NULL,
  `read` TINYINT(1) NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_user_notifications_user_id` (`user_id`),
  KEY `ix_user_notifications_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_profiles`;
CREATE TABLE `user_profiles` (
  `id` CHAR(36) NOT NULL,
  `name` VARCHAR(255) NULL,
  `email` VARCHAR(255) NULL,
  `avatar_url` MEDIUMTEXT NULL,
  `bio` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_user_profiles_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_progress`;
CREATE TABLE `user_progress` (
  `id` CHAR(36) NOT NULL,
  `uid` VARCHAR(191) NULL,
  `rooting_started` TINYINT(1) NULL DEFAULT 0,
  `rooting_completed` TINYINT(1) NULL DEFAULT 0,
  `rooting_completed_at` DATETIME NULL,
  `rooting_modules_done` LONGTEXT NULL,
  `blueprint_started` TINYINT(1) NULL DEFAULT 0,
  `blueprint_completed` TINYINT(1) NULL DEFAULT 0,
  `blueprint_completed_at` DATETIME NULL,
  `blueprint_pillars_done` LONGTEXT NULL,
  `course_hub_unlocked` TINYINT(1) NULL DEFAULT 0,
  `completed_skills` LONGTEXT NULL,
  `completed_lessons` LONGTEXT NULL,
  `streak` INT NULL DEFAULT 0,
  `last_activity_date` DATE NULL,
  `quiz_results` LONGTEXT NULL,
  `certificates` LONGTEXT NULL,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `dm_quiz_score` INT NULL,
  `dm_quiz_attempts` INT NULL DEFAULT 0,
  `dm_lang` MEDIUMTEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_user_progress_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `uid` VARCHAR(191) NOT NULL,
  `email` VARCHAR(191) NULL,
  `name` VARCHAR(191) NULL,
  `role` VARCHAR(191) NULL DEFAULT 'student',
  `pathway` VARCHAR(191) NULL DEFAULT 'gmn',
  `country` MEDIUMTEXT NULL,
  `avatar` MEDIUMTEXT NULL,
  `bio` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'active',
  `ban_reason` MEDIUMTEXT NULL,
  `last_seen` DATETIME NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `status_update` MEDIUMTEXT NULL,
  `location` MEDIUMTEXT NULL,
  `expertise` LONGTEXT NULL,
  `avatar_url` MEDIUMTEXT NULL,
  `display_name` MEDIUMTEXT NULL,
  `language` VARCHAR(191) NULL DEFAULT 'en',
  `streak` INT NULL DEFAULT 0,
  `state` MEDIUMTEXT NULL,
  `community` MEDIUMTEXT NULL,
  `phone_number` MEDIUMTEXT NULL,
  `phone_auth_enabled` TINYINT(1) NULL DEFAULT 0,
  `city` MEDIUMTEXT NULL,
  `phone` VARCHAR(191) NULL,
  `age_category` MEDIUMTEXT NULL,
  `profession` MEDIUMTEXT NULL,
  `interest_area` MEDIUMTEXT NULL,
  `volunteer_interest` TINYINT(1) NULL DEFAULT 0,
  `leadership_interest` TINYINT(1) NULL DEFAULT 0,
  `mentorship_interest` TINYINT(1) NULL DEFAULT 0,
  `digital_access` MEDIUMTEXT NULL,
  `reason_joining` MEDIUMTEXT NULL,
  `profile_complete` TINYINT(1) NULL DEFAULT 0,
  `last_reminder_sent_at` DATETIME NULL,
  `last_reminder_tier` INT NULL DEFAULT 0,
  `region` MEDIUMTEXT NULL,
  `lga` MEDIUMTEXT NULL,
  `location_captured` TINYINT(1) NULL DEFAULT 0,
  `account_type` VARCHAR(191) NULL DEFAULT 'learner',
  `reminders_opt_out` TINYINT(1) NULL DEFAULT 0,
  `referral_code` VARCHAR(191) NULL,
  PRIMARY KEY (`uid`),
  KEY `ix_users_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `volunteer_applications`;
CREATE TABLE `volunteer_applications` (
  `id` CHAR(36) NOT NULL,
  `uid` VARCHAR(191) NULL,
  `proceed` MEDIUMTEXT NULL,
  `network` VARCHAR(191) NULL,
  `role` VARCHAR(191) NULL,
  `chapter_interest` MEDIUMTEXT NULL,
  `chapter_name` MEDIUMTEXT NULL,
  `full_name` MEDIUMTEXT NULL,
  `gender` MEDIUMTEXT NULL,
  `dob` DATE NULL,
  `email` VARCHAR(191) NULL,
  `phone` VARCHAR(191) NULL,
  `country` MEDIUMTEXT NULL,
  `state_region` MEDIUMTEXT NULL,
  `lga_city` MEDIUMTEXT NULL,
  `city_area` MEDIUMTEXT NULL,
  `education` MEDIUMTEXT NULL,
  `edu_field` MEDIUMTEXT NULL,
  `experience` MEDIUMTEXT NULL,
  `skills` MEDIUMTEXT NULL,
  `languages` MEDIUMTEXT NULL,
  `social` MEDIUMTEXT NULL,
  `why` MEDIUMTEXT NULL,
  `value` MEDIUMTEXT NULL,
  `hours` MEDIUMTEXT NULL,
  `ethics` MEDIUMTEXT NULL,
  `verify` MEDIUMTEXT NULL,
  `cv_url` MEDIUMTEXT NULL,
  `id_url` MEDIUMTEXT NULL,
  `reference` MEDIUMTEXT NULL,
  `affirm_true` MEDIUMTEXT NULL,
  `agree` MEDIUMTEXT NULL,
  `status` VARCHAR(191) NULL DEFAULT 'new',
  `admin_notes` MEDIUMTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `marital_status` VARCHAR(191) NULL,
  `is_mother` MEDIUMTEXT NULL,
  `app_ref` VARCHAR(191) NULL,
  `attribution` LONGTEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_volunteer_applications_uid` (`uid`),
  KEY `ix_volunteer_applications_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `auth_accounts`;
CREATE TABLE `auth_accounts` (
  `uid` VARCHAR(191) NOT NULL, `email` VARCHAR(191) NULL, `phone` VARCHAR(64) NULL, `password_hash` VARCHAR(255) NULL, `google_sub` VARCHAR(191) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP, `last_sign_in` DATETIME NULL,
  PRIMARY KEY (`uid`), UNIQUE KEY `ux_auth_email` (`email`), KEY `ix_auth_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (`token` CHAR(64) NOT NULL, `uid` VARCHAR(191) NOT NULL, `expires_at` DATETIME NOT NULL, PRIMARY KEY (`token`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
DROP TABLE IF EXISTS `migration_log`;
CREATE TABLE `migration_log` (`tbl` VARCHAR(191) NOT NULL, `source_rows` INT NULL, `copied_rows` INT NULL, `finished_at` DATETIME NULL, `note` TEXT NULL, PRIMARY KEY (`tbl`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SET FOREIGN_KEY_CHECKS=1;
