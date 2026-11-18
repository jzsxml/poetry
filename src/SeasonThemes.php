<?php

declare(strict_types=1);

namespace Poetry;

final class SeasonThemes
{
    /** @var array<string, list<string>> */
    private const SOLAR_KEYWORDS = [
        '立春' => ['立春', '东风', '迎春'],
        '雨水' => ['雨水', '润物', '春雨'],
        '惊蛰' => ['惊蛰', '春雷', '桃花'],
        '春分' => ['春分', '昼夜', '燕归'],
        '清明' => ['清明时节', '清明雨', '踏青', '杏花村'],
        '谷雨' => ['谷雨', '秧绿', '蚕桑'],
        '立夏' => ['立夏', '绿树', '新荷'],
        '小满' => ['小满', '麦穗', '蚕忙'],
        '芒种' => ['芒种', '农家', '插秧'],
        '夏至' => ['夏至', '日长', '蝉鸣'],
        '小暑' => ['小暑', '温风', '荷塘'],
        '大暑' => ['大暑', '炎天', '流萤'],
        '立秋' => ['立秋', '梧桐', '一叶'],
        '处暑' => ['处暑', '新凉', '残暑'],
        '白露' => ['白露', '蒹葭', '鸿雁'],
        '秋分' => ['秋分', '丹桂', '明月'],
        '寒露' => ['寒露', '菊花', '深秋'],
        '霜降' => ['霜降', '枫红', '柿熟'],
        '立冬' => ['立冬', '初冬', '霜华'],
        '小雪' => ['小雪', '初雪', '梅蕾'],
        '大雪' => ['大雪', '飞雪', '玉龙'],
        '冬至' => ['冬至', '阳生', '饺子'],
        '小寒' => ['小寒', '苦寒', '梅花'],
        '大寒' => ['大寒', '腊尽', '迎春'],
    ];

    /** @var list<array<string, mixed>> */
    private static ?array $allThemes = null;

    /** @var array<string, array<string, mixed>> */
    private static ?array $byId = null;

    /** @var array<string, array<string, mixed>> */
    private static ?array $byJieQi = null;

    /** @var array<string, array<string, mixed>> */
    private static ?array $byFestival = null;

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        if (self::$allThemes !== null) {
            return self::$allThemes;
        }
        $lunar = [
            ['id' => 'chunjie', 'name' => '春节', 'basePriority' => 100, 'keywords' => ['春节', '元日', '屠苏', '爆竹', '桃符'], 'festivalNames' => ['春节'], 'lunarMonth' => 1, 'lunarDay' => 1],
            ['id' => 'yuanxiao', 'name' => '元宵节', 'basePriority' => 80, 'keywords' => ['元宵', '上元', '花灯', '火树银花'], 'festivalNames' => ['元宵节'], 'lunarMonth' => 1, 'lunarDay' => 15],
            ['id' => 'longtaitou', 'name' => '龙抬头', 'basePriority' => 55, 'keywords' => ['龙抬头', '二月二', '春耕'], 'lunarMonth' => 2, 'lunarDay' => 2],
            ['id' => 'huazhao', 'name' => '花朝节', 'basePriority' => 50, 'keywords' => ['花朝', '百花', '踏青'], 'lunarMonth' => 2, 'lunarDay' => 12],
            ['id' => 'shangsi', 'name' => '上巳节', 'basePriority' => 52, 'keywords' => ['上巳', '曲水', '兰亭', '踏青'], 'lunarMonth' => 3, 'lunarDay' => 3],
            ['id' => 'duanwu', 'name' => '端午节', 'basePriority' => 90, 'keywords' => ['端午', '屈原', '龙舟', '菖蒲', '榴花'], 'festivalNames' => ['端午节'], 'lunarMonth' => 5, 'lunarDay' => 5],
            ['id' => 'tiankuang', 'name' => '天贶节', 'basePriority' => 45, 'keywords' => ['晒衣', '伏天', '六月六'], 'lunarMonth' => 6, 'lunarDay' => 6],
            ['id' => 'qixi', 'name' => '七夕', 'basePriority' => 78, 'keywords' => ['七夕', '牛郎织女', '乞巧', '鹊桥'], 'festivalNames' => ['七夕节'], 'lunarMonth' => 7, 'lunarDay' => 7],
            ['id' => 'zhongyuan', 'name' => '中元节', 'basePriority' => 48, 'keywords' => ['中元', '河灯', '盂兰', '秋江'], 'lunarMonth' => 7, 'lunarDay' => 15],
            ['id' => 'zhongqiu', 'name' => '中秋节', 'basePriority' => 92, 'keywords' => ['中秋', '明月', '团圆', '桂花', '婵娟'], 'festivalNames' => ['中秋节'], 'lunarMonth' => 8, 'lunarDay' => 15],
            ['id' => 'chongyang', 'name' => '重阳节', 'basePriority' => 82, 'keywords' => ['重阳', '登高', '茱萸', '菊花酒'], 'festivalNames' => ['重阳节'], 'lunarMonth' => 9, 'lunarDay' => 9],
            ['id' => 'hanyi', 'name' => '寒衣节', 'basePriority' => 46, 'keywords' => ['寒衣', '十月一', '送衣'], 'lunarMonth' => 10, 'lunarDay' => 1],
            ['id' => 'laba', 'name' => '腊八节', 'basePriority' => 68, 'keywords' => ['腊八', '腊八粥', '腊月'], 'festivalNames' => ['腊八节'], 'lunarMonth' => 12, 'lunarDay' => 8],
            ['id' => 'xiaonian_north', 'name' => '北方小年', 'basePriority' => 65, 'keywords' => ['小年', '灶神', '祭灶'], 'festivalNames' => ['北方小年', '小年'], 'lunarMonth' => 12, 'lunarDay' => 23],
            ['id' => 'xiaonian_south', 'name' => '南方小年', 'basePriority' => 64, 'keywords' => ['小年', '扫尘', '糖瓜'], 'lunarMonth' => 12, 'lunarDay' => 24],
            ['id' => 'chuxi', 'name' => '除夕', 'basePriority' => 98, 'keywords' => ['除夕', '守岁', '屠苏', '爆竹'], 'festivalNames' => ['除夕']],
        ];
        $themes = [];
        foreach ($lunar as $row) {
            $row['type'] = 'lunar';
            $themes[] = $row;
        }
        foreach (self::SOLAR_KEYWORDS as $jieQi => $keywords) {
            $themes[] = [
                'id' => 'jq_' . $jieQi,
                'name' => $jieQi,
                'type' => 'solar_term',
                'basePriority' => 42,
                'keywords' => $keywords,
                'jieQi' => $jieQi,
            ];
        }
        self::$allThemes = $themes;
        self::$byId = [];
        self::$byJieQi = [];
        self::$byFestival = [];
        foreach ($themes as $t) {
            self::$byId[$t['id']] = $t;
            if (isset($t['jieQi'])) {
                self::$byJieQi[$t['jieQi']] = $t;
            }
            if (!empty($t['festivalNames']) && is_array($t['festivalNames'])) {
                foreach ($t['festivalNames'] as $fname) {
                    self::$byFestival[$fname] = $t;
                }
            }
        }

        return self::$allThemes;
    }

    /** @return array<string, mixed>|null */
    public static function byJieQi(string $name): ?array
    {
        self::all();

        return self::$byJieQi[$name] ?? null;
    }

    /** @return array<string, mixed>|null */
    public static function byFestivalName(string $name): ?array
    {
        self::all();

        return self::$byFestival[$name] ?? null;
    }

    public static function matchLunarDate(int $lunarMonth, int $lunarDay, array $theme): bool
    {
        if (!isset($theme['lunarMonth'], $theme['lunarDay'])) {
            return false;
        }
        $absMonth = abs($lunarMonth);
        if ($absMonth !== (int) $theme['lunarMonth']) {
            return false;
        }
        $days = $theme['lunarDay'];
        if (!is_array($days)) {
            $days = [$days];
        }

        return in_array($lunarDay, $days, true);
    }
}