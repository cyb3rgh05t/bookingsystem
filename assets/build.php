<?php
echo "Build-Prozess gestartet...\n";

// ============ CSS KOMBINIEREN ============
// Für Hauptseite
$mainCssFiles = [
    'css/fonts.css',    // Fonts zuerst!
    'css/theme.css',
    'css/style.css',
    'css/mobile.css'
];

$combinedCSS = '';
foreach ($mainCssFiles as $file) {
    if (file_exists($file)) {
        $combinedCSS .= "/* Source: $file */\n";
        $combinedCSS .= file_get_contents($file) . "\n";
    }
}

// CSS Minifizierung
$combinedCSS = preg_replace('/\/\*[^*]*\*+(?:[^\/][^*]*\*+)*\//', '', $combinedCSS); // Kommentare entfernen
$combinedCSS = preg_replace('/\s+/', ' ', $combinedCSS); // Whitespace reduzieren
$combinedCSS = str_replace('; ', ';', $combinedCSS);
$combinedCSS = str_replace(': ', ':', $combinedCSS);
$combinedCSS = str_replace(' {', '{', $combinedCSS);
$combinedCSS = str_replace('{ ', '{', $combinedCSS);
$combinedCSS = str_replace(' }', '}', $combinedCSS);
$combinedCSS = str_replace('} ', '}', $combinedCSS);
$combinedCSS = str_replace(', ', ',', $combinedCSS);

file_put_contents('css/main.min.css', $combinedCSS);
echo "✓ main.min.css erstellt (" . round(strlen($combinedCSS) / 1024, 2) . " KB)\n";

// Für Admin-Panel
$adminCssFiles = [
    'css/fonts.css',
    'css/theme.css',
    'css/style.css',
    'css/admin.css',
    'css/admin-mobile.css'
];

$adminCSS = '';
foreach ($adminCssFiles as $file) {
    if (file_exists($file)) {
        $adminCSS .= file_get_contents($file) . "\n";
    }
}

// Gleiche Minifizierung
$adminCSS = preg_replace('/\/\*[^*]*\*+(?:[^\/][^*]*\*+)*\//', '', $adminCSS);
$adminCSS = preg_replace('/\s+/', ' ', $adminCSS);
$adminCSS = str_replace('; ', ';', $adminCSS);
$adminCSS = str_replace(': ', ':', $adminCSS);
$adminCSS = str_replace(' {', '{', $adminCSS);
$adminCSS = str_replace('{ ', '{', $adminCSS);
$adminCSS = str_replace(' }', '}', $adminCSS);
$adminCSS = str_replace('} ', '}', $adminCSS);

file_put_contents('css/admin.min.css', $adminCSS);
echo "✓ admin.min.css erstellt (" . round(strlen($adminCSS) / 1024, 2) . " KB)\n";

// ============ JavaScript MINIFIZIERUNG ============
if (file_exists('js/booking.js')) {
    $js = file_get_contents('js/booking.js');

    // Einfache JS Minifizierung (Vorsicht: kann bei komplexem Code Probleme machen)
    $js = preg_replace('/\/\/.*$/m', '', $js); // Einzeilige Kommentare
    $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js); // Mehrzeilige Kommentare
    $js = preg_replace('/\s+/', ' ', $js);
    $js = str_replace(' = ', '=', $js);
    $js = str_replace(' + ', '+', $js);
    $js = str_replace(' - ', '-', $js);
    $js = str_replace(' * ', '*', $js);
    $js = str_replace(' / ', '/', $js);
    $js = str_replace(' { ', '{', $js);
    $js = str_replace(' } ', '}', $js);

    file_put_contents('js/booking.min.js', $js);
    echo "✓ booking.min.js erstellt (" . round(strlen($js) / 1024, 2) . " KB)\n";
}

echo "\nBuild erfolgreich abgeschlossen!\n";
echo "Vergiss nicht, die HTML-Dateien zu aktualisieren!\n";
