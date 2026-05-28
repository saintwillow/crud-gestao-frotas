<?php
$dir = __DIR__ . '/../';
echo "Scanning directory: $dir\n";

function scan($path) {
    $items = scandir($path);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $path . '/' . $item;
        if (is_dir($full)) {
            if ($item === 'scratch' || $item === '.git' || $item === '.vscode') continue;
            scan($full);
        } else {
            if (pathinfo($full, PATHINFO_EXTENSION) === 'php') {
                $content = file_get_contents($full);
                if (strpos($content, 'infraestrutura') !== false || strpos($content, 'sql_filtro') !== false) {
                    echo "Found: $full\n";
                }
            }
        }
    }
}

scan($dir);
echo "Scan finished.\n";
