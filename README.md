# 中华古诗词库 · PHP + MySQL

基于 [chinese-poetry](https://github.com/chinese-poetry/chinese-poetry) 的**独立 PHP 线路**：MySQL 8 + PHP-FPM，适合宝塔 / 小内存 VPS，**无需 Node.js**。HTTP API 路径与 JSON 字段与常见 Next.js + SQLite 版实现尽量对齐，便于换栈或前后端分离。

## 特性概览

- 十四典籍、近四十万篇，支持按作者、篇名、正文检索（MySQL `ngram` 全文）
- 简 / 繁展示（OpenCC，Cookie `poetry-script`）
- 首页时令荐诗（农历、传统节日、二十四节气，上海时区）
- 名人墙、随机一首、典籍浏览、作品阅读页（含同作者、上下篇）
- 检索总数封顶与文件缓存，适配低配 MySQL

## 技术栈

| 组件 | 用途 |
|------|------|
| PHP 8.1+ | 路由、视图、API |
| MySQL 8 | 持久化与 FULLTEXT |
| [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv) | `.env` |
| [overtrue/php-opencc](https://github.com/overtrue/php-opencc) | 简繁转换 |
| [6tail/lunar-php](https://github.com/6tail/lunar-php) | 农历与节气 |

PHP 扩展：`pdo_mysql`（运行必备）；从 SQLite 迁入时需 `pdo_sqlite`。

## 环境

- PHP 8.1+、Composer 2
- MySQL 8（端口等在 `.env` 配置）

## 快速开始

```bash
git clone <你的仓库 URL>
cd <仓库目录>
cp .env.example .env
composer install
```

创建库表：

```bash
# Linux / macOS / CMD
mysql -h127.0.0.1 -P3308 -uroot -p < sql/schema.sql
```

**Windows PowerShell**（不支持 `<` 重定向，且本地 MySQL 8 常需关 SSL）：

```powershell
Get-Content -Raw -Encoding UTF8 sql\schema.sql |
  mysql --host=127.0.0.1 --port=3308 --user=root --password=你的密码 --ssl-mode=DISABLED
```

### 导入诗词数据

本仓库**不含**庞大的数据库文件，需自备已按本项目结构导入好的 **SQLite `poetry.db`**，再迁入 MySQL：

1. 若你已有兼容的 `poetry.db`（例如从其他导入工具得到），在 `.env` 设置 `POETRY_SQLITE_PATH`（相对路径以**本项目根目录**为准），可放到 `storage/poetry.db`（该目录已 gitignore）。
2. 执行迁移：

```bash
composer run migrate
```

迁移会**批量多行 INSERT**、关闭 `unique_checks`，`body` 由 `paragraphs` 拼接。若遇 `max_allowed_packet` 报错，把 `MIGRATE_ROWS_PER_INSERT` 调小（如 `60`）。缩库体积可设 `MIGRATE_BODY_MAX=512`。

**迁移后建议**（生产同理）：

```bash
composer run ensure-search-indexes
composer run rebuild-author-wall
```

本地开发：

```bash
composer run serve
# http://localhost:8080
```

## 宝塔 / Nginx 部署

- 站点根目录、运行目录均指向本项目的 **`public/`**
- Apache：开启伪静态（`public/.htaccess` 已将非文件请求转到 `index.php`）
- Nginx 示例（宝塔「伪静态」可粘贴）：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/tmp/php-cgi-xx.sock;  # 按面板实际 socket 修改
}
```

- `.env` 放在项目根（与 `vendor`、`public` 同级），勿提交仓库
- 确保 `var/cache/` 对 FPM 用户可写
- **不需要** `npm` / 前端构建

## 页面路由

| 路径 | 说明 |
|------|------|
| `/` | 检索首页、时令荐诗 |
| `/work/{id}` | 作品阅读（RESTful；旧链 `/work?id=` 会 301 到此） |
| `/corpus/{id}` | 典籍篇目浏览 |
| `/authors` | 名人墙页 |

静态资源在 `public/assets/`；简繁切换写入 Cookie，API 与 HTML 均按 Cookie 返回对应字形。

## API 参考

数据库未连接时 API 返回 **503** JSON：`{"error":"数据库未就绪"}`。

除注明外，列表类接口会按 Cookie 做简繁本地化。作品 `id` 形如 `全唐诗/...`（需 URL 编码）。

### `GET /api/search`

| 参数 | 默认 | 说明 |
|------|------|------|
| `q` | — | 检索词，至少含一个汉字；最长 64 字 |
| `kind` | `all` | `all` \| `tang` \| `song` \| `ci` \| `yuanqu` |
| `page` | `1` | 页码，受总数封顶与 `SEARCH_BROWSE_MAX` 限制 |
| `limit` | `15` | 每页条数，1～20 |
| `after_id` | — | 可选，深分页游标 |

响应含 `hits`、`total`、`totalCapped`、`browseMax`、`highlightTerms` 等。

### `GET /api/work/{id}`

阅读器 JSON：`work`、`bio`、`authorMore`、`prev` / `next` 等。

### `GET /api/random`

| 参数 | 说明 |
|------|------|
| `kind` | 可选，`tang` / `song` / `ci` / `yuanqu` |

### `GET /api/corpora` · `GET /api/corpora/{id}/works` · `GET /api/authors/wall` · `GET /api/recommend/today`

参数与行为见源码 `src/Router.php`；荐诗支持 `limit`（3～20）、`offset`（0～200），按上海时区日历选题。

## Composer 脚本

| 命令 | 说明 |
|------|------|
| `composer run serve` | 内置服务器 `localhost:8080` |
| `composer run migrate` | SQLite → MySQL |
| `composer run ensure-search-indexes` | 建立 / 校验 FULLTEXT |
| `composer run rebuild-author-wall` | 重建离线池并清空荐诗缓存 |
| `composer run repair-paragraphs` | 从 `chinese-poetry` 源 JSON 修复异常正文（见下） |

`repair-paragraphs` 默认读取**上一级目录**的 `data/`（与仓库同级 clone [chinese-poetry](https://github.com/chinese-poetry/chinese-poetry) 到 `../data`）。仅在个别篇目 `paragraphs` 异常时需用。

## 性能与离线表

迁移或大批量更新后执行 `composer run rebuild-author-wall`。离线表与 `var/cache/` 说明见上文「MySQL ngram」与「环境变量」。

## MySQL ngram 全文（检索必备）

未建 FULLTEXT 时检索退回 **LIKE 全表扫描**。小内存机 `my.cnf` 建议：

```ini
[mysqld]
ngram_token_size=2
innodb_buffer_pool_size=512M
```

```bash
composer run ensure-search-indexes
```

验收：`php scripts/explain-search.php 床前明月光`

检索分档：**1～4 字** `ft_works_meta`；**≥5 字** `ft_works_trb`（不含 author）；**2～12 字纯汉字作者名**走 `idx_works_author`；荐诗用 `ft_works_ngram`。

## 环境变量

复制 `.env.example`。常用项：

| 变量 | 说明 |
|------|------|
| `DB_*` | MySQL 连接 |
| `POETRY_SQLITE_PATH` | 迁移用 SQLite 路径，如 `./storage/poetry.db` |
| `MIGRATE_*` | 迁移批大小、`body` 截断等 |
| `SITE_URL` | 站点对外 URL |
| `SEARCH_*` / `RANDOM_*` / `RECOMMEND_*` / `AUTHOR_WALL_*` | 检索、随机、荐诗、名人墙缓存（见 `.env.example` 注释） |

## 目录结构

```
public/           入口、静态资源
src/              路由、仓储、检索、荐诗
views/            PHP 页面
sql/              schema 与索引 SQL
scripts/          迁移与运维脚本
var/cache/        运行时缓存（不提交）
storage/          本地 poetry.db 等（不提交）
vendor/           Composer 依赖
```

## 故障排查

| 现象 | 建议 |
|------|------|
| 检索极慢 | `ensure-search-indexes`，确认 `ngram_token_size=2` |
| 随机 / 荐诗异常 | `rebuild-author-wall` |
| 503 | 检查 `.env`、MySQL、`sql/schema.sql` |
| 宝塔除首页 404 | 伪静态指向 `index.php` |

## 许可与数据

- 本项目代码以你仓库所选许可证为准（若未添加 LICENSE，请自行补充）。
- 诗词原文版权归属各原作者；数据来源于 [chinese-poetry](https://github.com/chinese-poetry/chinese-poetry) 等开源整理，请遵守其许可与注明习惯。

## 安全

勿将 `.env`、`var/`、`vendor/` 暴露为 Web 可下载目录；站点根目录只能是 `public/`。