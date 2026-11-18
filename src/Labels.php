<?php

declare(strict_types=1);

namespace Poetry;

final class Labels
{
    /** @var array<string, string> */
    private const KIND_BADGE = [
        'tang' => '唐',
        'song' => '宋',
        'ci' => '词',
        'shijing' => '经',
        'chuci' => '楚',
        'lunyu' => '论',
        'sishu' => '书',
        'caocao' => '魏',
        'wudai' => '五',
        'yuding' => '御',
        'shuimo' => '墨',
        'yuanqu' => '元',
        'nalan' => '清',
        'youmeng' => '梦',
        'mengxue' => '蒙',
    ];

    /** @var array<string, string> */
    private const KIND_LABEL = [
        'tang' => '唐诗',
        'song' => '宋诗',
        'ci' => '宋词',
        'shijing' => '诗经',
        'chuci' => '楚辞',
        'lunyu' => '论语',
        'sishu' => '四书',
        'caocao' => '魏诗',
        'wudai' => '五代',
        'yuding' => '御定',
        'shuimo' => '水墨',
        'yuanqu' => '元曲',
        'nalan' => '纳兰',
        'youmeng' => '清言',
        'mengxue' => '蒙学',
    ];

    public static function kindBadge(string $kind): string
    {
        return self::KIND_BADGE[$kind] ?? $kind;
    }

    public static function kindLabel(string $kind, ?string $corpus = null): string
    {
        if ($corpus !== null && $corpus !== '全唐诗' && $corpus !== '宋词') {
            return $corpus;
        }

        return self::KIND_LABEL[$kind] ?? ($corpus ?? $kind);
    }
}
