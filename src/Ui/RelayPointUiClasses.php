<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Ui;

/**
 * Maps semantic widget elements to CSS-framework-specific class strings,
 * so the Twig template stays framework-agnostic.
 */
final class RelayPointUiClasses
{
    private const THEMES = [
        'tailwind' => [
            'search_bar_wrapper' => 'flex gap-2 mb-3',
            'search_form' => 'flex flex-1 gap-2',
            'search_input' => 'flex-1 border rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2',
            'search_button' => 'px-4 py-2 text-sm font-medium transition-colors',
            'filter_wrapper' => 'hidden relative',
            'filter_button' => 'h-10 px-4 text-sm font-medium transition-colors',
            'filter_panel' => 'hidden absolute top-full right-0 mt-1 bg-white shadow-lg z-20 p-3 w-56',
            'filter_label' => 'text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2',
            'filter_carrier_list' => 'space-y-1',
            'grid' => 'grid grid-cols-1 md:grid-cols-2 gap-3',
            'list_panel' => 'order-2 md:order-1 h-[300px] md:h-[360px] overflow-y-auto bg-white',
            'list_placeholder' => 'p-6 text-center text-sm text-gray-400 italic',
            'map_wrapper' => 'order-1 md:order-2',
            'map' => 'h-[300px] md:h-[360px] z-0',
            'summary_wrapper' => 'hidden mt-4 p-4',
            'summary_inner' => 'flex flex-col md:flex-row gap-4',
            'summary_main' => 'flex-1',
            'summary_header_row' => 'flex items-center gap-2 mb-1',
            'summary_carrier_badge' => 'text-[10px] font-bold uppercase px-2 py-0.5 rounded',
            'summary_distance' => 'text-xs text-gray-400',
            'summary_name' => 'font-semibold text-gray-900 text-sm',
            'summary_address' => 'text-xs text-gray-500 mt-0.5',
            'summary_hours_wrapper' => 'md:border-l md:pl-4',
            'summary_hours_label' => 'text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1',
            'summary_hours_list' => 'space-y-0.5',
            'summary_footer' => 'mt-3 pt-3 flex justify-end',
            'confirm_button' => 'inline-flex items-center gap-2 text-white text-sm font-semibold px-6 py-2 transition-colors',
        ],
        'bootstrap' => [
            'search_bar_wrapper' => 'd-flex gap-2 mb-3',
            'search_form' => 'd-flex flex-grow-1 gap-2',
            'search_input' => 'form-control rounded-pill',
            'search_button' => 'btn btn-light',
            'filter_wrapper' => 'd-none position-relative',
            'filter_button' => 'btn btn-outline-secondary',
            'filter_panel' => 'd-none position-absolute top-100 end-0 mt-1 bg-white shadow z-3 p-3',
            'filter_label' => 'small fw-bold text-muted text-uppercase mb-2',
            'filter_carrier_list' => 'd-flex flex-column gap-1',
            'grid' => 'row row-cols-1 row-cols-md-2 g-3',
            'list_panel' => 'col order-2 order-md-1 bg-white overflow-auto',
            'list_placeholder' => 'p-4 text-center text-muted fst-italic',
            'map_wrapper' => 'col order-1 order-md-2',
            'map' => 'w-100 h-100',
            'summary_wrapper' => 'd-none mt-4 p-4 card',
            'summary_inner' => 'd-flex flex-column flex-md-row gap-4',
            'summary_main' => 'flex-fill',
            'summary_header_row' => 'd-flex align-items-center gap-2 mb-1',
            'summary_carrier_badge' => 'badge text-uppercase',
            'summary_distance' => 'small text-muted',
            'summary_name' => 'fw-semibold mb-0',
            'summary_address' => 'small text-muted mt-1',
            'summary_hours_wrapper' => 'border-start ps-3',
            'summary_hours_label' => 'small fw-bold text-muted text-uppercase mb-1',
            'summary_hours_list' => 'd-flex flex-column gap-1',
            'summary_footer' => 'mt-3 pt-3 d-flex justify-content-end border-top',
            'confirm_button' => 'btn btn-primary d-inline-flex align-items-center gap-2',
        ],
    ];

    /** @param 'tailwind'|'bootstrap' $theme */
    public function __construct(
        private readonly string $theme = 'tailwind',
    ) {}

    public function class(string $key): string
    {
        return self::THEMES[$this->theme][$key]
            ?? throw new \InvalidArgumentException(sprintf('Unknown UI class key "%s".', $key));
    }

    public function theme(): string
    {
        return $this->theme;
    }
}
