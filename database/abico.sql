-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 01, 2025 at 03:05 PM
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
-- Database: `abico`
--

-- --------------------------------------------------------

--
-- Table structure for table `check_exchanges`
--

CREATE TABLE `check_exchanges` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `check_number` varchar(100) NOT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `check_date` date DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('pending','cleared','bounced') DEFAULT 'pending',
  `received_by_user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE `deliveries` (
  `id` int(11) NOT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `delivery_date` date NOT NULL,
  `status` enum('pending','delivered','cancelled') DEFAULT 'pending',
  `delivered_by` varchar(255) DEFAULT NULL,
  `received_by_user_id` int(11) DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `confirm_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_items`
--

CREATE TABLE `delivery_items` (
  `id` int(11) NOT NULL,
  `delivery_id` int(11) NOT NULL,
  `purchase_order_item_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `received_quantity` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `current_stock` int(11) DEFAULT 0,
  `reorder_level` int(11) DEFAULT 0,
  `selling_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Cash', 'Cash Payment at Store', '2025-07-31 15:03:31'),
(2, 'Online Transfer', 'Gcash, Paymaya, Bank Transfer', '2025-07-31 15:03:31'),
(3, 'Check', 'Payment through Check', '2025-07-31 15:03:31');

-- --------------------------------------------------------

--
-- Table structure for table `payment_records`
--

CREATE TABLE `payment_records` (
  `id` int(11) NOT NULL,
  `sales_transaction_id` int(11) DEFAULT NULL,
  `sales_debt_id` int(11) DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `amount_tendered` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_due` decimal(12,2) NOT NULL DEFAULT 0.00,
  `change_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `received_by_user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `order_date` date NOT NULL,
  `status` enum('pending','approved','cancelled','completed') DEFAULT 'pending',
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `created_by_user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `total_price` decimal(12,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_debts`
--

CREATE TABLE `sales_debts` (
  `id` int(11) NOT NULL,
  `sales_transaction_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `amount_paid` decimal(12,2) DEFAULT 0.00,
  `balance_due` decimal(12,2) GENERATED ALWAYS AS (`total_amount` - `amount_paid`) STORED,
  `due_date` date DEFAULT NULL,
  `status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_transactions`
--

CREATE TABLE `sales_transactions` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method_id` int(11) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `status` enum('paid','debt','partial') DEFAULT 'paid',
  `created_by_user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_transaction_items`
--

CREATE TABLE `sales_transaction_items` (
  `id` int(11) NOT NULL,
  `sales_transaction_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `total_price` decimal(12,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `movement_type` enum('in','out','adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference_type` enum('delivery','sales','return','adjustment') DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `contact_number`, `email`, `address`, `created_at`, `updated_at`) VALUES
(1, 'Falcor Marketing', '', '', '', 'Sagay City', '2025-08-01 10:24:40', '2025-08-01 12:23:06'),
(2, 'Philphost', '', '', '', '', '2025-08-01 12:01:39', '2025-08-01 12:01:39'),
(3, 'Hari Marketing', '', '', '', '', '2025-08-01 12:31:10', '2025-08-01 12:31:25'),
(4, 'UHI', '', '', '', '', '2025-08-01 12:32:09', '2025-08-01 12:32:09'),
(5, 'Atlas', '', '', '', '', '2025-08-01 12:32:20', '2025-08-01 12:32:38'),
(6, 'Mandalagan Commodities, Inc.', '', '', '', '', '2025-08-01 12:32:52', '2025-08-01 12:32:59'),
(7, 'Solane', '', '', '', '', '2025-08-01 12:33:18', '2025-08-01 12:33:18'),
(8, 'Town Gaz', '', '', '', '', '2025-08-01 12:33:25', '2025-08-01 12:33:25'),
(9, 'Pryce Gas', '', '', '', '', '2025-08-01 12:33:30', '2025-08-01 12:33:37');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff','cashier') DEFAULT 'staff',
  `contact` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `contact`, `created_at`, `updated_at`) VALUES
(1, 'Administrator', 'admin', '$2y$10$sTqqa5woDdDeKSP54w8.WOrYcHIAScubOHaHkO5PKznuQr7ax562.', 'admin', '', '2025-07-31 22:03:31', '2025-08-01 02:40:52');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `check_exchanges`
--
ALTER TABLE `check_exchanges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `received_by_user_id` (`received_by_user_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_order_id` (`purchase_order_id`),
  ADD KEY `received_by_user_id` (`received_by_user_id`);

--
-- Indexes for table `delivery_items`
--
ALTER TABLE `delivery_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `delivery_id` (`delivery_id`),
  ADD KEY `purchase_order_item_id` (`purchase_order_item_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_records`
--
ALTER TABLE `payment_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sales_transaction_id` (`sales_transaction_id`),
  ADD KEY `sales_debt_id` (`sales_debt_id`),
  ADD KEY `payment_method_id` (`payment_method_id`),
  ADD KEY `received_by_user_id` (`received_by_user_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by_user_id` (`created_by_user_id`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_order_id` (`purchase_order_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `sales_debts`
--
ALTER TABLE `sales_debts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sales_transaction_id` (`sales_transaction_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `sales_transactions`
--
ALTER TABLE `sales_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `payment_method_id` (`payment_method_id`),
  ADD KEY `created_by_user_id` (`created_by_user_id`);

--
-- Indexes for table `sales_transaction_items`
--
ALTER TABLE `sales_transaction_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sales_transaction_id` (`sales_transaction_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `check_exchanges`
--
ALTER TABLE `check_exchanges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_items`
--
ALTER TABLE `delivery_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payment_records`
--
ALTER TABLE `payment_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_debts`
--
ALTER TABLE `sales_debts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_transactions`
--
ALTER TABLE `sales_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_transaction_items`
--
ALTER TABLE `sales_transaction_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `check_exchanges`
--
ALTER TABLE `check_exchanges`
  ADD CONSTRAINT `check_exchanges_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `check_exchanges_ibfk_2` FOREIGN KEY (`received_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD CONSTRAINT `deliveries_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`),
  ADD CONSTRAINT `deliveries_ibfk_2` FOREIGN KEY (`received_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `delivery_items`
--
ALTER TABLE `delivery_items`
  ADD CONSTRAINT `delivery_items_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`),
  ADD CONSTRAINT `delivery_items_ibfk_2` FOREIGN KEY (`purchase_order_item_id`) REFERENCES `purchase_order_items` (`id`),
  ADD CONSTRAINT `delivery_items_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Constraints for table `payment_records`
--
ALTER TABLE `payment_records`
  ADD CONSTRAINT `payment_records_ibfk_1` FOREIGN KEY (`sales_debt_id`) REFERENCES `sales_debts` (`id`),
  ADD CONSTRAINT `payment_records_ibfk_2` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`),
  ADD CONSTRAINT `payment_records_ibfk_3` FOREIGN KEY (`received_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`),
  ADD CONSTRAINT `purchase_order_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Constraints for table `sales_debts`
--
ALTER TABLE `sales_debts`
  ADD CONSTRAINT `sales_debts_ibfk_1` FOREIGN KEY (`sales_transaction_id`) REFERENCES `sales_transactions` (`id`),
  ADD CONSTRAINT `sales_debts_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `sales_transactions`
--
ALTER TABLE `sales_transactions`
  ADD CONSTRAINT `sales_transactions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `sales_transactions_ibfk_2` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`),
  ADD CONSTRAINT `sales_transactions_ibfk_3` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `sales_transaction_items`
--
ALTER TABLE `sales_transaction_items`
  ADD CONSTRAINT `sales_transaction_items_ibfk_1` FOREIGN KEY (`sales_transaction_id`) REFERENCES `sales_transactions` (`id`),
  ADD CONSTRAINT `sales_transaction_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
