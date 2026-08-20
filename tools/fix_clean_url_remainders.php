<?php
$root = dirname(__DIR__);
$skip = ['vendor', 'archive', 'node_modules', '.git', 'storage', 'tools'];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$n = 0;
foreach ($rii as $f) {
    if (!$f->isFile()) {
        continue;
    }
    $p = str_replace('\\', '/', $f->getPathname());
    foreach ($skip as $d) {
        if (str_contains($p, '/' . $d . '/')) {
            continue 2;
        }
    }
    if (!in_array(strtolower($f->getExtension()), ['php', 'js', 'html'], true)) {
        continue;
    }
    $c = file_get_contents($p);
    // href/action with .php immediately before ? # &
    $new = preg_replace('/\b(href|action)\s*=\s*(["\'])([^"\']*?)\.php([?&#])/i', '$1=$2$3$4', $c);
    if ($new !== $c) {
        file_put_contents($p, $new);
        echo substr($p, strlen($root) + 1) . "\n";
        $n++;
    }
}
echo "fixed $n files\n";
