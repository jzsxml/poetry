<?php

declare(strict_types=1);

namespace Poetry;

/**
 * 与 web/scripts/import.ts 对齐的 chinese-poetry JSON 解析。
 */
final class PoemJson
{
    /** @var list<string> */
    public const CORPUS_NAMES = [
        '诗经',
        '楚辞',
        '论语',
        '四书五经',
        '曹操诗集',
        '五代诗词',
        '全唐诗',
        '御定全唐詩',
        '水墨唐诗',
        '宋词',
        '元曲',
        '纳兰性德',
        '幽梦影',
        '蒙学',
    ];

    /** @var array<string, string> */
    private const CORPUS_KIND = [
        '诗经' => 'shijing',
        '楚辞' => 'chuci',
        '论语' => 'lunyu',
        '四书五经' => 'sishu',
        '曹操诗集' => 'caocao',
        '五代诗词' => 'wudai',
        '御定全唐詩' => 'yuding',
        '水墨唐诗' => 'shuimo',
        '元曲' => 'yuanqu',
        '纳兰性德' => 'nalan',
        '幽梦影' => 'youmeng',
        '蒙学' => 'mengxue',
    ];

    public static function shouldSkipJson(string $fileName): bool
    {
        if (str_contains($fileName, '2019y')) {
            return true;
        }
        if ($fileName === 'intro.json') {
            return true;
        }
        if (preg_match('/^authors?\./i', $fileName) === 1) {
            return true;
        }
        if ($fileName === 'author.song.json') {
            return true;
        }
        if (str_ends_with($fileName, 'authors.json')) {
            return true;
        }

        return false;
    }

    /**
     * @return list<string> 绝对路径
     */
    public static function walkJsonFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        $stack = [$dir];
        while ($stack !== []) {
            $current = array_pop($stack);
            $entries = scandir($current);
            if ($entries === false) {
                continue;
            }
            foreach ($entries as $ent) {
                if ($ent === '.' || $ent === '..') {
                    continue;
                }
                $full = $current . DIRECTORY_SEPARATOR . $ent;
                if (is_dir($full)) {
                    $stack[] = $full;

                    continue;
                }
                if (!str_ends_with($ent, '.json') || self::shouldSkipJson($ent)) {
                    continue;
                }
                $out[] = $full;
            }
        }
        sort($out, SORT_STRING);

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function flattenPoemCandidates(mixed $data): array
    {
        $out = [];
        $isPoemLike = static function (array $o): bool {
            if (isset($o['paragraphs']) && is_array($o['paragraphs']) && $o['paragraphs'] !== []) {
                return true;
            }
            if (isset($o['content']) && is_array($o['content']) && $o['content'] !== []
                && is_string($o['content'][0] ?? null)) {
                return true;
            }
            if (isset($o['para']) && is_array($o['para']) && $o['para'] !== []) {
                return true;
            }

            return isset($o['content']) && is_string($o['content']) && trim($o['content']) !== '';
        };

        $walk = static function (mixed $node) use (&$walk, &$out, $isPoemLike): void {
            if (is_array($node) && array_is_list($node)) {
                foreach ($node as $item) {
                    $walk($item);
                }

                return;
            }
            if (!is_array($node)) {
                return;
            }
            if ($isPoemLike($node)) {
                $out[] = $node;

                return;
            }
            if (isset($node['content']) && is_array($node['content'])) {
                $walk($node['content']);
            }
            if (isset($node['chapters']) && is_array($node['chapters'])) {
                $walk($node['chapters']);
            }
        };

        $walk($data);

        return $out;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{author: string, title: string, rhythmic: string, paragraphs: list<string>, notes: list<string>}|null
     */
    public static function normalizePoem(array $raw): ?array
    {
        $paragraphs = [];
        if (isset($raw['paragraphs']) && is_array($raw['paragraphs'])) {
            $paragraphs = WorkParagraphs::flattenList($raw['paragraphs']);
        } elseif (isset($raw['para']) && is_array($raw['para'])) {
            $paragraphs = WorkParagraphs::flattenList($raw['para']);
        } elseif (isset($raw['content']) && is_array($raw['content'])
            && is_string($raw['content'][0] ?? null)) {
            $paragraphs = WorkParagraphs::flattenList($raw['content']);
        } elseif (isset($raw['content']) && is_string($raw['content']) && trim($raw['content']) !== '') {
            $paragraphs = [trim($raw['content'])];
        }

        if ($paragraphs === []) {
            return null;
        }

        $title = '';
        if (isset($raw['title'])) {
            $title = (string) $raw['title'];
        } elseif (isset($raw['chapter'])) {
            $title = (string) $raw['chapter'];
        } elseif (isset($raw['section'])) {
            $title = (string) $raw['section'];
        } elseif (isset($raw['name'])) {
            $title = (string) $raw['name'];
        }

        if ($title === ''
            && isset($raw['section'], $raw['chapter'])
            && $raw['section'] !== $raw['chapter']) {
            $title = (string) $raw['section'] . '·' . (string) $raw['chapter'];
        }

        $author = isset($raw['author']) ? (string) $raw['author'] : '';
        $rhythmic = isset($raw['rhythmic']) ? (string) $raw['rhythmic'] : '';

        $notes = [];
        foreach (['note', 'notes', 'comment'] as $key) {
            if (!isset($raw[$key]) || !is_array($raw[$key])) {
                continue;
            }
            foreach ($raw[$key] as $x) {
                $notes[] = (string) $x;
            }
        }
        if (isset($raw['prologue']) && is_string($raw['prologue']) && trim($raw['prologue']) !== '') {
            array_unshift($notes, trim($raw['prologue']));
        }

        return [
            'author' => $author,
            'title' => $title,
            'rhythmic' => $rhythmic,
            'paragraphs' => $paragraphs,
            'notes' => $notes,
        ];
    }

    public static function kindForFile(string $corpus, string $fileName): string
    {
        if ($corpus === '全唐诗') {
            if (str_starts_with($fileName, 'poet.tang.')) {
                return 'tang';
            }
            if (str_starts_with($fileName, 'poet.song.')) {
                return 'song';
            }

            return 'tang';
        }
        if ($corpus === '宋词') {
            return 'ci';
        }

        return self::CORPUS_KIND[$corpus] ?? 'misc';
    }

    public static function workId(string $corpus, string $relSourceFile, int $index): string
    {
        $base = preg_replace('/\.json$/i', '', $relSourceFile) ?? $relSourceFile;
        $base = str_replace('/', '__', str_replace('\\', '/', $base));

        return $corpus . ':' . $base . ':' . $index;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function loadAuthorRows(string $dataRoot): array
    {
        $rows = [];
        $tangDir = $dataRoot . DIRECTORY_SEPARATOR . '全唐诗';
        $ciDir = $dataRoot . DIRECTORY_SEPARATOR . '宋词';

        $tangAuthors = $tangDir . DIRECTORY_SEPARATOR . 'authors.tang.json';
        if (is_file($tangAuthors)) {
            $rows = array_merge($rows, self::parseAuthorList($tangAuthors, 'tang', 'desc'));
        }
        $songAuthors = $tangDir . DIRECTORY_SEPARATOR . 'authors.song.json';
        if (is_file($songAuthors)) {
            $rows = array_merge($rows, self::parseAuthorList($songAuthors, 'song', 'desc'));
        }
        $ciAuthors = $ciDir . DIRECTORY_SEPARATOR . 'author.song.json';
        if (is_file($ciAuthors)) {
            $rows = array_merge($rows, self::parseCiAuthorList($ciAuthors));
        }

        return $rows;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private static function parseAuthorList(string $path, string $kind, string $descKey): array
    {
        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $a) {
            if (!is_array($a) || !isset($a['name'])) {
                continue;
            }
            $out[] = [(string) $a['name'], $kind, (string) ($a[$descKey] ?? '')];
        }

        return $out;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private static function parseCiAuthorList(string $path): array
    {
        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $a) {
            if (!is_array($a) || !isset($a['name'])) {
                continue;
            }
            $desc = (string) ($a['description'] ?? $a['short_description'] ?? '');
            $out[] = [(string) $a['name'], 'ci', $desc];
        }

        return $out;
    }
}