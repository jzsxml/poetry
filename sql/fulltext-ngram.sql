-- 可选：MySQL 配置 ngram_token_size 后执行（见 php/README.md）
USE poetry;
ALTER TABLE works ADD FULLTEXT INDEX ft_works_ngram (author, title, rhythmic, body) WITH PARSER ngram;
