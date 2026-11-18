# 中华古诗词库 · PHP + MySQL

基于 [chinese-poetry](https://github.com/chinese-poetry/chinese-poetry) 的**独立 PHP 站点**：MySQL 8 + PHP-FPM，适合宝塔 / 小内存 VPS，**无需 Node.js**。REST API 路径与 JSON 字段与常见 Next.js + SQLite 实现尽量对齐。

## 本仓库包含什么

| 包含 | 不包含（需本机自备） |
|------|----------------------|
| 站点代码、`sql/schema`、运维脚本 | `data/` 诗词 JSON（`git clone chinese-poetry`） |
| Composer 依赖声明 | `vendor/`（`composer install`） |
| `.env.example` | `.env`、MySQL 实际数据 |
| | `var/cache/`、`storage/*.db` |

十四典籍目录名与导入逻辑见 `src/PoemJson.php` 中 `CORPUS_NAMES`（诗经、楚辞、论语、四书五经、曹操诗集、五代诗词、全唐诗、御定全唐詩、水墨唐诗、宋词、元曲、纳兰性德、幽梦影、蒙学）。

## 特性概览

- 近四十万篇：按作者、篇名、正文检索（MySQL `ngram` 全文）
- 简 / 繁（OpenCC，Cookie `poetry-script`）
- 时令荐诗（农历、节日、二十四节气，Asia/Shanghai）
- 名人墙、随机一首、典籍浏览、作品阅读（同作者、上下篇）
- 检索总数封顶 + 文件缓存，适配低配 MySQL

## 技术栈

| 组件 | 用途 |
|------|------|
| PHP 8.1+ | 路由、视图、API |
| MySQL 8 | 持久化与 FULLTEXT |
| [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv) | `.env` |
| [overtrue/php-opencc](https://github.com/overtrue/php-opencc) | 简繁 |
| [6tail/lunar-php](https://github.com/6tail/lunar-php) | 农历 / 节气 |

**扩展**：`pdo_mysql`（必选）；`composer run migrate` 时还需 `pdo_sqlite`。

## 从零部署（清单）

1. 克隆本仓库，`composer install`，复制 `.env.example` → `.env`，改 MySQL 口令与端口。  
2. 执行 `sql/schema.sql` 建库建表。  
3. `git clone --depth 1 https://github.com/chinese-poetry/chinese-poetry.git data`（与 `POETRY_DATA_PATH` 一致）。  
4. `composer run import`（清空并重建 `works` / `authors`，耗时视机器约十数分钟～数十分钟）。  
5. `composer run ensure-search-indexes`（**检索必备**，首次较慢）。  
6. `composer run rebuild-author-wall`（随机 / 荐诗 / 名人墙离线池）。  
7. 宝塔将站点根设为 `public/`，或本地 `composer run serve`。

## 快速开始

```bash
git clone <你的仓库 URL>
cd <仓库目录>
cp .env.example .env
composer install
```

建表（Linux / macOS / CMD）：

```bash
mysql -h127.0.0.1 -P3308 -uroot -p < sql/schema.sql
```

**Windows PowerShell**（不支持 `<`，MySQL 8 本地常需关 SSL）：

```powershell
Get-Content -Raw -Encoding UTF8 sql\schema.sql |
  mysql --host=127.0.0.1 --port=3308 --user=root --password=你的密码 --ssl-mode=DISABLED
```

### 数据采集：`composer run import`

```bash
git clone --depth 1 https://github.com/chinese-poetry/chinese-poetry.git data
composer run import
```

- 解析规则与 `web/scripts/import.ts` 对齐（作品 `id`、`kind`、`source_file` 等）。  
- 导入前 **TRUNCATE** `works`、`authors`；`body` 由 `paragraphs` 拼接。  
- 批量写入：`MIGRATE_ROWS_PER_INSERT`（默认 120）、`MIGRATE_COMMIT_EVERY`（默认 6000）。  
- 遇 `max_allowed_packet`：调小 `MIGRATE_ROWS_PER_INSERT` 或增大 MySQL `max_allowed_packet`。  
- 可选 `MIGRATE_BODY_MAX=512` 缩小库体积（超长正文检索略受限）。

导入结束后**务必**：

```bash
composer run ensure-search-indexes
composer run rebuild-author-wall
```

### 备选：SQLite → MySQL

已有兼容 `poetry.db` 时，置于 `storage/`，设置 `POETRY_SQLITE_PATH`，再执行 `composer run migrate`，随后同样执行索引与 `rebuild-author-wall`。

### 本地预览

```bash
composer run serve
# http://localhost:8080
```

## 宝塔 / Nginx

- 网站根目录、运行目录：**`public/`**  
- Apache：启用伪静态（`public/.htaccess` → `index.php`）  
- Nginx 伪静态示例：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/tmp/php-cgi-xx.sock;
}
```

- `.env` 在项目根（与 `vendor` 同级），勿入库  
- `var/cache/` 需 FPM 用户可写  
- 无需 `npm` 构建  

## 页面路由

| 路径 | 说明 |
|------|------|
| `/` | 检索、时令荐诗 |
| `/work/{id}` | 阅读（`/work?id=` → 301） |
| `/corpus/{id}` | 典籍篇目 |
| `/authors` | 名人墙 |

## API 参考

库未就绪：`503`，`{"error":"数据库未就绪"}`。列表类接口按 Cookie 简繁本地化。作品 `id` 示例：`全唐诗:poet.tang.0:12`（URL 编码）。

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/search` | 检索 |
| GET | `/api/work/{id}` | 阅读 JSON |
| GET | `/api/random` | 随机一首 |
| GET | `/api/corpora` | 典籍元数据 + 计数 |
| GET | `/api/corpora/{id}/works` | 卷内篇目 |
| GET | `/api/authors/wall` | 名人墙 |
| GET | `/api/recommend/today` | 时令荐诗 |

### `GET /api/search`

| 参数 | 默认 | 说明 |
|------|------|------|
| `q` | — | 至少 1 个汉字，最长 64 |
| `kind` | `all` | `tang` / `song` / `ci` / `yuanqu` |
| `page` / `limit` | `1` / `15` | `limit` 最大 20 |
| `after_id` | — | 深分页游标 |

### `GET /api/corpora/{id}/works`

| 参数 | 默认 | 说明 |
|------|------|------|
| `page` / `limit` | `1` / `20` | `limit` 最大 30 |
| `segment` | — | 分段 id（宋词等） |

### `GET /api/authors/wall`

| 参数 | 默认 | 说明 |
|------|------|------|
| `kind` | `all` | 体裁 |
| `limit` | `100` | 20～120 |

### `GET /api/recommend/today`

| 参数 | 默认 | 说明 |
|------|------|------|
| `limit` | `6` | 3～20 |
| `offset` | `0` | 0～200 |

### `GET /api/random`

可选 `kind`：`tang` / `song` / `ci` / `yuanqu`（不含 `all`）。

## Composer 脚本

| 命令 | 说明 |
|------|------|
| `composer run import` | `POETRY_DATA_PATH` 下 JSON → MySQL |
| `composer run migrate` | `POETRY_SQLITE_PATH` → MySQL |
| `composer run ensure-search-indexes` | 建 / 校验 FULLTEXT |
| `composer run rebuild-author-wall` | `author_wall_stats`、`random_work_pool`、`season_recommend_pool`；清空 `var/cache/recommend-today/` |
| `composer run repair-paragraphs` | 按 `source_file` 从 `POETRY_DATA_PATH` 修复 `[object Object]` 正文 |
| `composer run serve` | `php -S localhost:8080 -t public` |

诊断：`php scripts/explain-search.php 床前明月光`

## 性能与离线数据

| 表 / 缓存 | 作用 |
|-----------|------|
| `author_wall_stats` | 名人墙聚合 |
| `random_work_pool` | 随机一首 |
| `season_recommend_pool` | 荐诗主题预采样 |
| `var/cache/recommend-today/` | 荐诗按日 JSON |
| `var/cache/search-total/` | 检索 COUNT 缓存 |
| `var/cache/author-wall/` | 名人墙 JSON 缓存 |

**import / migrate 之后**必须 `rebuild-author-wall`；改全文索引或大更新后建议重复执行。

## MySQL ngram 全文（检索必备）

无 FULLTEXT 时检索走 **LIKE 全表扫描**。`my.cnf` 建议：

```ini
[mysqld]
ngram_token_size=2
innodb_buffer_pool_size=512M
```

```bash
composer run ensure-search-indexes
```

| 查询 | 索引 | 说明 |
|------|------|------|
| 1～4 字 | `ft_works_meta` | author + title + rhythmic |
| ≥5 字 | `ft_works_trb` | title + rhythmic + body（不含 author） |
| 2～12 字纯汉字作者 | `idx_works_author` | 作者专搜 |
| 荐诗主题 | `ft_works_ngram` | 四列 |

亦可手工执行 `sql/fulltext-ngram.sql`、`sql/search-optimizations.sql`。

## 环境变量

| 变量 | 默认 | 说明 |
|------|------|------|
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | 见 `.env.example` | MySQL |
| `POETRY_DATA_PATH` | `./data` | 采集源（不提交 Git） |
| `POETRY_SQLITE_PATH` | `./storage/poetry.db` | 备选 SQLite |
| `MIGRATE_ROWS_PER_INSERT` | `120` | import / migrate 批大小 |
| `MIGRATE_COMMIT_EVERY` | `6000` | 提交间隔（行） |
| `MIGRATE_BODY_MAX` | `0` | 0=不截断 body |
| `SITE_URL` | `http://localhost:8080` | canonical |
| `SEARCH_TOTAL_CAP` | `10001` | 总数显示上限 |
| `SEARCH_TOTAL_CACHE_TTL` | `1800` | total 缓存（秒） |
| `SEARCH_BROWSE_MAX` | `500` | 可浏览结果上限 |
| `RANDOM_POOL_SIZE` | `384` | 随机池 |
| `RANDOM_FALLBACK_SAMPLE` | `2048` | 无池时抽样上界 |
| `SEASON_POOL_SIZE` | `96` | 节气池 |
| `RECOMMEND_CACHE_TTL` / `RECOMMEND_HTTP_MAX_AGE` | `86400` / `3600` | 荐诗缓存 |
| `AUTHOR_WALL_CACHE_TTL` / `AUTHOR_WALL_HTTP_MAX_AGE` | `604800` / `3600` | 名人墙缓存 |

## 目录结构

```
public/           index.php、静态资源
src/              路由、PoemJson、仓储、荐诗
views/            页面模板
sql/              schema、全文与离线表 DDL
scripts/          import、migrate、索引、诊断
data/             chinese-poetry（.gitignore）
storage/          本地 db（.gitignore）
var/cache/        运行缓存（.gitignore）
```

## Git 与换行

仓库含 `.gitattributes`（文本文件 **LF** 入库）。Windows 若 `git add` 出现 CRLF 提示，通常可忽略；协作建议保持 `core.autocrlf=input` 或依赖 `.gitattributes`。

## 故障排查

| 现象 | 处理 |
|------|------|
| 检索极慢 | `ensure-search-indexes`；`ngram_token_size=2` 后重建索引 |
| 随机 / 荐诗重复或空 | `rebuild-author-wall` |
| import 中断 / packet 错误 | 减小 `MIGRATE_ROWS_PER_INSERT` |
| `data` 不存在 | 按上文 clone；或改 `POETRY_DATA_PATH` |
| 503 | `.env`、MySQL 进程、`schema.sql` |
| 除首页 404 | 伪静态指向 `index.php` |
| 正文 `[object Object]` | `composer run repair-paragraphs`（需 `data/`） |

## 许可与数据

- 应用代码许可证见仓库 `LICENSE`（若未添加请自行约定）。  
- 诗词内容版权归原作者；整理数据来自 [chinese-poetry](https://github.com/chinese-poetry/chinese-poetry)，使用请遵守其许可与引用习惯。

## 安全

- 勿提交 `.env`；生产使用强密码、最小权限 DB 账号。  
- Web 根目录仅 `public/`；勿暴露 `vendor`、`var`、`data`。