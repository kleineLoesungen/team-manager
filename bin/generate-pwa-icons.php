<?php
// bin/generate-pwa-icons.php
//
// Standalone CLI script — no framework bootstrap, no DB.
// Generates the static PWA icon set (Anthrazit background + white "T" monogram)
// used by public/manifest.webmanifest and src/templates/layout.php.
//
// Re-run any time with: php bin/generate-pwa-icons.php

declare(strict_types=1);

if (!extension_loaded('gd')) {
    fwrite(STDERR, "GD extension not available.\n");
    exit(1);
}

const BG_COLOR = '#2f3640';

// Glyph rectangles in the 32-unit viewBox from landing/icon.svg (background rect skipped —
// generated icons use a full-bleed sharp-square background; the OS applies its own icon-shape mask).
const GLYPH_BAR  = ['x' => 5.0,  'y' => 7.0, 'w' => 22.0, 'h' => 5.0];  // horizontal bar of the "T"
const GLYPH_STEM = ['x' => 13.0, 'y' => 7.0, 'w' => 6.0,  'h' => 19.0]; // vertical stem of the "T"

// Pivot for glyph_scale, in 32-unit viewBox space: bbox center of the two rectangles above.
const PIVOT_X = 16.0;
const PIVOT_Y = 16.5;

/**
 * Scales an axis-aligned rectangle from 32-unit viewBox space into $size-space, then scales it
 * around the fixed pivot point by $glyph_scale (shrinks/grows the glyph without moving its center).
 * Returns [x1, y1, x2, y2] in $size-space, ready for imagefilledrectangle().
 */
function scale_rect(array $rect, int $size, float $glyph_scale): array {
    $k = $size / 32;

    $x1 = $rect['x'] * $k;
    $y1 = $rect['y'] * $k;
    $x2 = ($rect['x'] + $rect['w']) * $k;
    $y2 = ($rect['y'] + $rect['h']) * $k;

    $cx = PIVOT_X * $k;
    $cy = PIVOT_Y * $k;

    return [
        $cx + ($x1 - $cx) * $glyph_scale,
        $cy + ($y1 - $cy) * $glyph_scale,
        $cx + ($x2 - $cx) * $glyph_scale,
        $cy + ($y2 - $cy) * $glyph_scale,
    ];
}

function draw_icon(int $size, float $glyph_scale, string $out_path): void {
    $img = imagecreatetruecolor($size, $size);

    // Full-bleed background, no transparency, no baked-in corner rounding.
    [$r, $g, $b] = sscanf(BG_COLOR, '#%02x%02x%02x');
    $bg = imagecolorallocate($img, $r, $g, $b);
    imagefilledrectangle($img, 0, 0, $size - 1, $size - 1, $bg);

    $white = imagecolorallocate($img, 255, 255, 255);

    foreach ([GLYPH_BAR, GLYPH_STEM] as $rect) {
        [$x1, $y1, $x2, $y2] = scale_rect($rect, $size, $glyph_scale);
        imagefilledrectangle(
            $img,
            (int) round($x1),
            (int) round($y1),
            (int) round($x2) - 1,
            (int) round($y2) - 1,
            $white
        );
    }

    imagepng($img, $out_path);
    // No imagedestroy() call: it has had no effect since PHP 8.0 (GD images are
    // garbage-collected automatically) and is deprecated as of PHP 8.5.
}

$icons_dir = __DIR__ . '/../public/icons/';
if (!is_dir($icons_dir)) {
    mkdir($icons_dir, 0755, true);
}

draw_icon(192, 1.0, $icons_dir . 'icon-192.png');
draw_icon(512, 1.0, $icons_dir . 'icon-512.png');
draw_icon(512, 0.65, $icons_dir . 'icon-maskable-512.png');
draw_icon(180, 1.0, $icons_dir . 'apple-touch-icon-180.png');

echo "PWA icons generated in {$icons_dir}\n";
