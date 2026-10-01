-- Sweet Choice Seed Data
-- Sample Accounts, Categories, Products, Customizations, Banners, Slots, Settings

-- 1. Users (Admin and Customer)
-- Admin: admin@sweetchoice.jp / c
-- Customer: customer@sweetchoice.jp / customer123
INSERT INTO users (id, name, email, password, phone, postal_code, address, apartment, role, created_at) VALUES
(1, 'Sweet Choice Admin', 'admin@sweetchoice.jp', '$2y$10$54Ofl6vfvMY1hw.3QoY.U./c9ckyx/UTFvEDe54LoeGa9pIuEOnQm', '03-5555-0199', '104-0061', 'Ginza 4-2-11, Chuo-ku, Tokyo', 'Suite 501', 'admin', NOW()),
(2, 'Hana Tanaka', 'customer@sweetchoice.jp', '$2y$10$84b1ztrrttrrwxxBzUq6gufLIgjdN7lDirGiBUUVO3vQDWWpu8f8m', '090-1234-5678', '150-0001', 'Jingumae 5-10-1, Shibuya-ku, Tokyo', 'Apt 203', 'customer', NOW());

-- 2. Categories
INSERT INTO categories (id, name_en, name_ja, slug, description_en, description_ja, image, sort_order, is_active) VALUES
(1, 'Cakes', 'ケーキ', 'cakes', 'Artisanal Japanese sponge cakes, chiffon, and decadent mousses crafted with Hokkaido fresh cream.', '北海道産フレッシュ生クリームを贅沢に使った極上スポンジケーキ＆ムース。', 'cat_cakes.jpg', 1, 1),
(2, 'Puddings', 'プリン', 'puddings', 'Silky Japanese custard puddings and parfaits topped with bittersweet caramel and seasonal fruits.', 'ほろ苦いカラメルと濃厚な卵のコクが溶け合う至高のなめらかプリン。', 'cat_puddings.jpg', 2, 1),
(3, 'Cookies', 'クッキー', 'cookies', 'Crispy, melt-in-your-mouth sable cookies and artisanal tea-infused biscuits.', '発酵バターの芳醇な香りとサクほろ食感が楽しめる極上クッキー。', 'cat_cookies.jpg', 3, 1),
(4, 'Pastries', 'ペストリー', 'pastries', 'Flaky fruit tarts, golden cream puffs, and delicate Japanese-French pastries.', '旬のフルーツタルトやサクサク香ばしいシュークリームなどの焼菓子。', 'cat_pastries.jpg', 4, 1),
(5, 'Drinks', 'ドリンク', 'drinks', 'Authentic Kyoto Uji matcha lattes, artisanal cold brew coffee, and herbal fruit teas.', '京都宇治産厳選抹茶のラテや自家焙煎水出しコーヒー、果実ハーブティー。', 'cat_drinks.jpg', 5, 1);

-- 3. Products
-- Currency: JPY exclusively
INSERT INTO products (id, category_id, name_en, name_ja, slug, description_en, description_ja, price, image, stock, availability, is_popular, tags) VALUES
(1, 1, 'Strawberry Shortcake', '極上いちごのショートケーキ', 'strawberry-shortcake', 
 'Our signature dessert made with feather-light sponge, fresh Japanese strawberries, and pure Hokkaido whipped cream.',
 'ふわふわのスポンジ生地に、厳選した国産イチゴと北海道産フレッシュ生クリームをたっぷりサンドした王道ショートケーキ。',
 680, 'prod_strawberry_shortcake.jpg', 25, 'available', 1, 'fruit,creamy,sweet,light,happy,romantic,celebration'),

(2, 1, 'Rich Belgian Chocolate Cake', '濃厚ベルギーチョコレートケーキ', 'rich-belgian-chocolate-cake',
 'Decadent multi-layered cake crafted with 70% dark Belgian cocoa and velvety chocolate ganache.',
 'ベルギー産カカオ70%ダークチョコレートと生チョコガナッシュが重なる、濃厚でビターな贅沢ショコラケーキ。',
 720, 'prod_chocolate_cake.jpg', 20, 'available', 1, 'chocolate,creamy,sweet,comfort,stressed,sad,tired,romantic'),

(3, 1, 'Kyoto Uji Matcha Mille Crepe', '宇治抹茶ミルクレープ', 'kyoto-uji-matcha-mille-crepe',
 'Twenty layers of paper-thin matcha crepes stacked with premium Uji matcha diplomat cream and sweet red bean dust.',
 '京都宇治の最高級抹茶を惜しみなく使用。極薄のクレープ生地と抹茶ディプロマットクリームが織りなす繊細な20層。',
 700, 'prod_matcha_mille_crepe.jpg', 15, 'available', 1, 'creamy,light,cake,relaxed,happy,tired'),

(4, 1, 'Japanese Soufflé Cheesecake', 'ふわとろスフレチーズケーキ', 'japanese-souffle-cheesecake',
 'Melt-in-your-mouth cloud texture made with premium French cream cheese and a subtle touch of yuzu lemon zest.',
 '口に入れた瞬間しゅわっと溶ける極上の軽やかさ。厳選クリームチーズとほのかな高知県産柚子のアクセント。',
 650, 'prod_souffle_cheesecake.jpg', 12, 'limited', 1, 'creamy,light,cake,relaxed,happy,comfort'),

(5, 2, 'Artisan Custard Purin', '極上カスタードプリン', 'artisan-custard-purin',
 'Traditional Japanese custard pudding with farm-fresh organic eggs, Madagascar vanilla bean, and deep amber caramel.',
 '濃密な平飼い卵とマダガスカル産天然バニラビーンズを贅沢に使用。昔ながらのほろ苦いカラメルソースがアクセント。',
 480, 'prod_custard_purin.jpg', 30, 'available', 1, 'pudding,creamy,sweet,comfort,stressed,tired,relaxed'),

(6, 2, 'Strawberry Parfait Pudding', 'ストロベリーパフェプリン', 'strawberry-parfait-pudding',
 'Silky milk pudding layered with fresh strawberry compote, crunchy pistachio crumbles, and vanilla whipped cream.',
 'なめらかミルクプリンに甘酸っぱい自家製いちごコンポートと香ばしいピスタチオを重ねた贅沢パフェ仕立て。',
 580, 'prod_strawberry_pudding.jpg', 18, 'available', 1, 'fruit,pudding,creamy,sweet,happy,celebration,romantic'),

(7, 3, 'Kyoto Matcha Butter Cookies', '宇治抹茶サブレクッキー', 'kyoto-matcha-butter-cookies',
 'Crispy artisanal butter cookies richly blended with ceremonial matcha from Kyoto and subtle coarse cane sugar.',
 '宇治抹茶の奥深い苦味と発酵バターの甘美な香りが広がる、サクほろ食感のプレミアムサブレ。',
 550, 'prod_matcha_cookies.jpg', 40, 'available', 0, 'cookie,light,relaxed,energy'),

(8, 3, 'Triple Chocolate Fudge Cookies', 'トリプルショコラクッキー', 'triple-chocolate-fudge-cookies',
 'Soft-baked cookies stuffed with dark chocolate chunks, milk chocolate drizzle, and sea salt flakes.',
 '外はサクッ、中はしっとり。3種類の厳選チョコレートとフランス産ゲランド塩が引き立てるリッチな味わい。',
 520, 'prod_chocolate_cookies.jpg', 35, 'available', 1, 'chocolate,cookie,sweet,comfort,stressed,tired,energy'),

(9, 4, 'Seasonal Orchard Fruit Tart', '旬の果実とピスタチオのタルト', 'seasonal-orchard-fruit-tart',
 'Crispy almond crust filled with rich pistachio frangipane cream, crowned with seasonal berries, melon, and figs.',
 '香ばしいアーモンドタルト生地にピスタチオクリームを詰め、彩り豊かな旬のフルーツを贅沢に飾り付けました。',
 850, 'prod_fruit_tart.jpg', 10, 'limited', 1, 'fruit,creamy,sweet,light,happy,celebration,romantic,energy'),

(10, 4, 'Chou à la Crème (Vanilla Cream Puff)', '芳醇バニラシュークリーム', 'vanilla-cream-puff',
 'Crunchy almond-craquelin pastry shell overflowing with rich Madagascar vanilla custard and dairy cream.',
 '香ばしいアーモンドクッキーシューの中に、北海道産生クリームとバニラカスタードを隙間なく詰め込みました。',
 420, 'prod_cream_puff.jpg', 0, 'sold_out', 1, 'creamy,sweet,comfort,happy,stressed'),

(11, 5, 'Kyoto Uji Iced Matcha Latte', '宇治プレミアムアイス抹茶ラテ', 'kyoto-uji-iced-matcha-latte',
 'Freshly whisked ceremonial grade Uji matcha poured over chilled organic Hokkaido milk and light cane syrup.',
 '一杯ずつ丁寧に点てた極上宇治抹茶と、コク深い北海道産生乳の美しいグラデーションラテ。',
 580, 'prod_matcha_latte.jpg', 50, 'available', 1, 'creamy,light,relaxed,energy,happy'),

(12, 5, 'Cold Brew Specialty Coffee', '水出しスペシャリティアイスコーヒー', 'cold-brew-specialty-coffee',
 'Steeped for 16 hours in cold filtered water. Smooth body, notes of dark chocolate and roasted hazelnut.',
 '16時間かけてじっくり抽出。澄んだ透明感とダークチョコレートのようなビターなコクが広がる水出し珈琲。',
 450, 'prod_cold_brew.jpg', 50, 'available', 0, 'chocolate,light,energy,tired,stressed');

-- 4. Product Ingredients
INSERT INTO product_ingredients (product_id, ingredient_en, ingredient_ja) VALUES
(1, 'Hokkaido Fresh Cream', '北海道産生クリーム'),
(1, 'Japanese Strawberries', '国産フレッシュいちご'),
(1, 'Organic Wheat Flour', '国産小麦粉'),
(1, 'Free-Range Eggs', '平飼い卵'),
(1, 'Pure Beet Sugar', '甜菜糖'),
(1, 'Madagascar Vanilla', 'マダガスカル産バニラ'),

(2, 'Belgian 70% Dark Chocolate', 'ベルギー産70%ダークチョコレート'),
(2, 'French Cocoa Butter', 'フランス産ココアバター'),
(2, 'Hokkaido Whipping Cream', '北海道産生クリーム'),
(2, 'Organic Egg Yolks', '卵黄'),
(2, 'Brown Cane Sugar', 'ブラウンきび糖'),

(3, 'Kyoto Uji Matcha Powder', '京都宇治産最高級抹茶'),
(3, 'French Butter', 'フランス産発酵バター'),
(3, 'Organic Milk', '有機牛乳'),
(3, 'Wheat Flour', '小麦粉'),
(3, 'Pastry Cream', 'カスタードクリーム'),

(4, 'French Cream Cheese', 'フランス産クリームチーズ'),
(4, 'Fresh Egg Whites', '新鮮卵白'),
(4, 'Kochi Yuzu Zest', '高知県産柚子ピール'),
(4, 'Fresh Milk', '新鮮生乳'),

(5, 'Pasture-Raised Egg Yolks', '平飼い卵'),
(5, 'Hokkaido Jersey Milk', '北海道ジャージー牛乳'),
(5, 'Cane Sugar Caramel', 'きび糖カラメル'),
(5, 'Natural Vanilla Bean', '天然バニラビーンズ'),

(6, 'Japanese Strawberries', '国産いちご'),
(6, 'Jersey Milk Pudding', 'ジャージー牛乳プリン'),
(6, 'Bronte Pistachio', 'ブロンテ産ピスタチオ'),
(6, 'Pure Cream', '生クリーム'),

(7, 'Uji Ceremonial Matcha', '宇治抹茶'),
(7, 'Fermented Butter', '発酵バター'),
(7, 'Wheat Flour', '小麦粉'),
(7, 'Sea Salt', '海塩'),

(8, 'Dark Chocolate Chunks', 'ダークチョコチャンク'),
(8, 'Milk Chocolate', 'ミルクチョコ'),
(8, 'Guérande Sea Salt', 'ゲランドの塩'),
(8, 'Cocoa Powder', 'ココアパウダー'),

(9, 'Seasonal Strawberries & Figs', '季節のイチゴ＆無花果'),
(9, 'Almond Paste', 'アーモンドプードル'),
(9, 'Pistachio Frangipane', 'ピスタチオフランジパーヌ'),
(9, 'Tart Pastry Shell', 'タルト生地'),

(10, 'Choux Pastry', 'シュー生地'),
(10, 'Vanilla Custard', 'バニラカスタード'),
(10, 'Hokkaido Whipped Cream', '北海道産ホイップ'),
(10, 'Crushed Almonds', 'クラッシュアーモンド'),

(11, 'Ceremonial Grade Matcha', '最高級宇治抹茶'),
(11, 'Organic Whole Milk', '有機生乳'),
(11, 'Natural Sugar Cane Syrup', 'サトウキビシロップ'),

(12, 'Specialty Arabica Coffee Beans', '厳選アラビカ種コーヒー豆'),
(12, 'Pure Mineral Water', '天然ミネラル水');

-- 5. Product Allergies
INSERT INTO product_allergies (product_id, allergy_en, allergy_ja) VALUES
(1, 'Milk', '乳'),
(1, 'Eggs', '卵'),
(1, 'Wheat', '小麦'),
(2, 'Milk', '乳'),
(2, 'Eggs', '卵'),
(2, 'Wheat', '小麦'),
(2, 'Soy', '大豆'),
(3, 'Milk', '乳'),
(3, 'Eggs', '卵'),
(3, 'Wheat', '小麦'),
(4, 'Milk', '乳'),
(4, 'Eggs', '卵'),
(4, 'Wheat', '小麦'),
(5, 'Milk', '乳'),
(5, 'Eggs', '卵'),
(6, 'Milk', '乳'),
(6, 'Eggs', '卵'),
(6, 'Nuts (Pistachio)', 'ナッツ（ピスタチオ）'),
(7, 'Milk', '乳'),
(7, 'Wheat', '小麦'),
(8, 'Milk', '乳'),
(8, 'Eggs', '卵'),
(8, 'Wheat', '小麦'),
(8, 'Soy', '大豆'),
(9, 'Milk', '乳'),
(9, 'Eggs', '卵'),
(9, 'Wheat', '小麦'),
(9, 'Nuts (Almond, Pistachio)', 'ナッツ（アーモンド、ピスタチオ）'),
(10, 'Milk', '乳'),
(10, 'Eggs', '卵'),
(10, 'Wheat', '小麦'),
(10, 'Nuts (Almond)', 'ナッツ（アーモンド）'),
(11, 'Milk', '乳'),
(12, 'None', 'なし');

-- 6. Product Customizations (with additional JPY prices)
INSERT INTO product_customizations (product_id, group_name_en, group_name_ja, option_name_en, option_name_ja, price_extra, is_default) VALUES
-- Cake 1 (Strawberry Shortcake)
(1, 'Size', 'サイズ', 'Petite (4-inch / 1-2 people)', 'プチ (4号 / 1〜2人分)', 0, 1),
(1, 'Size', 'サイズ', 'Classic Medium (6-inch / 4-6 people)', 'ミディアム (6号 / 4〜6人分)', 800, 0),
(1, 'Size', 'サイズ', 'Grand Party (8-inch / 8-10 people)', 'グランデ (8号 / 8〜10人分)', 1800, 0),
(1, 'Flavor', 'フレーバー', 'Original Vanilla Cream', 'オリジナル バニラクリーム', 0, 1),
(1, 'Flavor', 'フレーバー', 'Chocolate Cream Sponge', 'ショコラクリーム＆ココアスポンジ', 150, 0),
(1, 'Decoration', 'デコレーション', 'Standard Strawberry Blossom', 'スタンダード飾り', 0, 1),
(1, 'Decoration', 'デコレーション', 'Extra Fresh Berries (+50% Strawberries)', '厳選イチゴ増量（+50%）', 300, 0),
(1, 'Decoration', 'デコレーション', 'Gold Leaf & Macaron Garland', '金箔＆マカロンデコレーション', 450, 0),
(1, 'Message Plaque', 'メッセージプレート', 'None', 'なし', 0, 1),
(1, 'Message Plaque', 'メッセージプレート', 'White Chocolate "Happy Birthday"', 'ホワイトチョコプレート「Happy Birthday」', 150, 0),
(1, 'Message Plaque', 'メッセージプレート', 'White Chocolate Custom Message', '特製チョコメッセージプレート', 200, 0),

-- Cake 2 (Chocolate Cake)
(2, 'Size', 'サイズ', 'Single Slice (Regular)', '1カット（レギュラー）', 0, 1),
(2, 'Size', 'サイズ', 'Whole Cake (6-inch / 4-6 people)', 'ホールケーキ (6号 / 4〜6人分)', 1200, 0),
(2, 'Toppings', 'トッピング', 'Classic Cocoa Powder', 'クラシックココア', 0, 1),
(2, 'Toppings', 'トッピング', 'Extra Raspberry Coulis', '特製ラズベリーソース添え', 120, 0),
(2, 'Toppings', 'トッピング', 'Roasted Hazelnuts & Gold Dust', '香ばしローストヘーゼルナッツ＆金粉', 250, 0),

-- Drink 11 (Matcha Latte)
(11, 'Sweetness Level', '甘さの調節', 'Standard Sweetness (100%)', 'おすすめ（通常）', 0, 1),
(11, 'Sweetness Level', '甘さの調節', 'Less Sweet (50%)', '甘さひかえめ (50%)', 0, 0),
(11, 'Sweetness Level', '甘さの調節', 'Unsweetened (0%)', '無糖 (0%)', 0, 0),
(11, 'Milk Choice', 'ミルクの選択', 'Hokkaido Whole Milk', '北海道産生乳', 0, 1),
(11, 'Milk Choice', 'ミルクの選択', 'Barista Oat Milk', '有機オーツミルク', 80, 0),
(11, 'Milk Choice', 'ミルクの選択', 'Almond Milk', '香ばしアーモンドミルク', 80, 0),
(11, 'Extra Topping', 'トッピング', 'None', 'なし', 0, 1),
(11, 'Extra Topping', 'トッピング', 'Matcha Gelato Float', '濃厚抹茶ジェラートのせ', 200, 0),
(11, 'Extra Topping', 'トッピング', 'Whipped Cream & Kuromitsu', 'ホイップ＆沖縄黒蜜がけ', 120, 0),

-- Drink 12 (Cold Brew Coffee)
(12, 'Serving Style', '提供スタイル', 'Iced with Glass Cup', 'アイス（氷入り）', 0, 1),
(12, 'Serving Style', '提供スタイル', 'Chilled (No Ice)', '冷（氷なし・濃厚）', 0, 0),
(12, 'Sweetness & Milk', '甘さとミルク', 'Black Coffee', 'ブラック（無糖）', 0, 1),
(12, 'Sweetness & Milk', '甘さとミルク', 'Fresh Dairy Cream & Gum Syrup', 'ポーション生クリーム＆ガムシロップ', 50, 0);

-- 7. Banners
INSERT INTO banners (id, title_en, title_ja, subtitle_en, subtitle_ja, button_text_en, button_text_ja, link, image, sort_order, is_active) VALUES
(1, 'Exquisite Japanese Confections', '日本の四季を彩る至高のスイーツ', 
 'Crafted with premium Hokkaido dairy, Kyoto ceremonial matcha, and farm-fresh strawberries.',
 '北海道産純生クリームと京都宇治抹茶、厳選フルーツで仕立てた特別なひととき。',
 'Explore Cakes', 'ケーキを見る', 'category.php?category=1', 'banner1.jpg', 1, 1),

(2, 'Let AI Find Your Craving', 'AIが今の気分にぴったりのデザートをご提案',
 'Tell us your mood and taste preferences for an instant personalized recommendation.',
 '今日の気分や好みのテイストを選ぶだけで、あなたにぴったりの一品をセレクト。',
 'Try AI Recommendation', 'AIおすすめ診断を試す', '#ai-recommendation-section', 'banner2.jpg', 2, 1),

(3, 'Spring Strawberry Festival', '春の苺コレクション開催中',
 'Delight in seasonal Shortcakes, Tartes, and Parfait Puddings made with sweet Tochigi berries.',
 '旬の国産いちごを惜しみなく使用した限定スイーツをお届けいたします。',
 'View Limited Items', '季節限定スイーツ', 'category.php?search=strawberry', 'banner3.jpg', 3, 1);

-- 8. Delivery Settings (Distance Tiers in JPY)
INSERT INTO delivery_settings (id, tier_label_en, tier_label_ja, min_km, max_km, fee, is_active) VALUES
(1, 'Local Delivery (0 - 3 km)', '近隣エリア（0〜3km）', 0.0, 3.0, 300, 1),
(2, 'Standard Delivery (3 - 5 km)', '標準エリア（3〜5km）', 3.0, 5.0, 500, 1),
(3, 'Extended Delivery (5 - 10 km)', '遠方エリア（5〜10km）', 5.0, 10.0, 800, 1);

-- 9. Pickup Slots
INSERT INTO pickup_slots (id, slot_time, max_orders, is_active) VALUES
(1, '10:00 - 11:00', 10, 1),
(2, '11:00 - 12:00', 10, 1),
(3, '12:00 - 13:00', 10, 1),
(4, '13:00 - 14:00', 10, 1),
(5, '14:00 - 15:00', 10, 1),
(6, '15:00 - 16:00', 10, 1),
(7, '16:00 - 17:00', 10, 1),
(8, '17:00 - 18:00', 10, 1),
(9, '18:00 - 19:00', 10, 1),
(10, '19:00 - 20:00', 10, 1);

-- 10. Delivery Slots
INSERT INTO delivery_slots (id, slot_time, max_orders, is_active) VALUES
(1, '11:00 - 13:00', 8, 1),
(2, '13:00 - 15:00', 8, 1),
(3, '15:00 - 17:00', 8, 1),
(4, '17:00 - 19:00', 8, 1),
(5, '19:00 - 20:30', 8, 1);

-- 11. Business Hours
INSERT INTO business_hours (id, day_of_week, day_order, opening_time, closing_time, is_closed) VALUES
(1, 'Monday', 1, '10:00:00', '20:00:00', 0),
(2, 'Tuesday', 2, '10:00:00', '20:00:00', 0),
(3, 'Wednesday', 3, '10:00:00', '20:00:00', 0),
(4, 'Thursday', 4, '10:00:00', '20:00:00', 0),
(5, 'Friday', 5, '10:00:00', '20:00:00', 0),
(6, 'Saturday', 6, '10:00:00', '20:00:00', 0),
(7, 'Sunday', 7, '10:00:00', '20:00:00', 0);

-- 12. Payment Methods
INSERT INTO payment_methods (id, code, name_en, name_ja, description_en, description_ja, is_active) VALUES
(1, 'credit_card', 'Credit / Debit Card', 'クレジットカード / デビットカード', 'Visa, Mastercard, JCB, American Express', 'Visa, Mastercard, JCB, Amex等対応', 1),
(2, 'online_pay', 'Online Payment (PayPay / Line Pay)', 'オンライン決済（PayPay / LINE Pay）', 'Instant QR code or app checkout', 'QRコード・アプリでスピーディーにお支払い', 1),
(3, 'cash_on_fulfillment', 'Cash on Pickup / Delivery', '店頭払い / 代金引換', 'Pay with cash upon receiving your sweets', '商品お受け取り時に現金でお支払い', 1);

-- 13. Shop Settings
INSERT INTO shop_settings (setting_key, setting_value, description) VALUES
('service_fee', '150', 'Base order handling & packaging fee in JPY'),
('shop_name', 'Sweet Choice', 'Store brand name'),
('shop_phone', '+81 3-5555-0199', 'Customer service phone number'),
('shop_email', 'contact@sweetchoice.jp', 'Customer service email address'),
('shop_address', 'Ginza 4-2-11, Chuo-ku, Tokyo 104-0061, Japan', 'Store physical address in Tokyo'),
('business_hours_display', 'Mon - Sun: 10:00 AM - 8:00 PM', 'Public display of store operating hours');

-- 14. Sample Completed Order (for testing user history & product reviews)
INSERT INTO orders (id, order_number, user_id, fulfillment_type, pickup_date, pickup_time, customer_name, customer_email, customer_phone, postal_code, address, apartment, subtotal, customization_total, delivery_fee, service_fee, grand_total, payment_method, payment_status, status, notes, created_at) VALUES
(1, 'SWC-2026-1001', 2, 'pickup', CURDATE(), '14:00 - 15:00', 'Hana Tanaka', 'customer@sweetchoice.jp', '090-1234-5678', '150-0001', 'Jingumae 5-10-1, Shibuya-ku, Tokyo', 'Apt 203', 1400, 300, 0, 150, 1850, 'credit_card', 'paid', 'Completed', 'Please include birthday candles!', DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO order_items (id, order_id, product_id, product_name, price, quantity, customization_summary, customization_price, subtotal) VALUES
(1, 1, 1, 'Strawberry Shortcake', 680, 2, 'Size: Petite, Decoration: Extra Fresh Berries (+¥300)', 300, 1700);

INSERT INTO order_customizations (id, order_item_id, group_name, option_name, price_extra) VALUES
(1, 1, 'Decoration', 'Extra Fresh Berries (+50% Strawberries)', 300);

-- 15. Sample Reviews
INSERT INTO reviews (id, product_id, user_id, order_id, rating, comment, is_approved, created_at) VALUES
(1, 1, 2, 1, 5, 'The sponge is unbelievably soft and the strawberries were so sweet! Best shortcake in Tokyo.', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 2, 2, 1, 5, 'Incredibly rich chocolate ganache. Perfect comfort dessert after a busy workday.', 1, DATE_SUB(NOW(), INTERVAL 1 DAY));

-- 16. Sample Favorites
INSERT INTO favorites (user_id, product_id, created_at) VALUES
(2, 1, NOW()),
(2, 3, NOW());
