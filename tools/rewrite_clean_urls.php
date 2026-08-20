<?php
/**
 * One-shot CLI: strip .php from browser-facing URLs (href, action, Location, JS).
 * Does NOT modify require/include paths.
 *
 * Run: php tools/rewrite_clean_urls.php
 */
$root = dirname(__DIR__);
$skipDirs = ['vendor', 'archive', 'node_modules', '.git', 'storage', 'tools'];

function should_skip_file(string $path, array $skipDirs): bool {
    $norm = str_replace('\\', '/', $path);
    foreach ($skipDirs as $d) {
        if (str_contains($norm, '/' . $d . '/')) {
            return true;
        }
    }
    return false;
}

function is_include_line(string $line): bool {
    return (bool) preg_match('/\b(require|include)(_once)?\s*(\(|\s)/i', $line);
}

/** Convert a public path: marketplace.php → marketplace ; index.php → ./ or / */
function clean_public_path(string $url): string {
    $url = trim($url);
    if ($url === '' || preg_match('#^(https?:|mailto:|tel:|javascript:|data:)#i', $url) || str_starts_with($url, '#')) {
        return $url;
    }
    if (!str_contains(strtolower($url), '.php')) {
        return $url;
    }

    $fragment = '';
    if (str_contains($url, '#')) {
        [$url, $frag] = explode('#', $url, 2);
        $fragment = '#' . $frag;
    }
    $qs = '';
    if (str_contains($url, '?')) {
        [$url, $qs] = explode('?', $url, 2);
        $qs = '?' . $qs;
    }

    // Only strip trailing .php from the path segment
    if (!preg_match('/\.php$/i', $url)) {
        return $url . $qs . $fragment; // .php mid-path unlikely
    }

    $path = preg_replace('/\.php$/i', '', $url);

    // index → home
    if (preg_match('#(^|/)\./index$#', $path) || preg_match('#(^|/)index$#', $path)) {
        if ($path === 'index' || $path === './index') {
            $path = './';
        } elseif ($path === '/index') {
            $path = '/';
        } elseif (str_ends_with($path, '/index')) {
            $path = substr($path, 0, -5); // keep trailing slash parent
            if ($path === '' || $path === '.') {
                $path = './';
            } elseif (!str_ends_with($path, '/')) {
                $path .= '/';
            }
        }
    }

    return $path . $qs . $fragment;
}

function transform_line(string $line): string {
    if (is_include_line($line)) {
        return $line;
    }

    // href / action attributes
    $line = preg_replace_callback(
        '/\b(href|action)\s*=\s*(["\'])([^"\']+)\2/i',
        static function ($m) {
            $clean = clean_public_path($m[3]);
            if ($clean === $m[3]) {
                return $m[0];
            }
            return $m[1] . '=' . $m[2] . $clean . $m[2];
        },
        $line
    );

    // header('Location: ...') / header("Location: ...")
    $line = preg_replace_callback(
        '/header\s*\(\s*(["\'])Location:\s*([^"\']*?)\1/i',
        static function ($m) {
            $target = $m[2];
            // Skip variables / expressions
            if (str_contains($target, '$') || str_contains($target, 'rdv_url(') || str_contains($target, 'rdv_blog_url(') || str_contains($target, 'rdv_store_')) {
                return $m[0];
            }
            $clean = clean_public_path($target);
            if ($clean === $target) {
                return $m[0];
            }
            return 'header(' . $m[1] . 'Location: ' . $clean . $m[1];
        },
        $line
    );

    // JS: window.location / location.href / .href = 'x.php'
    $line = preg_replace_callback(
        '/((?:window\.)?location(?:\.href)?\s*=\s*)(["\'])([^"\']+)\2/i',
        static function ($m) {
            $clean = clean_public_path($m[3]);
            if ($clean === $m[3]) {
                return $m[0];
            }
            return $m[1] . $m[2] . $clean . $m[2];
        },
        $line
    );

    // fetch('x.php') / $.ajax url: 'x.php' / axios
    $line = preg_replace_callback(
        '/\b(fetch|axios\.(?:get|post|put|delete))\(\s*(["\'])([^"\']+\.php[^"\']*)\2/i',
        static function ($m) {
            $clean = clean_public_path($m[3]);
            return $m[1] . '(' . $m[2] . $clean . $m[2];
        },
        $line
    );

    $line = preg_replace_callback(
        '/\burl\s*:\s*(["\'])([^"\']+\.php[^"\']*)\1/i',
        static function ($m) {
            $clean = clean_public_path($m[2]);
            return 'url: ' . $m[1] . $clean . $m[1];
        },
        $line
    );

    return $line;
}

function transform_file(string $content): string {
    $lines = preg_split("/\r\n|\n|\r/", $content);
    $out = [];
    foreach ($lines as $line) {
        $out[] = transform_line($line);
    }
    $result = implode("\n", $out);
    // Preserve final newline if original had one
    if (str_ends_with($content, "\n") && !str_ends_with($result, "\n")) {
        $result .= "\n";
    }
    return $result;
}

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$changed = [];
foreach ($rii as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    if (should_skip_file($path, $skipDirs)) {
        continue;
    }
    $ext = strtolower($file->getExtension());
    if (!in_array($ext, ['php', 'js', 'html'], true)) {
        continue;
    }
    // Never rewrite the rewrite tool itself mid-flight incorrectly — skip tools
    $before = file_get_contents($path);
    if ($before === false || !str_contains($before, '.php')) {
        continue;
    }
    $after = transform_file($before);
    if ($after !== $before) {
        file_put_contents($path, $after);
        $changed[] = str_replace('\\', '/', substr($path, strlen($root) + 1));
    }
}

echo 'Updated ' . count($changed) . " files\n";
foreach ($changed as $c) {
    echo " - $c\n";
}
