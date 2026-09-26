<?php
/**
 * Cohere AI Service (v2 Chat API) — Philippine Peso Edition
 * Get your API key from https://dashboard.cohere.com/api-keys
 */

if (!function_exists('cohereApiKey')) {
    function cohereApiKey() {
        // ⚠️ REPLACE WITH YOUR REAL KEY
        return 'cohere_3CLhEkoMg2277Cp11n2Q0KDdDVRs0gyXgHGHfYJ23gMc9F';
    }
}

if (!function_exists('cohereHasKey')) {
    function cohereHasKey() {
        $k = trim(cohereApiKey());
        return $k && $k !== 'YOUR_COHERE_API_KEY_HERE' && strpos($k, 'cohere_') === 0 && strlen($k) > 40;
    }
}

if (!function_exists('callCohereChat')) {
    function callCohereChat($messages, $model = 'command-a-03-2025') {
        if (!cohereHasKey()) return ['error' => 'missing_api_key'];

        $clean = [];
        foreach ($messages as $m) {
            $content = trim((string)($m['content'] ?? ''));
            if ($content === '') continue;
            $role = in_array($m['role'] ?? '', ['user','assistant','system'], true) ? $m['role'] : 'user';
            $clean[] = ['role' => $role, 'content' => $content];
        }

        if (empty($clean)) return ['error' => 'empty_messages', 'message' => 'No valid messages.'];

        $hasUser = false;
        foreach ($clean as $m) { if ($m['role'] === 'user') { $hasUser = true; break; } }
        if (!$hasUser) $clean[] = ['role' => 'user', 'content' => 'Please respond.'];

        $payload = [
            'model' => $model,
            'messages' => $clean,
            'max_tokens' => 300,
            'temperature' => 0.5,
        ];

        $ch = curl_init("https://api.cohere.com/v2/chat");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . cohereApiKey(),
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) return ['error' => 'curl: ' . $err];

        $decoded = json_decode($response, true);
        if ($httpCode >= 400) {
            return ['error' => 'http_' . $httpCode, 'message' => $decoded['message'] ?? $response];
        }
        return $decoded;
    }
}

if (!function_exists('extractCohereText')) {
    function extractCohereText($r) {
        if (!is_array($r)) return '';
        if (isset($r['message']['content']) && is_array($r['message']['content'])) {
            $parts = [];
            foreach ($r['message']['content'] as $block) {
                if (isset($block['text'])) $parts[] = $block['text'];
            }
            if ($parts) return trim(implode("\n", $parts));
        }
        if (isset($r['text'])) return trim($r['text']);
        if (isset($r['generations'][0]['text'])) return trim($r['generations'][0]['text']);
        return '';
    }
}

if (!function_exists('toPesoText')) {
    function toPesoText($text) {
        $text = str_replace(['USD ', 'usd '], '', $text);
        $text = str_replace('$', '₱', $text);
        return $text;
    }
}

/* ============================================================
   PRODUCT RECOMMENDATION
   ============================================================ */
if (!function_exists('getProductRecommendation')) {
    function getProductRecommendation($query, $products) {
        $query = trim($query);
        if ($query === '') return "Please ask a question about our products.";
        if (empty($products)) return "No products available.";

        if (!cohereHasKey()) {
            return localRecommend($query, $products) . "\n\n⚠️ Add your Cohere API key in ai/cohere_service.php for full AI-powered recommendations.";
        }

        $list = "";
        foreach ($products as $p) {
            $list .= sprintf("- %s | %s | ₱%s | %s\n",
                $p['name'], $p['category'], number_format($p['price'], 2), substr($p['description'], 0, 100));
        }

        $prompt = "You are a helpful e-commerce shopping assistant for a Philippine-based online store. "
                . "ALL prices are in Philippine Peso (PHP / ₱). Never use USD or $ in your responses.\n\n"
                . "Here are the available products:\n\n"
                . $list . "\n\n"
                . "Customer question: \"$query\"\n\n"
                . "Recommend the 1-2 best matching products and explain briefly in 2-3 sentences. "
                . "Always quote prices using the ₱ symbol (e.g., ₱13,000.00).";

        $models = ['command-a-03-2025', 'command-r-plus-08-2024', 'command-r-08-2024', 'command-r', 'command'];
        $lastError = '';
        foreach ($models as $m) {
            $r = callCohereChat([['role' => 'user', 'content' => $prompt]], $m);
            if (!isset($r['error'])) {
                $text = extractCohereText($r);
                if ($text !== '') return toPesoText($text);
                $lastError = 'Empty response from ' . $m;
                continue;
            }
            $lastError = $r['message'] ?? $r['error'];
        }
        return localRecommend($query, $products) . "\n\n(AI service error: " . $lastError . ")";
    }
}

if (!function_exists('localRecommend')) {
    function localRecommend($query, $products) {
        $q = strtolower($query);
        $words = array_filter(explode(' ', preg_replace('/[^a-z0-9 ]/', ' ', $q)), fn($w) => strlen($w) >= 3);
        $scored = [];
        foreach ($products as $p) {
            $score = 0;
            $haystack = strtolower($p['name'] . ' ' . $p['description'] . ' ' . $p['category']);
            foreach ($words as $w) {
                if (strpos($haystack, $w) !== false) $score += 3;
            }
            if (strpos(strtolower($p['category']), $q) !== false) $score += 5;
            if ($score > 0) $scored[] = ['name' => $p['name'], 'price' => $p['price'], 'score' => $score];
        }
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($scored, 0, 2);

        if (empty($top)) return "I couldn't find any products matching \"$query\". Try browsing our shop categories.";
        $out = "Based on your query, here are my recommendations:\n";
        foreach ($top as $t) {
            $out .= "• " . $t['name'] . " — ₱" . number_format($t['price'], 2) . "\n";
        }
        return trim($out);
    }
}

/* ============================================================
   AUTO CATEGORIZATION
   ============================================================ */
if (!function_exists('autoCategorize')) {
    function autoCategorize($productName, $description) {
        if (!cohereHasKey()) return localCategorize($productName, $description);

        $allowed = ['Electronics','Fashion','Home','Sports','Books','Toys','Beauty','Food','Automotive','Furniture','Jewelry','Garden'];

        $prompt = "Classify the product below into EXACTLY ONE of these categories:\n"
                . implode(', ', $allowed) . "\n\n"
                . "Product Name: $productName\n"
                . "Description: $description\n\n"
                . "Reply with ONLY the single category name. No punctuation. No explanation.";

        $models = ['command-a-03-2025', 'command-r-08-2024', 'command-r', 'command'];
        foreach ($models as $m) {
            $r = callCohereChat([['role' => 'user', 'content' => $prompt]], $m);
            if (isset($r['error'])) continue;
            $text = extractCohereText($r);
            $cat = cleanCategory($text, $allowed);
            if ($cat && strtolower($cat) !== 'general') return $cat;
        }
        return localCategorize($productName, $description);
    }
}

if (!function_exists('cleanCategory')) {
    function cleanCategory($raw, $allowed) {
        if (!$raw) return '';
        $raw = preg_replace('/["\'`\.\,\!\?\:\;]/', '', $raw);
        $raw = trim($raw);
        $lower = strtolower($raw);
        foreach ($allowed as $cat) {
            if (strpos($lower, strtolower($cat)) !== false) return $cat;
        }
        $firstWord = explode(' ', $raw)[0] ?? '';
        if (strlen($firstWord) < 3) return '';
        return ucwords(strtolower($firstWord));
    }
}

if (!function_exists('localCategorize')) {
    function localCategorize($productName, $description) {
        $text = strtolower($productName . ' ' . $description);
        $keywordMap = [
            'Electronics' => ['phone','laptop','computer','headphone','earphone','earbud','smartwatch','camera','speaker','tv','television','monitor','keyboard','mouse','charger','cable','tablet','ipad','iphone','android','gaming','pc','desktop','console','playstation','xbox','nintendo','gpu','cpu','ram','ssd','router','modem','printer','scanner','drone','alexa','echo'],
            'Fashion'     => ['shirt','tshirt','t-shirt','jean','pant','trouser','short','dress','skirt','shoe','sneaker','boot','sandal','slipper','jacket','coat','hoodie','sweater','cap','hat','belt','bag','backpack','wallet','purse','sunglass','glasses','tie','scarf','glove','sock','underwear','bra','lingerie'],
            'Home'        => ['sofa','couch','chair','table','desk','bed','mattress','pillow','blanket','curtain','lamp','light','rug','carpet','shelf','cabinet','drawer','kitchen','cookware','pan','pot','plate','bowl','cup','mug','utensil','blender','toaster','microwave','oven','refrigerator','fridge','vacuum','cleaner','broom','mop','fan','heater','air conditioner'],
            'Sports'      => ['ball','bat','racket','glove','helmet','pad','gym','dumbbell','barbell','weight','yoga','mat','treadmill','bicycle','bike','cycle','skate','skateboard','surf','swim','goggle','tennis','basketball','soccer','football','golf','boxing','punching','rope','resistance'],
            'Books'       => ['book','novel','textbook','journal','notebook','diary','magazine','comic','manual','guide','dictionary','encyclopedia'],
            'Toys'        => ['toy','lego','puzzle','doll','action figure','board game','card game','plush','stuffed','rc car','kite','yo-yo','marble'],
            'Beauty'      => ['makeup','lipstick','mascara','eyeliner','foundation','powder','blush','perfume','cologne','shampoo','conditioner','soap','lotion','cream','serum','sunscreen','nail','polish','razor','trimmer','brush','comb','hair dryer','straightener'],
            'Food'        => ['snack','chips','cookie','candy','chocolate','coffee','tea','juice','soda','water','milk','cheese','bread','rice','pasta','noodle','cereal','oat','honey','jam','peanut','almond','cashew','protein','supplement','vitamin'],
            'Automotive'  => ['car','truck','motorcycle','tire','wheel','engine','oil','brake','battery','headlight','taillight','mirror','seat cover','floor mat','dash cam','gps','horn','wiper'],
            'Furniture'   => ['sofa','couch','chair','table','desk','bed','dresser','wardrobe','bookshelf','cabinet','ottoman','bench','stool'],
            'Jewelry'     => ['ring','necklace','bracelet','earring','pendant','chain','gold','silver','diamond','pearl','gemstone','brooch','anklet'],
            'Garden'      => ['plant','seed','soil','pot','planter','watering','hose','shovel','rake','lawn','mower','fertilizer','garden tool','trellis','fence'],
        ];
        foreach ($keywordMap as $category => $keywords) {
            foreach ($keywords as $kw) {
                if (strpos($text, $kw) !== false) return $category;
            }
        }
        return 'Miscellaneous';
    }
}

/* ============================================================
   SEMANTIC SEARCH / RERANK
   ============================================================ */
if (!function_exists('callCohereRerank')) {
    function callCohereRerank($payload) {
        if (!cohereHasKey()) return ['error' => 'missing_api_key'];
        $ch = curl_init("https://api.cohere.com/v2/rerank");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . cohereApiKey(),
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) return ['error' => 'curl: ' . $err];
        return json_decode($response, true);
    }
}

if (!function_exists('rerankProducts')) {
    function rerankProducts($query, $products, $topN = 5) {
        if (empty($products)) return [];
        $query = trim($query);

        if (!cohereHasKey() || $query === '') {
            return localRerank($query, $products, $topN);
        }

        $docs = array_map(fn($p) => $p['name'] . ' - ' . $p['description'] . ' - Category: ' . $p['category'], $products);

        $r = callCohereRerank([
            'model' => 'rerank-v3.5',
            'query' => $query,
            'documents' => $docs,
            'top_n' => min($topN, count($docs))
        ]);

        if (!isset($r['results'])) {
            $r = callCohereRerank([
                'model' => 'rerank-english-v3.0',
                'query' => $query,
                'documents' => $docs,
                'top_n' => min($topN, count($docs))
            ]);
        }

        if (isset($r['error']) || !isset($r['results'])) {
            return localRerank($query, $products, $topN);
        }

        $out = [];
        foreach ($r['results'] as $item) {
            $out[] = ['product' => $products[$item['index']], 'score' => $item['relevance_score']];
        }
        return $out;
    }
}

if (!function_exists('localRerank')) {
    function localRerank($query, $products, $topN) {
        $words = array_filter(explode(' ', strtolower(preg_replace('/[^a-z0-9 ]/', ' ', $query))), fn($w) => strlen($w) >= 3);
        $results = [];
        foreach ($products as $p) {
            $score = 0;
            $haystack = strtolower($p['name'] . ' ' . $p['description'] . ' ' . $p['category']);
            foreach ($words as $w) {
                if (strpos($haystack, $w) !== false) $score += 3;
            }
            if ($score > 0) $results[] = ['product' => $p, 'score' => min($score / 10, 1)];
        }
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($results, 0, $topN);
    }
}

/* ============================================================
   EMBED SEMANTIC SEARCH (vector similarity)
   ============================================================ */
if (!function_exists('getEmbedding')) {
    function getEmbedding($text, $inputType = 'search_document') {
        if (!cohereHasKey()) return null;

        $ch = curl_init("https://api.cohere.com/v2/embed");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'embed-english-v3.0',
                'texts' => [$text],
                'input_type' => $inputType,
                'embedding_types' => ['float']
            ]),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . cohereApiKey(),
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 20
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);

        return $data['embeddings']['float'][0] ?? null;
    }
}

if (!function_exists('cosineSim')) {
    function cosineSim($a, $b) {
        $dot = 0; $na = 0; $nb = 0;
        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] ** 2;
            $nb += $b[$i] ** 2;
        }
        if ($na == 0 || $nb == 0) return 0;
        return $dot / (sqrt($na) * sqrt($nb));
    }
}

if (!function_exists('semanticSearchProducts')) {
    function semanticSearchProducts($query, $pdo, $topN = 6) {
        if (!cohereHasKey()) return [];

        $queryVec = getEmbedding($query, 'search_query');
        if (!$queryVec) return [];

        $stmt = $pdo->query("
            SELECT pe.product_id, pe.embedding, p.name, p.description, p.category, p.price, p.stock, p.image_url
            FROM product_embeddings pe
            JOIN products p ON pe.product_id = p.id
        ");
        $results = [];
        foreach ($stmt->fetchAll() as $row) {
            $vec = json_decode($row['embedding'], true);
            if (!is_array($vec)) continue;
            $score = cosineSim($queryVec, $vec);
            $results[] = [
                'product' => $row,
                'score' => $score
            ];
        }
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($results, 0, $topN);
    }
}