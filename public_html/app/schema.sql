SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id VARCHAR(191) NOT NULL,
  email VARCHAR(191) NOT NULL,
  username VARCHAR(64) NULL,
  password_hash VARCHAR(191) NULL,
  password_salt VARCHAR(191) NULL,
  name VARCHAR(160) NOT NULL,
  role VARCHAR(32) NOT NULL DEFAULT 'student',
  source_lang VARCHAR(8) NOT NULL DEFAULT 'tr',
  target_lang VARCHAR(8) NOT NULL DEFAULT 'ru',
  current_level VARCHAR(8) NOT NULL DEFAULT 'A1',
  xp INT NOT NULL DEFAULT 0,
  hearts INT NOT NULL DEFAULT 5,
  streak INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  failed_login_count INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  last_active_date DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY users_email_unique (email),
  UNIQUE KEY users_username_unique (username),
  KEY users_xp_idx (xp),
  KEY users_role_idx (role, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS curriculum (
  id VARCHAR(191) NOT NULL,
  target_lang VARCHAR(8) NOT NULL,
  level VARCHAR(8) NOT NULL,
  level_order INT NOT NULL,
  lesson_order INT NOT NULL,
  unit_title VARCHAR(255) NOT NULL,
  unit_summary TEXT NOT NULL,
  title VARCHAR(255) NOT NULL,
  summary TEXT NOT NULL,
  kind VARCHAR(64) NOT NULL,
  payload LONGTEXT NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY curriculum_route_idx (target_lang, level_order, lesson_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
  token_hash CHAR(64) NOT NULL,
  user_id VARCHAR(191) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (token_hash),
  KEY sessions_user_idx (user_id),
  KEY sessions_expiry_idx (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_progress (
  user_id VARCHAR(191) NOT NULL,
  lesson_id VARCHAR(191) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'completed',
  score INT NOT NULL DEFAULT 0,
  attempts INT NOT NULL DEFAULT 1,
  completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, lesson_id),
  KEY progress_user_idx (user_id, completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_items (
  user_id VARCHAR(191) NOT NULL,
  lesson_id VARCHAR(191) NOT NULL,
  exercise_key VARCHAR(191) NOT NULL,
  prompt TEXT NOT NULL,
  expected TEXT NOT NULL,
  mistakes INT NOT NULL DEFAULT 1,
  streak INT NOT NULL DEFAULT 0,
  next_review_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, lesson_id, exercise_key),
  KEY review_due_idx (user_id, next_review_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vocabulary_progress (
  user_id VARCHAR(191) NOT NULL,
  lesson_id VARCHAR(191) NOT NULL,
  mastery INT NOT NULL DEFAULT 0,
  reviews INT NOT NULL DEFAULT 0,
  lapses INT NOT NULL DEFAULT 0,
  next_review_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_reviewed_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, lesson_id),
  KEY vocabulary_due_idx (user_id, next_review_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS speaking_progress (
  user_id VARCHAR(191) NOT NULL,
  lesson_id VARCHAR(191) NOT NULL,
  best_score INT NOT NULL DEFAULT 0,
  attempts INT NOT NULL DEFAULT 0,
  last_transcript TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, lesson_id),
  KEY speaking_user_idx (user_id, best_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS story_progress (
  user_id VARCHAR(191) NOT NULL,
  story_id VARCHAR(191) NOT NULL,
  score INT NOT NULL DEFAULT 0,
  attempts INT NOT NULL DEFAULT 0,
  completed TINYINT(1) NOT NULL DEFAULT 0,
  completed_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, story_id),
  KEY story_user_idx (user_id, completed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exercise_attempts (
  id VARCHAR(191) NOT NULL,
  user_id VARCHAR(191) NOT NULL,
  lesson_id VARCHAR(191) NOT NULL,
  exercise_type VARCHAR(64) NOT NULL,
  skill VARCHAR(64) NOT NULL,
  correct TINYINT(1) NOT NULL DEFAULT 0,
  score INT NULL,
  prompt TEXT NOT NULL,
  expected TEXT NOT NULL,
  recognized TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY attempts_user_idx (user_id, created_at),
  KEY attempts_skill_idx (user_id, skill, correct)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_mastery (
  user_id VARCHAR(191) NOT NULL,
  lesson_id VARCHAR(191) NOT NULL,
  mastery INT NOT NULL DEFAULT 0,
  correct_count INT NOT NULL DEFAULT 0,
  wrong_count INT NOT NULL DEFAULT 0,
  next_review_at DATETIME NULL,
  last_review_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, lesson_id),
  KEY mastery_review_idx (user_id, next_review_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_mistakes (
  id VARCHAR(191) NOT NULL,
  user_id VARCHAR(191) NOT NULL,
  lesson_id VARCHAR(191) NOT NULL,
  skill VARCHAR(64) NOT NULL,
  prompt TEXT NOT NULL,
  expected TEXT NOT NULL,
  error_count INT NOT NULL DEFAULT 1,
  resolved_at DATETIME NULL,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY mistakes_user_idx (user_id, resolved_at, last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_profiles (
  user_id VARCHAR(191) NOT NULL,
  phone VARCHAR(64) NOT NULL DEFAULT '',
  goal TEXT NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'active',
  assigned_teacher_id VARCHAR(191) NULL,
  next_action TEXT NOT NULL,
  next_action_at DATETIME NULL,
  private_note TEXT NOT NULL,
  updated_by VARCHAR(191) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  KEY profiles_teacher_idx (assigned_teacher_id, status),
  KEY profiles_action_idx (next_action_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_notes (
  id VARCHAR(191) NOT NULL,
  user_id VARCHAR(191) NOT NULL,
  author_id VARCHAR(191) NOT NULL,
  note_type VARCHAR(32) NOT NULL DEFAULT 'general',
  content TEXT NOT NULL,
  pinned TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY notes_user_idx (user_id, pinned, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_lesson_packages (
  id VARCHAR(191) NOT NULL,
  user_id VARCHAR(191) NOT NULL,
  lesson_count INT NOT NULL,
  created_by VARCHAR(191) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY packages_user_idx (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_lessons (
  id VARCHAR(191) NOT NULL,
  package_id VARCHAR(191) NOT NULL,
  user_id VARCHAR(191) NOT NULL,
  lesson_number INT NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'pending',
  scheduled_at DATETIME NULL,
  duration_minutes INT NOT NULL DEFAULT 45,
  teacher_id VARCHAR(191) NULL,
  meeting_url TEXT NULL,
  topic TEXT NULL,
  teacher_note TEXT NULL,
  homework TEXT NULL,
  credit_used TINYINT(1) NOT NULL DEFAULT 0,
  completed_at DATETIME NULL,
  completed_by VARCHAR(191) NULL,
  updated_by VARCHAR(191) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY lessons_user_idx (user_id, status, lesson_number),
  KEY lessons_package_idx (package_id, lesson_number),
  KEY lessons_schedule_idx (scheduled_at, teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_tasks (
  id VARCHAR(191) NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  user_id VARCHAR(191) NULL,
  assigned_to VARCHAR(191) NULL,
  priority VARCHAR(32) NOT NULL DEFAULT 'normal',
  status VARCHAR(32) NOT NULL DEFAULT 'pending',
  due_at DATETIME NULL,
  created_by VARCHAR(191) NOT NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY tasks_status_idx (status, due_at),
  KEY tasks_assignee_idx (assigned_to, status),
  KEY tasks_user_idx (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_audit_logs (
  id VARCHAR(191) NOT NULL,
  actor_id VARCHAR(191) NOT NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(100) NOT NULL,
  entity_id VARCHAR(191) NOT NULL,
  summary TEXT NOT NULL,
  metadata LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY audit_created_idx (created_at),
  KEY audit_entity_idx (entity_type, entity_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_migrations (
  `key` VARCHAR(191) NOT NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grammar_progress (
  user_id VARCHAR(191) NOT NULL,
  topic_id VARCHAR(191) NOT NULL,
  target_lang VARCHAR(8) NOT NULL,
  level VARCHAR(8) NOT NULL,
  mastery INT NOT NULL DEFAULT 0,
  attempts INT NOT NULL DEFAULT 0,
  correct_count INT NOT NULL DEFAULT 0,
  wrong_count INT NOT NULL DEFAULT 0,
  status VARCHAR(32) NOT NULL DEFAULT 'not_started',
  next_review_at DATETIME NULL,
  last_reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, topic_id),
  KEY grammar_progress_review_idx (user_id, target_lang, next_review_at),
  KEY grammar_progress_level_idx (user_id, target_lang, level, mastery)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grammar_mistakes (
  id VARCHAR(191) NOT NULL,
  user_id VARCHAR(191) NOT NULL,
  topic_id VARCHAR(191) NOT NULL,
  target_lang VARCHAR(8) NOT NULL,
  level VARCHAR(8) NOT NULL,
  error_type VARCHAR(64) NOT NULL,
  prompt TEXT NOT NULL,
  expected TEXT NOT NULL,
  error_count INT NOT NULL DEFAULT 1,
  resolved_at DATETIME NULL,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY grammar_mistakes_user_idx (user_id, resolved_at, last_seen_at),
  KEY grammar_mistakes_topic_idx (user_id, topic_id, error_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grammar_content_overrides (
  topic_id VARCHAR(191) NOT NULL,
  target_lang VARCHAR(8) NOT NULL,
  level VARCHAR(8) NOT NULL,
  title VARCHAR(255) NOT NULL,
  summary TEXT NOT NULL,
  content_json LONGTEXT NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  updated_by VARCHAR(191) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (topic_id),
  KEY grammar_content_route_idx (target_lang, level, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
