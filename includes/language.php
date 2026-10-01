<?php
/**
 * Sweet Choice - Language and Localization Support
 * Supports English and Japanese with session persistence.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle explicit language change request
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ja'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
    // Preserve current URL without ?lang= parameter or redirect back cleanly
    $redirect_url = strtok($_SERVER['REQUEST_URI'], '?');
    $query_params = $_GET;
    unset($query_params['lang']);
    if (!empty($query_params)) {
        $redirect_url .= '?' . http_build_query($query_params);
    }
    header("Location: " . $redirect_url);
    exit;
}

// Default to English if not set
if (empty($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}

/**
 * Get current active language code ('en' or 'ja')
 */
function getCurrentLang(): string {
    return $_SESSION['lang'] ?? 'en';
}

/**
 * Check if current language is Japanese
 */
function isJapanese(): bool {
    return getCurrentLang() === 'ja';
}

/**
 * Dictionary of bilingual strings
 */
$translations = [
    'en' => [
        // Brand & Navigation
        'brand_name' => 'Sweet Choice',
        'nav_home' => 'Home',
        'nav_category' => 'Category',
        'nav_cart' => 'Cart',
        'nav_favorites' => 'Favorites',
        'nav_orders' => 'Orders',
        'nav_account' => 'My Account',
        'nav_login' => 'Login',
        'nav_register' => 'Register',
        'nav_logout' => 'Logout',
        'nav_admin' => 'Admin Panel',

        // Hero & Home
        'hero_title' => 'Handcrafted Japanese Desserts & Confections',
        'hero_subtitle' => 'Made fresh daily with Hokkaido cream, Kyoto matcha, and authentic seasonal fruits.',
        'hero_cta' => 'Order Now',
        'categories_title' => 'Dessert Categories',
        'categories_subtitle' => 'Explore our handcrafted delicacies across 5 artisan collections',
        'popular_title' => 'Popular Items',
        'popular_subtitle' => 'Customer favorites baked fresh with passion and precision',
        'seasonal_title' => 'Seasonal Special Collection',
        'seasonal_subtitle' => 'Limited edition treats inspired by Japan’s four distinct seasons',
        'view_all' => 'View All',
        'view_details' => 'View Details',
        'add_to_cart' => 'Add to Cart',
        'out_of_stock' => 'Sold Out',
        'limited_stock' => 'Limited',
        'available' => 'Available',
        'quick_add' => 'Quick Add',

        // AI Recommendation
        'ai_section_badge' => 'Interactive AI Sommelier',
        'ai_title' => 'What are you craving today?',
        'ai_subtitle' => 'Select your current mood and favorite flavor note to get an instant tailored recommendation.',
        'ai_mood_label' => 'How are you feeling?',
        'ai_taste_label' => 'What flavor profile are you craving?',
        'ai_button_find' => 'Find My Perfect Dessert',
        'ai_result_title' => 'AI Recommended For You',
        'ai_reason_prefix' => 'Why this dessert:',
        'ai_select_prompt' => 'Pick a mood and taste above to see your personalized dessert pairing!',
        'ai_loading' => 'Analyzing flavor profiles & mood matching...',

        // Moods
        'mood_happy' => 'Happy',
        'mood_sad' => 'Sad',
        'mood_stressed' => 'Stressed',
        'mood_tired' => 'Tired',
        'mood_romantic' => 'Romantic',
        'mood_celebrating' => 'Celebrating',
        'mood_relaxed' => 'Relaxed',
        'mood_energetic' => 'Energetic',

        // Tastes
        'taste_chocolate' => 'Chocolate',
        'taste_fruit' => 'Fruit',
        'taste_creamy' => 'Creamy',
        'taste_cake' => 'Cake',
        'taste_cookie' => 'Cookie',
        'taste_pudding' => 'Pudding',
        'taste_very_sweet' => 'Very Sweet',
        'taste_light_sweetness' => 'Light Sweetness',

        // Category & Product Page
        'search_placeholder' => 'Search desserts by name or ingredient...',
        'all_categories' => 'All Categories',
        'sort_by' => 'Sort By',
        'sort_featured' => 'Featured',
        'sort_price_low' => 'Price: Low to High',
        'sort_price_high' => 'Price: High to Low',
        'sort_name' => 'Name (A to Z)',
        'no_products_found' => 'No desserts found matching your criteria.',
        'ingredients_heading' => 'Artisanal Ingredients',
        'allergy_heading' => 'Allergy Information',
        'allergy_warning' => 'Contains allergens: ',
        'allergy_none' => 'No major common allergens reported.',
        'customization_heading' => 'Customize Your Treat',
        'quantity' => 'Quantity',
        'stock_remaining' => 'units left in stock',
        'add_to_favorites' => 'Favorite',
        'remove_from_favorites' => 'Unfavorite',
        'customer_reviews' => 'Customer Reviews',
        'no_reviews_yet' => 'No reviews yet for this dessert. Be the first to share your thoughts!',
        'write_review' => 'Write a Review',
        'rating' => 'Rating',
        'your_review' => 'Your Review',
        'submit_review' => 'Submit Review',
        'review_verified' => 'Verified Buyer',

        // Cart Page
        'cart_title' => 'Shopping Cart',
        'cart_empty' => 'Your dessert cart is empty.',
        'cart_empty_cta' => 'Discover delicious treats now',
        'item' => 'Item',
        'price' => 'Price',
        'customizations' => 'Customizations',
        'subtotal' => 'Subtotal',
        'remove' => 'Remove',
        'order_summary' => 'Order Summary',
        'product_subtotal' => 'Product Subtotal',
        'customization_fees' => 'Customization Fees',
        'delivery_fee' => 'Delivery Fee',
        'service_fee' => 'Service Fee',
        'grand_total' => 'Grand Total',
        'proceed_to_checkout' => 'Proceed to Checkout',
        'continue_shopping' => 'Continue Shopping',
        'free_delivery_tip' => 'Distance tiers: 0-3km: ¥300 | 3-5km: ¥500 | 5-10km: ¥800',

        // Checkout Page
        'checkout_title' => 'Checkout',
        'fulfillment_choice' => 'Fulfillment Option',
        'pickup_option' => 'Store Pickup',
        'pickup_desc' => 'Pick up your freshly packaged order at our Ginza boutique with zero delivery fees.',
        'delivery_option' => 'Local Delivery',
        'delivery_desc' => 'Hand-delivered to your door in temperature-controlled confectionery bags.',
        'pickup_details' => 'Pickup Date & Time',
        'delivery_details' => 'Delivery Details & Address',
        'select_date' => 'Select Date',
        'select_time' => 'Select Time Slot',
        'customer_info' => 'Customer Information',
        'full_name' => 'Full Name',
        'email_address' => 'Email Address',
        'phone_number' => 'Phone Number',
        'postal_code' => 'Postal Code',
        'street_address' => 'Street Address',
        'apartment_unit' => 'Apartment / Building / Floor',
        'distance_tier' => 'Estimated Delivery Distance',
        'payment_section' => 'Payment Method',
        'card_number' => 'Card Number',
        'card_exp' => 'MM / YY',
        'card_cvv' => 'CVV',
        'cardholder_name' => 'Cardholder Name',
        'payment_simulation_note' => 'Simulation Mode: Educational project. No real payment will be charged.',
        'order_notes' => 'Special Instructions / Allergy Notes',
        'place_order' => 'Place Order',

        // Order Confirmation & Orders
        'order_confirmed_title' => 'Thank You for Your Order!',
        'order_confirmed_subtitle' => 'Your confections are being prepared with love and precision.',
        'order_number' => 'Order Number',
        'order_date' => 'Order Date',
        'fulfillment_type' => 'Fulfillment Method',
        'scheduled_time' => 'Scheduled Time',
        'order_status' => 'Status',
        'status_received' => 'Order Received',
        'status_preparing' => 'Preparing',
        'status_ready_pickup' => 'Ready for Pickup',
        'status_out_delivery' => 'Out for Delivery',
        'status_completed' => 'Completed',
        'status_delivered' => 'Delivered',
        'status_cancelled' => 'Cancelled',
        'my_orders' => 'My Orders',
        'view_order_details' => 'View Receipt',
        'no_orders_found' => 'You haven’t placed any orders yet.',

        // Account
        'my_account' => 'My Account',
        'profile_info' => 'Profile Details',
        'update_profile' => 'Save Changes',
        'change_password' => 'Change Password',
        'current_password' => 'Current Password',
        'new_password' => 'New Password',
        'confirm_password' => 'Confirm New Password',
        'favorites_title' => 'My Favorite Desserts',
        'no_favorites' => 'You have no saved favorites yet.',

        // Auth
        'login_title' => 'Customer Login',
        'register_title' => 'Create a Sweet Choice Account',
        'password' => 'Password',
        'remember_me' => 'Remember Me',
        'dont_have_account' => 'Don’t have an account?',
        'already_have_account' => 'Already have an account?',
        'admin_login_title' => 'Staff / Admin Portal',

        // Footer
        'footer_about' => 'Sweet Choice is an artisanal Japanese dessert shop bringing delicate, cloud-soft pastries and seasonal confections to sweet lovers.',
        'footer_links' => 'Quick Links',
        'footer_hours' => 'Boutique Hours',
        'footer_contact' => 'Store Information',
        'copyright' => 'Sweet Choice Confectionery Co., Ltd. All rights reserved.',
        'all_prices_jpy' => 'All prices are displayed in Japanese Yen (JPY / ¥) including consumption tax.',
    ],
    'ja' => [
        // Brand & Navigation
        'brand_name' => 'スウィートチョイス',
        'nav_home' => 'ホーム',
        'nav_category' => 'カテゴリー',
        'nav_cart' => 'カート',
        'nav_favorites' => 'お気に入り',
        'nav_orders' => '注文履歴',
        'nav_account' => 'マイページ',
        'nav_login' => 'ログイン',
        'nav_register' => '会員登録',
        'nav_logout' => 'ログアウト',
        'nav_admin' => '管理者画面',

        // Hero & Home
        'hero_title' => '日本の四季を彩る極上手作りスイーツ',
        'hero_subtitle' => '北海道産純生クリームや京都宇治抹茶、旬の国産果実を使ったこだわりのお菓子を毎日焼き上げています。',
        'hero_cta' => '今すぐ注文する',
        'categories_title' => 'スイーツカテゴリー',
        'categories_subtitle' => 'パティシエ自慢の5つのスイーツコレクション',
        'popular_title' => '人気のデザート',
        'popular_subtitle' => '多くのお客様に愛されている当店自慢の定番スイーツ',
        'seasonal_title' => '季節の限定コレクション',
        'seasonal_subtitle' => '日本の四季折々の恵みを閉じ込めた期間限定スイーツ',
        'view_all' => 'すべて見る',
        'view_details' => '詳細を見る',
        'add_to_cart' => 'カートに入れる',
        'out_of_stock' => '売り切れ',
        'limited_stock' => '残りわずか',
        'available' => '販売中',
        'quick_add' => '簡単追加',

        // AI Recommendation
        'ai_section_badge' => 'AIスイーツソムリエ',
        'ai_title' => '今日の気分にぴったりのデザートを見つけよう',
        'ai_subtitle' => 'いまの気分とお好みの味を選ぶだけで、AIがあなたに最適なデザートを提案します。',
        'ai_mood_label' => '今の気分は？',
        'ai_taste_label' => 'どんなテイストをお探しですか？',
        'ai_button_find' => 'おすすめを診断する',
        'ai_result_title' => 'AIのおすすめスイーツ',
        'ai_reason_prefix' => 'おすすめの理由：',
        'ai_select_prompt' => '上の気分とテイストを選んで「おすすめを診断する」を押してください。',
        'ai_loading' => '気分とテイストから最適なスイーツを解析中...',

        // Moods
        'mood_happy' => '幸せ・嬉しい',
        'mood_sad' => '悲しい・憂鬱',
        'mood_stressed' => 'ストレス・疲れ',
        'mood_tired' => 'お疲れ気味',
        'mood_romantic' => 'ロマンチック',
        'mood_celebrating' => 'お祝い・記念日',
        'mood_relaxed' => 'のんびり・リラックス',
        'mood_energetic' => '元気が欲しい',

        // Tastes
        'taste_chocolate' => 'チョコレート',
        'taste_fruit' => 'フルーツ・果実',
        'taste_creamy' => '濃厚・クリーミー',
        'taste_cake' => 'ケーキ',
        'taste_cookie' => '焼き菓子・クッキー',
        'taste_pudding' => 'プリン',
        'taste_very_sweet' => '甘め・濃厚な甘み',
        'taste_light_sweetness' => '甘さ控えめ・上品',

        // Category & Product Page
        'search_placeholder' => '商品名や原材料で検索...',
        'all_categories' => 'すべてのカテゴリー',
        'sort_by' => '並び替え',
        'sort_featured' => 'おすすめ順',
        'sort_price_low' => '価格が安い順',
        'sort_price_high' => '価格が高い順',
        'sort_name' => '商品名順',
        'no_products_found' => '条件に一致するスイーツは見つかりませんでした。',
        'ingredients_heading' => '厳選素材・原材料',
        'allergy_heading' => 'アレルギー物質（特定原材料等）',
        'allergy_warning' => '含まれるアレルゲン：',
        'allergy_none' => '特定アレルゲンは含まれていません。',
        'customization_heading' => 'カスタム・オプション',
        'quantity' => '数量',
        'stock_remaining' => '個の在庫あり',
        'add_to_favorites' => 'お気に入り追加',
        'remove_from_favorites' => 'お気に入り解除',
        'customer_reviews' => 'お客様の声・レビュー',
        'no_reviews_yet' => 'まだレビューがありません。最初のご感想をぜひお聞かせください！',
        'write_review' => 'レビューを投稿する',
        'rating' => '評価',
        'your_review' => 'レビュー本文',
        'submit_review' => 'レビューを投稿',
        'review_verified' => '購入者認証済み',

        // Cart Page
        'cart_title' => 'ショッピングカート',
        'cart_empty' => 'カートに商品が入っていません。',
        'cart_empty_cta' => '自慢のスイーツを探す',
        'item' => '商品',
        'price' => '価格',
        'customizations' => 'カスタム内容',
        'subtotal' => '小計',
        'remove' => '削除',
        'order_summary' => 'ご注文内訳',
        'product_subtotal' => '商品小計',
        'customization_fees' => 'オプション追加料金',
        'delivery_fee' => '配送料',
        'service_fee' => 'サービス料',
        'grand_total' => '合計金額（税込）',
        'proceed_to_checkout' => 'ご注文手続きへ進む',
        'continue_shopping' => '買い物を続ける',
        'free_delivery_tip' => '配送料目安: 0〜3km: ¥300 | 3〜5km: ¥500 | 5〜10km: ¥800',

        // Checkout Page
        'checkout_title' => 'ご注文手続き',
        'fulfillment_choice' => 'お受け取り方法の選択',
        'pickup_option' => '銀座店 店頭受取',
        'pickup_desc' => '銀座本店にて出来立てをお受け取りいただけます（配送料無料）。',
        'delivery_option' => 'ご指定先へのお届け（デリバリー）',
        'delivery_desc' => '専用保冷バッグにて大切にご指定の場所までお届けいたします。',
        'pickup_details' => '受取日時',
        'delivery_details' => 'お届け先・お届け日時',
        'select_date' => '受取日・お届け日',
        'select_time' => '時間帯を選択',
        'customer_info' => 'お客様情報',
        'full_name' => 'お名前',
        'email_address' => 'メールアドレス',
        'phone_number' => 'お電話番号',
        'postal_code' => '郵便番号',
        'street_address' => 'ご住所（都道府県・市区町村・番地）',
        'apartment_unit' => '建物名・部屋番号',
        'distance_tier' => '店舗からの配達距離エリア',
        'payment_section' => 'お支払い方法',
        'card_number' => 'カード番号',
        'card_exp' => '有効期限（MM/YY）',
        'card_cvv' => 'セキュリティコード（CVV）',
        'cardholder_name' => 'カード名義人',
        'payment_simulation_note' => '【シミュレーション】実際の決済・引き落としは発生しません。',
        'order_notes' => '備考・ご要望（アレルギーやメッセージ等のご指示）',
        'place_order' => '注文を確定する',

        // Order Confirmation & Orders
        'order_confirmed_title' => 'ご注文ありがとうございます！',
        'order_confirmed_subtitle' => 'パティシエが心を込めてスイーツの準備を開始いたします。',
        'order_number' => '注文番号',
        'order_date' => '注文日時',
        'fulfillment_type' => '受取・配送方法',
        'scheduled_time' => '予定日時',
        'order_status' => 'ご注文状況',
        'status_received' => '注文受付済み',
        'status_preparing' => '製造・準備中',
        'status_ready_pickup' => '受取可能',
        'status_out_delivery' => '配達中',
        'status_completed' => '完了',
        'status_delivered' => '配達完了',
        'status_cancelled' => 'キャンセル',
        'my_orders' => 'ご注文履歴',
        'view_order_details' => '明細を見る',
        'no_orders_found' => 'まだご注文履歴がありません。',

        // Account
        'my_account' => 'マイページ',
        'profile_info' => 'ご登録情報',
        'update_profile' => '登録情報を更新',
        'change_password' => 'パスワード変更',
        'current_password' => '現在のパスワード',
        'new_password' => '新しいパスワード',
        'confirm_password' => '新しいパスワード（確認）',
        'favorites_title' => 'お気に入りスイーツ一覧',
        'no_favorites' => 'お気に入りに登録されたスイーツはありません。',

        // Auth
        'login_title' => 'お客様ログイン',
        'register_title' => '新規会員登録',
        'password' => 'パスワード',
        'remember_me' => '次回から自動ログイン',
        'dont_have_account' => 'アカウントをお持ちでない方',
        'already_have_account' => 'すでにアカウントをお持ちの方',
        'admin_login_title' => '管理者・スタッフログイン',

        // Footer
        'footer_about' => 'Sweet Choice（スウィートチョイス）は、素材本来の美味しさを大切にした手作りスイーツ専門店です。',
        'footer_links' => 'リンク',
        'footer_hours' => '営業時間',
        'footer_contact' => '店舗情報',
        'copyright' => 'Sweet Choice Confectionery Co., Ltd. 無断転載を禁じます。',
        'all_prices_jpy' => '表示価格はすべて日本円（¥ / JPY）消費税込です。',
    ]
];

/**
 * Translate a key into the active language
 */
function __(string $key, ?string $fallback = null): string {
    global $translations;
    $lang = getCurrentLang();
    if (isset($translations[$lang][$key])) {
        return $translations[$lang][$key];
    }
    if (isset($translations['en'][$key])) {
        return $translations['en'][$key];
    }
    return $fallback ?? $key;
}

/**
 * Return localized field from database row (e.g. name_en vs name_ja)
 */
function getLocalized(array $item, string $baseField): string {
    $lang = getCurrentLang();
    $specificField = $baseField . '_' . $lang;
    if (!empty($item[$specificField])) {
        return $item[$specificField];
    }
    $enField = $baseField . '_en';
    if (!empty($item[$enField])) {
        return $item[$enField];
    }
    if (isset($item[$baseField])) {
        return (string)$item[$baseField];
    }
    return '';
}
