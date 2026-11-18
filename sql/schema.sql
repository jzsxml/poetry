-- MySQL 8 · 与 SQLite 导入模型对齐（UTF-8MB4）
CREATE DATABASE IF NOT EXISTS poetry
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE poetry;

DROP TABLE IF EXISTS works;
DROP TABLE IF EXISTS authors;

CREATE TABLE works (
  id VARCHAR(320) NOT NULL PRIMARY KEY,
  corpus VARCHAR(64) NOT NULL,
  kind VARCHAR(32) NOT NULL,
  author VARCHAR(255) NOT NULL DEFAULT '',
  title VARCHAR(512) NOT NULL DEFAULT '',
  rhythmic VARCHAR(255) NOT NULL DEFAULT '',
  paragraphs JSON NOT NULL,
  notes JSON NOT NULL,
  body MEDIUMTEXT NULL COMMENT 'FTS 正文（与 SQLite works_fts.body 一致）',
  source_file VARCHAR(512) NOT NULL,
  source_index INT NOT NULL,
  KEY idx_works_corpus (corpus),
  KEY idx_works_kind (kind),
  KEY idx_works_author (author),
  KEY idx_works_source (corpus, source_file, source_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE authors (
  name VARCHAR(255) NOT NULL,
  kind VARCHAR(32) NOT NULL,
  description TEXT NOT NULL,
  PRIMARY KEY (name, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
