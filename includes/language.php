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
    $_SESSION['lang_chosen'] = true;
    setcookie('sweetchoice_lang', $_GET['lang'], time() + (86400 * 30), '/');
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

// Default to Japanese if not explicitly set or chosen
if (empty($_SESSION['lang_chosen'])) {
    if (!empty($_COOKIE['sweetchoice_lang']) && in_array($_COOKIE['sweetchoice_lang'], ['en', 'ja'], true)) {
        $_SESSION['lang'] = $_COOKIE['sweetchoice_lang'];
        $_SESSION['lang_chosen'] = true;
    } else {
        $_SESSION['lang'] = 'ja';
    }
} elseif (empty($_SESSION['lang'])) {
    $_SESSION['lang'] = 'ja';
}

/**
 * Get current active language code ('en' or 'ja')
 */
function getCurrentLang(): string {
    return $_SESSION['lang'] ?? 'ja';
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
        'brand_name' => 'SWEET CHOICE',
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
        'hero_title' => 'Discover Your Perfect Sweet Moment.',
        'hero_subtitle' => 'Handcrafted desserts, thoughtfully selected to make every moment special.',
        'hero_cta' => 'Order Now',
        'hero_cta_explore' => 'Explore Our Collection',
        'hero_cta_sommelier' => 'Find Your Perfect Dessert',
        'special_offers_tag' => 'Smart Special Offers',
        'special_offers_title' => ' Special Offers',
        'special_offers_subtitle' => 'Enjoy special prices on selected desserts for a limited time.',
        'special_offers_badge' => 'Smart Offer',
        'units_available' => 'units in stock',
        'collections_tag' => 'Curated Collections',
        'collections_title' => 'MENU',
        'collections_subtitle' => 'From airy sponge cakes to delicate Parisian macarons, each piece is handcrafted daily.',
        'pairings_tag' => "Recommendations",
        'pairings_title' => 'Complementary Pairings',
        'pairings_subtitle' => 'Elevate your tasting experience with curated combinations of desserts and beverages, complete with bundle savings.',
        'pairings_combo_label' => 'Pairing Set',
        'pairings_regular_total' => 'Regular Total',
        'pairings_combo_price' => 'Bundle Price',
        'pairings_save_badge' => 'Save %s',
        'pairings_view_pair' => 'Explore Pairing',
        'seasonal_campaign_tag' => 'Seasonal Edition',
        'seasonal_campaign_title' => 'Seasonal Campaign',
        'seasonal_campaign_subtitle' => 'Limited-edition confections celebrating the fleeting beauty of seasonal ingredients.',
        'brand_story_tag' => 'Our Artisan Heritage',
        'brand_story_title' => 'The Art of Japanese Pâtisserie',
        'brand_story_p1' => 'Born in the heart of Ginza, Sweet Choice blends timeless French pâtisserie techniques with Japan’s deep reverence for pristine seasonal ingredients.',
        'brand_story_p2' => 'From airy sponge cakes folded with rich Hokkaido fresh dairy to ceremonial grade Kyoto Uji matcha, every creation is baked with mindful sweetness and culinary devotion.',
        'brand_pillar_1_title' => 'Hokkaido Fresh Dairy',
        'brand_pillar_1_desc' => 'Pure, unadulterated fresh cream delivering an ethereal, cloud-soft melt.',
        'brand_pillar_2_title' => 'Kyoto Ceremonial Matcha',
        'brand_pillar_2_desc' => 'Single-origin Uji green tea ground with traditional stone mills.',
        'brand_pillar_3_title' => 'Mindful Sweetness',
        'brand_pillar_3_desc' => 'Lightly sweetened to allow natural aromatics and delicate textures to shine.',
        'categories_title' => 'Dessert Categories',
        'categories_subtitle' => 'Explore our handcrafted delicacies collections',
        'popular_tag' => 'Boutique Favorites',
        'popular_title' => 'Popular Items',
        'popular_subtitle' => 'Customer favorites baked fresh with passion and precision',
        'badge_popular' => 'Popular',
        'seasonal_title' => 'Seasonal Special Collection',
        'seasonal_subtitle' => 'Limited-edition treats for seasonal events and celebrations',
        'view_all' => 'View All',
        'view_details' => 'Details',
        'add_to_cart' => 'Add to Cart',
        'out_of_stock' => 'Sold Out',
        'limited_stock' => 'Limited',
        'available' => 'Available',
        'quick_add' => 'Add',

        // AI Recommendation
        'ai_section_badge' => 'PERSONALIZED DESSERT FINDER',
        'ai_title' => 'What are you craving today?',
        'ai_subtitle' => 'Select your current mood and favorite flavor note to get an instant tailored recommendation.',
        'ai_mood_label' => 'How are you feeling?',
        'ai_taste_label' => 'What flavor profile are you craving?',
        'ai_button_find' => 'Find My Perfect Dessert',
        'ai_result_title' => 'Your Dessert Matches',
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

        // Dessert & Drink Pairing with Inventory Discounts
        'pairing_drinks_title' => 'Recommended Drinks for This Dessert',
        'pairing_desserts_title' => 'Recommended Desserts for This Drink',
        'pairing_drinks_subtitle' => 'Enhance your dessert experience with our chef-selected beverage pairings.',
        'pairing_desserts_subtitle' => 'Pair your favorite drink with one of our handcrafted confections.',
        'pairing_section_badge' => 'PERFECT PAIRING',
        'pairing_discount_badge' => '%d%% OFF',
        'pairing_instock_badge' => 'IN-STOCK SPECIAL',
        'pairing_add_pairing' => '+ Add Pairing',
        'pairing_original_price' => 'Original',
        'pairing_discount_applied' => 'Pairing discount applied',
        'pairing_bonus_notice' => 'Inventory Bonus: Special discount applied to this recommended item!',
        'pairing_cart_discount_label' => 'Pairing Discount',

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
        'dine_in_option' => 'Dine-in (店内)',
        'dine_in_desc' => 'Enjoy freshly crafted desserts in our Ginza salon with zero service or delivery fees.',
        'dine_in_details' => 'Dine-in Details',
        'dine_in_location_note' => 'Enjoy your order at our Ginza Boutique Café: Ginza 4-2-11, Chuo-ku, Tokyo. No service fee applies.',
        'pickup_option' => 'Store Pickup',
        'pickup_desc' => 'Pick up your freshly packaged order at our Ginza boutique with zero service or delivery fees.',
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
        'payment_provider' => 'Payment Provider',
        'select_fulfillment_prompt' => 'Please select a fulfillment option above to view available payment methods.',
        'cash_dine_in_only_note' => 'Cash payment is only available for Dine-in (店内) orders.',
        'online_payment_select_provider' => 'Select Online Payment Provider',
        'online_payment_demo_notice' => 'Demonstration Payment Details: These are sample payment details for demonstration only — not actual payment credentials. No real payment will be charged.',
        'card_details_title' => 'Credit / Debit Card Information',
        'card_security_note' => 'Demo checkout: Card details are validated only and are never stored in our database.',
        'card_number' => 'Card Number',
        'card_exp' => 'Expiration Date (MM/YY)',
        'card_cvv' => 'CVV',
        'cardholder_name' => 'Cardholder Name',
        'payment_simulation_note' => 'Simulation Mode: Educational project. No real payment will be charged.',
        'not_selected' => 'Not selected',
        'service_fee_delivery_only' => 'Local Delivery only',
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

        // Checkout Progress Stepper
        'step_cart' => 'Cart',
        'step_auth' => 'Login / Register',
        'step_minigame' => 'Mini-Game',
        'step_payment' => 'Payment',
        'step_confirmation' => 'Confirmation',
        'step_skipped' => 'Skipped',

        // Auth, Registration & Account (Bilingual)
        'auth_login_subtitle' => 'Access your Sweet Choice favorites, cart, and past orders.',
        'auth_register_subtitle' => 'Create your account to complete checkout, save favorites, and track orders.',
        'auth_checkout_notice' => 'Please log in or create an account to continue to checkout. Your cart items are safely saved.',
        'auth_invalid_credentials' => 'Invalid email or password.',
        'auth_admin_required' => 'Access denied. Administrator privileges required.',
        'auth_required_fields' => 'Please fill in all required fields.',
        'auth_invalid_email' => 'Please provide a valid email address.',
        'auth_password_min' => 'Password must be at least 6 characters long.',
        'auth_password_mismatch' => 'Passwords do not match.',
        'auth_email_exists' => 'An account with this email address already exists.',
        'auth_csrf_expired' => 'Session expired. Please refresh and try again.',
        'auth_demo_account' => 'Demo Customer Account:',
        'password_confirm' => 'Confirm Password',
        'password_min_hint' => 'Password (min 6 characters)',
        'optional_label' => 'Optional',
        'account_welcome' => 'Hello, %s',
        'account_subtitle' => 'Manage your boutique preferences, saved addresses, and dessert order history.',
        'account_name_email_required' => 'Name and email are required.',
        'account_email_in_use' => 'This email is already in use by another account.',
        'account_current_pass_wrong' => 'Current password does not match.',
        'account_new_pass_min' => 'New password must be at least 6 characters.',
        'account_pass_mismatch' => 'New passwords do not match.',
        'account_no_orders' => 'No past orders found.',
        'account_no_favorites' => 'No saved favorites yet.',

        // Cart & Merge Notices
        'cart_merge_success' => 'Your guest cart items have been safely merged into your account.',
        'cart_merge_adjusted' => 'Some cart quantities were adjusted based on current stock availability.',
        'cart_remove_confirm' => 'Remove this item from your cart?',
        'cart_minigame_eligible_hint' => 'Your order qualifies for the Sweet Run Mini-Game! Play during checkout for a chance to earn a ¥200 discount.',
        'cart_minigame_need_more_hint' => 'Add %s more in products (excluding customizations & delivery) to unlock the Sweet Run Mini-Game for a ¥200 discount!',
        'cart_subtotal_note' => 'Only product prices × quantities count toward the ¥3,000 mini-game minimum.',

        // Mini-Game & Eligibility
        'minigame_title' => 'Sweet Run Mini-Game Challenge',
        'minigame_badge' => 'CHECKOUT BONUS GAME',
        'minigame_qualified_msg' => 'Your order qualifies for the mini-game!',
        'minigame_not_qualified_msg' => 'Your order does not meet the ¥3,000 minimum purchase requirement (product subtotal only). Proceeding directly to payment settings.',
        'minigame_rules_title' => 'How to Win a ¥200 Discount',
        'minigame_rule_target' => 'Score strictly higher than 2,000 points (2,001+ pts) in Sweet Run.',
        'minigame_rule_reward' => 'Earn a ¥200 discount applied directly to this order.',
        'minigame_rule_attempts' => 'Attempt 1 is free. Watch a short Demo Ad to unlock Attempt 2 and Attempt 3 (max 3 attempts / 2 ads per order).',
        'minigame_rule_cap' => 'Maximum mini-game discount is ¥200 per order, even if you win multiple times.',
        'minigame_attempt_label' => 'Attempt %d of %d',
        'minigame_attempt_free' => 'Free Attempt',
        'minigame_attempt_ad' => 'Ad-Unlocked Attempt',
        'minigame_attempts_used' => 'Attempts Used',
        'minigame_ads_used' => 'Demo Ads Watched',
        'minigame_best_score' => 'Best Score',
        'minigame_discount_status' => 'Mini-Game Discount',
        'minigame_discount_earned_badge' => '¥200 Discount Earned!',
        'minigame_discount_not_earned' => 'Not earned yet',
        'minigame_congrats_discount' => 'Congratulations! You earned a ¥200 discount!',
        'minigame_try_again_score' => 'You scored %s points. Score over 2,000 points (2,001+) to earn the ¥200 discount!',
        'minigame_exact_2000_notice' => 'So close! Exactly 2,000 points does not qualify—you need strictly over 2,000 points (2,001+).',
        'minigame_all_attempts_used' => 'You have used all 3 attempts for this order.',
        'minigame_already_won_continue' => 'Your ¥200 discount is locked in! You can continue to payment now, or use any remaining attempts for fun (discount stays capped at ¥200).',
        'minigame_continue_payment' => 'Continue to Payment Settings',
        'minigame_skip_to_payment' => 'Skip Mini-Game & Go to Payment',
        'minigame_start_free' => 'Start Free Attempt (1/3)',
        'minigame_start_unlocked' => 'Play Unlocked Attempt (%d/3)',
        'minigame_round_in_progress' => 'Attempt %d in progress — Good luck!',
        'minigame_score_rejected' => 'This score could not be verified by the server. Please play normally inside the game.',
        'minigame_freeplay_badge' => 'FREE PLAY MODE (Entertainment Only)',
        'minigame_back_to_shop' => 'Back to Shop',
        'minigame_legacy_disabled' => 'The post-order free mini-cake campaign has ended. Mini-game ¥200 discounts are now available during checkout on orders with ¥3,000+ in products.',
        'minigame_attempt_history' => 'Attempt History',
        'minigame_no_attempts_yet' => 'No attempts played yet. Start your free attempt!',
        'minigame_won_tag' => 'WIN (+¥200 OFF)',
        'minigame_controls_hint' => 'Controls: Arrow Keys / A・D / Swipe to move lanes, Up / Space to jump, Down to slide.',

        // Demo Rewarded Advertisement Mode
        'ad_watch_btn' => 'Watch an advertisement to play again',
        'ad_watch_prompt' => 'Watch an advertisement to unlock another attempt.',
        'ad_demo_badge' => 'DEMO REWARDED ADVERTISEMENT MODE',
        'ad_demo_title' => 'Simulated Sweet Choice Promotion',
        'ad_demo_headline' => 'Discover your favorite desserts at Sweet Choice!',
        'ad_demo_subtext' => 'Handcrafted in Ginza with Hokkaido pure dairy & Kyoto Uji matcha. Enjoy seasonal cakes, macarons, and curated drink pairings.',
        'ad_demo_disclaimer' => 'School Project Demo Mode: This is a simulated advertisement for educational demonstration. No external ad network is connected and no real ad revenue is generated.',
        'ad_countdown_label' => 'Reward unlocks in %d seconds...',
        'ad_unlocking' => 'Verifying demo ad completion with server...',
        'ad_unlocked_success' => 'Advertisement complete! 1 additional attempt has been unlocked.',
        'ad_limit_reached' => 'Maximum of 2 advertisements per order reached.',
        'ad_too_early' => 'Please finish watching the 5-second demo advertisement before claiming your attempt.',
        'ad_duplicate_error' => 'This advertisement reward has already been processed.',
        'ad_cancel_btn' => 'Cancel',
        'ad_finish_before_ad' => 'Please finish your current attempt before watching an advertisement.',

        // Payment Settings, Confirmation & Order History
        'checkout_payment_title' => 'Payment & Fulfillment Settings',
        'checkout_eligible_subtotal' => 'Eligible Product Subtotal',
        'checkout_minigame_discount' => 'Mini-Game Discount',
        'checkout_other_promotions' => 'Pairing Promotions',
        'checkout_final_amount' => 'Final Amount',
        'checkout_pickup_location' => 'Pickup Location:',
        'checkout_notes_placeholder' => 'Allergies, birthday candles, delivery instructions...',
        'checkout_err_contact' => 'Please fill in your name, email, and phone number.',
        'checkout_err_pickup_slot' => 'Please select a pickup date and time slot.',
        'checkout_err_delivery_addr' => 'Please provide a full delivery postal code and street address.',
        'checkout_err_delivery_slot' => 'Please select a delivery date and time slot.',
        'checkout_err_duplicate_submit' => 'This order has already been submitted. Please check your Order History.',
        'checkout_err_stock' => 'Some items in your cart are out of stock or exceed available inventory. Please review your cart.',
        'checkout_err_unexpected' => 'An unexpected error occurred while placing your order. Please try again.',
        'checkout_discount_voided' => 'Your cart subtotal dropped below ¥3,000, so the mini-game discount was removed.',
        'checkout_back_to_game' => 'Back to Mini-Game',
        'checkout_back_to_cart' => 'Back to Cart',
        'order_date_label' => 'Order Date',
        'order_payment_status_paid' => 'Paid (Simulated)',
        'order_payment_status_pending' => 'Pending (Pay on Fulfillment)',
        'order_boutique_address_label' => 'Boutique Address',
        'order_guest_lookup_title' => 'Guest Order Lookup',
        'order_guest_lookup_desc' => 'Placed an order previously without an account? Enter your Order Number and Email address to view your receipt and status.',
        'order_guest_lookup_btn' => 'Find My Order',
        'order_guest_lookup_not_found' => 'No order found matching this Order Number and Email address.',
        'order_guest_lookup_found' => 'Order Found:',
        'order_guest_lookup_login_prompt' => 'Log in to view all your past orders',
        'order_view_complete_receipt' => 'View Complete Receipt',
        'order_access_denied' => 'Please log in to view this order receipt.',
        'order_minigame_discount_badge' => 'Sweet Run -¥200',
        'order_no_orders_desc' => 'Our pastry chefs are ready to craft something sweet for you.',
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
        'hero_title' => '心ほどける、特別な甘いひとときを。',
        'hero_subtitle' => '選び抜かれた素材と職人の手仕事で、日常に寄り添う至福のスイーツをお届けします。',
        'hero_cta' => '今すぐ注文する',
        'hero_cta_explore' => 'コレクションを見る',
        'hero_cta_sommelier' => 'おすすめデザートを見つける',
        'special_offers_tag' => 'スマートスペシャルオファー',
        'special_offers_title' => 'お得な限定オファー',
        'special_offers_subtitle' => '期間限定で、一部のスイーツをお得な価格でお楽しみいただけます。',
        'special_offers_badge' => '限定オファー',
        'units_available' => '点限定',
        'collections_tag' => '厳選コレクション',
        'collections_title' => 'メニュー',
        'collections_subtitle' => '極上ショートケーキからパリ仕込みのマカロンまで、職人が毎日心を込めて仕上げています。',
        'pairings_tag' => 'おすすめ',
        'pairings_title' => '極上ペアリング・セット',
        'pairings_subtitle' => 'スイーツとドリンクの極上のマリアージュ。今だけのお得なペアリング割引でお楽しみいただけます。',
        'pairings_combo_label' => 'おすすめセット',
        'pairings_regular_total' => '通常合計',
        'pairings_combo_price' => 'セット特別価格',
        'pairings_save_badge' => '%s お得',
        'pairings_view_pair' => 'ペアリングを見る',
        'seasonal_campaign_tag' => '季節限定コレクション',
        'seasonal_campaign_title' => '期間限定商品',
        'seasonal_campaign_subtitle' => '旬の果実や素材を贅沢に使用した、今だけの特別なコレクションをお届けします。',
        'brand_story_tag' => 'パティシエのこだわり',
        'brand_story_title' => '銀座から届ける、日本のパティスリー美学',
        'brand_story_p1' => '東京・銀座にアトリエを構えるSweet Choiceは、伝統的なフランス菓子技法と、日本の豊かな四季の恵みを融合させたスイーツ専門店です。',
        'brand_story_p2' => '北海道産純生クリームの繊細な口どけ、京都宇治の極上抹茶。甘さを控えめに仕上げることで、素材本来の香りと深い余韻を際立たせています。',
        'brand_pillar_1_title' => '北海道産 純生クリーム',
        'brand_pillar_1_desc' => 'すっきりと軽やかで、口に入れた瞬間に広がる豊かなコクとミルクの余韻。',
        'brand_pillar_2_title' => '京都宇治 宇治抹茶',
        'brand_pillar_2_desc' => '石臼挽きの伝統製法で作られた一番茶のみを使用し、豊かな香りを引き出します。',
        'brand_pillar_3_title' => '上品な甘さの調和',
        'brand_pillar_3_desc' => '甘さを抑え、素材が持つ繊細な香りと食感を大切にした味わいをお届けします。',
        'categories_title' => 'スイーツカテゴリー',
        'categories_subtitle' => 'こだわりのスイーツコレクションをお楽しみください',
        'popular_tag' => '厳選人気商品',
        'popular_title' => '人気のデザート',
        'popular_subtitle' => '多くのお客様に愛されている当店自慢の定番スイーツ',
        'badge_popular' => '人気',
        'seasonal_title' => '季節の限定コレクション',
        'seasonal_subtitle' => '季節のイベントやお祝いを彩る限定スイーツ',
        'view_all' => 'すべて見る',
        'view_details' => '詳細',
        'add_to_cart' => 'カートに入れる',
        'out_of_stock' => '売り切れ',
        'limited_stock' => '残りわずか',
        'available' => '販売中',
        'quick_add' => '追加',

        // AI Recommendation
        'ai_section_badge' => 'ぴったりスイーツ探し',
        'ai_title' => '今日の気分にぴったりのデザートを見つけよう',
        'ai_subtitle' => '今の気分とお好みの味を選ぶだけで、あなたにぴったりのデザートが見つかります。。',
        'ai_mood_label' => '今の気分は？',
        'ai_taste_label' => 'どんなテイストをお探しですか？',
        'ai_button_find' => 'おすすめを診断する',
        'ai_result_title' => 'おすすめのスイーツ',
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

        // Dessert & Drink Pairing with Inventory Discounts
        'pairing_drinks_title' => 'このスイーツにおすすめのドリンク',
        'pairing_desserts_title' => 'このドリンクにおすすめのスイーツ',
        'pairing_drinks_subtitle' => 'スイーツのおいしさを引き立てる、当店おすすめのペアリングドリンクです。',
        'pairing_desserts_subtitle' => 'ドリンクにぴったりの、パティシエおすすめスイーツです。',
        'pairing_section_badge' => 'おすすめペアリング',
        'pairing_discount_badge' => '%d%% OFF',
        'pairing_instock_badge' => '在庫連動セール',
        'pairing_add_pairing' => '+ ペアリングを追加',
        'pairing_original_price' => '通常価格',
        'pairing_discount_applied' => 'ペアリング割引適用中',
        'pairing_bonus_notice' => '在庫連動特典: おすすめ商品に特別割引が適用されています！',
        'pairing_cart_discount_label' => 'ペアリング割引',

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
        'dine_in_option' => '店内 (Dine-in)',
        'dine_in_desc' => '銀座店サロンにて出来立てをお楽しみいただけます（サービス料・配送料無料）。',
        'dine_in_details' => '店内飲食のご案内',
        'dine_in_location_note' => '銀座本店カフェスペースにてお召し上がりいただけます（東京都中央区銀座4-2-11）。サービス料はかかりません。',
        'pickup_option' => '店頭受取 (Store Pickup)',
        'pickup_desc' => '銀座本店にて出来立てをお受け取りいただけます（サービス料・配送料無料）。',
        'delivery_option' => 'デリバリー (Local Delivery)',
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
        'payment_provider' => '決済サービス',
        'select_fulfillment_prompt' => '先にお受け取り方法を選択すると、ご利用可能なお支払い方法が表示されます。',
        'cash_dine_in_only_note' => '現金払いは「店内（Dine-in）」をご選択の場合のみご利用いただけます。',
        'online_payment_select_provider' => 'オンライン決済サービスを選択してください',
        'online_payment_demo_notice' => '【デモ決済情報】これらはデモンストレーション用のサンプル表示であり、実際の決済認証情報ではありません。実際の課金は発生しません。',
        'card_details_title' => 'クレジットカード / デビットカード情報',
        'card_security_note' => 'デモ決済：カード情報は入力チェックのみ行われ、データベースには一切保存されません。',
        'card_number' => 'カード番号',
        'card_exp' => '有効期限（MM/YY）',
        'card_cvv' => 'セキュリティコード（CVV）',
        'cardholder_name' => 'カード名義人',
        'payment_simulation_note' => '【シミュレーション】実際の決済・引き落としは発生しません。',
        'not_selected' => '未選択',
        'service_fee_delivery_only' => 'デリバリー時のみ適用',
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

        // Checkout Progress Stepper
        'step_cart' => 'カート',
        'step_auth' => 'ログイン・登録',
        'step_minigame' => 'ミニゲーム',
        'step_payment' => 'お支払い設定',
        'step_confirmation' => '注文完了',
        'step_skipped' => '対象外',

        // Auth, Registration & Account (Bilingual)
        'auth_login_subtitle' => 'お気に入り、カート、注文履歴をご利用いただけます。',
        'auth_register_subtitle' => 'アカウントを作成して、ご注文手続き・お気に入り登録・注文履歴の確認をご利用ください。',
        'auth_checkout_notice' => 'ご注文手続きを続けるには、ログインまたは新規会員登録が必要です。カート内の商品はそのまま保持されます。',
        'auth_invalid_credentials' => 'メールアドレスまたはパスワードが正しくありません。',
        'auth_admin_required' => 'アクセスが拒否されました。管理者権限が必要です。',
        'auth_required_fields' => '必須項目をすべてご入力ください。',
        'auth_invalid_email' => '有効なメールアドレスをご入力ください。',
        'auth_password_min' => 'パスワードは6文字以上で入力してください。',
        'auth_password_mismatch' => '確認用パスワードが一致しません。',
        'auth_email_exists' => 'このメールアドレスはすでに登録されています。',
        'auth_csrf_expired' => 'セッションの有効期限が切れました。ページを再読み込みしてもう一度お試しください。',
        'auth_demo_account' => 'デモ用お客様アカウント:',
        'password_confirm' => 'パスワード（確認）',
        'password_min_hint' => 'パスワード（6文字以上）',
        'optional_label' => '任意',
        'account_welcome' => 'こんにちは、%s 様',
        'account_subtitle' => 'ご登録情報、お届け先住所、ご注文履歴を管理できます。',
        'account_name_email_required' => 'お名前とメールアドレスは必須です。',
        'account_email_in_use' => 'このメールアドレスは他のアカウントで使用されています。',
        'account_current_pass_wrong' => '現在のパスワードが一致しません。',
        'account_new_pass_min' => '新しいパスワードは6文字以上で入力してください。',
        'account_pass_mismatch' => '新しいパスワードと確認用パスワードが一致しません。',
        'account_no_orders' => '過去のご注文履歴はありません。',
        'account_no_favorites' => 'お気に入りに登録された商品はまだありません。',

        // Cart & Merge Notices
        'cart_merge_success' => 'ゲストカート内の商品をアカウントに統合しました。',
        'cart_merge_adjusted' => '在庫状況に応じて一部商品の数量を調整しました。',
        'cart_remove_confirm' => 'この商品をカートから削除しますか？',
        'cart_minigame_eligible_hint' => 'ご注文がミニゲームの参加対象になりました！購入手続き中にSweet Runで2,000点を超えると200円割引を獲得できます。',
        'cart_minigame_need_more_hint' => '商品合計（カスタマイズ・配送料を除く）があと%s以上で、200円割引が狙えるミニゲームに参加できます！',
        'cart_subtotal_note' => '※ ミニゲーム参加条件（3,000円以上）は商品価格×数量の合計のみで計算されます。',

        // Mini-Game & Eligibility
        'minigame_title' => 'Sweet Run ミニゲームチャレンジ',
        'minigame_badge' => 'ご注文特典ミニゲーム',
        'minigame_qualified_msg' => 'ご注文がミニゲームの参加対象になりました！',
        'minigame_not_qualified_msg' => 'ご注文金額（商品小計）が3,000円未満のため、ミニゲームには参加できません。お支払い設定へ進みます。',
        'minigame_rules_title' => '200円割引の獲得ルール',
        'minigame_rule_target' => 'Sweet Runで2,000点を超えるスコア（2,001点以上）を達成してください。',
        'minigame_rule_reward' => '達成すると本注文に200円の割引が自動適用されます。',
        'minigame_rule_attempts' => '1回目は無料です。デモ広告を視聴すると2回目・3回目が解放されます（1注文につき最大3回・広告最大2回）。',
        'minigame_rule_cap' => '複数回達成しても、1注文あたりのミニゲーム割引は最大200円です。',
        'minigame_attempt_label' => '挑戦 %d / %d 回目',
        'minigame_attempt_free' => '無料挑戦',
        'minigame_attempt_ad' => '広告解放による挑戦',
        'minigame_attempts_used' => '使用済み挑戦回数',
        'minigame_ads_used' => 'デモ広告視聴回数',
        'minigame_best_score' => '最高スコア',
        'minigame_discount_status' => 'ミニゲーム割引',
        'minigame_discount_earned_badge' => '200円割引を獲得済み！',
        'minigame_discount_not_earned' => '未獲得',
        'minigame_congrats_discount' => 'おめでとうございます！200円の割引を獲得しました！',
        'minigame_try_again_score' => 'スコアは %s 点でした。200円割引を獲得するには2,000点を超えるスコア（2,001点以上）が必要です！',
        'minigame_exact_2000_notice' => '惜しい！ちょうど2,000点は対象外です。2,001点以上（2,000点超）を目指しましょう！',
        'minigame_all_attempts_used' => 'このご注文の挑戦回数（全3回）をすべて使用しました。',
        'minigame_already_won_continue' => '200円割引を獲得済みです！このままお支払い設定へ進むか、残りの回数をお楽しみいただけます（割引上限は200円のままです）。',
        'minigame_continue_payment' => 'お支払い設定へ進む',
        'minigame_skip_to_payment' => 'ミニゲームをスキップしてお支払いへ',
        'minigame_start_free' => '無料挑戦をスタート（1/3回目）',
        'minigame_start_unlocked' => '解放された挑戦をスタート（%d/3回目）',
        'minigame_round_in_progress' => '第%d回目の挑戦中 — がんばってください！',
        'minigame_score_rejected' => 'スコアをサーバーで検証できませんでした。ゲーム内で通常通りプレイしてください。',
        'minigame_freeplay_badge' => 'フリープレイモード（エンタメ専用）',
        'minigame_back_to_shop' => 'ショップへ戻る',
        'minigame_legacy_disabled' => '注文後のミニケーキ特典キャンペーンは終了しました。現在は商品合計3,000円以上のご注文手続き中に200円割引ミニゲームをご利用いただけます。',
        'minigame_attempt_history' => '挑戦履歴',
        'minigame_no_attempts_yet' => 'まだ挑戦していません。無料挑戦から始めましょう！',
        'minigame_won_tag' => '達成（200円割引）',
        'minigame_controls_hint' => '操作方法: 左右キー / A・D / スワイプでレーン移動、上 / Spaceでジャンプ、下でスライディング。',

        // Demo Rewarded Advertisement Mode
        'ad_watch_btn' => '広告を見てもう一度挑戦する',
        'ad_watch_prompt' => '広告を見て、もう一度挑戦しましょう！',
        'ad_demo_badge' => 'デモ広告モード（学校プロジェクト用）',
        'ad_demo_title' => 'Sweet Choice シミュレーション広告',
        'ad_demo_headline' => 'Sweet Choiceでお気に入りのスイーツを見つけよう！',
        'ad_demo_subtext' => '北海道産純生クリームと京都宇治抹茶を使用した銀座アトリエ特製スイーツ。季節限定ケーキやドリンクペアリングも好評販売中。',
        'ad_demo_disclaimer' => '※ 学校プロジェクト用デモ広告モード：これは教育目的のシミュレーション広告です。外部広告ネットワークには接続しておらず、実際の広告収益は発生しません。',
        'ad_countdown_label' => 'あと %d 秒で追加挑戦が解放されます...',
        'ad_unlocking' => 'サーバーでデモ広告の視聴完了を確認中...',
        'ad_unlocked_success' => 'デモ広告の視聴が完了しました！追加の挑戦回数が1回解放されました。',
        'ad_limit_reached' => '1注文あたりの広告視聴上限（2回）に達しました。',
        'ad_too_early' => '追加挑戦を受け取るには、5秒間のデモ広告を最後までご覧ください。',
        'ad_duplicate_error' => 'この広告報酬はすでに処理済みです。',
        'ad_cancel_btn' => 'キャンセル',
        'ad_finish_before_ad' => '広告を見る前に、現在の挑戦を完了してください。',

        // Payment Settings, Confirmation & Order History
        'checkout_payment_title' => 'お支払い・お受け取り設定',
        'checkout_eligible_subtotal' => '対象商品小計',
        'checkout_minigame_discount' => 'ミニゲーム割引',
        'checkout_other_promotions' => 'ペアリング割引',
        'checkout_final_amount' => '最終お支払い金額',
        'checkout_pickup_location' => 'お受け取り店舗:',
        'checkout_notes_placeholder' => 'アレルギー、バースデーキャンドル、配達時のご要望など...',
        'checkout_err_contact' => 'お名前、メールアドレス、電話番号をご入力ください。',
        'checkout_err_pickup_slot' => '店舗受取の日付と時間帯を選択してください。',
        'checkout_err_delivery_addr' => '配達先の郵便番号と住所をご入力ください。',
        'checkout_err_delivery_slot' => '配達の日付と時間帯を選択してください。',
        'checkout_err_duplicate_submit' => 'このご注文はすでに送信済みです。注文履歴をご確認ください。',
        'checkout_err_stock' => 'カート内の一部商品が売り切れまたは在庫不足です。カートをご確認ください。',
        'checkout_err_unexpected' => 'ご注文の処理中にエラーが発生しました。もう一度お試しください。',
        'checkout_discount_voided' => '商品合計が3,000円未満になったため、ミニゲーム割引は解除されました。',
        'checkout_back_to_game' => 'ミニゲームに戻る',
        'checkout_back_to_cart' => 'カートに戻る',
        'order_date_label' => 'ご注文日時',
        'order_payment_status_paid' => 'お支払い済み（シミュレーション）',
        'order_payment_status_pending' => '未払い（お受け取り時にお支払い）',
        'order_boutique_address_label' => '店舗住所',
        'order_guest_lookup_title' => 'ゲスト注文検索',
        'order_guest_lookup_desc' => '以前アカウント未登録でご注文された方は、注文番号とメールアドレスを入力してご注文内容を確認できます。',
        'order_guest_lookup_btn' => '注文を検索する',
        'order_guest_lookup_not_found' => '入力された注文番号とメールアドレスに一致するご注文が見つかりませんでした。',
        'order_guest_lookup_found' => 'ご注文が見つかりました:',
        'order_guest_lookup_login_prompt' => 'ログインしてすべての注文履歴を見る',
        'order_view_complete_receipt' => 'ご注文明細を見る',
        'order_access_denied' => 'このご注文明細を閲覧するにはログインが必要です。',
        'order_minigame_discount_badge' => 'Sweet Run -¥200',
        'order_no_orders_desc' => 'パティシエが心を込めて美味しいスイーツをご用意してお待ちしております。',
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
    if (isset($translations['ja'][$key])) {
        return $translations['ja'][$key];
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
    $altField = ($lang === 'ja') ? $baseField . '_en' : $baseField . '_ja';
    if (!empty($item[$altField])) {
        return $item[$altField];
    }
    if (isset($item[$baseField])) {
        return (string)$item[$baseField];
    }
    return '';
}
