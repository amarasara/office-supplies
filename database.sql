-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 03, 2026 at 12:24 PM
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
-- Database: `office_supplies`
--
CREATE DATABASE IF NOT EXISTS `office_supplies` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `office_supplies`;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = shown in dropdown, 0 = deleted (soft delete; history keeps the name)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `is_active`) VALUES
(1, 'บัญชี', 1),
(2, 'จัดซื้อ', 1),
(3, 'ทรัพยากรบุคคล', 1),
(4, 'ขนส่ง', 1),
(5, 'การตลาด', 1),
(6, 'QC', 1),
(7, 'QA', 1);

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(10) UNSIGNED NOT NULL,
  `group_name` varchar(180) NOT NULL COMMENT 'Product group / brand name',
  `name` varchar(255) NOT NULL COMMENT 'Variant / full item name',
  `image` varchar(300) NOT NULL DEFAULT 'default.png',
  `unit` varchar(60) NOT NULL DEFAULT 'ชิ้น',
  `pack_qty` int(10) UNSIGNED DEFAULT NULL COMMENT 'Pieces of unit per pack, e.g. 12 -> 1 แพ็ค / 12 ชิ้น. NULL/empty = not shown.',
  `current_stock` int(11) NOT NULL DEFAULT 0,
  `min_stock` int(11) NOT NULL DEFAULT 5,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `group_name`, `name`, `image`, `unit`, `pack_qty`, `current_stock`, `min_stock`, `created_at`) VALUES
(1, 'สำนักงาน', 'ปากกาแลนเซอร์(ไลน์ผลิต) นง.', 'default.png', 'ด้าม', NULL, 0, 5, '2026-09-03 10:52:59'),
(2, 'สำนักงาน', 'ปากกาแลนเซอร์(ไลน์ผลิต) แดง', 'default.png', 'ด้าม', NULL, 38, 5, '2026-09-03 10:52:59'),
(3, 'สำนักงาน', 'ปากกาตรางช้าง(ออฟฟิค) นง.', 'default.png', 'ด้าม', NULL, 163, 5, '2026-09-03 10:52:59'),
(4, 'สำนักงาน', 'ปากกาตรางช้าง(ออฟฟิค) แดง', 'default.png', 'ด้าม', NULL, 10, 5, '2026-09-03 10:52:59'),
(5, 'สำนักงาน', 'เทปลบคำผิด', 'default.png', 'อัน', NULL, 5, 5, '2026-09-03 10:52:59'),
(6, 'ปากกา', 'ปากกาไฮไลท์ เหลือง', 'default.png', 'ด้าม', NULL, 0, 5, '2026-09-03 10:52:59'),
(7, 'สำนักงาน', 'ปากกาเคมี เหลือง', 'default.png', 'ด้าม', NULL, 1, 5, '2026-09-03 10:52:59'),
(8, 'สำนักงาน', 'ปากกาเคมี แดง', 'default.png', 'ด้าม', NULL, 6, 5, '2026-09-03 10:52:59'),
(9, 'สำนักงาน', 'ปากกาเคมี ดำ', 'default.png', 'ด้าม', NULL, 1, 5, '2026-09-03 10:52:59'),
(10, 'สำนักงาน', 'ปากกาเคมี นง.', 'default.png', 'ด้าม', NULL, 17, 5, '2026-09-03 10:52:59'),
(11, 'ปากกา', 'ปากกาไวท์บอร์ด ดำ', 'default.png', 'ด้าม', NULL, 1, 5, '2026-09-03 10:52:59'),
(12, 'ปากกา', 'ปากกาไวท์บอร์ด แดง', 'default.png', 'ด้าม', NULL, 1, 5, '2026-09-03 10:52:59'),
(13, 'ปากกา', 'ปากกาไวท์บอร์ด นง.', 'default.png', 'ด้าม', NULL, 3, 5, '2026-09-03 10:52:59'),
(14, 'สำนักงาน', 'ปากกาไวท์บอร์ด เขียว', 'default.png', 'ด้าม', NULL, 1, 5, '2026-09-03 10:52:59'),
(15, 'สำนักงาน', 'หมึกเติมปากกาเคมี', 'default.png', 'กล่อง', NULL, 8, 5, '2026-09-03 10:52:59'),
(16, 'สำนักงาน', 'หมึกเติมแท่นประทับตรายาง', 'default.png', 'กล่อง', NULL, 4, 5, '2026-09-03 10:52:59'),
(17, 'สำนักงาน', 'แท่นหมึกประทับตรายางพิมพ์', 'default.png', 'กล่อง', NULL, 1, 5, '2026-09-03 10:52:59'),
(18, 'สำนักงาน', 'ตรายางปั๊ม (คำว่า อนุญาต)', 'default.png', 'อัน', NULL, 1, 5, '2026-09-03 10:52:59'),
(19, 'สำนักงาน', 'ดินสอไม้', 'default.png', 'แท่ง', NULL, 4, 5, '2026-09-03 10:52:59'),
(20, 'สำนักงาน', 'ไม้บรรทัด', 'default.png', 'อัน', NULL, 0, 5, '2026-09-03 10:52:59'),
(21, 'กาว', 'กาวแท่ง', 'default.png', 'แท่ง', NULL, 3, 5, '2026-09-03 10:52:59'),
(22, 'สำนักงาน', 'เครื่องคิดเลข', 'default.png', 'เครื่อง', NULL, 1, 5, '2026-09-03 10:52:59'),
(23, 'สำนักงาน', 'ปฏิทิน', 'default.png', 'อัน', NULL, 0, 5, '2026-09-03 10:52:59'),
(24, 'สำนักงาน', 'แม็ก', 'default.png', 'อัน', NULL, 1, 5, '2026-09-03 10:52:59'),
(25, 'สำนักงาน', 'ที่ถอดลวด เย็บกระดาษ', 'default.png', 'อัน', NULL, 0, 5, '2026-09-03 10:52:59'),
(26, 'แม็ก', 'ลูกแม็ก NO.10-1M', 'default.png', 'กล่อง', NULL, 87, 5, '2026-09-03 10:52:59'),
(27, 'แม็ก', 'ลูกแม็ก NO.35-1M', 'default.png', 'กล่อง', NULL, 6, 5, '2026-09-03 10:52:59'),
(28, 'สำนักงาน', 'ลวดเสียบ', 'default.png', 'กล่อง', NULL, 12, 5, '2026-09-03 10:52:59'),
(29, 'สำนักงาน', 'แท่นตัดเทป', 'default.png', 'อัน', NULL, 0, 5, '2026-09-03 10:52:59'),
(30, 'สำนักงาน', 'เทป 2 หน้าแบบหนา', 'default.png', 'ม้วน', NULL, 2, 5, '2026-09-03 10:52:59'),
(31, 'สำนักงาน', 'เทป 2 หน้าแบบบาง', 'default.png', 'ม้วน', NULL, 0, 5, '2026-09-03 10:52:59'),
(32, 'สำนักงาน', 'เทปใส 1"เล็ก', 'default.png', 'ม้วน', NULL, 33, 5, '2026-09-03 10:52:59'),
(33, 'สำนักงาน', 'เทปใส 2" ใหญ่', 'default.png', 'ม้วน', NULL, 1, 5, '2026-09-03 10:52:59'),
(34, 'สำนักงาน', 'ฟุตเหล็ก', 'default.png', 'อัน', NULL, 5, 5, '2026-09-03 10:52:59'),
(35, 'กรรไกร', 'กรรไกร', 'item_35_1788407994.jpg', 'อัน', NULL, 0, 5, '2026-09-03 10:52:59'),
(36, 'คัตเตอร์', 'ใบมีดคัตเตอร์ ใหญ่', 'default.png', 'กล่อง', NULL, 6, 5, '2026-09-03 10:52:59'),
(37, 'คัตเตอร์', 'ใบมีดคัตเตอร์ เล็ก', 'default.png', 'กล่อง', NULL, 8, 5, '2026-09-03 10:52:59'),
(38, 'คลิปดำ', 'คลิปดำ NO.107 (60 mm)', 'default.png', 'ชิ้น', 6, 18, 5, '2026-09-03 10:52:59'),
(39, 'คลิปดำ', 'คลิปดำ NO.108 (22mm)', 'default.png', 'ชิ้น', 12, 39, 5, '2026-09-03 10:52:59'),
(40, 'คลิปดำ', 'คลิปดำ NO.109 (17.5 mm)', 'default.png', 'ชิ้น', 12, 78, 5, '2026-09-03 10:52:59'),
(41, 'คลิปดำ', 'คลิปดำ NO.110 (12 mm)', 'default.png', 'ชิ้น', 12, 84, 5, '2026-09-03 10:52:59'),
(42, 'คลิปดำ', 'คลิปดำ NO.111 (10 mm)', 'default.png', 'ชิ้น', 12, 0, 5, '2026-09-03 10:52:59'),
(43, 'คลิปดำ', 'คลิปดำ NO.112 (8 mm)', 'default.png', 'ชิ้น', 12, 60, 5, '2026-09-03 10:52:59'),
(44, 'คลิปดำ', 'คลิปดำ NO.113', 'default.png', 'ชิ้น', NULL, 96, 5, '2026-09-03 10:52:59'),
(45, 'สำนักงาน', 'ถ่านเม็ดกระดุม 3 A CR2032', 'default.png', 'ก้อน', NULL, 9, 5, '2026-09-03 10:52:59'),
(46, 'สำนักงาน', 'ถ่านเม็ดกระดุม 1.5V LR1130', 'default.png', 'ก้อน', NULL, 5, 5, '2026-09-03 10:52:59'),
(47, 'สำนักงาน', 'ถ่านเขียว', 'default.png', 'ก้อน', NULL, 13, 5, '2026-09-03 10:52:59'),
(48, 'สำนักงาน', 'ถ่าน 9V', 'default.png', 'ก้อน', NULL, 4, 5, '2026-09-03 10:52:59'),
(49, 'สำนักงาน', 'ถ่าน AA', 'default.png', 'ก้อน', NULL, 27, 5, '2026-09-03 10:52:59'),
(50, 'สำนักงาน', 'ถ่าน AAA', 'default.png', 'ก้อน', NULL, 0, 5, '2026-09-03 10:52:59'),
(51, 'สำนักงาน', 'ซองจดหมายขาว', 'default.png', 'ซอง', 50, 516, 5, '2026-09-03 10:52:59'),
(52, 'สำนักงาน', 'ซองน้ำตาล A4', 'default.png', 'ซอง', NULL, 0, 5, '2026-09-03 10:52:59'),
(53, 'สำนักงาน', 'ซองน้ำตาล A6', 'default.png', 'ซอง', NULL, 13, 5, '2026-09-03 10:52:59'),
(54, 'สำนักงาน', 'กระดาษ A4', 'default.png', 'รีม', NULL, 22, 5, '2026-09-03 10:52:59'),
(55, 'สำนักงาน', 'กระดาษไฮเจ็ท', 'default.png', 'แผ่น', NULL, 39, 5, '2026-09-03 10:52:59'),
(56, 'กระดาษ', 'กระดาษดับเบิ้ล 10', 'default.png', 'แผ่น', NULL, 0, 5, '2026-09-03 10:52:59'),
(57, 'สำนักงาน', 'กระดาษ Copy คาร์บอน', 'default.png', 'แผ่น', NULL, 100, 5, '2026-09-03 10:52:59'),
(58, 'สำนักงาน', 'กระดาษสีแดง', 'default.png', 'แผ่น', NULL, 0, 5, '2026-09-03 10:52:59'),
(59, 'สำนักงาน', 'กระดาษสีเหลือง', 'default.png', 'แผ่น', NULL, 900, 5, '2026-09-03 10:52:59'),
(60, 'สำนักงาน', 'กระดาษสีส้ม', 'default.png', 'แผ่น', NULL, 900, 5, '2026-09-03 10:52:59'),
(61, 'สำนักงาน', 'โพตอิท 3*3 (มีกาว)', 'default.png', 'ชิ้น', NULL, 0, 5, '2026-09-03 10:52:59'),
(62, 'โน๊ต', 'กระดาษโน๊ต (ไม่มีกาว)', 'default.png', 'แพ็ค', NULL, 2, 5, '2026-09-03 10:52:59'),
(63, 'กระดาษ', 'แผ่นเคลือบ A4', 'default.png', 'แผ่น', NULL, 290, 5, '2026-09-03 10:52:59'),
(64, 'สำนักงาน', 'เคลือบบัตร', 'default.png', 'อัน', NULL, 120, 5, '2026-09-03 10:52:59'),
(65, 'สำนักงาน', 'คลิปหนีบติดบัตร', 'default.png', 'ชิ้น', NULL, 70, 5, '2026-09-03 10:52:59'),
(66, 'สำนักงาน', 'ป้ายสติ๊กเกอร์ราคา', 'default.png', 'แผ่น', NULL, 4, 5, '2026-09-03 10:52:59'),
(67, 'สมุด', 'สมุดปกอ่อน', 'default.png', 'เล่ม', NULL, 41, 5, '2026-09-03 10:52:59'),
(68, 'สมุด', 'สมุดบันทึกใหญ่', 'default.png', 'เล่ม', NULL, 0, 5, '2026-09-03 10:52:59'),
(69, 'สำนักงาน', 'คลิปบอร์ด', 'default.png', 'อัน', NULL, 21, 5, '2026-09-03 10:52:59'),
(70, 'แฟ้ม', 'แฟ้มซองเปิดข้าง A4', 'default.png', 'ซอง', NULL, 163, 5, '2026-09-03 10:52:59'),
(71, 'แฟ้ม', 'แฟ้มโชว์เอกสาร A4', 'default.png', 'เล่ม', NULL, 2, 5, '2026-09-03 10:52:59'),
(72, 'สำนักงาน', 'ลิ้นแฟ้ม', 'default.png', 'ชิ้น', NULL, 0, 5, '2026-09-03 10:52:59'),
(73, 'กุญแจ', 'ป้ายกุญแจ', 'default.png', 'ชิ้น', NULL, 10, 5, '2026-09-03 10:52:59'),
(74, 'สำนักงาน', 'ตะกร้าใส่ซองเอกสาร', 'default.png', 'กล่อง', NULL, 2, 5, '2026-09-03 10:52:59'),
(75, 'สำนักงาน', 'ซอง 11 รู A4', 'default.png', 'ซอง', NULL, 21, 5, '2026-09-03 10:52:59');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `type` enum('STOCK_IN','STOCK_OUT','EDIT') NOT NULL,
  `quantity` int(11) NOT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `requester` varchar(180) NOT NULL DEFAULT '',
  `note` varchar(400) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Indexes for dumped tables
--

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tx_item` (`item_id`),
  ADD KEY `fk_tx_dept` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

ALTER TABLE `departments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

ALTER TABLE `items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

ALTER TABLE `transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_tx_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tx_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- MIGRATION – run this ONE line on an existing database that was created
-- before the 'EDIT' transaction type existed (needed so history.php can
-- show "แก้ไขข้อมูล" entries alongside stock in/out). Safe to run even if
-- already applied.
--
-- ALTER TABLE `transactions` MODIFY `type` ENUM('STOCK_IN','STOCK_OUT','EDIT') NOT NULL;

--
-- MIGRATION – departments soft delete (add / delete department from the
-- stock-out form). db.php runs this automatically when the column is
-- missing, so running it by hand is optional.
--
-- ALTER TABLE `departments` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;