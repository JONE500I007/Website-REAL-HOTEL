-- =====================================================================
-- JustHottel — migration 2026-08-17
--
-- Run this ONCE against the live database, e.g.
--     docker compose exec db2 mysql -u root -p"<password>" "<db_name>" < migrations/2026-08-17_province_amenities_demo.sql
-- or paste it into the mysql prompt / phpMyAdmin SQL tab.
--
-- STEP 1 is not re-runnable: a "Duplicate column name" / "Duplicate key
-- name" error there just means it was already applied — safe to ignore and
-- continue. STEP 2 and STEP 3 are re-runnable as often as you like.
-- =====================================================================


-- ---------------------------------------------------------------------
-- STEP 1 — schema
-- ---------------------------------------------------------------------

-- Province is stored separately from the free-text `location` so hotel.php
-- can offer an exact-match dropdown filter instead of guessing at substrings.
ALTER TABLE `hotels`
  ADD COLUMN `province` VARCHAR(100) NOT NULL DEFAULT '' AFTER `location`;

-- Lets STEP 2 use INSERT IGNORE, so re-running never duplicates a tag.
ALTER TABLE `amenities`
  ADD UNIQUE KEY `idx_amenity_title` (`title`);


-- ---------------------------------------------------------------------
-- STEP 2 — amenity tags (สิ่งอำนวยความสะดวกเด่น)
--
-- Every `icon` below is a Material Symbols Outlined name, the icon font the
-- site already loads in every page header — no extra icon library needed.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `amenities` (`title`, `icon`, `display_order`) VALUES
-- อาหารและเครื่องดื่ม
('ร้านอาหารในโรงแรม',        'restaurant',             7),
('บาร์ / เลานจ์',            'local_bar',              8),
('คาเฟ่ / ร้านกาแฟ',         'local_cafe',             9),
('อาหารเช้าแบบบุฟเฟต์',      'brunch_dining',         10),
('รูมเซอร์วิส 24 ชม.',       'room_service',          11),
('มุมปิ้งย่าง / บาร์บีคิว',   'outdoor_grill',         12),
('เครื่องชงกาแฟในห้อง',      'coffee_maker',          13),
('ไมโครเวฟ',                'microwave',             14),
('ครัวในห้องพัก',            'kitchen',               15),

-- สันทนาการ
('สปา & นวดแผนไทย',          'spa',                   16),
('อ่างจากุซซี่',             'hot_tub',               17),
('ห้องโยคะ / คลาสออกกำลังกาย', 'self_improvement',    18),
('สนามเทนนิส',               'sports_tennis',         19),
('สนามกอล์ฟ',                'golf_course',           20),
('ดำน้ำ / กิจกรรมทางน้ำ',     'scuba_diving',          21),
('เดินป่า / เทรกกิ้ง',        'hiking',                22),
('มุมอ่านหนังสือ',            'library_books',         23),

-- ทำเลและวิว
('ติดชายหาด',                'beach_access',          24),
('วิวภูเขา / ธรรมชาติ',       'landscape',             25),
('สวนหย่อม',                 'yard',                  26),
('ใกล้รถไฟฟ้า / ขนส่งสาธารณะ', 'train',                27),

-- ในห้องพัก
('ทีวีจอแบน',                'tv',                    28),
('ระเบียงส่วนตัว',            'balcony',               29),
('โต๊ะทำงาน',                'desk',                  30),
('อ่างอาบน้ำ',               'bathtub',               31),
('เครื่องทำน้ำอุ่น',          'shower',                32),
('เตารีด',                   'iron',                  33),
('ตู้เสื้อผ้า / ตู้นิรภัย',    'checkroom',             34),
('ห้องปลอดบุหรี่',            'smoke_free',            35),

-- บริการ
('พนักงานต้อนรับ 24 ชม.',     'support_agent',         36),
('บริการซักรีด',              'local_laundry_service', 37),
('เครื่องซักผ้าหยอดเหรียญ',   'wash',                  38),
('ทำความสะอาดห้องรายวัน',     'cleaning_services',     39),
('รับฝากสัมภาระ',             'luggage',               40),
('รถรับส่งสนามบิน',           'airport_shuttle',       41),
('บริการเช่ารถ / มอเตอร์ไซค์', 'two_wheeler',          42),
('จักรยานให้เช่า',            'pedal_bike',            43),
('บริการปริ้นเอกสาร',         'print',                 44),
('แลกเปลี่ยนเงินตรา',         'currency_exchange',     45),
('รับบัตรเครดิต',             'credit_card',           46),
('ร้านสะดวกซื้อ',             'store',                 47),

-- สิ่งอำนวยความสะดวกส่วนกลาง
('ห้องประชุม / สัมมนา',       'meeting_room',          48),
('ลิฟต์',                    'elevator',              49),
('รปภ. 24 ชม.',              'security',              50),
('ที่ชาร์จรถไฟฟ้า (EV)',      'ev_station',            51),
('สิ่งอำนวยความสะดวกผู้พิการ', 'accessible',           52),

-- ครอบครัวและสัตว์เลี้ยง
('เหมาะสำหรับเด็ก',           'child_friendly',        53),
('เตียงเด็กเสริม',            'crib',                  54),
('อนุญาตให้นำสัตว์เลี้ยง',     'pets',                  55);


-- ---------------------------------------------------------------------
-- STEP 3 — 4 demo hotels owned by TheOwner2@gmail.com
--
-- Image files are committed under php/src/uploads/{hotels,rooms}/, so a
-- `git pull` on the server puts them in place before this runs.
-- ---------------------------------------------------------------------

SET @owner_id = (SELECT `id` FROM `users` WHERE `email` = 'TheOwner2@gmail.com' LIMIT 1);

-- Clear any previous run of this seed so the whole STEP 3 block is
-- re-runnable. room_types / room_images / hotel_amenities cascade from the
-- hotels delete; hotel_images and hotel_category_items have no FK, so they
-- are cleaned up by hand first.
DELETE hci FROM `hotel_category_items` hci
  JOIN `hotels` h ON h.id = hci.hotel_id
  WHERE h.hotel_name IN ('เดอะ ริเวอร์ไซด์ แกรนด์ โฮเทล', 'ภูวิว บูทีค รีสอร์ท',
                         'ดิ เออร์เบิน สวีท กรุงเทพ', 'ทริปเปิล ทรีส์ เรสซิเดนซ์');
DELETE hi FROM `hotel_images` hi
  JOIN `hotels` h ON h.id = hi.hotel_id
  WHERE h.hotel_name IN ('เดอะ ริเวอร์ไซด์ แกรนด์ โฮเทล', 'ภูวิว บูทีค รีสอร์ท',
                         'ดิ เออร์เบิน สวีท กรุงเทพ', 'ทริปเปิล ทรีส์ เรสซิเดนซ์');
DELETE FROM `hotels`
  WHERE `hotel_name` IN ('เดอะ ริเวอร์ไซด์ แกรนด์ โฮเทล', 'ภูวิว บูทีค รีสอร์ท',
                         'ดิ เออร์เบิน สวีท กรุงเทพ', 'ทริปเปิล ทรีส์ เรสซิเดนซ์');


-- ---- Hotel 1: เชียงใหม่ ------------------------------------------------
INSERT INTO `hotels`
  (`hotel_name`, `location`, `province`, `latitude`, `longitude`, `price`,
   `description`, `facilities`, `surrounding`, `type`, `owner_id`)
VALUES
  ('เดอะ ริเวอร์ไซด์ แกรนด์ โฮเทล', '188 ถนนช้างคลาน ต.ช้างคลาน อ.เมือง', 'เชียงใหม่',
   18.7883000, 98.9853000, '1200',
   'โรงแรมริมแม่น้ำปิงใจกลางเมืองเชียงใหม่ เดินถึงไนท์บาซาร์ใน 5 นาที ห้องพักกว้างขวางตกแต่งสไตล์ล้านนาร่วมสมัย พร้อมสระว่ายน้ำกลางแจ้งและห้องอาหารเปิดตลอดวัน',
   'สระว่ายน้ำกลางแจ้ง, ห้องอาหาร, ฟิตเนส, ที่จอดรถ, Wi-Fi ฟรีทั่วโรงแรม',
   'ไนท์บาซาร์เชียงใหม่ 450 ม., วัดพระสิงห์ 2.1 กม., ประตูท่าแพ 1.4 กม., สนามบินเชียงใหม่ 4.5 กม.',
   'โรงแรม', @owner_id);
SET @h1 = LAST_INSERT_ID();

INSERT INTO `hotel_images` (`hotel_id`, `image_path`) VALUES
  (@h1, 'uploads/hotels/hotel_demo1_1.jpg'),
  (@h1, 'uploads/hotels/hotel_demo1_2.jpg'),
  (@h1, 'uploads/hotels/hotel_demo1_3.jpg'),
  (@h1, 'uploads/hotels/hotel_demo1_4.jpg');

INSERT INTO `hotel_amenities` (`hotel_id`, `amenity_id`)
  SELECT @h1, `id` FROM `amenities` WHERE `title` IN
  ('รวมอาหารเช้า', 'สระว่ายน้ำ', 'Wi-Fi ฟรี', 'ที่จอดรถฟรี', 'เครื่องปรับอากาศ', 'ฟิตเนส',
   'ร้านอาหารในโรงแรม', 'สปา & นวดแผนไทย', 'พนักงานต้อนรับ 24 ชม.', 'ลิฟต์',
   'บริการซักรีด', 'รับฝากสัมภาระ', 'ทีวีจอแบน', 'ห้องปลอดบุหรี่', 'รับบัตรเครดิต');

INSERT INTO `room_types` (`hotel_id`, `room_name`, `capacity`, `price_per_night`, `quantity`, `description`, `amenities`) VALUES
  (@h1, 'ห้องสแตนดาร์ด ดับเบิล', 2, 1200.00, 12, 'ห้องขนาด 28 ตร.ม. เตียงดับเบิล 1 เตียง พร้อมหน้าต่างบานใหญ่รับแสงธรรมชาติ', 'เครื่องปรับอากาศ, ทีวี, ตู้เย็น, เครื่องทำน้ำอุ่น, Wi-Fi ฟรี'),
  (@h1, 'ห้องดีลักซ์ ทวิน วิวแม่น้ำ', 3, 1800.00, 8, 'ห้องขนาด 36 ตร.ม. เตียงเดี่ยว 2 เตียง ระเบียงส่วนตัวมองเห็นแม่น้ำปิง', 'เครื่องปรับอากาศ, ระเบียง, ทีวี, ตู้เย็น, เครื่องชงกาแฟ, Wi-Fi ฟรี');
SET @r1a = (SELECT `id` FROM `room_types` WHERE `hotel_id` = @h1 AND `room_name` = 'ห้องสแตนดาร์ด ดับเบิล');
SET @r1b = (SELECT `id` FROM `room_types` WHERE `hotel_id` = @h1 AND `room_name` = 'ห้องดีลักซ์ ทวิน วิวแม่น้ำ');

INSERT INTO `room_images` (`room_type_id`, `image_path`) VALUES
  (@r1a, 'uploads/rooms/room_demo1a_1.jpg'),
  (@r1a, 'uploads/rooms/room_demo1a_2.jpg'),
  (@r1b, 'uploads/rooms/room_demo1b_1.jpg'),
  (@r1b, 'uploads/rooms/room_demo1b_2.jpg');


-- ---- Hotel 2: ภูเก็ต ---------------------------------------------------
INSERT INTO `hotels`
  (`hotel_name`, `location`, `province`, `latitude`, `longitude`, `price`,
   `description`, `facilities`, `surrounding`, `type`, `owner_id`)
VALUES
  ('ภูวิว บูทีค รีสอร์ท', '55/9 หมู่ 3 ถนนป่าตอง ต.กะทู้ อ.กะทู้', 'ภูเก็ต',
   7.8804000, 98.3923000, '1950',
   'บูทีครีสอร์ทบรรยากาศเงียบสงบบนเนินเขา ห่างจากหาดป่าตองเพียง 10 นาที ทุกห้องมีระเบียงส่วนตัวมองเห็นวิวทะเลอันดามัน เหมาะทั้งคู่รักและครอบครัว',
   'สระว่ายน้ำอินฟินิตี้, ห้องอาหาร, ห้องประชุม, รถรับส่งสนามบิน, ที่จอดรถ',
   'หาดป่าตอง 2.8 กม., ถนนบางลา 3.1 กม., จุดชมวิวกะรน 8 กม., สนามบินภูเก็ต 38 กม.',
   'รีสอร์ท', @owner_id);
SET @h2 = LAST_INSERT_ID();

INSERT INTO `hotel_images` (`hotel_id`, `image_path`) VALUES
  (@h2, 'uploads/hotels/hotel_demo2_1.jpg'),
  (@h2, 'uploads/hotels/hotel_demo2_2.jpg'),
  (@h2, 'uploads/hotels/hotel_demo2_3.jpg'),
  (@h2, 'uploads/hotels/hotel_demo2_4.jpg');

INSERT INTO `hotel_amenities` (`hotel_id`, `amenity_id`)
  SELECT @h2, `id` FROM `amenities` WHERE `title` IN
  ('รวมอาหารเช้า', 'สระว่ายน้ำ', 'Wi-Fi ฟรี', 'ที่จอดรถฟรี', 'เครื่องปรับอากาศ',
   'ร้านอาหารในโรงแรม', 'บาร์ / เลานจ์', 'สปา & นวดแผนไทย', 'ติดชายหาด', 'วิวภูเขา / ธรรมชาติ',
   'ระเบียงส่วนตัว', 'ห้องประชุม / สัมมนา', 'รถรับส่งสนามบิน', 'บริการเช่ารถ / มอเตอร์ไซค์',
   'ดำน้ำ / กิจกรรมทางน้ำ', 'เหมาะสำหรับเด็ก', 'สวนหย่อม', 'ทีวีจอแบน');

INSERT INTO `room_types` (`hotel_id`, `room_name`, `capacity`, `price_per_night`, `quantity`, `description`, `amenities`) VALUES
  (@h2, 'ซูพีเรีย คิงเบด', 2, 1950.00, 10, 'ห้องขนาด 32 ตร.ม. เตียงคิงไซส์ ระเบียงส่วนตัวมองเห็นสวน', 'เครื่องปรับอากาศ, ระเบียง, ทีวี, ตู้เย็น, เครื่องทำน้ำอุ่น, Wi-Fi ฟรี'),
  (@h2, 'ดีลักซ์ ทวินเบด วิวทะเล', 3, 2400.00, 6, 'ห้องขนาด 40 ตร.ม. เตียงเดี่ยว 2 เตียง ระเบียงกว้างมองเห็นทะเลอันดามัน', 'เครื่องปรับอากาศ, ระเบียง, ทีวี, ตู้เย็น, เครื่องชงกาแฟ, อ่างอาบน้ำ'),
  (@h2, 'แฟมิลี่ สวีท', 4, 3600.00, 4, 'ห้องสวีท 2 ห้องนอน ขนาด 58 ตร.ม. พร้อมมุมนั่งเล่นและครัวเล็ก เหมาะสำหรับครอบครัว', 'เครื่องปรับอากาศ, ครัว, ระเบียง, ทีวี 2 เครื่อง, ตู้เย็น, อ่างอาบน้ำ');
SET @r2a = (SELECT `id` FROM `room_types` WHERE `hotel_id` = @h2 AND `room_name` = 'ซูพีเรีย คิงเบด');
SET @r2b = (SELECT `id` FROM `room_types` WHERE `hotel_id` = @h2 AND `room_name` = 'ดีลักซ์ ทวินเบด วิวทะเล');
SET @r2c = (SELECT `id` FROM `room_types` WHERE `hotel_id` = @h2 AND `room_name` = 'แฟมิลี่ สวีท');

INSERT INTO `room_images` (`room_type_id`, `image_path`) VALUES
  (@r2a, 'uploads/rooms/room_demo2a_1.jpg'),
  (@r2a, 'uploads/rooms/room_demo2a_2.jpg'),
  (@r2b, 'uploads/rooms/room_demo2b_1.jpg'),
  (@r2b, 'uploads/rooms/room_demo2b_2.jpg'),
  (@r2c, 'uploads/rooms/room_demo2c_1.jpg'),
  (@r2c, 'uploads/rooms/room_demo2c_2.jpg');


-- ---- Hotel 3: กรุงเทพมหานคร --------------------------------------------
INSERT INTO `hotels`
  (`hotel_name`, `location`, `province`, `latitude`, `longitude`, `price`,
   `description`, `facilities`, `surrounding`, `type`, `owner_id`)
VALUES
  ('ดิ เออร์เบิน สวีท กรุงเทพ', '1029 ถนนสุขุมวิท แขวงคลองเตยเหนือ เขตวัฒนา', 'กรุงเทพมหานคร',
   13.7367000, 100.5602000, '2500',
   'โรงแรมระดับ 4 ดาวใจกลางสุขุมวิท เดินถึง BTS อโศกและ MRT สุขุมวิทใน 3 นาที ล็อบบี้โปร่งสูงพร้อมโคมระย้า ห้องพักทันสมัยมองเห็นวิวเมือง เหมาะทั้งเดินทางธุรกิจและท่องเที่ยว',
   'ฟิตเนส 24 ชม., ห้องอาหาร, บาร์ชั้นดาดฟ้า, ห้องประชุม, ที่ชาร์จรถ EV, ที่จอดรถในอาคาร',
   'BTS อโศก 250 ม., MRT สุขุมวิท 350 ม., เทอร์มินอล 21 400 ม., สยามพารากอน 4 กม.',
   'โรงแรม', @owner_id);
SET @h3 = LAST_INSERT_ID();

INSERT INTO `hotel_images` (`hotel_id`, `image_path`) VALUES
  (@h3, 'uploads/hotels/hotel_demo3_1.jpg'),
  (@h3, 'uploads/hotels/hotel_demo3_2.jpg'),
  (@h3, 'uploads/hotels/hotel_demo3_3.jpg'),
  (@h3, 'uploads/hotels/hotel_demo3_4.jpg');

INSERT INTO `hotel_amenities` (`hotel_id`, `amenity_id`)
  SELECT @h3, `id` FROM `amenities` WHERE `title` IN
  ('รวมอาหารเช้า', 'Wi-Fi ฟรี', 'เครื่องปรับอากาศ', 'ฟิตเนส', 'ร้านอาหารในโรงแรม',
   'บาร์ / เลานจ์', 'คาเฟ่ / ร้านกาแฟ', 'รูมเซอร์วิส 24 ชม.', 'ใกล้รถไฟฟ้า / ขนส่งสาธารณะ',
   'ห้องประชุม / สัมมนา', 'ลิฟต์', 'รปภ. 24 ชม.', 'ที่ชาร์จรถไฟฟ้า (EV)',
   'พนักงานต้อนรับ 24 ชม.', 'บริการซักรีด', 'บริการปริ้นเอกสาร', 'แลกเปลี่ยนเงินตรา',
   'รับบัตรเครดิต', 'โต๊ะทำงาน', 'ทีวีจอแบน', 'ห้องปลอดบุหรี่', 'สิ่งอำนวยความสะดวกผู้พิการ');

INSERT INTO `room_types` (`hotel_id`, `room_name`, `capacity`, `price_per_night`, `quantity`, `description`, `amenities`) VALUES
  (@h3, 'ซูพีเรีย ซิตี้วิว', 2, 2500.00, 15, 'ห้องขนาด 30 ตร.ม. เตียงคิงไซส์ กระจกบานใหญ่มองเห็นวิวเมือง พร้อมโต๊ะทำงาน', 'เครื่องปรับอากาศ, โต๊ะทำงาน, ทีวี, ตู้เย็น, เครื่องชงกาแฟ, ตู้นิรภัย'),
  (@h3, 'เอ็กเซ็กคิวทีฟ สวีท', 2, 3800.00, 8, 'ห้องสวีทขนาด 45 ตร.ม. แยกส่วนนั่งเล่น พร้อมสิทธิ์ใช้เอ็กเซ็กคิวทีฟเลานจ์', 'เครื่องปรับอากาศ, โซฟา, โต๊ะทำงาน, อ่างอาบน้ำ, เครื่องชงกาแฟ, ตู้นิรภัย'),
  (@h3, 'แกรนด์ สวีท', 4, 5500.00, 3, 'ห้องสวีทมุมตึกขนาด 72 ตร.ม. 2 ห้องนอน วิวพาโนรามา 2 ด้าน พร้อมครัวเล็ก', 'เครื่องปรับอากาศ, ครัว, โซฟา, อ่างอาบน้ำ, ทีวี 2 เครื่อง, ตู้นิรภัย');
SET @r3a = (SELECT `id` FROM `room_types` WHERE `hotel_id` = @h3 AND `room_name` = 'ซูพีเรีย ซิตี้วิว');
SET @r3b = (SELECT `id` FROM `room_types` WHERE `hotel_id` = @h3 AND `room_name` = 'เอ็กเซ็กคิวทีฟ สวีท');
SET @r3c = (SELECT `id` FROM `room_types` WHERE `hotel_id` = @h3 AND `room_name` = 'แกรนด์ สวีท');

INSERT INTO `room_images` (`room_type_id`, `image_path`) VALUES
  (@r3a, 'uploads/rooms/room_demo3a_1.jpg'),
  (@r3a, 'uploads/rooms/room_demo3a_2.jpg'),
  (@r3b, 'uploads/rooms/room_demo3b_1.jpg'),
  (@r3b, 'uploads/rooms/room_demo3b_2.jpg'),
  (@r3c, 'uploads/rooms/room_demo3c_1.jpg'),
  (@r3c, 'uploads/rooms/room_demo3c_2.jpg');


-- ---- Hotel 4: ชลบุรี ---------------------------------------------------
INSERT INTO `hotels`
  (`hotel_name`, `location`, `province`, `latitude`, `longitude`, `price`,
   `description`, `facilities`, `surrounding`, `type`, `owner_id`)
VALUES
  ('ทริปเปิล ทรีส์ เรสซิเดนซ์', '77/12 หมู่ 9 ถนนพัทยาสาย 2 ต.หนองปรือ อ.บางละมุง', 'ชลบุรี',
   12.9236000, 100.8825000, '950',
   'ที่พักสไตล์เรสซิเดนซ์ราคาย่อมเยาในพัทยา ห้องพักสะอาดใหม่ ตกแต่งสดใส มีครัวและเครื่องซักผ้าให้ใช้ เหมาะสำหรับพักยาวและครอบครัวที่มาเที่ยวทะเล',
   'ที่จอดรถฟรี, ลิฟต์, เครื่องซักผ้าหยอดเหรียญ, ร้านสะดวกซื้อ, Wi-Fi ฟรี',
   'หาดพัทยา 1.6 กม., เซ็นทรัลพัทยา 1.2 กม., วอล์คกิ้งสตรีท 3.4 กม., สวนนงนุช 18 กม.',
   'เรสซิเดนซ์', @owner_id);
SET @h4 = LAST_INSERT_ID();

INSERT INTO `hotel_images` (`hotel_id`, `image_path`) VALUES
  (@h4, 'uploads/hotels/hotel_demo4_1.jpg'),
  (@h4, 'uploads/hotels/hotel_demo4_2.jpg'),
  (@h4, 'uploads/hotels/hotel_demo4_3.jpg'),
  (@h4, 'uploads/hotels/hotel_demo4_4.jpg');

INSERT INTO `hotel_amenities` (`hotel_id`, `amenity_id`)
  SELECT @h4, `id` FROM `amenities` WHERE `title` IN
  ('Wi-Fi ฟรี', 'ที่จอดรถฟรี', 'เครื่องปรับอากาศ', 'ลิฟต์', 'รปภ. 24 ชม.',
   'เครื่องซักผ้าหยอดเหรียญ', 'ร้านสะดวกซื้อ', 'ครัวในห้องพัก', 'ไมโครเวฟ',
   'ทีวีจอแบน', 'ระเบียงส่วนตัว', 'อนุญาตให้นำสัตว์เลี้ยง', 'เหมาะสำหรับเด็ก',
   'เตียงเด็กเสริม', 'จักรยานให้เช่า', 'รับฝากสัมภาระ', 'ห้องปลอดบุหรี่');

INSERT INTO `room_types` (`hotel_id`, `room_name`, `capacity`, `price_per_night`, `quantity`, `description`, `amenities`) VALUES
  (@h4, 'สตูดิโอ ดับเบิล', 2, 950.00, 20, 'ห้องสตูดิโอขนาด 26 ตร.ม. เตียงดับเบิล พร้อมมุมครัวเล็กและระเบียง', 'เครื่องปรับอากาศ, ครัวเล็ก, ทีวี, ตู้เย็น, ระเบียง, Wi-Fi ฟรี'),
  (@h4, 'ดีลักซ์ ทวิน', 2, 1350.00, 12, 'ห้องขนาด 32 ตร.ม. เตียงเดี่ยว 2 เตียง กว้างขวางพร้อมโต๊ะทำงาน', 'เครื่องปรับอากาศ, ทีวี, ตู้เย็น, โต๊ะทำงาน, ระเบียง, Wi-Fi ฟรี'),
  (@h4, 'สวีท ครอบครัว', 4, 2200.00, 5, 'ห้องชุด 2 ห้องนอนขนาด 52 ตร.ม. พร้อมครัวเต็มรูปแบบและเครื่องซักผ้าในห้อง', 'เครื่องปรับอากาศ, ครัวเต็มรูปแบบ, เครื่องซักผ้า, ทีวี 2 เครื่อง, ระเบียง');
SET @r4a = (SELECT `id` FROM `room_types` WHERE `hotel_id` = @h4 AND `room_name` = 'สตูดิโอ ดับเบิล');
SET @r4b = (SELECT `id` FROM `room_types` WHERE `hotel_id` = @h4 AND `room_name` = 'ดีลักซ์ ทวิน');
SET @r4c = (SELECT `id` FROM `room_types` WHERE `hotel_id` = @h4 AND `room_name` = 'สวีท ครอบครัว');

INSERT INTO `room_images` (`room_type_id`, `image_path`) VALUES
  (@r4a, 'uploads/rooms/room_demo4a_1.jpg'),
  (@r4a, 'uploads/rooms/room_demo4a_2.jpg'),
  (@r4b, 'uploads/rooms/room_demo4b_1.jpg'),
  (@r4b, 'uploads/rooms/room_demo4b_2.jpg'),
  (@r4c, 'uploads/rooms/room_demo4c_1.jpg'),
  (@r4c, 'uploads/rooms/room_demo4c_2.jpg');


-- Sanity check — should print the owner id and 4 rows.
SELECT @owner_id AS owner_id;
SELECT h.id, h.hotel_name, h.province, h.price,
       (SELECT COUNT(*) FROM room_types  rt WHERE rt.hotel_id = h.id) AS room_types,
       (SELECT COUNT(*) FROM hotel_images hi WHERE hi.hotel_id = h.id) AS images
FROM hotels h
WHERE h.owner_id = @owner_id
ORDER BY h.id;
