-- 名人墙预聚合（迁移后执行 composer run rebuild-author-wall）
USE poetry;

CREATE TABLE IF NOT EXISTS author_wall_stats (
  kind VARCHAR(32) NOT NULL,
  author VARCHAR(255) NOT NULL,
  work_count INT UNSIGNED NOT NULL,
  PRIMARY KEY (kind, author),
  KEY idx_stats_kind_count (kind, work_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;