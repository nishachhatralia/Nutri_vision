<?php
/**
 * NutriVision AI - Inline SVG Icon Helper
 *
 * Returns the raw markup of an icon from /icons/*.svg so it can be
 * embedded directly in the page (not as an <img>). Embedding inline
 * lets the icon inherit `color` from its surrounding CSS (via
 * `currentColor` inside the SVG), so the exact same icon file renders
 * green on the navbar and white inside a colored button, instead of
 * depending on the device's emoji font (which is what made these
 * icons randomly disappear / show as a flat colour block before).
 *
 * Usage:  <?php echo nv_icon('goals'); ?>
 *         <?php echo nv_icon('history', 18); ?>
 */
function nv_icon($name, $size = 20) {
    static $cache = [];

    if (!isset($cache[$name])) {
        $path = __DIR__ . '/../icons/' . basename($name) . '.svg';
        $cache[$name] = is_file($path) ? file_get_contents($path) : '';
    }

    $svg = $cache[$name];
    if ($svg === '') {
        return '';
    }

    // Inject width/height/class without needing a template engine
    $svg = preg_replace(
        '/<svg /',
        '<svg class="nv-icon nv-icon-' . htmlspecialchars($name) . '" width="' . (int)$size . '" height="' . (int)$size . '" ',
        $svg,
        1
    );

    return $svg;
}
