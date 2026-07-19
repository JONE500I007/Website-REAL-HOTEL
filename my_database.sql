-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db2
-- Generation Time: Jul 19, 2026 at 01:11 PM
-- Server version: 9.7.0
-- PHP Version: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `my_database`
--

-- --------------------------------------------------------

--
-- Table structure for table `amenities`
--

CREATE TABLE `amenities` (
  `id` int NOT NULL,
  `title` varchar(100) NOT NULL,
  `icon` varchar(50) NOT NULL DEFAULT 'check_circle',
  `display_order` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `amenities`
--

INSERT INTO `amenities` (`id`, `title`, `icon`, `display_order`) VALUES
(1, 'รวมอาหารเช้า', 'free_breakfast', 1),
(2, 'สระว่ายน้ำ', 'pool', 2),
(3, 'Wi-Fi ฟรี', 'wifi', 3),
(4, 'ที่จอดรถฟรี', 'local_parking', 4),
(5, 'เครื่องปรับอากาศ', 'ac_unit', 5),
(6, 'ฟิตเนส', 'fitness_center', 6);

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int NOT NULL,
  `first_name` text NOT NULL,
  `last_name` text NOT NULL,
  `email` text NOT NULL,
  `phone` text NOT NULL,
  `checkin` text NOT NULL,
  `checkout` text NOT NULL,
  `guests` int NOT NULL,
  `hotel_id` int DEFAULT NULL,
  `room_type_id` int DEFAULT NULL,
  `book_hotel_name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `total_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_slip` varchar(255) DEFAULT NULL,
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending_verification'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `first_name`, `last_name`, `email`, `phone`, `checkin`, `checkout`, `guests`, `hotel_id`, `room_type_id`, `book_hotel_name`, `total_price`, `payment_slip`, `payment_status`) VALUES
(1, 'A', 'B', 'a@a.com', '0800000000', '2026-09-01', '2026-09-02', 1, NULL, NULL, 'X', 0.00, NULL, 'pending_verification'),
(8, 'ONE', 'OF THE TEST MEME DO NOT A TEST', 'ingkawat2023reals@gmail.com', '0993113131', '2026-07-11', '2026-07-12', 1, 1, 6, 'awdawdawd', 1111.00, 'slip_6a52071fe408c.png', 'confirmed');

-- --------------------------------------------------------

--
-- Table structure for table `hotels`
--

CREATE TABLE `hotels` (
  `id` int NOT NULL,
  `hotel_name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `location` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `price` text NOT NULL,
  `description` text NOT NULL,
  `facilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `surrounding` text NOT NULL,
  `type` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `owner_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `hotels`
--

INSERT INTO `hotels` (`id`, `hotel_name`, `location`, `latitude`, `longitude`, `price`, `description`, `facilities`, `surrounding`, `type`, `owner_id`) VALUES
(1, 'awdawdawd', 'ไม่บอกหนอก', 17.3663673, 101.4518738, '1000.599', 'awdawdawd', '123123', '12412414awdawdawd', '', 5);

-- --------------------------------------------------------

--
-- Table structure for table `hotel_amenities`
--

CREATE TABLE `hotel_amenities` (
  `id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `amenity_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `hotel_amenities`
--

INSERT INTO `hotel_amenities` (`id`, `hotel_id`, `amenity_id`) VALUES
(6, 1, 2),
(7, 1, 3);

-- --------------------------------------------------------

--
-- Table structure for table `hotel_categories`
--

CREATE TABLE `hotel_categories` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `show_on` varchar(10) NOT NULL DEFAULT 'both',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `hotel_categories`
--

INSERT INTO `hotel_categories` (`id`, `title`, `display_order`, `show_on`, `created_at`) VALUES
(1, 'โรงแรมราคาประหยัด', 1, 'both', '2026-07-19 09:22:27'),
(2, 'โรงแรมที่แนะนำ', 2, 'both', '2026-07-19 09:22:27'),
(3, 'โรงแรมหรู', 3, 'both', '2026-07-19 09:22:27');

-- --------------------------------------------------------

--
-- Table structure for table `hotel_category_items`
--

CREATE TABLE `hotel_category_items` (
  `id` int NOT NULL,
  `category_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `display_order` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `hotel_category_items`
--

INSERT INTO `hotel_category_items` (`id`, `category_id`, `hotel_id`, `display_order`) VALUES
(1, 2, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `hotel_images`
--

CREATE TABLE `hotel_images` (
  `id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `image_path` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `hotel_images`
--

INSERT INTO `hotel_images` (`id`, `hotel_id`, `image_path`) VALUES
(1, 1, 'uploads/hotels/hotel_6a47b0138bf30.png'),
(13, 1, 'uploads/hotels/hotel_6a47b4c58dea0.png'),
(14, 1, 'uploads/hotels/hotel_6a47b4c592806.png'),
(15, 1, 'uploads/hotels/hotel_6a47b4dbc6b98.png'),
(16, 1, 'uploads/hotels/hotel_6a47b4dbcb991.jpg'),
(17, 1, 'uploads/hotels/hotel_6a47b4dbd4297.jpg'),
(18, 1, 'uploads/hotels/hotel_6a47b4dbd89de.png'),
(19, 1, 'uploads/hotels/hotel_6a47b4dbde93a.jpeg'),
(21, 1, 'uploads/hotels/hotel_6a47b4dbe6efb.png');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `user_id` int NOT NULL,
  `parent_id` int DEFAULT NULL,
  `rating` tinyint DEFAULT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `hotel_id`, `user_id`, `parent_id`, `rating`, `comment`, `created_at`, `updated_at`) VALUES
(8, 1, 3, NULL, 5, 'เริ่มฮา จริงแล้วนะนาย :3', '2026-07-14 17:40:56', NULL),
(11, 1, 5, 8, NULL, 'กูว่าไม่โม้', '2026-07-19 06:30:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `room_images`
--

CREATE TABLE `room_images` (
  `id` int NOT NULL,
  `room_type_id` int NOT NULL,
  `image_path` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `room_images`
--

INSERT INTO `room_images` (`id`, `room_type_id`, `image_path`) VALUES
(5, 4, 'uploads/rooms/room_6a47b46919163.jpg'),
(6, 4, 'uploads/rooms/room_6a47b4691e128.jpg'),
(7, 4, 'uploads/rooms/room_6a47b4803cf77.jpg'),
(8, 4, 'uploads/rooms/room_6a47b48041ef4.jpg'),
(9, 4, 'uploads/rooms/room_6a47b48045fe0.jpg'),
(10, 4, 'uploads/rooms/room_6a47b4804acb8.jpg'),
(11, 6, 'uploads/rooms/room_6a47b67cc2686.jpg'),
(12, 6, 'uploads/rooms/room_6a47b67cc7972.jpg'),
(13, 6, 'uploads/rooms/room_6a47b67ccc523.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `room_types`
--

CREATE TABLE `room_types` (
  `id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `room_name` varchar(255) NOT NULL,
  `capacity` int NOT NULL DEFAULT '2',
  `price_per_night` decimal(10,2) NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `description` text,
  `amenities` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `room_types`
--

INSERT INTO `room_types` (`id`, `hotel_id`, `room_name`, `capacity`, `price_per_night`, `quantity`, `description`, `amenities`, `created_at`) VALUES
(4, 1, 'fthfthftj', 2, 1200.00, 5, 'efefef', 'rgrgerg', '2026-07-03 12:37:33'),
(6, 1, '423423424', 4, 1111.00, 2, '12312312', '124124124124', '2026-07-03 13:17:48'),
(9, 1, '565656', 33, 3434.00, 44, '343434', '53535353', '2026-07-03 15:30:59');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `role` varchar(255) NOT NULL,
  `profile_picture` varchar(255) NOT NULL DEFAULT 'default.jpg',
  `google_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `verify_token` varchar(64) DEFAULT NULL,
  `verify_token_expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `phone_number`, `password`, `role`, `profile_picture`, `google_id`, `email_verified_at`, `reset_token`, `reset_token_expires_at`, `verify_token`, `verify_token_expires_at`) VALUES
(1, 'adwadw awdiosejfsf', 'awdiojijeifehuifd@gmail.com', 'adwadw', '$2y$10$69QfiUwMi/3v55xrEjee..1bchpGmVgdr1C3DbdSz1gc96iJSaYRS', 'user', 'default.jpg', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'rgjirg krgiirgjrgjigr', 'adaaaa@gmail.com', 'adaaaa@gmail.com', '$2y$10$X4w7oCuYn03JZRWPaCJaDuqDVNOpgDqh9l4aatn0uTfeo/aQdX0I6', 'user', 'profile_2_1782767972.jpg', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'ONE OF THE TEST MEME DO NOT A TEST', 'ingkawat2023reals@gmail.com', '0993113131', NULL, 'user', 'profile_3_1783074554.jpg', '109208015442849269891', '2026-07-19 08:19:05', NULL, NULL, NULL, NULL),
(4, 'okok@gmail.com', 'okok@gmail.com', '0993434343', '$2y$10$c7w1PhdAlietUj89yZKgRef2SYotRgj2CpjTXpfyIFOE1.8/2v2ZK', 'user', 'default.jpg', NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'SANS', 'okko0990okko@gmail.com', '0921231231', NULL, 'owner', 'profile_5_6a553018b0ca8.jpg', '109647387825501843945', '2026-07-19 08:19:05', NULL, NULL, NULL, NULL),
(12, 'inthistest@gmail.com', 'inthistest@gmail.com', '094596343', '$2y$10$zbIuf/laM4Hh.8Yk56omGu/K2c.H.7VK7zrkKCCbtHO5KidVT2NYa', 'admin', 'default.jpg', NULL, NULL, NULL, NULL, NULL, NULL),
(22, 'ThisTest', 'kooossjgwawtuijvbgjn@gmail.com', '0931223223', '$2y$10$9yV5H8Lsj6RL5LaFXEBt3uAIt7K/qIAeUadlZx140SFxDYoWe69O2', 'user', 'default.jpg', NULL, '2026-07-19 09:08:38', NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `amenities`
--
ALTER TABLE `amenities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hotels`
--
ALTER TABLE `hotels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hotel_amenities`
--
ALTER TABLE `hotel_amenities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_hotel_amenity` (`hotel_id`,`amenity_id`),
  ADD KEY `idx_ha_hotel` (`hotel_id`),
  ADD KEY `idx_ha_amenity` (`amenity_id`);

--
-- Indexes for table `hotel_categories`
--
ALTER TABLE `hotel_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hotel_category_items`
--
ALTER TABLE `hotel_category_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_category_hotel` (`category_id`,`hotel_id`),
  ADD KEY `idx_items_category` (`category_id`),
  ADD KEY `idx_items_hotel` (`hotel_id`);

--
-- Indexes for table `hotel_images`
--
ALTER TABLE `hotel_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reviews_hotel` (`hotel_id`),
  ADD KEY `idx_reviews_parent` (`parent_id`),
  ADD KEY `idx_reviews_user` (`user_id`);

--
-- Indexes for table `room_images`
--
ALTER TABLE `room_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_images_room` (`room_type_id`);

--
-- Indexes for table `room_types`
--
ALTER TABLE `room_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_types_hotel` (`hotel_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `amenities`
--
ALTER TABLE `amenities`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `hotels`
--
ALTER TABLE `hotels`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `hotel_amenities`
--
ALTER TABLE `hotel_amenities`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `hotel_categories`
--
ALTER TABLE `hotel_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `hotel_category_items`
--
ALTER TABLE `hotel_category_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `hotel_images`
--
ALTER TABLE `hotel_images`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `room_images`
--
ALTER TABLE `room_images`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `room_types`
--
ALTER TABLE `room_types`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `hotel_amenities`
--
ALTER TABLE `hotel_amenities`
  ADD CONSTRAINT `fk_ha_amenity` FOREIGN KEY (`amenity_id`) REFERENCES `amenities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ha_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_images`
--
ALTER TABLE `room_images`
  ADD CONSTRAINT `fk_room_images_room` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_types`
--
ALTER TABLE `room_types`
  ADD CONSTRAINT `fk_room_types_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
