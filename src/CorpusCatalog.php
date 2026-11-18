<?php

declare(strict_types=1);

namespace Poetry;

final class CorpusCatalog
{
    /** @return list<array{id: string, name: string, inDb: bool, desc?: string, segments?: list<array{id: string, label: string, kinds: list<string>}>}> */
    public static function all(): array
    {
        return self::CATALOG;
    }

    public static function byId(string $id): ?array
    {
        foreach (self::CATALOG as $c) {
            if ($c['id'] === $id) {
                return $c;
            }
        }

        return null;
    }

    public static function browseFilter(array $corpus, ?string $segmentId): ?array
    {
        if (empty($corpus['inDb'])) {
            return null;
        }
        $segments = $corpus['segments'] ?? null;
        if ($segments) {
            $seg = null;
            if ($segmentId) {
                foreach ($segments as $s) {
                    if ($s['id'] === $segmentId) {
                        $seg = $s;
                        break;
                    }
                }
            } else {
                $seg = $segments[0] ?? null;
            }
            if (!$seg) {
                return null;
            }

            return ['corpus' => $corpus['id'], 'kinds' => $seg['kinds']];
        }

        return ['corpus' => $corpus['id']];
    }

    /** @var list<array{id: string, name: string, inDb: bool, desc?: string, segments?: list<array{id: string, label: string, kinds: list<string>}>}> */
    private const CATALOG = [
        ['id' => '诗经', 'name' => '诗经', 'inDb' => true, 'desc' => '中国最早的诗歌总集，收周代民歌与贵族作品三百零五篇。'],
        ['id' => '楚辞', 'name' => '楚辞', 'inDb' => true, 'desc' => '先秦楚地文学与屈原等骚体名篇，与《诗经》并称中国文学源头。'],
        ['id' => '论语', 'name' => '论语', 'inDb' => true, 'desc' => '孔子言行录，儒家经典，影响两千余年诗文与思想。'],
        ['id' => '四书五经', 'name' => '四书五经', 'inDb' => true, 'desc' => '大学、中庸、孟子等与经部文献，常作诵读与蒙学典故出处。'],
        ['id' => '曹操诗集', 'name' => '曹操诗集', 'inDb' => true, 'desc' => '曹魏诗歌，雄浑悲凉，开建安风骨之门。'],
        ['id' => '五代诗词', 'name' => '五代诗词', 'inDb' => true, 'desc' => '唐末五代词与近体诗，衔接唐宋词风。'],
        [
            'id' => '全唐诗',
            'name' => '全唐诗',
            'inDb' => true,
            'desc' => '唐人诗作总汇，可览唐诗与宋诗诸篇。',
            'segments' => [
                ['id' => 'tang', 'label' => '唐诗', 'kinds' => ['tang']],
                ['id' => 'song', 'label' => '宋诗', 'kinds' => ['song']],
            ],
        ],
        ['id' => '御定全唐詩', 'name' => '御定全唐詩', 'inDb' => true, 'desc' => '清代御纂本《全唐诗》，篇目完备，便于比对研究。'],
        ['id' => '水墨唐诗', 'name' => '水墨唐诗', 'inDb' => true, 'desc' => '精选唐诗篇目，意境清远，宜览读欣赏。'],
        ['id' => '宋词', 'name' => '宋词', 'inDb' => true, 'desc' => '宋代长短句词作，婉约豪放并举，词体艺术至盛。'],
        ['id' => '元曲', 'name' => '元曲', 'inDb' => true, 'desc' => '元代散曲与杂剧曲辞，語白清丽，近俗语情。'],
        ['id' => '纳兰性德', 'name' => '纳兰性德', 'inDb' => true, 'desc' => '清代词人纳兰容若词作，哀感顽艳，传唱最广。'],
        ['id' => '幽梦影', 'name' => '幽梦影', 'inDb' => true, 'desc' => '张潮清言小品《幽梦影》，品评诗文人生，隽语连篇。'],
        ['id' => '蒙学', 'name' => '蒙学', 'inDb' => true, 'desc' => '三字经、千字文等蒙学韵文与典故，便于记诵启蒙。'],
    ];
}
