<?php

/**
 * Outline-SVGs im Stil der Navigation (stroke, currentColor, abgerundete Enden).
 */

if (!function_exists('svg_icon_pencil')) {
    function svg_icon_pencil(int $size = 20): string {
        $w = htmlspecialchars((string) $size, ENT_QUOTES, 'UTF-8');
        return '<svg class="fab-svg fab-svg-pencil" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="' . $w . '" height="' . $w . '" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . '<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.318 4.954 4.954-1.318a2 2 0 0 0 .83-.497z"/>'
            . '<path d="m15 5 4 4"/>'
            . '</svg>';
    }
}

if (!function_exists('svg_icon_trash')) {
    function svg_icon_trash(int $size = 20): string {
        $w = htmlspecialchars((string) $size, ENT_QUOTES, 'UTF-8');
        return '<svg class="fab-svg fab-svg-trash" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="' . $w . '" height="' . $w . '" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . '<path d="M3 6h18"/>'
            . '<path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>'
            . '<path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>'
            . '<path d="M10 11v6"/>'
            . '<path d="M14 11v6"/>'
            . '</svg>';
    }
}
