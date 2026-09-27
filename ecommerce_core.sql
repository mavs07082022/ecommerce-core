-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 27, 2026 at 01:25 PM
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
-- Table structure for table `ai_faq`
--

CREATE TABLE `ai_faq` (
  `id` int(11) NOT NULL,
  `keywords` varchar(255) NOT NULL,
  `question` varchar(255) DEFAULT NULL,
  `answer` text NOT NULL,
  `priority` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ai_faq`
--

INSERT INTO `ai_faq` (`id`, `keywords`, `question`, `answer`, `priority`, `created_at`) VALUES
(1, 'delivery,days,how long,shipping time,when,arrive', 'How long does delivery take?', '📦 Standard delivery takes 3-5 business days. Express delivery takes 1-2 business days. Same-day delivery is available in select areas (delivered the same day if ordered before 2 PM).', 10, '2026-09-27 09:36:50'),
(2, 'track,where is my order,order status', 'How do I track my order?', '🚚 You can track your order from your dashboard by clicking \"Track\" next to the order. You\'ll see real-time updates from processing → shipped → delivered.', 9, '2026-09-27 09:36:50'),
(3, 'return,refund,exchange', 'How do I return an item?', '↩️ Go to your dashboard, find the delivered order, and click \"Return\". Fill in the reason and submit. Our team will review within 24 hours. Refunds are processed within 3-5 business days after approval.', 8, '2026-09-27 09:36:50'),
(4, 'payment,gcash,card,cod,how to pay', 'What payment methods do you accept?', '💳 We accept GCash, Credit/Debit Card, and Cash on Delivery (COD). For GCash/Card, upload a screenshot of the payment for verification.', 7, '2026-09-27 09:36:50'),
(5, 'cancel,change order,modify', 'Can I cancel my order?', '❌ Yes, you can cancel while the order is still in \"Pending\" or \"Processing\" status. Go to your dashboard and click \"Cancel\" next to the order.', 6, '2026-09-27 09:36:50'),
(6, 'contact,seller,admin,message', 'How do I contact the seller?', '💬 Click the \"Chat\" icon in the sidebar to message our team directly. We typically respond within a few hours.', 5, '2026-09-27 09:36:50'),
(7, 'warranty,guarantee,defective', 'What if my product is defective?', '🛡️ All products come with a 7-day replacement warranty for manufacturing defects. Please submit a return request with photos/videos of the issue.', 4, '2026-09-27 09:36:50'),
(8, 'stock,available,out of stock', 'Is this item in stock?', '📊 Stock levels are shown on each product page. If it says \"Out of Stock\", you can message us to ask about restock timelines.', 3, '2026-09-27 09:36:50');

-- --------------------------------------------------------

--
-- Table structure for table `chat_conversations`
--

CREATE TABLE `chat_conversations` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `needs_human` tinyint(1) DEFAULT 0,
  `customer_typing_at` timestamp NULL DEFAULT NULL,
  `staff_typing_at` timestamp NULL DEFAULT NULL,
  `subject` varchar(150) DEFAULT 'General Inquiry',
  `status` enum('open','closed') DEFAULT 'open',
  `last_message_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_conversations`
--

INSERT INTO `chat_conversations` (`id`, `customer_id`, `staff_id`, `needs_human`, `customer_typing_at`, `staff_typing_at`, `subject`, `status`, `last_message_at`, `created_at`) VALUES
(1, 10, 3, 0, '2026-09-27 09:51:30', '2026-09-27 09:51:40', 'General Inquiry', 'open', '2026-09-27 09:51:40', '2026-09-27 09:41:30');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_role` enum('customer','admin','product_manager','ai') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `conversation_id`, `sender_id`, `sender_role`, `message`, `is_read`, `delivered_at`, `read_at`, `created_at`) VALUES
(1, 1, 0, 'ai', '👋 Hi! I\'m your AI assistant. Ask me about delivery times, order status, returns, or payments. If I can\'t help, I\'ll connect you to a real person.', 0, NULL, NULL, '2026-09-27 09:41:30'),
(2, 1, 10, 'customer', 'How many days to deliver my order?', 1, '2026-09-27 09:51:36', '2026-09-27 09:51:36', '2026-09-27 09:42:06'),
(3, 1, 0, 'ai', '📦 Standard delivery takes 3-5 business days. Express delivery takes 1-2 business days. Same-day delivery is available in select areas (delivered the same day if ordered before 2 PM).', 0, NULL, NULL, '2026-09-27 09:42:06'),
(4, 1, 10, 'customer', 'What payment methods do you accept?', 1, '2026-09-27 09:51:36', '2026-09-27 09:51:36', '2026-09-27 09:42:13'),
(5, 1, 0, 'ai', '💳 We accept GCash, Credit/Debit Card, and Cash on Delivery (COD). For GCash/Card, upload a screenshot of the payment for verification.', 0, NULL, NULL, '2026-09-27 09:42:13'),
(6, 1, 10, 'customer', 'Can I cancel my order?', 1, '2026-09-27 09:51:36', '2026-09-27 09:51:36', '2026-09-27 09:42:20'),
(7, 1, 0, 'ai', '❌ Yes, you can cancel while the order is still in \"Pending\" or \"Processing\" status. Go to your dashboard and click \"Cancel\" next to the order.', 0, NULL, NULL, '2026-09-27 09:42:20'),
(8, 1, 10, 'customer', 'I want to talk to a human', 1, '2026-09-27 09:51:36', '2026-09-27 09:51:36', '2026-09-27 09:42:32'),
(9, 1, 0, 'ai', '👤 Got it — I\'ve flagged this for our team. A real person will reply as soon as possible. In the meantime, feel free to leave more details here.', 0, NULL, NULL, '2026-09-27 09:42:32'),
(10, 1, 10, 'customer', 'pwede koba i pa rush order ko?', 1, '2026-09-27 09:51:36', '2026-09-27 09:51:36', '2026-09-27 09:43:17'),
(11, 1, 0, 'ai', '🤖 I\'m not sure about that one. Here are things I can help with:\n\n• Delivery times and order tracking\n• Return and refund policy\n• Payment methods\n• Product availability\n• Cancelling orders\n\nFor anything else, tap **\"Talk to a human\"** below to reach our team.', 0, NULL, NULL, '2026-09-27 09:43:17'),
(12, 1, 3, 'product_manager', 'yespo pwede pero may additional fee', 1, '2026-09-27 09:50:44', '2026-09-27 09:50:44', '2026-09-27 09:43:56'),
(13, 1, 10, 'customer', 'hi', 1, '2026-09-27 09:51:36', '2026-09-27 09:51:36', '2026-09-27 09:50:49'),
(14, 1, 0, 'ai', '🙋 Got it! Your message has been forwarded to our team (Admin / Product Manager).\n\nThey will reply to you as soon as possible — usually within a few hours during business hours.\n\n⏳ Please wait for their response. You can continue typing here if you have additional details to add.', 0, NULL, NULL, '2026-09-27 09:50:49'),
(15, 1, 10, 'customer', 'pwede mag pa rush ng order? willing to add pay', 1, '2026-09-27 09:51:36', '2026-09-27 09:51:36', '2026-09-27 09:51:30'),
(16, 1, 0, 'ai', '🙋 Got it! Your message has been forwarded to our team (Admin / Product Manager).\n\nThey will reply to you as soon as possible — usually within a few hours during business hours.\n\n⏳ Please wait for their response. You can continue typing here if you have additional details to add.', 0, NULL, NULL, '2026-09-27 09:51:30'),
(17, 1, 3, 'product_manager', 'pwede po', 1, '2026-09-27 09:51:41', '2026-09-27 09:51:41', '2026-09-27 09:51:40');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_proofs`
--

CREATE TABLE `delivery_proofs` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `rider_id` int(11) NOT NULL,
  `parcel_photo` varchar(255) NOT NULL,
  `payment_photo` varchar(255) DEFAULT NULL,
  `amount_collected` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT 'COD',
  `notes` text DEFAULT NULL,
  `recipient_name` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_proofs`
--

INSERT INTO `delivery_proofs` (`id`, `order_id`, `rider_id`, `parcel_photo`, `payment_photo`, `amount_collected`, `payment_method`, `notes`, `recipient_name`, `latitude`, `longitude`, `created_at`) VALUES
(1, 8, 3, 'assets/uploads/delivery_proofs/71281c0f203b4f106d5e2d5a.jpg', 'assets/uploads/delivery_proofs/a314ecfb1293c26bdeda5c07.jpg', 2.00, 'GCash', '', 'Jhona Arro', NULL, NULL, '2026-09-27 11:02:43'),
(2, 11, 3, 'assets/uploads/delivery_proofs/b2b399070733334f9eb2f906.jpg', 'assets/uploads/delivery_proofs/fa71943d47f892c741794a61.jpg', 25000.00, 'COD', '', 'Jhona Arro', NULL, NULL, '2026-09-27 11:18:56');

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
(8, 12, 'cerbitomarkie5@gmail.com', '897834', 'register', '2026-09-27 12:59:20', 1, '2026-09-27 04:49:20'),
(9, 13, 'estreracarlosmavean17@gmail.com', '323063', 'register', '2026-09-27 16:51:47', 1, '2026-09-27 08:41:47'),
(10, 10, 'jhonaarro55@gmail.com', '755055', 'reset', '2026-09-27 17:23:15', 1, '2026-09-27 09:13:15'),
(11, 12, 'cerbitomarkie5@gmail.com', '368415', 'reset', '2026-09-27 17:23:47', 1, '2026-09-27 09:21:47'),
(12, 12, 'cerbitomarkie5@gmail.com', '785762', 'reset', '2026-09-27 17:24:12', 1, '2026-09-27 09:22:12'),
(13, 12, 'cerbitomarkie5@gmail.com', '195545', 'register', '2026-09-27 17:30:10', 1, '2026-09-27 09:28:10');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(20) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `rider_id` int(11) DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `out_for_delivery_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `amount_collected` decimal(10,2) DEFAULT NULL,
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

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `rider_id`, `assigned_at`, `out_for_delivery_at`, `delivered_at`, `amount_collected`, `total_amount`, `status`, `shipping_address`, `contact_phone`, `notes`, `payment_method`, `delivery_option`, `delivery_fee`, `payment_status`, `payment_reference`, `payment_proof`, `paid_at`, `created_at`) VALUES
(1, 'ORD-000001', 2, NULL, NULL, NULL, NULL, NULL, 13000.00, 'processing', 'phase 3 area a acorda st payatas quezon citty', NULL, NULL, 'COD', 'standard', 0.00, 'unpaid', NULL, NULL, NULL, '2026-09-25 03:58:35'),
(2, 'ORD-20260925-ADF57', 6, NULL, NULL, NULL, NULL, NULL, 13000.00, 'pending', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY, QUEZON CITY, METRO MANILA 1119', '09278878724', NULL, 'Card', 'standard', 0.00, 'pending_verification', NULL, NULL, NULL, '2026-09-25 05:00:15'),
(3, 'ORD-20260925-6CE0F', 6, NULL, NULL, NULL, NULL, NULL, 25000.00, 'cancelled', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY, QUEZON CITY, METRO MANILA 1119', '09278878724', NULL, 'GCash', 'standard', 0.00, 'unpaid', NULL, NULL, NULL, '2026-09-25 05:46:23'),
(4, 'ORD-20260925-1A1D5', 6, NULL, NULL, NULL, NULL, NULL, 2.00, 'cancelled', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY, QUEZON CITY, METRO MANILA 1119', '09278878724', NULL, 'GCash', 'standard', 0.00, 'unpaid', NULL, NULL, NULL, '2026-09-25 05:56:27'),
(5, 'ORD-20260925-C262B', 6, NULL, NULL, NULL, NULL, NULL, 2.00, 'shipped', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY, QUEZON CITY, METRO MANILA 1119', '09278878724', NULL, 'GCash', 'standard', 0.00, 'pending_verification', '1045421919122', 'assets/uploads/pay_5_6ab6132b49bca.png', NULL, '2026-09-25 06:10:42'),
(6, 'ORD-20260925-345D5', 6, NULL, NULL, NULL, NULL, NULL, 2.00, 'delivered', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY, QUEZON CITY, METRO MANILA 1119', '09278878724', NULL, 'GCash', 'standard', 0.00, 'paid', '1045421919122', 'assets/uploads/pay_6_6ab614b36fb08.png', '2026-09-25 20:33:33', '2026-09-25 06:28:49'),
(7, 'ORD-20260925-EF663', 6, NULL, NULL, NULL, NULL, NULL, 2.00, 'pending', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY, QUEZON CITY, METRO MANILA 1119', '09278878724', NULL, 'COD', 'standard', 0.00, 'unpaid', NULL, NULL, NULL, '2026-09-25 12:38:49'),
(8, 'ORD-20260927-77975', 10, 3, '2026-09-27 19:00:20', '2026-09-27 19:00:20', '2026-09-27 19:02:43', 2.00, 2.00, 'delivered', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY, QUEZON CITY, METRO MANILA 1119', '09278878724', NULL, 'GCash', 'standard', 0.00, 'paid', '1045421919122', 'assets/uploads/pay_8_6ab8e9a398c9e.png', '2026-09-27 18:23:59', '2026-09-27 10:01:19'),
(9, 'ORD-20260927-A9750', 10, NULL, NULL, NULL, NULL, NULL, 13000.00, 'pending', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY, QUEZON CITY, METRO MANILA 1119', '09278878724', NULL, 'GCash', 'standard', 0.00, 'unpaid', NULL, NULL, NULL, '2026-09-27 11:07:47'),
(10, 'ORD-20260927-C0AB7', 10, NULL, NULL, NULL, NULL, NULL, 25000.00, 'pending', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY, QUEZON CITY, METRO MANILA 1119', '09278878724', NULL, 'GCash', 'standard', 0.00, 'unpaid', NULL, NULL, NULL, '2026-09-27 11:15:47'),
(11, 'ORD-20260927-0C794', 10, 3, '2026-09-27 19:18:15', '2026-09-27 19:18:15', '2026-09-27 19:18:56', 25000.00, 25000.00, 'delivered', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY, QUEZON CITY, METRO MANILA 1119', '09278878724', NULL, 'COD', 'standard', 0.00, 'paid', NULL, NULL, '2026-09-27 19:18:56', '2026-09-27 11:17:28');

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
(7, 7, 4, 1, 2.00),
(8, 8, 4, 1, 2.00),
(9, 9, 3, 1, 13000.00),
(10, 10, 1, 1, 25000.00),
(11, 11, 1, 1, 25000.00);

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
(15, 7, 'pending', 'Order ORD-20260925-EF663 placed with Standard (3-5 days)', 'Processing Center', '2026-09-25 12:38:49'),
(16, 6, 'shipped', 'Your package has been shipped', 'In Transit', '2026-09-27 09:15:45'),
(17, 6, 'delivered', 'Package has been delivered successfully', 'Customer Address', '2026-09-27 09:15:54'),
(18, 8, 'pending', 'Order ORD-20260927-77975 placed with Standard (3-5 days)', 'Processing Center', '2026-09-27 10:01:19'),
(19, 8, 'pending', 'Payment submitted via GCash. Awaiting admin verification.', 'Payment Center', '2026-09-27 10:02:11'),
(20, 8, 'pending', 'Payment verified and confirmed', 'Payment Center', '2026-09-27 10:23:59'),
(21, 8, 'shipped', 'Assigned to rider Anjonel Rosales (09278878724) for delivery', 'In Transit', '2026-09-27 11:00:20'),
(22, 8, 'delivered', 'Order delivered by Anjonel Rosales · ₱2.00 collected', 'Customer Address', '2026-09-27 11:02:43'),
(23, 9, 'pending', 'Order ORD-20260927-A9750 placed with Standard (3-5 days)', 'Processing Center', '2026-09-27 11:07:47'),
(24, 10, 'pending', 'Order ORD-20260927-C0AB7 placed with Standard (3-5 days)', 'Processing Center', '2026-09-27 11:15:47'),
(25, 11, 'pending', 'Order ORD-20260927-0C794 placed with Standard (3-5 days)', 'Processing Center', '2026-09-27 11:17:28'),
(26, 11, 'pending', 'Order placed with Cash on Delivery. Payment will be collected upon delivery.', 'Payment Center', '2026-09-27 11:17:30'),
(27, 11, 'processing', 'Order is being prepared in our warehouse', 'Warehouse', '2026-09-27 11:18:06'),
(28, 11, 'shipped', 'Assigned to rider Anjonel Rosales (09278878724) for delivery', 'In Transit', '2026-09-27 11:18:15'),
(29, 11, 'delivered', 'Order delivered by Anjonel Rosales · ₱25,000.00 collected', 'Customer Address', '2026-09-27 11:18:56');

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
(1, 'Laptop', 'Gaming laptop', 25000.00, 'General', 12, 'assets/uploads/prod_6ab5c09bad536.png', '2026-09-25 00:30:19'),
(3, 'gaming pc', 'gaming pc and for student, affordable', 13000.00, 'Electronics', 0, 'assets/uploads/prod_6ab5c22428e3c.png', '2026-09-25 00:36:52'),
(4, 'Vape V2', 'Black elite vape Version 2', 2.00, 'Electronics', 0, 'assets/uploads/prod_6ab60ccb814db.png', '2026-09-25 05:54:22');

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
  `media_url` varchar(255) DEFAULT NULL,
  `media_type` enum('image','video') DEFAULT NULL,
  `is_verified_purchase` tinyint(1) DEFAULT 0,
  `helpful_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_reviews`
--

INSERT INTO `product_reviews` (`id`, `product_id`, `user_id`, `order_id`, `rating`, `review_text`, `media_url`, `media_type`, `is_verified_purchase`, `helpful_count`, `created_at`, `updated_at`) VALUES
(1, 4, 6, NULL, 5, 'sobrang solid humipak habang nag cocode e noh', NULL, NULL, 0, 0, '2026-09-27 09:16:18', '2026-09-27 09:57:59');

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
-- Table structure for table `review_media`
--

CREATE TABLE `review_media` (
  `id` int(11) NOT NULL,
  `review_id` int(11) NOT NULL,
  `file_url` varchar(255) NOT NULL,
  `media_type` enum('image','video') NOT NULL DEFAULT 'image',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `review_media`
--

INSERT INTO `review_media` (`id`, `review_id`, `file_url`, `media_type`, `created_at`) VALUES
(1, 1, 'assets/uploads/reviews/03be795b27b20cefe90d3485.jpg', 'image', '2026-09-27 09:57:59');

-- --------------------------------------------------------

--
-- Table structure for table `riders`
--

CREATE TABLE `riders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `vehicle_type` varchar(50) DEFAULT 'Motorcycle',
  `plate_number` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `riders`
--

INSERT INTO `riders` (`id`, `user_id`, `full_name`, `phone`, `email`, `vehicle_type`, `plate_number`, `status`, `created_at`) VALUES
(3, 17, 'Anjonel Rosales', '09278878724', 'cheaterisreal04@gmail.com', 'Motorcycle', 'ABC789', 'active', '2026-09-27 10:52:50');

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
  `role` enum('admin','product_manager','customer','rider') DEFAULT 'customer',
  `status` enum('active','inactive') DEFAULT 'active',
  `email_verified` tinyint(1) DEFAULT 0,
  `profile_completed` tinyint(1) DEFAULT 0,
  `must_change_password` tinyint(1) DEFAULT 0,
  `temp_password_issued_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `phone`, `address`, `city`, `province`, `postal_code`, `password`, `full_name`, `role`, `status`, `email_verified`, `profile_completed`, `must_change_password`, `temp_password_issued_at`, `created_at`) VALUES
(1, 'admin', 'admin@ecommerce.com', NULL, NULL, NULL, NULL, NULL, '$2y$10$Ef/Wi80jY5/NNIUO7kH0yen37ffqmNgdAjL5J1DyTpsJ1tmJSiWrm', 'System Admin', 'admin', 'active', 1, 1, 0, NULL, '2026-09-24 14:21:26'),
(2, 'customer', 'customer@test.com', '09171234567', '123 Sample Street', 'Manila', 'Metro Manila', '1000', '$2y$10$Hbb7sYr3zAFyikF2QDWqq.O7EKKfmvFdxbREHIEXWtf4SD5qv8hPu', 'John Doe', 'customer', 'active', 1, 1, 0, NULL, '2026-09-24 14:22:10'),
(3, 'pm', 'pm@ecommerce.com', NULL, NULL, NULL, NULL, NULL, '$2y$10$QVuImCrj3wRU1a3NSF46Y.k0f81uJXm6wkj2BGhF5EF2Y3gizDCyy', 'Product Manager', 'product_manager', 'active', 1, 1, 0, NULL, '2026-09-25 03:50:52'),
(5, 'Christian', 'christianjoco03@gmail.com', '09278878724', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY', 'QUEZON CITY', 'METRO MANILA', '1119', '$2y$10$tMsKzLyBt3NYT8Ym/btk/OAVlTQpTPSJGp5eq.s/aYpu28oULE2uK', 'Christian joco', 'customer', 'active', 0, 1, 0, NULL, '2026-09-25 04:48:40'),
(6, 'Mavean', 'yengmavean@gmail.com', '09278878724', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY', 'QUEZON CITY', 'METRO MANILA', '1119', '$2y$10$h0Itr0qTZUxpmF8PAHGu7OLGJsRG0nVN/0S0Io0MF6/tD2M73mz5W', 'ESTRERA, CARLOS MAVEAN L.', 'customer', 'active', 1, 1, 0, NULL, '2026-09-25 04:56:39'),
(10, 'Jhona', 'jhonaarro55@gmail.com', '09278878724', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY', 'QUEZON CITY', 'METRO MANILA', '1119', '$2y$10$qPi1a3kUDBrS5fWlN1a4kOJEQJM0Drl8IQAREGShVgnRxi9xq2SsS', 'Jhona Arro', 'customer', 'active', 1, 1, 0, NULL, '2026-09-26 10:24:02'),
(12, 'markc', 'cerbitomarkie5@gmail.com', '09278878724', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY', 'QUEZON CITY', 'METRO MANILA', '1119', '$2y$10$pErpLrmUlSWdVCZLbIhwBuyJSf/61BINy0AqLPWoKPiGEIiTOAQRq', 'mark', 'customer', 'active', 1, 1, 0, NULL, '2026-09-27 04:49:20'),
(13, 'caloy', 'estreracarlosmavean17@gmail.com', '09278878724', 'PHASE 3 AREA A ACORDA ST. PAYATAS LUPANG PANGAKO QUEZON CITY', 'QUEZON CITY', 'METRO MANILA', '1119', '$2y$10$WQiW5YMcib14GyG2wTyH0uggNv8ET1OANr66J440hf95kKQlWll0m', 'ESTRERA, CARLOS MAVEAN L.', 'customer', 'active', 1, 1, 0, NULL, '2026-09-27 08:41:47'),
(17, 'Anjonel', 'cheaterisreal04@gmail.com', '09278878724', NULL, NULL, NULL, NULL, '$2y$10$KSa1i5pntgCzTbgTYTu8R.GxGbCZAgbgGTuS6iS49E62gq4.SpTme', 'Anjonel Rosales', 'rider', 'active', 1, 1, 0, '2026-09-27 18:59:20', '2026-09-27 10:52:50');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ai_faq`
--
ALTER TABLE `ai_faq`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conversation_id` (`conversation_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `conv_id_idx` (`conversation_id`,`id`);

--
-- Indexes for table `delivery_proofs`
--
ALTER TABLE `delivery_proofs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `rider_id` (`rider_id`);

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
  ADD PRIMARY KEY (`id`),
  ADD KEY `rider_id` (`rider_id`);

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
-- Indexes for table `review_media`
--
ALTER TABLE `review_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `review_id` (`review_id`);

--
-- Indexes for table `riders`
--
ALTER TABLE `riders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

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
-- AUTO_INCREMENT for table `ai_faq`
--
ALTER TABLE `ai_faq`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `delivery_proofs`
--
ALTER TABLE `delivery_proofs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `email_otps`
--
ALTER TABLE `email_otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `order_tracking`
--
ALTER TABLE `order_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `returns`
--
ALTER TABLE `returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `review_media`
--
ALTER TABLE `review_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `riders`
--
ALTER TABLE `riders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
