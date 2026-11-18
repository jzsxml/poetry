-- 节令荐诗离线预采样（composer run rebuild-author-wall 时写入）
CREATE TABLE IF NOT EXISTS season_recommend_pool (
  theme_id VARCHAR(64) NOT NULL,
  slot SMALLINT UNSIGNED NOT NULL,
  work_id VARCHAR(320) NOT NULL,
  score SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (theme_id, slot),
  KEY idx_season_pool_work (work_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;