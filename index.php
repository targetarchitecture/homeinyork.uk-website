<?php
/**
 * homeinyork.uk
 * A gallery of York, England — images sourced from Wikimedia Commons (CC-licensed / public domain).
 * No text. Just York.
 */

define('CACHE_FILE', __DIR__ . '/cache/york_images.json');
define('CACHE_TTL', 86400);   // Refresh image list once per day

// ─── Curated fallback set ────────────────────────────────────────────────────
// Used when the Wikimedia API is unavailable on first load.
// All via Wikimedia's thumb.php endpoint — no hash knowledge needed.
function fallback_images(): array {
    $files = [
        ['f' => 'York_Minster_Shambles.JPG',                                           'alt' => 'York Minster from The Shambles'],
        ['f' => 'YorkMinster.JPG',                                                      'alt' => 'York Minster from the city walls'],
        ['f' => 'East_facade_of_York_Minster_(2).jpg',                                 'alt' => 'East facade of York Minster'],
        ['f' => 'Central_Tower%2C_York_Minster.jpg',                                   'alt' => 'Central Tower, York Minster'],
        ['f' => 'Shambles%2C_seen_from_the_North_York_20240523_0034_DxO.jpg',          'alt' => 'The Shambles from the north'],
        ['f' => 'Shambles%2C_seen_from_the_South_York_20240523_0048_DxO.jpg',          'alt' => 'The Shambles from the south'],
        ['f' => 'Shambles%2C_No._2_looking_east_York_20240521_0010_DxO.jpg',           'alt' => 'The Shambles looking east'],
        ['f' => 'Shambles%2C_No._40_looking_east_York_20240521_0003_DxO.jpg',          'alt' => 'The Shambles No. 40'],
        ['f' => 'The_Shambles_(27064375854).jpg',                                       'alt' => 'The Shambles, York'],
    ];
    return array_map(fn($f) => [
        'url' => 'https://commons.wikimedia.org/w/thumb.php?f=' . $f['f'] . '&w=900',
        'alt' => $f['alt'],
    ], $files);
}

// ─── Wikimedia Commons API search ────────────────────────────────────────────
function wikimedia_search(string $query, int $limit = 20): array {
    $url = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
        'action'       => 'query',
        'generator'    => 'search',
        'gsrsearch'    => $query,
        'gsrnamespace' => 6,           // File namespace only
        'prop'         => 'imageinfo',
        'iiprop'       => 'url|size|mediatype',
        'iiurlwidth'   => 900,
        'format'       => 'json',
        'gsrlimit'     => $limit,
    ]);

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 8,
            'header'  => "User-Agent: homeinyork.uk gallery (+https://homeinyork.uk)\r\n",
        ],
    ]);

    $response = @file_get_contents($url, false, $ctx);
    if (!$response) return [];

    $data    = json_decode($response, true);
    $results = [];

    foreach ($data['query']['pages'] ?? [] as $page) {
        $info = $page['imageinfo'][0] ?? null;
        if (!$info || $info['mediatype'] !== 'BITMAP') continue;
        if (empty($info['thumburl'])) continue;
        if (($info['thumbwidth'] ?? 0) < 300) continue; // skip thumbnails

        $title = str_replace(['File:', '_'], ['', ' '], $page['title']);
        $title = preg_replace('/\.(jpe?g|png|gif|webp)$/i', '', $title);

        $results[] = ['url' => $info['thumburl'], 'alt' => $title];
    }

    return $results;
}

// ─── Load images (from cache or API) ─────────────────────────────────────────
function load_images(): array {
    // Serve from cache if fresh
    if (file_exists(CACHE_FILE) && (time() - filemtime(CACHE_FILE)) < CACHE_TTL) {
        $cached = json_decode(file_get_contents(CACHE_FILE), true);
        if (!empty($cached)) return $cached;
    }

    // Fetch from Wikimedia Commons with a handful of well-targeted searches
    $queries = [
        'York Minster England cathedral',
        'The Shambles York England street',
        'York city walls England medieval',
        'River Ouse York England',
        'Clifford Tower York England',
        'Bootham Bar York gate',
        'York Guildhall England',
        'York Museum Gardens ruins England',
    ];

    $all = [];
    foreach ($queries as $q) {
        $batch = wikimedia_search($q, 12);
        $all   = array_merge($all, $batch);
        if ($batch) usleep(200000); // 200 ms between requests — polite to WMF
    }

    // Deduplicate by URL
    $seen   = [];
    $unique = [];
    foreach ($all as $img) {
        if (!isset($seen[$img['url']])) {
            $seen[$img['url']] = true;
            $unique[] = $img;
        }
    }

    shuffle($unique);

    // Persist to cache
    if (!empty($unique)) {
        @mkdir(dirname(CACHE_FILE), 0755, true);
        file_put_contents(CACHE_FILE, json_encode($unique));
    }

    return $unique;
}

$images = load_images();

// If API failed on first cold load, use the curated fallback set
if (empty($images)) {
    $images = fallback_images();
}
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home of The Joblings online</title>
    <meta name="description" content="York, England">
    <meta name="robots" content="index, follow">
    <style>
        /* ── Reset ── */
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* ── Canvas ── */
        html, body {
            background: #000;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Masonry grid via CSS columns ── */
        .gallery {
            columns: 5 220px;   /* 5 cols on wide screens, naturally fewer on small */
            column-gap: 2px;
            padding: 2px;
        }

        /* ── Each image cell ── */
        .gallery__item {
            break-inside: avoid;     /* never split an image across columns */
            display: block;
            margin-bottom: 2px;
            overflow: hidden;        /* clips the zoom transform */
            position: relative;
        }

        /* ── Image defaults: slightly hushed ── */
        .gallery__item img {
            display: block;
            width: 100%;
            height: auto;
            filter: saturate(0.85) brightness(0.82);
            transition:
                transform 0.55s cubic-bezier(0.22, 1, 0.36, 1),
                filter    0.55s ease;
            will-change: transform, filter;
        }

        /* ── Hover: the photograph wakes up ── */
        .gallery__item:hover img {
            transform: scale(1.05);
            filter: saturate(1.05) brightness(1.0);
        }

        /* ── Responsive breakpoints ── */
        @media (max-width: 480px) {
            .gallery {
                columns: 2;
                column-gap: 1px;
                padding: 1px;
            }
            .gallery__item { margin-bottom: 1px; }
        }

        @media (min-width: 481px) and (max-width: 768px) {
            .gallery { columns: 3; }
        }

        @media (min-width: 769px) and (max-width: 1099px) {
            .gallery { columns: 4; }
        }

        /* ── Respect reduced-motion preference ── */
        @media (prefers-reduced-motion: reduce) {
            .gallery__item img { transition: filter 0.3s ease; }
            .gallery__item:hover img { transform: none; }
        }
    </style>
</head>
<body>

<main class="gallery" aria-label="Photographs of York, England">
<?php foreach ($images as $img): ?>
    <div class="gallery__item">
        <img
            src="<?= htmlspecialchars($img['url'], ENT_QUOTES, 'UTF-8') ?>"
            alt="<?= htmlspecialchars($img['alt'], ENT_QUOTES, 'UTF-8') ?>"
            loading="lazy"
            decoding="async"
        >
    </div>
<?php endforeach; ?>
</main>

</body>
</html>
