<?php
// Generate SVG placeholder image
header('Content-Type: image/svg+xml');
header('Cache-Control: max-age=3600');

$width = isset($_GET['w']) ? (int)$_GET['w'] : 300;
$height = isset($_GET['h']) ? (int)$_GET['h'] : 250;
$text = isset($_GET['t']) ? urldecode($_GET['t']) : 'Produk';

$svg = <<<SVG
<svg width="$width" height="$height" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:#e0e7ff;stop-opacity:1" />
            <stop offset="100%" style="stop-color:#f3e8ff;stop-opacity:1" />
        </linearGradient>
    </defs>
    <rect width="$width" height="$height" fill="url(#grad)"/>
    <g opacity="0.3">
        <circle cx="100" cy="80" r="50" fill="#6366f1"/>
        <circle cx="250" cy="120" r="35" fill="#ec4899"/>
        <circle cx="150" cy="200" r="40" fill="#6366f1"/>
    </g>
    <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="24" font-weight="bold" text-anchor="middle" dominant-baseline="middle" fill="#6366f1" opacity="0.6">🖼️ Gambar</text>
    <text x="50%" y="60%" font-family="Arial, sans-serif" font-size="14" text-anchor="middle" dominant-baseline="middle" fill="#9333ea" opacity="0.5">Belum tersedia</text>
</svg>
SVG;

echo $svg;
?>
