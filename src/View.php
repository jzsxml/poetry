<?php

declare(strict_types=1);

namespace Poetry;

final class View
{
    private static string $variant = 'simp';

    /** @var list<string> */
    private static array $scripts = [];

    public static function variant(): string
    {
        return self::$variant;
    }

    public static function boot(): void
    {
        $raw = strtolower(trim($_COOKIE[Site::SCRIPT_COOKIE] ?? 'simp'));
        self::$variant = ($raw === 'trad' || $raw === 'traditional') ? 'trad' : 'simp';
    }

    public static function loc(string $text): string
    {
        return Chinese::displayText($text, self::$variant);
    }

    /** @param array<string, mixed> $hit */
    public static function locHit(array $hit): array
    {
        foreach (['author', 'title', 'rhythmic', 'preview'] as $key) {
            if (isset($hit[$key]) && is_string($hit[$key])) {
                $raw = $hit[$key];
                if ($key !== 'author') {
                    $raw = Chinese::stripUnpairedBookTitleMarks($raw);
                }
                $hit[$key] = self::loc($raw);
            }
        }

        return $hit;
    }

    /** @param list<array<string, mixed>> $hits */
    public static function locHits(array $hits): array
    {
        return array_map([self::class, 'locHit'], $hits);
    }

    /** @param array<string, mixed> $work */
    public static function locWork(array $work): array
    {
        foreach (['author', 'title', 'rhythmic'] as $key) {
            if (isset($work[$key]) && is_string($work[$key])) {
                $raw = $key === 'author'
                    ? $work[$key]
                    : Chinese::stripUnpairedBookTitleMarks($work[$key]);
                $work[$key] = self::loc($raw);
            }
        }
        if (isset($work['paragraphs']) && is_array($work['paragraphs'])) {
            $work['paragraphs'] = array_map(
                static fn ($line) => self::loc((string) $line),
                $work['paragraphs']
            );
        }
        if (isset($work['notes']) && is_array($work['notes'])) {
            $work['notes'] = array_map(
                static fn ($note) => self::loc((string) $note),
                $work['notes']
            );
        }

        return $work;
    }

    /** @param array<string, mixed>|null $nav */
    public static function locNav(?array $nav): ?array
    {
        if ($nav === null) {
            return null;
        }
        foreach (['author', 'title', 'rhythmic'] as $key) {
            if (isset($nav[$key]) && is_string($nav[$key])) {
                $raw = $key === 'author'
                    ? $nav[$key]
                    : Chinese::stripUnpairedBookTitleMarks($nav[$key]);
                $nav[$key] = self::loc($raw);
            }
        }

        return $nav;
    }

    /** @param array<string, mixed> $payload */
    public static function locReaderPayload(array $payload): array
    {
        if (isset($payload['work']) && is_array($payload['work'])) {
            $payload['work'] = self::locWork($payload['work']);
        }
        if (isset($payload['bio']) && is_string($payload['bio'])) {
            $payload['bio'] = self::loc($payload['bio']);
        }
        if (isset($payload['authorMore']) && is_array($payload['authorMore'])) {
            $payload['authorMore'] = self::locHits($payload['authorMore']);
        }
        $payload['prev'] = self::locNav(isset($payload['prev']) && is_array($payload['prev']) ? $payload['prev'] : null);
        $payload['next'] = self::locNav(isset($payload['next']) && is_array($payload['next']) ? $payload['next'] : null);

        return $payload;
    }

    /** @param array<string, mixed> $corpus */
    public static function locCorpusEntry(array $corpus): array
    {
        foreach (['name', 'desc'] as $key) {
            if (isset($corpus[$key]) && is_string($corpus[$key])) {
                $corpus[$key] = self::loc($corpus[$key]);
            }
        }
        if (isset($corpus['segments']) && is_array($corpus['segments'])) {
            $corpus['segments'] = array_map(
                static function (array $seg): array {
                    if (isset($seg['label']) && is_string($seg['label'])) {
                        $seg['label'] = self::loc($seg['label']);
                    }

                    return $seg;
                },
                $corpus['segments']
            );
        }

        return $corpus;
    }

    public static function e(?string $s): string
    {
        return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param list<string> $scripts extra JS paths under /assets/
     */
    public static function layoutStart(string $title, array $scripts = []): void
    {
        self::boot();
        self::$scripts = $scripts;
        $pageTitle = $title;
        require dirname(__DIR__) . '/views/partials/layout-start.php';
    }

    public static function layoutEnd(): void
    {
        $extraScripts = self::$scripts;
        require dirname(__DIR__) . '/views/partials/layout-end.php';
    }

    public static function iconSearch(int $size = 18): string
    {
        return self::iconSvg($size, '<circle cx="11" cy="11" r="6.5" /><path d="M16.5 16.5 21 21" />');
    }

    public static function iconShuffle(int $size = 18): string
    {
        return self::iconSvg(
            $size,
            '<path d="M16 3h5v5" /><path d="M4 20 14.5 9.5" /><path d="m14.5 9.5 6.5-6.5" />'
            . '<path d="M21 16v5h-5" /><path d="m16 21 5-5" /><path d="M4 4l5 5" />'
        );
    }

    public static function iconUsers(int $size = 18): string
    {
        return self::iconSvg(
            $size,
            '<path d="M16 20v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1" />'
            . '<circle cx="9" cy="8" r="3.5" />'
            . '<path d="M22 20v-1a4 4 0 0 0-3-3.87" /><path d="M16 4.13a4 4 0 0 1 0 7.75" />'
        );
    }

    public static function iconType(int $size = 16): string
    {
        return self::iconSvg($size, '<path d="M4 7V5h9v2" /><path d="M8.5 5v14" /><path d="M13 13h6" /><path d="M16 10v6" />');
    }

    public static function iconVolume(int $size = 16): string
    {
        return self::iconSvg($size, '<path d="M11 5 6 9H3v6h3l5 4V5z" /><path d="M15.5 8.5a5 5 0 0 1 0 7" /><path d="M18 6a8 8 0 0 1 0 12" />');
    }

    public static function iconVolumeOff(int $size = 16): string
    {
        return self::iconSvg($size, '<path d="M11 5 6 9H3v6h3l5 4V5z" /><path d="m22 9-6 6M16 9l6 6" />');
    }

    public static function iconPrint(int $size = 16): string
    {
        return self::iconSvg(
            $size,
            '<path d="M6 9V3h12v6" /><rect x="6" y="13" width="12" height="8" rx="1" />'
            . '<path d="M6 17H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2" />'
        );
    }

    public static function iconFile(int $size = 16): string
    {
        return self::iconSvg($size, '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><path d="M14 2v6h6" />');
    }

    public static function iconBook(int $size = 16): string
    {
        return self::iconSvg(
            $size,
            '<path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H19" />'
            . '<path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H19v20H6.5A2.5 2.5 0 0 1 4 19.5z" />'
            . '<path d="M8 7h8M8 11h6" />'
        );
    }

    /** 导出 PDF（折页文档） */
    public static function iconPdf(int $size = 16): string
    {
        return self::iconSvg(
            $size,
            '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />'
            . '<path d="M14 2v6h6" /><path d="M9 13h6" /><path d="M9 17h4" />'
        );
    }

    public static function iconZoomIn(int $size = 16): string
    {
        return self::iconSvg($size, '<circle cx="11" cy="11" r="6.5" /><path d="M11 8v6M8 11h6" /><path d="M16.5 16.5 21 21" />');
    }

    public static function iconZoomOut(int $size = 16): string
    {
        return self::iconSvg($size, '<circle cx="11" cy="11" r="6.5" /><path d="M8 11h6" /><path d="M16.5 16.5 21 21" />');
    }

    public static function iconChevronLeft(int $size = 14): string
    {
        return self::iconSvg($size, '<path d="M15 6 9 12l6 6" />');
    }

    public static function iconChevronRight(int $size = 14): string
    {
        return self::iconSvg($size, '<path d="M9 6l6 6-6 6" />');
    }

    private static function iconSvg(int $size, string $body): string
    {
        $size = (string) $size;

        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
            . 'stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . $body
            . '</svg>';
    }
}
