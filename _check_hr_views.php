<?php
$root = __DIR__;
$views = [];

foreach (glob($root . '/app/Http/Controllers/Backend/Hr/*.php') as $f) {
    $c = file_get_contents($f);
    if (preg_match_all("/(?:view|loadView)\\(\\s*['\"]([^'\"]+)['\"]/", $c, $m)) {
        foreach ($m[1] as $v) {
            $views[$v][] = basename($f);
        }
    }
}

$hrDir = $root . '/resources/views/backend/pages/hr';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($hrDir));
foreach ($it as $file) {
    if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }
    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $c = file_get_contents($file->getPathname());
    if (preg_match_all("/@include\\(\\s*['\"](backend\\.pages\\.hr\\.[^'\"]+)['\"]/", $c, $m)) {
        foreach ($m[1] as $v) {
            $views[$v][] = $rel;
        }
    }
    foreach ([
        'backend.pages.document.partials.standard_document_header_contact',
        'backend.pages.document.partials.standard_document_header_spacer',
    ] as $e) {
        if (strpos($c, $e) !== false) {
            $views[$e][] = $rel;
        }
    }
}

$missing = [];
foreach ($views as $v => $refs) {
    $path = $root . '/resources/views/' . str_replace('.', '/', $v) . '.blade.php';
    if (!is_file($path)) {
        foreach (array_unique($refs) as $r) {
            $missing[] = "$v | $r";
        }
    }
}

sort($missing);
echo empty($missing) ? "NONE\n" : implode("\n", $missing) . "\n";
echo "TOTAL_REFERENCED=" . count($views) . "\n";
