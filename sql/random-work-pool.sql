-- 随机一首预采样池（composer run rebuild-author-wall）
USE poetry;

CREATE TABLE IF NOT EXISTS random_work_pool (
  kind VARCHAR(32) NOT NULL,
  slot SMALLINT UNSIGNED NOT NULL,
  work_id VARCHAR(320) NOT NULL,
  PRIMARY KEY (kind, slot),
  KEY idx_pool_work (work_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;