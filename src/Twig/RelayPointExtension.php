<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class RelayPointExtension extends AbstractExtension
{
    /** @param list<string> $relayMethodCodes */
    public function __construct(
        private readonly array $relayMethodCodes,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('relay_method_codes', $this->getRelayMethodCodes(...)),
        ];
    }

    /** @return list<string> */
    public function getRelayMethodCodes(): array
    {
        return $this->relayMethodCodes;
    }
}
