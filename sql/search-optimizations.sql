-- 检索性能：需在 my.cnf [mysqld] 设置 ngram_token_size=2 后执行
USE poetry;

-- 体裁 + 游标翻页
ALTER TABLE works ADD KEY idx_works_kind_id (kind, id);

-- 1～4 字：author + title + rhythmic（无 body）
-- 列清单与旧版不一致时须：ALTER TABLE works DROP INDEX ft_works_meta;
ALTER TABLE works ADD FULLTEXT INDEX ft_works_meta (author, title, rhythmic) WITH PARSER ngram;

-- ≥5 字：title + rhythmic + body（不含 author，2G 小内存；作者名走专搜 author 索引）
ALTER TABLE works ADD FULLTEXT INDEX ft_works_trb (title, rhythmic, body) WITH PARSER ngram;

-- 节令荐诗 / searchThemeKeyword：四列全文
ALTER TABLE works ADD FULLTEXT INDEX ft_works_ngram (author, title, rhythmic, body) WITH PARSER ngram;