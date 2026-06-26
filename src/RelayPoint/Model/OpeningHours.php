<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\RelayPoint\Model;

final class OpeningHours
{
    public function __construct(
        public readonly string $day,
        public readonly string $hours,
    ) {
    }
}
