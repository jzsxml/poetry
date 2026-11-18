<?php

declare(strict_types=1);

namespace Poetry;

final class Site
{
    public const NAME = '中华古诗词库';

    public const TITLE = '中华古诗词库 · 标题作者检索';

    public const TAGLINE = '十四典籍 · 近四十万篇诗词曲赋 · 按作者与篇名检索';

    public const DESCRIPTION =
        '中华古诗词库收录诗经、楚辞、全唐诗、宋词、元曲等十四种典籍，近四十万篇诗词曲赋，支持按作者与篇名检索、简繁切换，便于赏析与学习。';

    public const SCRIPT_COOKIE = 'poetry-script';

    /** @var list<array{value: string, label: string}> */
    public const KIND_FILTER_OPTIONS = [
        ['value' => 'all', 'label' => '全部'],
        ['value' => 'tang', 'label' => '唐诗'],
        ['value' => 'song', 'label' => '宋诗'],
        ['value' => 'ci', 'label' => '宋词'],
        ['value' => 'yuanqu', 'label' => '元曲'],
    ];
}
