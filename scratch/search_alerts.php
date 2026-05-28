<?php
$dir = __DIR__ . '/../';
echo "Scanning for alert boxes...\n";

function scan($path) {
    $items = scandir($path);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $path . '/' . $item;
        if (is_dir($full)) {
            if ($item === 'scratch' || $item === '.git' || $item === '.vscode' || $item === 'css' || $item === 'js') continue;
            scan($full);
        } else {
            if (pathinfo($full, PATHINFO_EXTENSION) === 'php') {
                $content = file_get_contents($full);
                // We are looking for things like <div class="alert alert-success or similar, and $_GET['msg'] inside index.php files
                if (basename($full) === 'index.php' || basename($full) === 'abastecimentos.php' || basename($full) === 'ocorrencias.php' || basename($full) === 'ordens.php') {
                    if (strpos($content, 'alert-success') !== false || strpos($content, 'alert alert-') !== false || strpos($content, 'msg=') !== false || strpos($content, "['msg']") !== false) {
                        echo "Found target: $full\n";
                    }
                }
            }
        }
    }
}

scan($dir);
echo "Scan finished.\n";
