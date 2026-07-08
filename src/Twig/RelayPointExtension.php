<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Twig;

use Keirontw\SyliusRelayPointPlugin\Ui\RelayPointUiClasses;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class RelayPointExtension extends AbstractExtension
{
    /** @param list<string> $relayMethodCodes */
    public function __construct(
        private readonly array $relayMethodCodes,
        private readonly RelayPointUiClasses $uiClasses,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('relay_method_codes', $this->getRelayMethodCodes(...)),
            new TwigFunction('relay_ui_class', $this->uiClasses->class(...)),
            new TwigFunction('relay_ui_theme', $this->uiClasses->theme(...)),
        ];
    }

    /** @return list<string> */
    public function getRelayMethodCodes(): array
    {
        return $this->relayMethodCodes;
    }
}
