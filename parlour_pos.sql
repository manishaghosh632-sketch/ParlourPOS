-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 15, 2026 at 12:21 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `parlour_pos`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('Pending','Confirmed','Checked In','In Progress','Completed','Cancelled','No Show') DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `customer_id`, `service_id`, `staff_id`, `appointment_date`, `appointment_time`, `status`, `notes`, `created_at`) VALUES
(3, 3, 5, 25, '2026-09-11', '10:30:00', 'Completed', 'Natural look', '2026-09-10 17:03:26'),
(4, 3, 1, 25, '2026-09-11', '14:30:00', 'Pending', 'Clean and clear cutting', '2026-09-11 05:06:52'),
(5, 4, 5, 25, '2026-09-12', '18:06:00', 'Pending', '', '2026-09-11 07:36:14');

-- --------------------------------------------------------

--
-- Table structure for table `business_settings`
--

CREATE TABLE `business_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `catalog_items`
--

CREATE TABLE `catalog_items` (
  `id` int(11) NOT NULL,
  `type` enum('Service','Product') NOT NULL,
  `category` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `tax_percent` decimal(5,2) DEFAULT 0.00,
  `current_stock` int(11) DEFAULT NULL,
  `min_stock` int(11) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `stock` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `catalog_items`
--

INSERT INTO `catalog_items` (`id`, `type`, `category`, `name`, `image`, `price`, `tax_percent`, `current_stock`, `min_stock`, `status`, `created_at`, `stock`) VALUES
(1, 'Service', 'Hair', 'Premium Haircut & Styling', NULL, 1200.00, 5.00, NULL, NULL, 'Active', '2026-09-06 14:35:13', 0),
(2, 'Service', 'Facial', 'Gold Glow Facial', NULL, 2500.00, 5.00, NULL, NULL, 'Active', '2026-09-06 14:35:13', 0),
(3, 'Product', 'Skin Care', 'Radiance Night Serum', NULL, 850.00, 12.00, 50, 10, 'Active', '2026-09-06 14:35:13', 10),
(4, 'Product', 'Hair Care', 'Argan Oil Shampoo', NULL, 600.00, 12.00, 30, 5, 'Active', '2026-09-06 14:35:13', 30),
(5, 'Service', 'Makeup', 'Bridal Makeup', NULL, 3500.00, 5.00, NULL, NULL, 'Active', '2026-09-08 13:54:27', 0),
(6, 'Product', 'Skin Care', 'Whiteglow Herbal Facewash', NULL, 790.00, 0.00, 15, 2, 'Active', '2026-09-09 06:59:10', 15),
(7, 'Product', 'Cream', 'Smoothy Body hair Removal Cream', NULL, 390.00, 0.00, NULL, NULL, 'Active', '2026-09-09 18:44:17', 50);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `total_spending` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `google_id` varchar(255) DEFAULT NULL,
  `loyalty_points` int(11) DEFAULT 0,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `membership_tier` varchar(50) DEFAULT 'Standard'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `mobile`, `email`, `password_hash`, `status`, `total_spending`, `created_at`, `google_id`, `loyalty_points`, `reset_token`, `reset_token_expiry`, `membership_tier`) VALUES
(3, 'Suman Ghosh', '9123349100', 'manishaghosh632@gmail.com', '$2y$10$up58sy9apzQh2HvWZ1rJA.4CAf3V7JUydREjd/UzTdnkuxThQHfkG', 'Active', 0.00, '2026-09-10 12:05:00', NULL, 0, NULL, NULL, 'Gold'),
(4, 'PRITAM BHAUMIK', '9002260825', 'pritambhaumiksmail1203@gmail.com', '$2y$10$gWmrjMGGoUiplY7umhciGeHGs.zS3WsaRfY1ae1rUgFk4/4b5R6he', 'Active', 0.00, '2026-09-11 07:26:24', NULL, 0, NULL, NULL, 'Standard');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `customer_id`, `appointment_id`, `staff_id`, `service_id`, `rating`, `comments`, `created_at`) VALUES
(2, 3, 3, 25, 5, 4, 'The beautician did excellent job and I\'m quite satisfied', '2026-09-11 05:14:23');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `cashier_id` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `grand_total` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','UPI','Card','Bank Transfer','Other') NOT NULL,
  `payment_status` enum('Paid','Partial','Due','Refunded') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `catalog_id` int(11) NOT NULL,
  `staff_assigned_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `staff_commission` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','UPI','Card','Bank Transfer','Other') NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `product_id`, `user_id`, `quantity`, `unit_price`, `total_cost`, `created_at`) VALUES
(1, 3, 1, 10, 10.00, 100.00, '2026-09-09 18:59:19'),
(2, 7, 1, 50, 280.00, 14000.00, '2026-09-09 19:01:07'),
(3, 4, 22, 30, 300.00, 9000.00, '2026-09-11 04:41:26'),
(4, 6, 1, 15, 300.00, 4500.00, '2026-09-11 07:15:31');

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

CREATE TABLE `refunds` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `processor_id` int(11) NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL,
  `reason` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_beauticians`
--

CREATE TABLE `service_beauticians` (
  `service_id` int(11) NOT NULL,
  `beautician_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_beauticians`
--

INSERT INTO `service_beauticians` (`service_id`, `beautician_id`) VALUES
(1, 25),
(2, 16),
(5, 25);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'parlour_name', 'Crafting Styles', '2026-09-09 11:27:12'),
(2, 'currency', 'INR', '2026-09-06 14:35:13'),
(3, 'tax_rate', '5.00', '2026-09-06 14:35:13');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('Super Admin','Manager','Receptionist','Beautician') NOT NULL,
  `commission_type` enum('Percentage','Fixed','None') DEFAULT 'None',
  `commission_value` decimal(10,2) DEFAULT 0.00,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `photo` varchar(255) DEFAULT NULL,
  `document` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `google_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `role`, `commission_type`, `commission_value`, `status`, `photo`, `document`, `created_at`, `google_id`) VALUES
(1, 'Suman Ghosh', 'admin@parlour.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', 'None', 0.00, 'Active', NULL, NULL, '2026-09-06 14:35:13', NULL),
(16, 'Suman Ghosh', 'manishaghosh632@gmail.com', '9432249614', '$2y$10$iIBZm8TbwYRl7KxVavfZve01VC3u6CAze8XlO9PQyAazx1bszCSwG', 'Beautician', 'None', 0.00, 'Active', NULL, NULL, '2026-09-09 19:20:50', NULL),
(17, 'Sanat Ghosh', 'sanat6519@gmail.com', '9432590252', '$2y$10$BhjbyAY/JvgbaVBOeqEeEOsaYajt2jlBv4sYt5.BnDdLxqj8R.5p.', 'Receptionist', 'None', 0.00, 'Active', NULL, NULL, '2026-09-10 03:36:07', NULL),
(22, 'Sohom Sarkar', 'johnsoha033@gmail.com', '7431097978', '$2y$10$yQm.bpR2e5CZN3RcmEwQE.JocfGEiDA.IeUz8/kETKKtAgRrf5Ywy', 'Manager', 'None', 0.00, 'Active', NULL, NULL, '2026-09-10 12:14:26', NULL),
(25, 'Ankit Roy', 'ankitroy181104@gmail.com', '9153573191', '$2y$10$PT4/LQtBAB0oEjvZ84b0keImlbgxN0orQCistIEUZAzsfMnvquNNy', 'Beautician', 'None', 0.00, 'Active', NULL, NULL, '2026-09-10 12:25:56', NULL),
(26, 'Samprity Maity', 'sampritimaity20@gmail.com', '7872241699', '$2y$10$r7UYk3n8KNo9Nbtt.46tSeGYJZdF0CyruMaBwyZDecctgbx92QJQW', 'Beautician', 'None', 0.00, 'Active', NULL, NULL, '2026-09-11 07:12:29', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `business_settings`
--
ALTER TABLE `business_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `catalog_items`
--
ALTER TABLE `catalog_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mobile` (`mobile`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `cashier_id` (`cashier_id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `catalog_id` (`catalog_id`),
  ADD KEY `staff_assigned_id` (`staff_assigned_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `cashier_id` (`cashier_id`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `refunds`
--
ALTER TABLE `refunds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `processor_id` (`processor_id`);

--
-- Indexes for table `service_beauticians`
--
ALTER TABLE `service_beauticians`
  ADD PRIMARY KEY (`service_id`,`beautician_id`),
  ADD KEY `beautician_id` (`beautician_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `catalog_items`
--
ALTER TABLE `catalog_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `refunds`
--
ALTER TABLE `refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `catalog_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoice_items_ibfk_2` FOREIGN KEY (`catalog_id`) REFERENCES `catalog_items` (`id`),
  ADD CONSTRAINT `invoice_items_ibfk_3` FOREIGN KEY (`staff_assigned_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `refunds`
--
ALTER TABLE `refunds`
  ADD CONSTRAINT `refunds_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `refunds_ibfk_2` FOREIGN KEY (`processor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `service_beauticians`
--
ALTER TABLE `service_beauticians`
  ADD CONSTRAINT `service_beauticians_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `catalog_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_beauticians_ibfk_2` FOREIGN KEY (`beautician_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
