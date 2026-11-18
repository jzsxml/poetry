<?php

declare(strict_types=1);

namespace Poetry;

use com\nlf\calendar\JieQi;
use com\nlf\calendar\Solar;
use DateTimeImmutable;
use DateTimeZone;

final class SeasonCalendar
{
    /** @return array{y: int, m: int, d: int} */
    private static function shanghaiYmd(?DateTimeImmutable $date): array
    {
        $dt = $date ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
        $dt = $dt->setTimezone(new DateTimeZone('Asia/Shanghai'));

        return [
            'y' => (int) $dt->format('Y'),
            'm' => (int) $dt->format('n'),
            'd' => (int) $dt->format('j'),
        ];
    }

    private static function jieQiName(?JieQi $obj): string
    {
        if ($obj === null) {
            return '';
        }

        return trim($obj->getName());
    }

    /**
     * @return array{
     *   solarYmd: string,
     *   lunarLabel: string,
     *   activeThemes: list<array{theme: array<string, mixed>, priority: int, role: string}>
     * }
     */
    public static function resolveToday(?DateTimeImmutable $date = null): array
    {
        ['y' => $y, 'm' => $m, 'd' => $d] = self::shanghaiYmd($date);
        $solar = Solar::fromYmd($y, $m, $d);
        $lunar = $solar->getLunar();
        $lunarMonth = $lunar->getMonth();
        $lunarDay = $lunar->getDay();
        $lunarLabel = $lunar->toString();

        /** @var list<array{theme: array<string, mixed>, priority: int, role: string}> $active */
        $active = [];
        $seen = [];

        $push = static function (array $item) use (&$active, &$seen): void {
            $id = $item['theme']['id'];
            if (isset($seen[$id])) {
                return;
            }
            $seen[$id] = true;
            $active[] = $item;
        };

        $jqToday = $lunar->getJieQi();
        if ($jqToday !== '') {
            $theme = SeasonThemes::byJieQi($jqToday);
            if ($theme !== null) {
                $push([
                    'theme' => $theme,
                    'priority' => $theme['basePriority'] + 55,
                    'role' => 'solar_exact',
                ]);
            }
        }

        $festivals = array_merge($lunar->getFestivals(), $lunar->getOtherFestivals());
        foreach ($festivals as $fname) {
            $theme = SeasonThemes::byFestivalName($fname);
            if ($theme !== null) {
                $push([
                    'theme' => $theme,
                    'priority' => $theme['basePriority'] + 25,
                    'role' => 'festival',
                ]);
            }
        }

        foreach (SeasonThemes::all() as $theme) {
            if (($theme['type'] ?? '') !== 'lunar' || ($theme['id'] ?? '') === 'chuxi') {
                continue;
            }
            if (SeasonThemes::matchLunarDate($lunarMonth, $lunarDay, $theme)) {
                $push([
                    'theme' => $theme,
                    'priority' => $theme['basePriority'] + 15,
                    'role' => 'lunar_day',
                ]);
            }
        }

        if (in_array('除夕', $festivals, true)) {
            $theme = SeasonThemes::byFestivalName('除夕');
            if ($theme !== null) {
                $push([
                    'theme' => $theme,
                    'priority' => $theme['basePriority'] + 25,
                    'role' => 'festival',
                ]);
            }
        }

        if ($jqToday === '') {
            $prev = $lunar->getPrevJieQi();
            $currentName = self::jieQiName($prev);
            if ($currentName !== '') {
                $theme = SeasonThemes::byJieQi($currentName);
                if ($theme !== null) {
                    $push([
                        'theme' => $theme,
                        'priority' => $theme['basePriority'] + 5,
                        'role' => 'solar_current',
                    ]);
                }
            }
        }

        usort($active, static fn (array $a, array $b) => $b['priority'] <=> $a['priority']);

        return [
            'solarYmd' => sprintf('%d-%02d-%02d', $y, $m, $d),
            'lunarLabel' => $lunarLabel,
            'activeThemes' => $active,
        ];
    }
}