<?php
/**
 * RD Vendora news / blog helpers.
 */

if (!function_exists('rdv_blog_categories')) {
    function rdv_blog_categories() {
        return [
            'platform' => 'Platform',
            'guides' => 'Guides',
            'payments' => 'Payments',
            'marketplace' => 'Marketplace',
            'company' => 'Company',
        ];
    }
}

if (!function_exists('rdv_blog_category_label')) {
    function rdv_blog_category_label($key) {
        $map = rdv_blog_categories();
        return $map[$key] ?? 'News';
    }
}

if (!function_exists('rdv_ensure_blog_table')) {
    function rdv_ensure_blog_table(mysqli $conn) {
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS blog_posts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(160) NOT NULL,
            title VARCHAR(220) NOT NULL,
            excerpt TEXT NULL,
            body MEDIUMTEXT NOT NULL,
            category VARCHAR(40) NOT NULL DEFAULT 'platform',
            author VARCHAR(120) NOT NULL DEFAULT 'RD Vendora team',
            image_url VARCHAR(500) NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'draft',
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            published_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_slug (slug),
            KEY idx_status_pub (status, published_at),
            KEY idx_category (category),
            KEY idx_featured (is_featured)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            rdv_blog_seed_if_empty($conn);
        } catch (Throwable $e) {
            error_log('rdv_ensure_blog_table: ' . $e->getMessage());
        }
    }
}

if (!function_exists('rdv_blog_seed_defaults')) {
    function rdv_blog_seed_defaults() {
        return [
            [
                'slug' => 'open-a-store',
                'title' => 'How to open a store on RD Vendora',
                'excerpt' => 'Registration, store creation, and the first products — the path that already exists in the dashboard.',
                'category' => 'guides',
                'author' => 'RD Vendora team',
                'is_featured' => 1,
                'published_at' => '2026-08-17 09:00:00',
                'legacy' => 'blog-open-a-store.php',
                'body' => '<p>RD Vendora is meant to get you from “I want to sell online” to a working storefront without assembling a dozen separate tools. This article describes the path that already exists in the product.</p>
<h2>1. Create an account</h2>
<p>Use the register page to create a user account with a real email address you can access. You will need that inbox for password resets and, if you subscribe, newsletter confirmation. Keep the password unique to this site.</p>
<h2>2. Create the store</h2>
<p>After you sign in, the dashboard prompts you to create a store: a name, basic settings, and the catalogue you will sell. You can adjust appearance later from store settings. If the platform asks for company documents, submit them from the documents area so administrators can review them—selling privileges may wait on that review.</p>
<h2>3. Add products</h2>
<p>Add clear titles, prices, and photos you have the right to use. Stock levels and variants, where the product form supports them, should match what you can actually ship. Empty or copied listings make it harder for customers to trust you and harder for the marketplace to stay useful.</p>
<h2>4. Share the storefront</h2>
<p>Your public storefront is the page customers use to browse. The marketplace, when enabled, can also surface products from several stores. Share the real URL; do not promise features the dashboard does not show for your plan.</p>',
            ],
            [
                'slug' => 'accepting-payments',
                'title' => 'Accepting payments with Paystack and Flutterwave',
                'excerpt' => 'What buyers and sellers should confirm about checkout before treating an order as paid.',
                'category' => 'payments',
                'author' => 'RD Vendora team',
                'is_featured' => 1,
                'published_at' => '2026-08-17 08:30:00',
                'legacy' => 'blog-accepting-payments.php',
                'body' => '<p>When a customer checks out on RD Vendora, card and local payment methods are collected by Paystack or Flutterwave—not typed into a homemade card form on this site. That is intentional: those companies handle card data and many of the fraud checks.</p>
<h2>What you should confirm</h2>
<ul>
<li>Your store and the platform have the payment keys configured for the environment you are in (test versus live).</li>
<li>The amount and currency on the checkout screen match what you intend to charge.</li>
<li>You understand the provider’s fees, payout timing, and dispute process. Those rules come from Paystack or Flutterwave, not from a slogan on a marketing page.</li>
</ul>
<h2>What RD Vendora does</h2>
<p>The platform creates the order, sends the customer to (or through) the selected provider, and records payment references when verification succeeds. If a payment fails, the order should not be treated as paid. Always check the order status in your dashboard rather than a screenshot from a customer alone.</p>
<h2>What we do not claim</h2>
<p>We do not claim that every global wallet (for example Apple Pay or PayPal) is wired in unless your checkout screen actually shows it. If you need a method that is not listed, contact us—do not advertise it to customers in the meantime.</p>',
            ],
            [
                'slug' => 'trustworthy-store',
                'title' => 'Keeping your store trustworthy',
                'excerpt' => 'Listings, contact details, and fulfilment notes that help a stranger decide your shop is real.',
                'category' => 'guides',
                'author' => 'RD Vendora team',
                'is_featured' => 0,
                'published_at' => '2026-08-16 16:00:00',
                'legacy' => 'blog-trustworthy-store.php',
                'body' => '<p>Customers decide quickly whether a shop feels real. On RD Vendora you control most of that impression: photos, copy, prices, and how you answer messages.</p>
<h2>Write listings a stranger can verify</h2>
<p>Use your own photographs when you can. State size, condition, and what is included. If something is made to order, say so and give a realistic time range. Invented “limited stock” countdown timers that are not true will erode trust and can breach advertising rules in some places.</p>
<h2>Be reachable</h2>
<p>Keep a working email and, if you publish a phone number, one you actually answer. RD Vendora’s contact and chat tools only help if someone on your side reads them.</p>
<h2>Explain fulfilment</h2>
<p>If you ship, say from where and roughly how long. If you offer pickup, say where. Hidden shipping costs at the last step are a common reason for abandoned carts and complaints.</p>
<h2>Follow the platform rules</h2>
<p>Read the <a href="community-guidelines">Community Guidelines</a>. Do not post fake testimonials. Product HTML that tries to run scripts will be treated as abuse.</p>',
            ],
            [
                'slug' => 'marketplace-multiple-stores',
                'title' => 'How the marketplace lists products from more than one store',
                'excerpt' => 'When the public marketplace is on, shoppers can browse catalogues that belong to different vendors on the same platform.',
                'category' => 'marketplace',
                'author' => 'RD Vendora team',
                'is_featured' => 1,
                'published_at' => '2026-08-16 11:00:00',
                'legacy' => '',
                'body' => '<p>RD Vendora is a multi-vendor platform. That means more than one store can exist on the same installation, each with its own products, orders, and settings.</p>
<h2>What shoppers see</h2>
<p>The public marketplace page can show products from those stores when the feature is enabled. A product still belongs to the store that listed it. Checkout and fulfilment follow that store’s setup, not a single shared warehouse unless you have built that yourself.</p>
<h2>What store owners should know</h2>
<p>Keep titles and photos accurate so your listings are not confused with another vendor’s. If an administrator reviews documents before you can sell, complete that step rather than assuming the marketplace will ignore it.</p>
<p>If the marketplace page is empty, it usually means no published products are available to list yet—not that the site is “down.”</p>',
            ],
            [
                'slug' => 'newsletter-now-available',
                'title' => 'RD Vendora newsletter: how to subscribe and unsubscribe',
                'excerpt' => 'The public site now offers a double opt-in newsletter for platform news and practical store resources.',
                'category' => 'company',
                'author' => 'RD Vendora team',
                'is_featured' => 0,
                'published_at' => '2026-08-15 14:00:00',
                'legacy' => '',
                'body' => '<p>You can subscribe to the RD Vendora newsletter from the footer of public pages. We send platform news, product updates, and practical notes for people who run a store here.</p>
<h2>Double opt-in</h2>
<p>After you submit your email, we send a confirmation link. We do not treat the address as verified until you open that link. If you did not request a subscription, ignore the email.</p>
<h2>Leaving the list</h2>
<p>Every newsletter email includes an unsubscribe link. You can also use the unsubscribe page linked from the sitemap. We keep a record of the unsubscribed status so the same address is not mailed again unless someone confirms a new subscription.</p>',
            ],
            [
                'slug' => 'cookies-and-ads',
                'title' => 'Cookies, privacy choices, and ads on the public site',
                'excerpt' => 'Necessary cookies run the site. Analytics and advertising cookies wait until you choose them.',
                'category' => 'platform',
                'author' => 'RD Vendora team',
                'is_featured' => 0,
                'published_at' => '2026-08-15 10:00:00',
                'legacy' => '',
                'body' => '<p>Public pages on RD Vendora show a cookie banner the first time you visit. Necessary cookies (for example a sign-in session) are required for the site to work. Optional analytics and advertising cookies are off until you accept them.</p>
<h2>Advertisements</h2>
<p>Some public pages reserve labelled advertisement areas. Live Google ads load only if the site owner has configured AdSense and you have allowed advertising cookies. We do not ask anyone to click ads, and we do not invent advertiser brands.</p>
<h2>Where to read more</h2>
<p>The <a href="privacy">Privacy Policy</a> and <a href="cookies">Cookie Policy</a> describe what we collect and how to change your choice later using Cookie settings on the page.</p>',
            ],
            [
                'slug' => 'contacting-support',
                'title' => 'How to contact RD Vendora support',
                'excerpt' => 'Use the contact form with your account email and, if relevant, the store or order name.',
                'category' => 'company',
                'author' => 'RD Vendora team',
                'is_featured' => 0,
                'published_at' => '2026-08-14 12:00:00',
                'legacy' => '',
                'body' => '<p>If something on the platform is broken, unclear, or you need a human reply, use the <a href="contact">contact form</a>.</p>
<h2>What to include</h2>
<ul>
<li>The email address on your RD Vendora account.</li>
<li>The store name, if the issue is about a vendor dashboard or listings.</li>
<li>An order reference, if the issue is about a payment or fulfilment.</li>
<li>What you expected to happen and what you saw instead.</li>
</ul>
<p>We do not handle card disputes for Paystack or Flutterwave; those go through the payment provider. We can still look at whether the order was marked paid on this site.</p>',
            ],
            [
                'slug' => 'plans-and-pricing',
                'title' => 'Where to check plans and what a price actually covers',
                'excerpt' => 'Subscription options are the ones on the pricing and billing screens—not a promise invented on a marketing page.',
                'category' => 'platform',
                'author' => 'RD Vendora team',
                'is_featured' => 0,
                'published_at' => '2026-08-13 09:30:00',
                'legacy' => '',
                'body' => '<p>If you need a paid plan, open the <a href="pricing">pricing</a> page and, after you sign in, the billing screen. Those pages are the source of truth for what you are buying.</p>
<h2>Payment processing is separate</h2>
<p>Paystack and Flutterwave charge their own fees on customer checkouts. That is not the same as an RD Vendora subscription. If a screen does not list a platform commission, do not assume there is none—read the plan details in the dashboard.</p>
<h2>Cancelling</h2>
<p>Use the billing or account area. Access typically continues until the end of a period you already paid for, unless that screen states something different.</p>',
            ],
        ];
    }
}

if (!function_exists('rdv_blog_seed_if_empty')) {
    function rdv_blog_seed_if_empty(mysqli $conn) {
        $res = $conn->query('SELECT COUNT(*) AS c FROM blog_posts');
        $count = $res ? (int) ($res->fetch_assoc()['c'] ?? 0) : 0;
        if ($count > 0) {
            return;
        }
        $sql = 'INSERT INTO blog_posts (slug, title, excerpt, body, category, author, status, is_featured, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return;
        }
        foreach (rdv_blog_seed_defaults() as $post) {
            $status = 'published';
            $featured = (int) $post['is_featured'];
            $stmt->bind_param(
                'sssssssis',
                $post['slug'],
                $post['title'],
                $post['excerpt'],
                $post['body'],
                $post['category'],
                $post['author'],
                $status,
                $featured,
                $post['published_at']
            );
            $stmt->execute();
        }
        $stmt->close();
    }
}

if (!function_exists('rdv_blog_slugify')) {
    function rdv_blog_slugify($text) {
        $text = strtolower(trim((string) $text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim((string) $text, '-');
        if ($text === '') {
            return '';
        }
        return substr($text, 0, 150);
    }
}

if (!function_exists('rdv_blog_unique_slug')) {
    function rdv_blog_unique_slug(mysqli $conn, $slug, $ignoreId = 0) {
        $base = rdv_blog_slugify($slug);
        if ($base === '') {
            $base = 'story-' . substr(bin2hex(random_bytes(4)), 0, 8);
        }
        $candidate = $base;
        $n = 2;
        while (true) {
            $stmt = $conn->prepare('SELECT id FROM blog_posts WHERE slug = ? LIMIT 1');
            $stmt->bind_param('s', $candidate);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row || (int) $row['id'] === (int) $ignoreId) {
                return $candidate;
            }
            $candidate = $base . '-' . $n;
            $n++;
            if ($n > 80) {
                return $base . '-' . bin2hex(random_bytes(3));
            }
        }
    }
}

if (!function_exists('rdv_blog_sanitize_html')) {
    function rdv_blog_sanitize_html($html) {
        $html = (string) $html;
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
        $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html);
        $allowed = '<p><br><br/><h2><h3><h4><ul><ol><li><strong><b><em><i><a><blockquote><hr><span>';
        $html = strip_tags($html, $allowed);
        $html = preg_replace_callback('/<a\s+[^>]*href\s*=\s*(["\'])(.*?)\1[^>]*>/i', static function ($m) {
            $href = trim($m[2]);
            $ok = preg_match('#^(https?:)?//#i', $href)
                || preg_match('#^mailto:#i', $href)
                || preg_match('#^[a-z0-9][a-z0-9._/?&=#%-]*\.php#i', $href)
                || preg_match('#^/[a-z0-9]#i', $href);
            if (!$ok) {
                return '<a>';
            }
            return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" rel="noopener noreferrer">';
        }, $html);
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        return $html;
    }
}

if (!function_exists('rdv_blog_excerpt')) {
    function rdv_blog_excerpt($post, $words = 32) {
        $text = trim((string) ($post['excerpt'] ?? ''));
        if ($text === '') {
            $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($post['body'] ?? ''))));
        }
        $parts = preg_split('/\s+/', $text, $words + 1) ?: [];
        if (count($parts) > $words) {
            $text = implode(' ', array_slice($parts, 0, $words)) . '…';
        }
        return $text;
    }
}

if (!function_exists('rdv_blog_reading_minutes')) {
    function rdv_blog_reading_minutes($post) {
        $words = str_word_count(strip_tags((string) ($post['body'] ?? '')));
        return max(1, (int) ceil($words / 200));
    }
}

if (!function_exists('rdv_blog_format_date')) {
    function rdv_blog_format_date($datetime, $withTime = false) {
        $ts = strtotime((string) $datetime);
        if (!$ts) {
            return '';
        }
        return $withTime ? date('j F Y, g:ia', $ts) : date('j F Y', $ts);
    }
}

if (!function_exists('rdv_blog_url')) {
    function rdv_blog_url($slug) {
        $slug = trim((string) $slug);
        if (function_exists('rdv_url')) {
            return rdv_url('blog/' . $slug);
        }
        return 'blog/' . rawurlencode($slug);
    }
}

if (!function_exists('rdv_blog_index_url')) {
    function rdv_blog_index_url($params = []) {
        $qs = array_filter($params, static function ($v) {
            return $v !== '' && $v !== null;
        });
        if (isset($qs['page']) && (int) $qs['page'] <= 1) {
            unset($qs['page']);
        }
        $base = function_exists('rdv_url') ? rdv_url('blog') : 'blog';
        if (!$qs) {
            return $base;
        }
        return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($qs);
    }
}

if (!function_exists('rdv_blog_legacy_slug')) {
    function rdv_blog_legacy_slug($filename) {
        $map = [
            'blog-open-a-store.php' => 'open-a-store',
            'blog-accepting-payments.php' => 'accepting-payments',
            'blog-trustworthy-store.php' => 'trustworthy-store',
        ];
        return $map[basename($filename)] ?? '';
    }
}

if (!function_exists('rdv_blog_public_where')) {
    function rdv_blog_public_where() {
        return "status = 'published' AND published_at IS NOT NULL AND published_at <= NOW()";
    }
}

if (!function_exists('rdv_blog_get_by_slug')) {
    function rdv_blog_get_by_slug(mysqli $conn, $slug, $includeDraft = false) {
        rdv_ensure_blog_table($conn);
        $slug = rdv_blog_slugify($slug);
        $sql = 'SELECT * FROM blog_posts WHERE slug = ?';
        if (!$includeDraft) {
            $sql .= ' AND ' . rdv_blog_public_where();
        }
        $sql .= ' LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('rdv_blog_get_by_id')) {
    function rdv_blog_get_by_id(mysqli $conn, $id) {
        rdv_ensure_blog_table($conn);
        $id = (int) $id;
        $stmt = $conn->prepare('SELECT * FROM blog_posts WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('rdv_blog_apply_filters')) {
    function rdv_blog_apply_filters($opts, &$types, &$params) {
        $where = [rdv_blog_public_where()];
        $types = '';
        $params = [];
        $cat = trim((string) ($opts['category'] ?? ''));
        if ($cat !== '' && isset(rdv_blog_categories()[$cat])) {
            $where[] = 'category = ?';
            $types .= 's';
            $params[] = $cat;
        }
        $q = trim((string) ($opts['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(title LIKE ? OR excerpt LIKE ? OR body LIKE ?)';
            $types .= 'sss';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($opts['featured'])) {
            $where[] = 'is_featured = 1';
        }
        if (!empty($opts['exclude_id'])) {
            $where[] = 'id <> ?';
            $types .= 'i';
            $params[] = (int) $opts['exclude_id'];
        }
        return implode(' AND ', $where);
    }
}

if (!function_exists('rdv_blog_count')) {
    function rdv_blog_count(mysqli $conn, $opts = []) {
        rdv_ensure_blog_table($conn);
        $types = '';
        $params = [];
        $where = rdv_blog_apply_filters($opts, $types, $params);
        $sql = 'SELECT COUNT(*) AS c FROM blog_posts WHERE ' . $where;
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['c'] ?? 0);
    }
}

if (!function_exists('rdv_blog_list')) {
    function rdv_blog_list(mysqli $conn, $opts = []) {
        rdv_ensure_blog_table($conn);
        $limit = max(1, min(50, (int) ($opts['limit'] ?? 12)));
        $offset = max(0, (int) ($opts['offset'] ?? 0));
        $types = '';
        $params = [];
        $where = rdv_blog_apply_filters($opts, $types, $params);
        $sql = 'SELECT * FROM blog_posts WHERE ' . $where . ' ORDER BY published_at DESC, id DESC LIMIT ? OFFSET ?';
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows ?: [];
    }
}

if (!function_exists('rdv_blog_featured')) {
    function rdv_blog_featured(mysqli $conn, $limit = 3) {
        $featured = rdv_blog_list($conn, ['featured' => true, 'limit' => $limit]);
        if (count($featured) >= $limit) {
            return $featured;
        }
        $ids = array_column($featured, 'id');
        $latest = rdv_blog_list($conn, ['limit' => $limit + 6]);
        foreach ($latest as $post) {
            if (in_array($post['id'], $ids, true)) {
                continue;
            }
            $featured[] = $post;
            if (count($featured) >= $limit) {
                break;
            }
        }
        return array_slice($featured, 0, $limit);
    }
}

if (!function_exists('rdv_blog_latest')) {
    function rdv_blog_latest(mysqli $conn, $limit = 8, $excludeId = 0) {
        return rdv_blog_list($conn, ['limit' => $limit, 'exclude_id' => $excludeId]);
    }
}

if (!function_exists('rdv_blog_related')) {
    function rdv_blog_related(mysqli $conn, $post, $limit = 3) {
        $same = rdv_blog_list($conn, [
            'category' => $post['category'] ?? '',
            'limit' => $limit + 1,
            'exclude_id' => (int) ($post['id'] ?? 0),
        ]);
        if (count($same) >= $limit) {
            return array_slice($same, 0, $limit);
        }
        $ids = array_column($same, 'id');
        $more = rdv_blog_latest($conn, $limit + 4, (int) ($post['id'] ?? 0));
        foreach ($more as $row) {
            if (in_array($row['id'], $ids, true)) {
                continue;
            }
            $same[] = $row;
            if (count($same) >= $limit) {
                break;
            }
        }
        return array_slice($same, 0, $limit);
    }
}

if (!function_exists('rdv_blog_admin_list')) {
    function rdv_blog_admin_list(mysqli $conn, $opts = []) {
        rdv_ensure_blog_table($conn);
        $sql = 'SELECT * FROM blog_posts WHERE 1=1';
        $types = '';
        $params = [];
        $status = trim((string) ($opts['status'] ?? ''));
        if (in_array($status, ['draft', 'published'], true)) {
            $sql .= ' AND status = ?';
            $types .= 's';
            $params[] = $status;
        }
        $q = trim((string) ($opts['q'] ?? ''));
        if ($q !== '') {
            $sql .= ' AND (title LIKE ? OR slug LIKE ?)';
            $types .= 'ss';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= ' ORDER BY updated_at DESC LIMIT 200';
        $stmt = $conn->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows ?: [];
    }
}

if (!function_exists('rdv_blog_save')) {
    function rdv_blog_save(mysqli $conn, $data, $id = 0) {
        rdv_ensure_blog_table($conn);
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '' || strlen($title) > 220) {
            return ['ok' => false, 'message' => 'Enter a headline of up to 220 characters.'];
        }
        $slugIn = trim((string) ($data['slug'] ?? ''));
        $slug = rdv_blog_unique_slug($conn, $slugIn !== '' ? $slugIn : $title, $id);
        $excerpt = trim((string) ($data['excerpt'] ?? ''));
        if (strlen($excerpt) > 600) {
            $excerpt = substr($excerpt, 0, 600);
        }
        $body = rdv_blog_sanitize_html($data['body'] ?? '');
        if (trim(strip_tags($body)) === '') {
            return ['ok' => false, 'message' => 'Write the article body.'];
        }
        $cats = rdv_blog_categories();
        $category = (string) ($data['category'] ?? 'platform');
        if (!isset($cats[$category])) {
            $category = 'platform';
        }
        $author = trim((string) ($data['author'] ?? 'RD Vendora team'));
        if ($author === '') {
            $author = 'RD Vendora team';
        }
        $author = substr($author, 0, 120);
        $image = trim((string) ($data['image_url'] ?? ''));
        if ($image !== '' && !preg_match('#^(https?:)?//|^uploads/#i', $image)) {
            $image = '';
        }
        $image = substr($image, 0, 500);
        $status = (($data['status'] ?? '') === 'published') ? 'published' : 'draft';
        $featured = !empty($data['is_featured']) ? 1 : 0;
        $publishedAt = trim((string) ($data['published_at'] ?? ''));
        if ($publishedAt === '' || strtotime($publishedAt) === false) {
            $publishedAt = date('Y-m-d H:i:s');
        } else {
            $publishedAt = date('Y-m-d H:i:s', strtotime($publishedAt));
        }

        if ($id > 0) {
            $stmt = $conn->prepare('UPDATE blog_posts SET slug=?, title=?, excerpt=?, body=?, category=?, author=?, image_url=?, status=?, is_featured=?, published_at=? WHERE id=?');
            $stmt->bind_param('ssssssssisi', $slug, $title, $excerpt, $body, $category, $author, $image, $status, $featured, $publishedAt, $id);
        } else {
            $stmt = $conn->prepare('INSERT INTO blog_posts (slug, title, excerpt, body, category, author, image_url, status, is_featured, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssssssis', $slug, $title, $excerpt, $body, $category, $author, $image, $status, $featured, $publishedAt);
        }
        $ok = $stmt->execute();
        $err = $stmt->error;
        $newId = $id > 0 ? $id : (int) $stmt->insert_id;
        $stmt->close();
        if (!$ok) {
            return ['ok' => false, 'message' => 'Could not save the story. ' . $err];
        }
        return ['ok' => true, 'id' => $newId, 'slug' => $slug, 'message' => 'Story saved.'];
    }
}

if (!function_exists('rdv_blog_delete')) {
    function rdv_blog_delete(mysqli $conn, $id) {
        $id = (int) $id;
        $stmt = $conn->prepare('DELETE FROM blog_posts WHERE id = ?');
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('rdv_blog_sitemap_paths')) {
    function rdv_blog_sitemap_paths(mysqli $conn) {
        $paths = [['blog.php', date('Y-m-d'), '0.8', 'daily']];
        foreach (rdv_blog_list($conn, ['limit' => 50]) as $post) {
            $mod = date('Y-m-d', strtotime($post['updated_at'] ?: $post['published_at']));
            $paths[] = [rdv_blog_url($post['slug']), $mod, '0.6', 'monthly'];
        }
        return $paths;
    }
}

if (!function_exists('rdv_blog_article_schema')) {
    function rdv_blog_article_schema($post) {
        $url = rtrim(defined('APP_URL') ? APP_URL : '', '/') . '/' . rdv_blog_url($post['slug']);
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $post['title'],
            'description' => rdv_blog_excerpt($post, 40),
            'datePublished' => date('c', strtotime((string) $post['published_at'])),
            'dateModified' => date('c', strtotime((string) ($post['updated_at'] ?? $post['published_at']))),
            'author' => ['@type' => 'Organization', 'name' => $post['author'] ?: 'RD Vendora'],
            'publisher' => ['@type' => 'Organization', 'name' => 'RD Vendora', 'url' => rtrim(defined('APP_URL') ? APP_URL : '', '/')],
            'mainEntityOfPage' => $url,
            'articleSection' => rdv_blog_category_label($post['category'] ?? ''),
        ];
        if (!empty($post['image_url'])) {
            $img = $post['image_url'];
            if (strpos($img, 'http') !== 0) {
                $img = rtrim(defined('APP_URL') ? APP_URL : '', '/') . '/' . ltrim($img, '/');
            }
            $data['image'] = $img;
        }
        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }
}

if (!function_exists('rdv_blog_h')) {
    function rdv_blog_h($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rdv_blog_thumb_html')) {
    function rdv_blog_thumb_html($post, $showLabel = true) {
        $cat = rdv_blog_h($post['category'] ?? 'platform');
        $label = rdv_blog_h(rdv_blog_category_label($post['category'] ?? ''));
        $img = trim((string) ($post['image_url'] ?? ''));
        $html = '<span class="rdv-news-thumb" data-cat="' . $cat . '">';
        if ($img !== '') {
            $html .= '<img src="' . rdv_blog_h($img) . '" alt="">';
        } elseif ($showLabel) {
            $html .= '<span class="rdv-news-thumb-label">' . $label . '</span>';
        }
        $html .= '</span>';
        return $html;
    }
}
