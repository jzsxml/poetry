<?php

declare(strict_types=1);

namespace Poetry;

final class RecommendToday
{
    public const ROLE_LABEL = [
      'festival' => '今日佳节',
      'lunar_day' => '农历节令',
      'solar_exact' => '节气',
      'solar_current' => '当前节气',
  ];

    public function __construct(private PoetryRepository $repo)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function recommend(?\DateTimeImmutable $date = null, int $themeLimit = 2, int $poemLimit = 8, int $offset = 0): array
    {
        $calendar = SeasonCalendar::resolveToday($date);
        $themes = array_slice($calendar['activeThemes'], 0, $themeLimit);
        $primary = $calendar['activeThemes'][0] ?? null;
        $secondary = array_slice($calendar['activeThemes'], 1, max(0, $themeLimit - 1));

        $seen = [];
        /** @var list<array<string, mixed>> $hits */
        $hits = [];

        if ($themes === []) {
            return array_merge($calendar, [
                'primary' => null,
                'secondary' => [],
                'hits' => [],
                'totalMatched' => 0,
            ]);
        }

        $perTheme = max(3, (int) ceil($poemLimit / count($themes)));
        foreach ($themes as $active) {
            $batch = $this->collectForTheme($active['theme'], $perTheme, $offset, $seen);
            foreach ($batch as $h) {
                $hits[] = $h;
                if (count($hits) >= $poemLimit) {
                    break 2;
                }
            }
        }

        $trimmed = array_slice($hits, 0, $poemLimit);

        return array_merge($calendar, [
            'primary' => $primary,
            'secondary' => $secondary,
            'hits' => $trimmed,
            'totalMatched' => count($trimmed),
        ]);
    }

    /**
     * @param array<string, mixed> $rec
     * @return array<string, mixed>
     */
    public function enrichFeatured(array $rec): array
    {
        if ($rec['primary'] === null || ($rec['hits'] ?? []) === []) {
            return $rec;
        }
        $hits = is_array($rec['hits']) ? $rec['hits'] : [];
        $featured = $this->pickFeaturedForTheme($rec['primary']['theme'], $hits);
        if ($featured === null) {
            return $rec;
        }
        $preview = (string) ($featured['preview'] ?? '');
        if ($preview === '' || mb_strlen($preview, 'UTF-8') < 24) {
            $work = $this->repo->getWorkById((string) $featured['id']);
            if ($work === null) {
                return $rec;
            }
            $paras = is_array($work['paragraphs'] ?? null) ? $work['paragraphs'] : [];
            $preview = mb_substr(implode('', array_slice($paras, 0, 2)), 0, 120, 'UTF-8');
        }
        $rest = array_values(array_filter(
            $rec['hits'],
            static fn (array $h) => ($h['id'] ?? '') !== $featured['id']
        ));
        $head = array_merge($featured, [
            'preview' => $preview,
            'themeId' => $rec['primary']['theme']['id'],
            'themeName' => $rec['primary']['theme']['name'],
        ]);
        $merged = array_merge([$head], $rest);
        $rec['hits'] = array_slice($merged, 0, count($rec['hits']));

        return $rec;
    }

    /**
     * @param array<string, mixed> $theme
     * @param array<string, bool> $seen
     * @return list<array<string, mixed>>
     */
    private function collectForTheme(array $theme, int $limit, int $offset, array &$seen): array
    {
        /** @var list<array<string, mixed>> $out */
        $out = [];
        $themeId = (string) ($theme['id'] ?? '');
        if ($themeId !== '') {
            $db = Database::pdo();
            $poolHits = SeasonRecommendPool::fetchHits(
                $db,
                $this->repo,
                $themeId,
                $theme,
                $limit,
                $offset,
                $seen
            );
            if (count($poolHits) >= min($limit, 1)) {
                return $poolHits;
            }
        }

        $keywords = $theme['keywords'] ?? [];
        if ($keywords === []) {
            return $out;
        }

        foreach ($keywords as $ki => $_kw) {
            $kw = $keywords[($ki + $offset) % count($keywords)];
            $searchOffset = (int) floor($offset / count($keywords)) * $limit;
            $result = $this->repo->searchThemeKeyword($kw, $limit * 2, $searchOffset);
            $ranked = $result['hits'];
            usort($ranked, fn (array $a, array $b) => $this->scoreHit($b, $keywords) <=> $this->scoreHit($a, $keywords));
            foreach ($ranked as $h) {
                $id = (string) ($h['id'] ?? '');
                if ($id === '' || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $out[] = array_merge($h, [
                    'themeId' => $theme['id'],
                    'themeName' => $theme['name'],
                ]);
                if (count($out) >= $limit) {
                    return $out;
                }
            }
        }

        return $out;
    }

    /** @param list<string> $keywords */
    private function scoreHit(array $hit, array $keywords): int
    {
        $title = (string) ($hit['title'] ?? '');
        $rhythmic = (string) ($hit['rhythmic'] ?? '');
        $preview = (string) ($hit['preview'] ?? '');
        $score = 0;
        foreach ($keywords as $kw) {
            if ($title !== '' && str_contains($title, $kw)) {
                $score += 8;
            } elseif ($rhythmic !== '' && str_contains($rhythmic, $kw)) {
                $score += 5;
            } elseif ($preview !== '' && str_contains($preview, $kw)) {
                $score += 2;
            } else {
                $score += 1;
            }
        }

        return $score;
    }

    /** @param array<string, mixed> $theme */
    /**
     * @param list<array<string, mixed>> $existingHits
     */
    private function pickFeaturedForTheme(array $theme, array $existingHits = []): ?array
    {
        $keywords = array_slice($theme['keywords'] ?? [], 0, 2);
        $name = (string) ($theme['name'] ?? '');
        foreach ($existingHits as $h) {
            $title = (string) ($h['title'] ?? '');
            if ($title === '') {
                continue;
            }
            foreach ($keywords as $kw) {
                if (str_contains($title, $kw)) {
                    return $h;
                }
            }
            if ($name !== '' && str_contains($title, $name)) {
                return $h;
            }
        }
        foreach ($keywords as $kw) {
            $result = $this->repo->searchThemeKeyword($kw, 20, 0);
            foreach ($result['hits'] as $h) {
                $title = (string) ($h['title'] ?? '');
                if (($title !== '' && str_contains($title, $kw))
                    || ($title !== '' && $name !== '' && str_contains($title, $name))) {
                    return $h;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $rec
     * @return array<string, mixed>
     */
    public function toApiPayload(array $rec, int $offset): array
    {
        $primary = null;
        if ($rec['primary'] !== null) {
            $p = $rec['primary'];
            $role = (string) $p['role'];
            $primary = [
                'name' => $p['theme']['name'],
                'type' => $p['theme']['type'] ?? '',
                'role' => $role,
                'roleLabel' => self::ROLE_LABEL[$role] ?? '时令',
            ];
        }

        $secondary = [];
        foreach ($rec['secondary'] ?? [] as $s) {
            $role = (string) $s['role'];
            $secondary[] = [
                'name' => $s['theme']['name'],
                'type' => $s['theme']['type'] ?? '',
                'role' => $role,
                'roleLabel' => self::ROLE_LABEL[$role] ?? '时令',
            ];
        }

        return [
            'solarYmd' => $rec['solarYmd'],
            'lunarLabel' => $rec['lunarLabel'],
            'primary' => $primary,
            'secondary' => $secondary,
            'hits' => View::locHits($rec['hits'] ?? []),
            'totalMatched' => $rec['totalMatched'] ?? 0,
            'offset' => $offset,
        ];
    }
}