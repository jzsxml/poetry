<?php

declare(strict_types=1);

namespace Poetry;

final class KindFilter
{
    /** @var list<string> */
    public const OPTIONS = ['all', 'tang', 'song', 'ci', 'yuanqu'];

    public static function parse(?string $raw): string
  {
      $k = strtolower(trim($raw ?? 'all'));

      return in_array($k, self::OPTIONS, true) ? $k : 'all';
  }

  public static function isWorkKind(?string $raw): bool
  {
      if ($raw === null || $raw === '') {
          return false;
      }
      $k = strtolower($raw);

      return $k !== 'all' && in_array($k, self::OPTIONS, true);
  }
}
