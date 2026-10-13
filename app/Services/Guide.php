<?php

namespace App\Services;

/**
 * Answers staff how-to questions from the written manuals. The Gemini key
 * stays on the server. This service does not read sales, stock, or customers.
 */
final class Guide
{
    private const MAX_SECTIONS = 6;
    private const MAX_EXCERPT = 14000;

    public static function ready(array $settings): bool
    {
        return trim((string)($settings['gemini_api_key'] ?? '')) !== '';
    }

    /** @return array<string, string> */
    public static function models(): array
    {
        return [
            'gemini-3.8-flash' => 'Gemini 3.8 Flash',
            'gemini-3.8-live' => 'Gemini 3.8 Live',
            'gemini-3.1-pro-preview' => 'Gemini 3.1 Pro',
            'gemini-2.5-flash' => 'Gemini 2.5 Flash',
            'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash Lite',
            'gemini-2.0-flash' => 'Gemini 2.0 Flash',
        ];
    }

    /** @return array{text:string,html:string,links:list<array{label:string,href:string,blank:bool}>} */
    public function answer(array $settings, string $question, array $history, string $role): array
    {
        $key = trim((string)($settings['gemini_api_key'] ?? ''));
        if ($key === '') {
            throw new \RuntimeException('A manager still needs to save a Gemini key under System Settings, Guide.');
        }

        $model = (string)($settings['gemini_model'] ?? 'gemini-2.5-flash');
        if (!isset(self::models()[$model])) {
            $model = 'gemini-2.5-flash';
        }

        $excerpts = $this->excerpts($question);
        $instruction = $this->instruction($role, $excerpts);
        $contents = $this->contents($history, $question);

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
        $payload = [
            'systemInstruction' => ['parts' => [['text' => $instruction]]],
            'contents' => $contents,
            'generationConfig' => $this->generationConfig($model),
        ];
        [$code, $raw] = $this->post($url, $key, $payload);
        if ($code === 400 && isset($payload['generationConfig']['thinkingConfig'])) {
            unset($payload['generationConfig']['thinkingConfig']);
            [$code, $raw] = $this->post($url, $key, $payload);
        }

        $data = json_decode($raw, true);
        $text = is_array($data) ? $this->replyText($data) : '';
        if ($text !== '') {
            return $this->present($this->scrub($text, $key));
        }

        $detail = is_array($data) ? (string)($data['error']['message'] ?? '') : '';
        if ($code === 400 || $code === 404) {
            throw new \RuntimeException('The saved Gemini model could not be used. Choose another model under System Settings, Guide.');
        }
        if ($code === 401 || $code === 403) {
            throw new \RuntimeException('Gemini rejected the saved key. A manager can paste a new one under System Settings, Guide.');
        }
        if ($detail !== '' && stripos($detail, 'quota') !== false) {
            throw new \RuntimeException('The Gemini quota is used up for now. Try again later.');
        }
        throw new \RuntimeException('The guide could not get an answer. Try again in a moment.');
    }

    /** @return array<string, mixed> */
    private function generationConfig(string $model): array
    {
        $config = [
            'temperature' => 0.2,
            'maxOutputTokens' => 8192,
        ];
        if (str_starts_with($model, 'gemini-3') && !str_contains($model, 'live')) {
            $config['thinkingConfig'] = ['thinkingLevel' => 'low'];
        } elseif (str_contains($model, '2.5-flash')) {
            $config['thinkingConfig'] = ['thinkingBudget' => 0];
        }
        return $config;
    }

    private function replyText(array $data): string
    {
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        if (!is_array($parts)) {
            return '';
        }
        $chunks = [];
        foreach ($parts as $part) {
            if (!is_array($part) || !empty($part['thought'])) {
                continue;
            }
            $bit = trim((string)($part['text'] ?? ''));
            if ($bit !== '') {
                $chunks[] = $bit;
            }
        }
        return trim(implode("\n\n", $chunks));
    }

    /** @param list<array{role?:string,text?:string}> $history */
    private function contents(array $history, string $question): array
    {
        $contents = [];
        foreach (array_slice($history, -6) as $turn) {
            if (!is_array($turn)) {
                continue;
            }
            $role = ($turn['role'] ?? '') === 'model' ? 'model' : 'user';
            $text = trim((string)($turn['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            if (strlen($text) > 4000) {
                $text = substr($text, 0, 4000);
            }
            $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $question]]];
        return $contents;
    }

    private function instruction(string $role, string $excerpts): string
    {
        $who = $role !== '' ? $role : 'staff';
        $body = "You are the staff guide for smartTrade POS Pro. The person asking is a {$who}.\n"
            . "Answer only from the manual excerpts below.\n"
            . "Write in everyday language. Do not use markdown, asterisks, backticks, file names, or web addresses.\n"
            . "Start with one short sentence that answers the question.\n"
            . "If the excerpts give steps, leave a blank line and number them 1. 2. 3.\n"
            . "Name screens in plain words, such as Vendors, Purchase inquiry, Purchase orders, Customer shop, or Point of sale.\n"
            . "When a screen should be opened, end with one line in this form: Open: Purchase inquiry\n"
            . "Use a screen name from this list only: Dashboard, Point of sale, Products, Categories, Stock levels, Receiving bay, Goods received, Purchase inquiry, Purchase orders, Vendors, Reports, End of day, Shifts, Users, Payroll, Locations, Tables, Events, Settings, Kitchen, Menu, Customer shop, Members, Transfers.\n"
            . "If the excerpts do not cover the question, say so in the first sentence and do not invent steps.\n"
            . "Do not discuss live sales, stock counts, or customer records.\n\n";
        if ($excerpts === '') {
            return $body . "Manual excerpts: none matched this question.";
        }
        return $body . "Manual excerpts:\n" . $excerpts;
    }

    private function excerpts(string $question): string
    {
        $tokens = $this->tokens($question);
        if ($tokens === []) {
            return '';
        }

        $root = defined('BASE_PATH') ? BASE_PATH : dirname(APP_PATH);
        $books = [
            'User guide' => $root . '/user_guide.md',
            'Training manual' => $root . '/TRAINING_MANUAL.md',
        ];
        $scored = [];
        foreach ($books as $name => $path) {
            if (!is_file($path)) {
                continue;
            }
            $text = (string)file_get_contents($path);
            foreach ($this->sections($text) as $section) {
                $score = $this->score($section['heading'], $section['text'], $tokens);
                if ($score <= 0) {
                    continue;
                }
                $scored[] = ['score' => $score, 'book' => $name, 'heading' => $section['heading'], 'text' => $section['text']];
            }
        }
        usort($scored, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        $out = '';
        $used = 0;
        foreach (array_slice($scored, 0, self::MAX_SECTIONS) as $section) {
            $block = '--- ' . $section['book'] . ': ' . $section['heading'] . " ---\n" . $section['text'] . "\n\n";
            if (strlen($out) + strlen($block) > self::MAX_EXCERPT) {
                break;
            }
            $out .= $block;
            $used++;
        }
        return $used > 0 ? trim($out) : '';
    }

    /** @return list<array{heading:string,text:string}> */
    private function sections(string $markdown): array
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $sections = [];
        $h2 = '';
        $title = '';
        $buf = [];
        $flush = static function () use (&$sections, &$title, &$buf, &$h2): void {
            $body = trim(implode("\n", $buf));
            if ($title === '' || $body === '') {
                return;
            }
            $heading = $h2 !== '' && $h2 !== $title ? $h2 . ' — ' . $title : $title;
            $sections[] = ['heading' => $heading, 'text' => $body];
        };
        foreach ($lines as $line) {
            if (preg_match('/^(#{1,3})\s+(.+)$/', $line, $match)) {
                $flush();
                $buf = [$line];
                $title = trim($match[2]);
                if (strlen($match[1]) <= 2) {
                    $h2 = $title;
                }
                continue;
            }
            $buf[] = $line;
        }
        $flush();
        return $sections;
    }

    /** @param list<string> $tokens */
    private function score(string $heading, string $text, array $tokens): int
    {
        $head = strtolower($heading);
        $body = strtolower($text);
        $score = 0;
        foreach ($tokens as $token) {
            $pattern = '/\b' . preg_quote($token, '/') . '/';
            $inHead = preg_match_all($pattern, $head);
            $inBody = preg_match_all($pattern, $body);
            if ($inHead) {
                $score += 8 * $inHead;
            }
            if ($inBody) {
                $score += min(4, $inBody);
            }
        }
        return $score;
    }

    /** @return list<string> */
    private function tokens(string $question): array
    {
        $clean = strtolower((string)preg_replace('/[^a-z0-9\s]/i', ' ', $question));
        $words = preg_split('/\s+/', $clean) ?: [];
        $stop = [
            'the', 'and', 'for', 'how', 'what', 'when', 'where', 'who', 'why', 'can', 'you', 'your',
            'with', 'from', 'this', 'that', 'are', 'was', 'does', 'do', 'to', 'of', 'an', 'in', 'on',
            'it', 'is', 'my', 'me', 'we', 'our', 'please', 'help', 'need', 'want', 'should', 'would',
            'could', 'there', 'they', 'them', 'into', 'about', 'after', 'before', 'does', 'did',
        ];
        $out = [];
        foreach ($words as $word) {
            if (strlen($word) < 3 || in_array($word, $stop, true)) {
                continue;
            }
            $out[$this->stem($word)] = true;
        }
        return array_keys($out);
    }

    private function stem(string $word): string
    {
        foreach (['ing', 'ed', 'es', 's'] as $suffix) {
            if (str_ends_with($word, $suffix) && strlen($word) - strlen($suffix) >= 3) {
                return substr($word, 0, -strlen($suffix));
            }
        }
        return $word;
    }

    /** @return array{0:int,1:string} */
    private function post(string $url, string $key, array $body): array
    {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('The guide could not prepare the question.');
        }
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('The guide cannot call Gemini from this server.');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $key,
            ],
            CURLOPT_POSTFIELDS => $json,
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($raw)) {
            throw new \RuntimeException('The guide could not reach Gemini.');
        }
        return [$code, $this->scrub($raw, $key)];
    }

    private function scrub(string $text, string $key): string
    {
        if ($key !== '' && str_contains($text, $key)) {
            $text = str_replace($key, '[key]', $text);
        }
        return $text;
    }

    /** @return array{text:string,html:string,links:list<array{label:string,href:string,blank:bool}>} */
    private function present(string $raw): array
    {
        $links = [];
        $seen = [];
        if (preg_match_all('/index\.php\?page=([a-z0-9_]+)(?:&(?:amp;)?tab=([a-z0-9_]+))?/i', $raw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->addPageLink($links, $seen, $match[1], $match[2] ?? '');
            }
        }

        $text = preg_replace('/\s*\([^)]*index\.php\?[^)]*\)/i', '', $raw) ?? $raw;
        $text = preg_replace('/`([^`]*)`/', '$1', $text) ?? $text;
        $text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '$1', $text) ?? $text;
        $text = preg_replace('/index\.php\?page=\S+/i', '', $text) ?? $text;

        if (preg_match('/^Open:\s*(.+)$/mi', $text, $openLine)) {
            $this->addNamedLink($links, $seen, trim($openLine[1]));
            $text = preg_replace('/^Open:\s*.+$/mi', '', $text) ?? $text;
        }
        $this->scanScreenLinks($text, $links, $seen);

        $text = trim((string)preg_replace("/[ \t]+\n/", "\n", $text));
        $text = trim((string)preg_replace("/\n{3,}/", "\n\n", $text));

        return [
            'text' => $text,
            'html' => $this->toHtml($text),
            'links' => array_slice($links, 0, 3),
        ];
    }

    /** @return list<array{name:string,href:string,blank:bool,keys:list<string>}> */
    private function screenCatalog(): array
    {
        return [
            ['name' => 'Purchase inquiry', 'href' => 'index.php?page=purchasing&tab=inquiry', 'blank' => false, 'keys' => ['purchase inquiry']],
            ['name' => 'Purchase orders', 'href' => 'index.php?page=purchasing&tab=orders', 'blank' => false, 'keys' => ['purchase order', 'purchase orders']],
            ['name' => 'Goods received', 'href' => 'index.php?page=purchasing&tab=received', 'blank' => false, 'keys' => ['goods received']],
            ['name' => 'Receiving bay', 'href' => 'index.php?page=receiving_bay', 'blank' => false, 'keys' => ['receiving bay']],
            ['name' => 'Stock levels', 'href' => 'index.php?page=inventory', 'blank' => false, 'keys' => ['stock level', 'stock levels']],
            ['name' => 'Customer shop', 'href' => 'index.php?page=shop', 'blank' => true, 'keys' => ['customer shop', 'customer ordering', 'ordering portal', 'customer order', 'online customer']],
            ['name' => 'Point of sale', 'href' => 'index.php?page=pos', 'blank' => true, 'keys' => ['point of sale']],
            ['name' => 'End of day', 'href' => 'index.php?page=z_read', 'blank' => false, 'keys' => ['end of day', 'z-read', 'z read']],
            ['name' => 'Kitchen display', 'href' => 'index.php?page=kds', 'blank' => true, 'keys' => ['kitchen display']],
            ['name' => 'Dashboard', 'href' => 'index.php?page=dashboard', 'blank' => false, 'keys' => ['dashboard']],
            ['name' => 'Products', 'href' => 'index.php?page=products', 'blank' => false, 'keys' => ['products']],
            ['name' => 'Categories', 'href' => 'index.php?page=categories', 'blank' => false, 'keys' => ['categories']],
            ['name' => 'Vendors', 'href' => 'index.php?page=vendors', 'blank' => false, 'keys' => ['vendor', 'vendors']],
            ['name' => 'Reports', 'href' => 'index.php?page=reports', 'blank' => false, 'keys' => ['master reports', 'reports']],
            ['name' => 'Shifts', 'href' => 'index.php?page=shifts', 'blank' => false, 'keys' => ['shifts']],
            ['name' => 'Users', 'href' => 'index.php?page=users', 'blank' => false, 'keys' => ['users']],
            ['name' => 'Payroll', 'href' => 'index.php?page=payroll', 'blank' => false, 'keys' => ['payroll']],
            ['name' => 'Locations', 'href' => 'index.php?page=locations', 'blank' => false, 'keys' => ['locations']],
            ['name' => 'Tables', 'href' => 'index.php?page=tables', 'blank' => false, 'keys' => ['tables', 'table settings']],
            ['name' => 'Events', 'href' => 'index.php?page=events', 'blank' => false, 'keys' => ['events']],
            ['name' => 'Settings', 'href' => 'index.php?page=settings', 'blank' => false, 'keys' => ['system settings', 'settings']],
            ['name' => 'Kitchen', 'href' => 'index.php?page=kitchen', 'blank' => false, 'keys' => ['kitchen']],
            ['name' => 'Menu', 'href' => 'index.php?page=menu', 'blank' => false, 'keys' => ['menu builder']],
            ['name' => 'Members', 'href' => 'index.php?page=members', 'blank' => false, 'keys' => ['members']],
            ['name' => 'Transfers', 'href' => 'index.php?page=transfers', 'blank' => false, 'keys' => ['transfer stock', 'transfers']],
        ];
    }

    /** @param list<array{label:string,href:string,blank:bool}> $links @param array<string,bool> $seen */
    private function addPageLink(array &$links, array &$seen, string $page, string $tab): void
    {
        $page = strtolower($page);
        $tab = strtolower($tab);
        $name = match (true) {
            $page === 'purchasing' && $tab === 'inquiry' => 'Purchase inquiry',
            $page === 'purchasing' && $tab === 'orders' => 'Purchase orders',
            $page === 'purchasing' && $tab === 'received' => 'Goods received',
            $page === 'shop' => 'Customer shop',
            $page === 'pos' => 'Point of sale',
            $page === 'inventory' => 'Stock levels',
            $page === 'receiving_bay' => 'Receiving bay',
            $page === 'z_read' => 'End of day',
            $page === 'kds' => 'Kitchen display',
            default => ucfirst(str_replace('_', ' ', $page)),
        };
        $this->addNamedLink($links, $seen, $name);
    }

    /** @param list<array{label:string,href:string,blank:bool}> $links @param array<string,bool> $seen */
    private function addNamedLink(array &$links, array &$seen, string $name): void
    {
        $wanted = strtolower(trim($name, " ."));
        foreach ($this->screenCatalog() as $screen) {
            $names = array_merge([strtolower($screen['name'])], $screen['keys']);
            if (!in_array($wanted, $names, true)) {
                continue;
            }
            if (isset($seen[$screen['href']])) {
                return;
            }
            $seen[$screen['href']] = true;
            $links[] = [
                'label' => 'Open ' . $screen['name'],
                'href' => $screen['href'],
                'blank' => $screen['blank'],
            ];
            return;
        }
    }

    /** @param list<array{label:string,href:string,blank:bool}> $links @param array<string,bool> $seen */
    private function scanScreenLinks(string $text, array &$links, array &$seen): void
    {
        $hay = strtolower($text);
        $hits = [];
        foreach ($this->screenCatalog() as $screen) {
            if (isset($seen[$screen['href']])) {
                continue;
            }
            $at = null;
            foreach ($screen['keys'] as $key) {
                $pos = strpos($hay, $key);
                if ($pos !== false && ($at === null || $pos < $at)) {
                    $at = $pos;
                }
            }
            if ($at !== null) {
                $hits[] = ['at' => $at, 'screen' => $screen];
            }
        }
        usort($hits, static fn(array $a, array $b): int => $a['at'] <=> $b['at']);
        foreach ($hits as $hit) {
            if (count($links) >= 3) {
                break;
            }
            $screen = $hit['screen'];
            if (isset($seen[$screen['href']])) {
                continue;
            }
            $seen[$screen['href']] = true;
            $links[] = [
                'label' => 'Open ' . $screen['name'],
                'href' => $screen['href'],
                'blank' => $screen['blank'],
            ];
        }
    }

    private function toHtml(string $text): string
    {
        $blocks = preg_split("/\n\s*\n/", trim($text)) ?: [];
        $html = '';
        $first = true;
        foreach ($blocks as $block) {
            $lines = preg_split('/\n/', trim($block)) ?: [];
            $steps = [];
            $prose = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (preg_match('/^\d+[\).\]]\s+(.+)$/', $line, $match)) {
                    $steps[] = $match[1];
                } else {
                    $prose[] = $line;
                }
            }
            if ($prose !== []) {
                $class = $first ? 'guide-lead' : 'guide-note';
                $html .= '<p class="' . $class . '">' . $this->emphasize(implode(' ', $prose)) . '</p>';
                $first = false;
            }
            if ($steps !== []) {
                $html .= '<ol class="guide-steps">';
                foreach ($steps as $step) {
                    $html .= '<li>' . $this->emphasize($step) . '</li>';
                }
                $html .= '</ol>';
                $first = false;
            }
        }
        return $html;
    }

    private function emphasize(string $text): string
    {
        $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $names = [];
        foreach ($this->screenCatalog() as $screen) {
            $names[] = $screen['name'];
        }
        usort($names, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($names as $name) {
            $quoted = preg_quote(htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), '/');
            $safe = preg_replace('/\b' . $quoted . '\b/i', '<strong>$0</strong>', $safe) ?? $safe;
        }
        return $safe;
    }
}
