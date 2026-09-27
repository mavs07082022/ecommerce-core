-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 27, 2026 at 10:48 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ecommerce_core`
--

-- --------------------------------------------------------

--
-- Table structure for table `email_otps`
--

CREATE TABLE `email_otps` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `purpose` enum('register','login','reset') DEFAULT 'register',
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_otps`
--

INSERT INTO `email_otps` (`id`, `user_id`, `email`, `otp_code`, `purpose`, `expires_at`, `used`, `created_at`) VALUES
(2, 5, 'christianjoco03@gmail.com', '118182', 'register', '2026-09-25 06:58:40', 0, '2026-09-25 04:48:40'),
(3, 6, 'yengmavean@gmail.com', '636414', 'register', '2026-09-25 13:06:39', 1, '2026-09-25 04:56:39'),
(5, 9, 'cerbitomarkie5@gmail.com', '928589', 'register', '2026-09-26 18:27:54', 1, '2026-09-26 10:17:54'),
(6, 10, 'jhonaarro55@gmail.com', '360147', 'register', '2026-09-26 18:34:02', 1, '2026-09-26 10:24:02'),
(8, 12, 'cerbitomarkie5@gmail.com', '897834', 'register', '2026-09-27 12:59:20', 0, '2026-09-27 04:49:20'),
(9, 13, 'estreracarlosmavean17@gmail.com', '323063', 'register', '2026-09-27 16:51:47', 1, '2026-09-27 08:41:47');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(20) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled','returned') DEFAULT 'pending',
  `shipping_address` text DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT 'COD',
  `delivery_option` varchar(50) DEFAULT 'standard',
  `delivery_fee` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('unpaid','pending_verification','paid','refunded') DEFAULT 'unpaid',
  `payment_reference` varchar(100) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `total_amount`, `status`, `shipping_address`, `contact_phone`, `notes`, `payment_method`, `delivery_option`, `delivery_fee`, `payment_status`, `payment_reference`, `payment_proof`, `paid_at`, `created_at`) VALUES
(1, 'ORD-000001', 2, 13000.00, 'processing', 'phase 3 area a acorda st payatas quezon citty', NULL, NULL, 'COD', 'standard', 0.00, 'unpaid', NULL, NULL, NULL, '2026-09-25 03:58:35'),
(2, 'ORD-20260925-ADF57', 6, 13000.00, 'pending', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY, QUEZON CITY, METRO MANILA 1119', '09278878724', NULL, 'Card', 'standard', 0.00, 'pending_verification', NULL, NULL, NULL, '2026-09-25 05:00:15'),
(3, 'ORD-20260925-6CE0F', 6, 25000.00, 'cancelled', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY, QUEZON CITY, METRO MANILA 1119', '09278878724', NULL, 'GCash', 'standard', 0.00, 'unpaid', NULL, NULL, NULL, '2026-09-25 05:46:23'),
(4, 'ORD-20260925-1A1D5', 6, 2.00, 'cancelled', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY, QUEZON CITY, METRO MANILA 1119', '09278878724', NULL, 'GCash', 'standard', 0.00, 'unpaid', NULL, NULL, NULL, '2026-09-25 05:56:27'),
(5, 'ORD-20260925-C262B', 6, 2.00, 'shipped', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY, QUEZON CITY, METRO MANILA 1119', '09278878724', NULL, 'GCash', 'standard', 0.00, 'pending_verification', '1045421919122', 'assets/uploads/pay_5_6ab6132b49bca.png', NULL, '2026-09-25 06:10:42'),
(6, 'ORD-20260925-345D5', 6, 2.00, 'processing', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY, QUEZON CITY, METRO MANILA 1119', '09278878724', NULL, 'GCash', 'standard', 0.00, 'paid', '1045421919122', 'assets/uploads/pay_6_6ab614b36fb08.png', '2026-09-25 20:33:33', '2026-09-25 06:28:49'),
(7, 'ORD-20260925-EF663', 6, 2.00, 'pending', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY, QUEZON CITY, METRO MANILA 1119', '09278878724', NULL, 'COD', 'standard', 0.00, 'unpaid', NULL, NULL, NULL, '2026-09-25 12:38:49');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(1, 1, 3, 1, 13000.00),
(2, 2, 3, 1, 13000.00),
(3, 3, 1, 1, 25000.00),
(4, 4, 4, 1, 2.00),
(5, 5, 4, 1, 2.00),
(6, 6, 4, 1, 2.00),
(7, 7, 4, 1, 2.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_tracking`
--

CREATE TABLE `order_tracking` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `message` text DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_tracking`
--

INSERT INTO `order_tracking` (`id`, `order_id`, `status`, `message`, `location`, `created_at`) VALUES
(1, 2, 'pending', 'Order ORD-20260925-ADF57 placed successfully', 'Processing Center', '2026-09-25 05:00:15'),
(2, 2, 'pending', 'Payment submitted via Card. Awaiting verification.', 'System', '2026-09-25 05:03:11'),
(3, 3, 'pending', 'Order ORD-20260925-6CE0F placed successfully', 'Processing Center', '2026-09-25 05:46:23'),
(4, 4, 'pending', 'Order ORD-20260925-1A1D5 placed successfully', 'Processing Center', '2026-09-25 05:56:27'),
(5, 4, 'cancelled', 'Order cancelled by customer', 'System', '2026-09-25 06:10:29'),
(6, 3, 'cancelled', 'Order cancelled by customer', 'System', '2026-09-25 06:10:32'),
(7, 5, 'pending', 'Order ORD-20260925-C262B placed successfully', 'Processing Center', '2026-09-25 06:10:42'),
(8, 5, 'pending', 'Payment submitted via GCash. Awaiting admin verification.', 'Payment Center', '2026-09-25 06:22:35'),
(9, 5, 'processing', 'Order is being prepared in our warehouse', 'Warehouse', '2026-09-25 06:23:15'),
(10, 5, 'shipped', 'Your package has been shipped', 'In Transit', '2026-09-25 06:23:25'),
(11, 6, 'pending', 'Order ORD-20260925-345D5 placed successfully', 'Processing Center', '2026-09-25 06:28:49'),
(12, 6, 'pending', 'Payment submitted via GCash. Awaiting admin verification.', 'Payment Center', '2026-09-25 06:29:07'),
(13, 6, 'pending', 'Payment verified and confirmed', 'Payment Center', '2026-09-25 12:33:33'),
(14, 6, 'processing', 'Order is being prepared in our warehouse', 'Warehouse', '2026-09-25 12:33:42'),
(15, 7, 'pending', 'Order ORD-20260925-EF663 placed with Standard (3-5 days)', 'Processing Center', '2026-09-25 12:38:49');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `category`, `stock`, `image_url`, `created_at`) VALUES
(1, 'Laptop', 'Gaming laptop', 25000.00, 'General', 14, 'assets/uploads/prod_6ab5c09bad536.png', '2026-09-25 00:30:19'),
(3, 'gaming pc', 'gaming pc and for student, affordable', 13000.00, 'Electronics', 1, 'assets/uploads/prod_6ab5c22428e3c.png', '2026-09-25 00:36:52'),
(4, 'Vape V2', 'Black elite vape Version 2', 2.00, 'Electronics', 1, 'assets/uploads/prod_6ab60ccb814db.png', '2026-09-25 05:54:22');

-- --------------------------------------------------------

--
-- Table structure for table `product_embeddings`
--

CREATE TABLE `product_embeddings` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `embedding` longtext NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `rating` tinyint(1) NOT NULL CHECK (`rating` between 1 and 5),
  `review_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `returns`
--

CREATE TABLE `returns` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected','refunded') DEFAULT 'pending',
  `refund_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `province` varchar(80) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','product_manager','customer') DEFAULT 'customer',
  `status` enum('active','inactive') DEFAULT 'active',
  `email_verified` tinyint(1) DEFAULT 0,
  `profile_completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `phone`, `address`, `city`, `province`, `postal_code`, `password`, `full_name`, `role`, `status`, `email_verified`, `profile_completed`, `created_at`) VALUES
(1, 'admin', 'admin@ecommerce.com', NULL, NULL, NULL, NULL, NULL, '$2y$10$Ef/Wi80jY5/NNIUO7kH0yen37ffqmNgdAjL5J1DyTpsJ1tmJSiWrm', 'System Admin', 'admin', 'active', 1, 1, '2026-09-24 14:21:26'),
(2, 'customer', 'customer@test.com', '09171234567', '123 Sample Street', 'Manila', 'Metro Manila', '1000', '$2y$10$Hbb7sYr3zAFyikF2QDWqq.O7EKKfmvFdxbREHIEXWtf4SD5qv8hPu', 'John Doe', 'customer', 'active', 1, 1, '2026-09-24 14:22:10'),
(3, 'pm', 'pm@ecommerce.com', NULL, NULL, NULL, NULL, NULL, '$2y$10$QVuImCrj3wRU1a3NSF46Y.k0f81uJXm6wkj2BGhF5EF2Y3gizDCyy', 'Product Manager', 'product_manager', 'active', 1, 1, '2026-09-25 03:50:52'),
(5, 'Christian', 'christianjoco03@gmail.com', '09278878724', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY', 'QUEZON CITY', 'METRO MANILA', '1119', '$2y$10$tMsKzLyBt3NYT8Ym/btk/OAVlTQpTPSJGp5eq.s/aYpu28oULE2uK', 'Christian joco', 'customer', 'active', 0, 1, '2026-09-25 04:48:40'),
(6, 'Mavean', 'yengmavean@gmail.com', '09278878724', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY', 'QUEZON CITY', 'METRO MANILA', '1119', '$2y$10$h0Itr0qTZUxpmF8PAHGu7OLGJsRG0nVN/0S0Io0MF6/tD2M73mz5W', 'ESTRERA, CARLOS MAVEAN L.', 'customer', 'active', 1, 1, '2026-09-25 04:56:39'),
(10, 'Jhona', 'jhonaarro55@gmail.com', '09278878724', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY', 'QUEZON CITY', 'METRO MANILA', '1119', '$2y$10$YIatnTNzbCpK3WaVAWxEAur9nKD4rw6usq3Bz6jtfea5SUX/ugqq2', 'Jhona Arro', 'customer', 'active', 1, 1, '2026-09-26 10:24:02'),
(12, 'markc', 'cerbitomarkie5@gmail.com', '09278878724', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY', 'QUEZON CITY', 'METRO MANILA', '1119', '$2y$10$BKOj.DkAu/wMT35c25s8ZOaa.FfqM5pG9K.Onl3ltEKoqnZREru/S', 'mark', 'customer', 'active', 0, 1, '2026-09-27 04:49:20'),
(13, 'caloy', 'estreracarlosmavean17@gmail.com', '09278878724', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY', 'QUEZON CITY', 'METRO MANILA', '1119', '$2y$10$WQiW5YMcib14GyG2wTyH0uggNv8ET1OANr66J440hf95kKQlWll0m', 'ESTRERA, CARLOS MAVEAN L.', 'customer', 'active', 1, 1, '2026-09-27 08:41:47');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `email_otps`
--
ALTER TABLE `email_otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `otp_code` (`otp_code`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_tracking`
--
ALTER TABLE `order_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_embeddings`
--
ALTER TABLE `product_embeddings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_id` (`product_id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_product` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `returns`
--
ALTER TABLE `returns`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `email_otps`
--
ALTER TABLE `email_otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `order_tracking`
--
ALTER TABLE `order_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `product_embeddings`
--
ALTER TABLE `product_embeddings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `returns`
--
ALTER TABLE `returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
