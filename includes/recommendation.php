<?php
/**
 * Sweet Choice - AI Dessert Recommendation Engine
 * Matches customer's mood and taste profile against product tags.
 * Designed with a clean interface so an external AI/LLM API can be connected in the future.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/language.php';

class DessertRecommender {
    protected PDO $pdo;

    // Mapping moods to search tags and psychological sensory profiles
    protected array $moodTagMap = [
        'happy' => ['tags' => ['happy', 'fruit', 'sweet', 'celebration'], 'tone' => 'uplifting'],
        'sad' => ['tags' => ['comfort', 'chocolate', 'creamy', 'sweet'], 'tone' => 'comforting'],
        'stressed' => ['tags' => ['comfort', 'chocolate', 'creamy', 'sweet'], 'tone' => 'soothing'],
        'tired' => ['tags' => ['energy', 'chocolate', 'creamy', 'sweet'], 'tone' => 'revitalizing'],
        'romantic' => ['tags' => ['romantic', 'fruit', 'chocolate', 'celebration'], 'tone' => 'enchanting'],
        'celebrating' => ['tags' => ['celebration', 'fruit', 'sweet', 'happy'], 'tone' => 'festive'],
        'relaxed' => ['tags' => ['relaxed', 'light', 'creamy'], 'tone' => 'serene'],
        'energetic' => ['tags' => ['energy', 'light', 'fruit', 'cookie'], 'tone' => 'invigorating']
    ];

    // Mapping tastes to direct tags
    protected array $tasteTagMap = [
        'chocolate' => ['chocolate'],
        'fruit' => ['fruit'],
        'creamy' => ['creamy'],
        'cake' => ['cake'],
        'cookie' => ['cookie'],
        'pudding' => ['pudding'],
        'very_sweet' => ['sweet'],
        'light_sweetness' => ['light']
    ];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Recommend desserts based on Mood and Taste
     *
     * @param string $mood
     * @param string $taste
     * @param int $limit
     * @return array
     */
    public function getRecommendations(string $mood, string $taste, int $limit = 3): array {
        $normalizedMood = strtolower(trim($mood));
        $normalizedTaste = strtolower(trim(str_replace(' ', '_', $taste)));

        $targetTags = [];

        if (isset($this->moodTagMap[$normalizedMood])) {
            $targetTags = array_merge($targetTags, $this->moodTagMap[$normalizedMood]['tags']);
        } else {
            $targetTags[] = 'sweet';
        }

        if (isset($this->tasteTagMap[$normalizedTaste])) {
            $targetTags = array_merge($targetTags, $this->tasteTagMap[$normalizedTaste]);
        }

        $targetTags = array_unique($targetTags);

        // Fetch active products
        $stmt = $this->pdo->query("SELECT p.*, c.name_en AS category_name_en, c.name_ja AS category_name_ja 
                                   FROM products p 
                                   JOIN categories c ON p.category_id = c.id 
                                   WHERE p.availability != 'sold_out' 
                                   ORDER BY p.is_popular DESC, p.id ASC");
        $products = $stmt->fetchAll();

        $scoredProducts = [];

        foreach ($products as $prod) {
            $productTags = array_map('trim', explode(',', strtolower($prod['tags'] ?? '')));
            $score = 0;

            // Direct taste match gets high weight
            if (in_array($normalizedTaste, $productTags, true) || 
                (isset($this->tasteTagMap[$normalizedTaste]) && array_intersect($this->tasteTagMap[$normalizedTaste], $productTags))) {
                $score += 5;
            }

            // Mood match tags
            foreach ($targetTags as $tag) {
                if (in_array($tag, $productTags, true)) {
                    $score += 2;
                }
            }

            // Category match bonus
            if (stripos($prod['category_name_en'], $taste) !== false) {
                $score += 4;
            }

            // Popularity slight boost
            if (!empty($prod['is_popular'])) {
                $score += 1;
            }

            // Generate personalized AI reasoning
            $reason = $this->generateReason($prod, $normalizedMood, $normalizedTaste);

            $prod['recommendation_score'] = $score;
            $prod['recommendation_reason_en'] = $reason['en'];
            $prod['recommendation_reason_ja'] = $reason['ja'];

            $scoredProducts[] = $prod;
        }

        // Sort descending by score
        usort($scoredProducts, function($a, $b) {
            return $b['recommendation_score'] <=> $a['recommendation_score'];
        });

        return array_slice($scoredProducts, 0, $limit);
    }

    /**
     * Generate sensory rationale for why this dessert matches the customer's state
     */
    protected function generateReason(array $product, string $mood, string $taste): array {
        $prodNameEn = $product['name_en'];
        $prodNameJa = $product['name_ja'];

        // Tailored rationale matrix
        $moodRationales = [
            'stressed' => [
                'en' => "{$prodNameEn} offers rich, velvety notes that help melt away stress and give you a comforting sweet retreat.",
                'ja' => "心身の緊張を優しくほぐす濃厚な口どけ。慌ただしい一日の終わりに安らぎを届けるおすすめの一品です。"
            ],
            'sad' => [
                'en' => "{$prodNameEn} provides pure comfort with its warm flavor profile, wrapping you like a cozy blanket.",
                'ja' => "ほっと心温まる優しい甘み。少し落ち込んだ気分のときに、元気をチャージしてくれる至福の味わいです。"
            ],
            'tired' => [
                'en' => "{$prodNameEn} delivers a pleasant energy recharge with natural sweetness and decadent artisan textures.",
                'ja' => "上質な糖分と芳醇な香りが心地よいリフレッシュを演出。疲れた身体と心をふんわり癒やします。"
            ],
            'happy' => [
                'en' => "{$prodNameEn} bursts with joyful, vibrant flavors that perfectly complement your cheerful mood!",
                'ja' => "華やかな風味と彩りが、今のハッピーな気分をさらに盛り上げてくれる最高のデザートです！"
            ],
            'romantic' => [
                'en' => "{$prodNameEn} is crafted with delicate aesthetics and harmonious notes, ideal for an intimate, memorable moment.",
                'ja' => "繊細な美しさと上品な甘みが調和した逸品。大切な人との甘美な時間にぴったりです。"
            ],
            'celebrating' => [
                'en' => "{$prodNameEn} feels like a special occasion in every bite—luxurious, photogenic, and celebratory!",
                'ja' => "贅沢な素材使いと美しい佇まい。お祝いの特別なひとときを華やかに彩ります。"
            ],
            'relaxed' => [
                'en' => "{$prodNameEn} has gentle, balanced sweetness that pairs seamlessly with a serene afternoon tea.",
                'ja' => "甘さ控えめで軽やかな余韻。穏やかなティータイムのひとときに寄り添う上品なスイーツです。"
            ],
            'energetic' => [
                'en' => "{$prodNameEn} provides crisp, refreshing sweetness to fuel your creative energy throughout the day.",
                'ja' => "すっきりとしたキレのある美味しさで、アクティブな一日をさらに前向きに後押ししてくれます。"
            ]
        ];

        if (isset($moodRationales[$mood])) {
            return $moodRationales[$mood];
        }

        return [
            'en' => "{$prodNameEn} harmonizes wonderfully with your {$taste} craving, bringing you pure artisan bliss.",
            'ja' => "厳選された素材が織りなす極上の味わい。お客様のお好みのテイストに一番ぴったりの特別な一品です。"
        ];
    }
}

/**
 * Procedural helper to get recommendations
 */
function getAIDessertRecommendations(PDO $pdo, string $mood, string $taste, int $limit = 3): array {
    $engine = new DessertRecommender($pdo);
    return $engine->getRecommendations($mood, $taste, $limit);
}
