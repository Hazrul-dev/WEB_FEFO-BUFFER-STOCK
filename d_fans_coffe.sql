-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 04, 2025 at 07:27 AM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.0.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `d_fans_coffe`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(20) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `description`, `created_at`) VALUES
(1, 1, 'CREATE', 'pesanan', 4, 'Membuat pesanan baru', '2025-06-01 19:00:03'),
(2, 1, 'CREATE', 'pesanan', 5, 'Membuat pesanan baru', '2025-06-01 19:04:05'),
(3, 1, 'INSERT', 'pesanan', 1, 'Membuat pesanan baru #1', '2025-06-01 22:20:54'),
(4, 1, 'INSERT', 'pesanan', 2, 'Membuat pesanan baru #2', '2025-06-01 22:21:07'),
(5, 1, 'UPDATE', 'pesanan', 1, 'Membatalkan pesanan #1', '2025-06-03 23:33:14'),
(6, 1, 'UPDATE', 'pesanan', 1, 'Membatalkan pesanan #1', '2025-06-03 23:35:11'),
(7, 1, 'INSERT', 'pesanan', 3, 'Membuat pesanan baru #3', '2025-06-04 01:09:06'),
(8, 1, 'INSERT', 'pesanan', 4, 'Membuat pesanan baru #4', '2025-06-04 01:09:22'),
(9, 1, 'UPDATE', 'pesanan', 3, 'Membatalkan pesanan #3', '2025-06-04 01:34:46'),
(10, 1, 'UPDATE', 'pesanan', 3, 'Membatalkan pesanan #3', '2025-06-04 01:35:06'),
(11, 1, 'INSERT', 'pesanan', 5, 'Membuat pesanan baru #5', '2025-06-04 01:52:44'),
(12, 1, 'INSERT', 'pesanan', 6, 'Membuat pesanan baru #6', '2025-06-04 01:52:57'),
(13, 1, 'UPDATE', 'pesanan', 5, 'Membatalkan pesanan #5', '2025-06-04 01:53:07'),
(14, 4, 'UPDATE', 'pesanan', 6, 'Mengubah status pesanan ke Dikirim', '2025-06-04 11:22:04'),
(15, 4, 'UPDATE', 'pesanan', 6, 'Mengubah status pesanan ke Dikirim', '2025-06-04 11:22:25'),
(16, 1, 'UPDATE', 'pesanan', 6, 'Menerima pesanan #6 dan menambahkan ke barang masuk', '2025-06-04 11:36:40'),
(17, 1, 'INSERT', 'pesanan', 7, 'Membuat pesanan baru #7', '2025-06-04 11:39:00'),
(18, 1, 'INSERT', 'pesanan', 8, 'Membuat pesanan baru #8', '2025-06-04 11:39:13'),
(19, 4, 'UPDATE', 'pesanan', 7, 'Mengubah status pesanan ke Dikirim', '2025-06-04 11:41:03'),
(20, 1, 'UPDATE', 'pesanan', 7, 'Menerima pesanan #7 dan menambahkan ke barang masuk', '2025-06-04 11:41:25'),
(21, 1, 'UPDATE', 'pesanan', 7, 'Menyelesaikan pesanan #7', '2025-06-04 12:10:27');

-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bahan_baku`
--

CREATE TABLE `bahan_baku` (
  `id` int(11) NOT NULL,
  `nama_bahan` varchar(100) NOT NULL,
  `stok` int(11) NOT NULL DEFAULT 0,
  `buffer_stock` int(11) DEFAULT 0,
  `jenis_id` int(11) NOT NULL,
  `satuan` varchar(20) NOT NULL,
  `lead_time` int(11) NOT NULL COMMENT 'dalam hari',
  `safety_stock` int(11) NOT NULL,
  `rop` int(11) NOT NULL COMMENT 'Reorder Point',
  `last_restock_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bahan_baku`
--

INSERT INTO `bahan_baku` (`id`, `nama_bahan`, `stok`, `buffer_stock`, `jenis_id`, `satuan`, `lead_time`, `safety_stock`, `rop`, `last_restock_date`) VALUES
(1, 'Kopi Ulee Kareng', 130, 80, 1, 'Kg', 7, 30, 80, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `barang_keluar`
--

CREATE TABLE `barang_keluar` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `bahan_id` int(11) NOT NULL,
  `no_faktur` varchar(50) DEFAULT NULL,
  `kuantitas` int(11) NOT NULL,
  `satuan` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barang_keluar`
--

INSERT INTO `barang_keluar` (`id`, `tanggal`, `bahan_id`, `no_faktur`, `kuantitas`, `satuan`, `user_id`) VALUES
(1, '2025-05-27', 1, '120100', 100, 'Kg', 1);

-- --------------------------------------------------------

--
-- Table structure for table `barang_masuk`
--

CREATE TABLE `barang_masuk` (
  `id` int(11) NOT NULL,
  `bahan_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `kuantitas` int(11) NOT NULL,
  `harga_satuan` decimal(10,2) NOT NULL,
  `total_harga` decimal(10,2) NOT NULL,
  `tanggal_masuk` datetime NOT NULL,
  `tanggal_expired` date NOT NULL,
  `catatan` text DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `pesanan_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barang_masuk`
--

INSERT INTO `barang_masuk` (`id`, `bahan_id`, `supplier_id`, `kuantitas`, `harga_satuan`, `total_harga`, `tanggal_masuk`, `tanggal_expired`, `catatan`, `user_id`, `pesanan_id`) VALUES
(6, 1, 6, 10, '1500.00', '15000.00', '2025-06-04 11:36:40', '2025-07-04', 'bagus', 1, 6),
(7, 1, 6, 20, '1500.00', '30000.00', '2025-06-04 11:41:25', '2025-07-04', 'Kondisi barang baik', 1, 7);

-- --------------------------------------------------------

--
-- Table structure for table `jenis_bahan`
--

CREATE TABLE `jenis_bahan` (
  `id` int(11) NOT NULL,
  `nama_jenis` varchar(50) NOT NULL,
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jenis_bahan`
--

INSERT INTO `jenis_bahan` (`id`, `nama_jenis`, `keterangan`) VALUES
(1, 'Biji Kopi', 'Bahan utama minuman kopi'),
(2, 'Teh', 'Bahan utama minuman teh'),
(3, 'Susu', 'Bahan tambahan minuman'),
(4, 'Pemanis', 'Bahan pemanis minuman'),
(5, 'Kemasan', 'Bahan untuk kemasan produk'),
(6, 'Bahan Makanan', 'Bahan untuk makanan pendamping');

-- --------------------------------------------------------

--
-- Table structure for table `kartu_stok`
--

CREATE TABLE `kartu_stok` (
  `id` int(11) NOT NULL,
  `bahan_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `stok_awal` int(11) NOT NULL,
  `masuk` int(11) NOT NULL DEFAULT 0,
  `keluar` int(11) NOT NULL DEFAULT 0,
  `stok_akhir` int(11) NOT NULL,
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kartu_stok`
--

INSERT INTO `kartu_stok` (`id`, `bahan_id`, `tanggal`, `stok_awal`, `masuk`, `keluar`, `stok_akhir`, `keterangan`) VALUES
(1, 1, '2025-05-27', 200, 100, 0, 300, 'Barang masuk - 0122'),
(2, 1, '2025-05-27', 100, 0, 100, 0, 'Barang keluar - 120100');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `is_read`, `created_at`) VALUES
(1, 4, 'Pesanan baru #1: Kopi Ulee Kareng sebanyak 10 kg. Harap konfirmasi pengiriman.', 0, '2025-06-01 22:20:54'),
(2, 4, 'Pesanan baru #2: Kopi Ulee Kareng sebanyak 10 kg. Harap konfirmasi pengiriman.', 0, '2025-06-01 22:21:07'),
(3, 4, 'Pesanan #1 (Kopi Ulee Kareng 10kg) telah dibatalkan oleh admin. Alasan: Lama', 0, '2025-06-03 23:33:14'),
(4, 4, 'Pesanan #1 (Kopi Ulee Kareng 10kg) telah dibatalkan oleh admin. Alasan: Lama', 0, '2025-06-03 23:35:11'),
(5, 4, 'Pesanan baru #3: Kopi Ulee Kareng sebanyak 10 kg. Harap konfirmasi pengiriman.', 0, '2025-06-04 01:09:06'),
(6, 4, 'Pesanan baru #4: Kopi Ulee Kareng sebanyak 10 kg. Harap konfirmasi pengiriman.', 0, '2025-06-04 01:09:22'),
(7, 4, 'Pesanan #3 (Kopi Ulee Kareng 10kg) telah dibatalkan oleh admin. Alasan: ada', 0, '2025-06-04 01:34:46'),
(8, 4, 'Pesanan #3 (Kopi Ulee Kareng 10kg) telah dibatalkan oleh admin. Alasan: ada', 0, '2025-06-04 01:35:06'),
(9, 4, 'Pesanan baru #5: Kopi Ulee Kareng sebanyak 10 kg. Harap konfirmasi pengiriman.', 0, '2025-06-04 01:52:44'),
(10, 4, 'Pesanan baru #6: Kopi Ulee Kareng sebanyak 10 kg. Harap konfirmasi pengiriman.', 0, '2025-06-04 01:52:57'),
(11, 4, 'Pesanan #5 (Kopi Ulee Kareng 10kg) telah dibatalkan oleh admin. Alasan: tidak ada\\r\\n', 0, '2025-06-04 01:53:07'),
(12, 1, 'Pesanan #6 (Kopi Ulee Kareng 10kg) telah dikirim oleh supplier PT Indah Jaya. Catatan: terimakasih', 1, '2025-06-04 11:22:04'),
(13, 1, 'Pesanan #6 (Kopi Ulee Kareng 10kg) telah dikirim oleh supplier PT Indah Jaya. Catatan: terimakasih', 1, '2025-06-04 11:22:25'),
(14, 4, 'Pesanan #6 (Kopi Ulee Kareng 10kg) telah diterima oleh admin.', 0, '2025-06-04 11:36:40'),
(15, 4, 'Pesanan baru #7: Kopi Ulee Kareng sebanyak 20 kg. Harap konfirmasi pengiriman.', 0, '2025-06-04 11:39:00'),
(16, 4, 'Pesanan baru #8: Kopi Ulee Kareng sebanyak 20 kg. Harap konfirmasi pengiriman.', 0, '2025-06-04 11:39:13'),
(17, 1, 'Pesanan #7 (Kopi Ulee Kareng 20kg) telah dikirim oleh supplier PT Indah Jaya. Catatan: terimakasih', 1, '2025-06-04 11:41:03'),
(18, 4, 'Pesanan #7 (Kopi Ulee Kareng 20kg) telah diterima oleh admin.', 0, '2025-06-04 11:41:25'),
(19, 4, 'Pesanan #7 (Kopi Ulee Kareng 20kg) telah diselesaikan oleh admin.', 0, '2025-06-04 12:10:27');

-- --------------------------------------------------------

--
-- Table structure for table `pesanan`
--

CREATE TABLE `pesanan` (
  `id` int(11) NOT NULL,
  `bahan_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga_satuan` decimal(10,2) NOT NULL,
  `total_harga` decimal(10,2) NOT NULL,
  `status` enum('Dipesan','Dikirim','Diterima','Selesai','Dibatalkan') NOT NULL DEFAULT 'Dipesan',
  `catatan_pengiriman` text DEFAULT NULL,
  `tanggal_pesanan` datetime NOT NULL,
  `tanggal_pengiriman` datetime DEFAULT NULL,
  `tanggal_diterima` datetime DEFAULT NULL,
  `tanggal_selesai` datetime DEFAULT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pesanan`
--

INSERT INTO `pesanan` (`id`, `bahan_id`, `supplier_id`, `jumlah`, `harga_satuan`, `total_harga`, `status`, `catatan_pengiriman`, `tanggal_pesanan`, `tanggal_pengiriman`, `tanggal_diterima`, `tanggal_selesai`, `user_id`) VALUES
(5, 1, 6, 10, '1500.00', '15000.00', 'Dibatalkan', 'tidak ada\\r\\n', '2025-06-04 01:52:44', NULL, '2025-06-04 01:53:07', NULL, 1),
(6, 1, 6, 10, '1500.00', '15000.00', 'Diterima', 'terimakasih', '2025-06-04 01:52:57', '2025-06-04 11:22:25', '2025-06-04 11:36:40', NULL, 1),
(7, 1, 6, 20, '1500.00', '30000.00', 'Selesai', 'terimakasih', '2025-06-04 11:39:00', '2025-06-04 11:41:03', '2025-06-04 11:41:25', '2025-06-04 12:10:27', 1);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nama_supplier` varchar(100) NOT NULL,
  `kontak` varchar(20) NOT NULL,
  `alamat` text NOT NULL,
  `provinsi` varchar(50) NOT NULL,
  `negara` varchar(50) NOT NULL,
  `kode_pos` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `user_id`, `nama_supplier`, `kontak`, `alamat`, `provinsi`, `negara`, `kode_pos`) VALUES
(6, 0, 'PT Indah Jaya', '12012245', 'Jl. Kemuning Jakarta Selatan', 'DKI Jakarta', 'Indonesia', '20112');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `jabatan` enum('Admin','Staff','Manager','Supplier') NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama`, `jabatan`, `supplier_id`, `email`, `foto`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'Admin', NULL, 'admin@dfanscoffe.com', NULL, '2025-05-20 16:59:27'),
(2, 'Jaka', '$2y$10$oNH4m28Azmq19BYWlXgZW.QaDiJLOK4NhizSsE9n/KBDAyVkjZz4i', 'Jaka Hasibuan', 'Staff', NULL, 'jaka23@gmail.com', NULL, '2025-05-21 19:39:56'),
(4, 'ptindah', '$2y$10$E4h.oYQzlDz5pMoXxsf7y.HSjOTrIDzy0zoP3xAgDqTm7vMpJ1M9.', 'PT Indah Jaya', 'Supplier', 6, 'ptindahjaya200@gmail.com', NULL, '2025-06-01 12:02:59');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `bahan_baku`
--
ALTER TABLE `bahan_baku`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jenis_id` (`jenis_id`);

--
-- Indexes for table `barang_keluar`
--
ALTER TABLE `barang_keluar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bahan_id` (`bahan_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `barang_masuk`
--
ALTER TABLE `barang_masuk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bahan_id` (`bahan_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `pesanan_id` (`pesanan_id`);

--
-- Indexes for table `jenis_bahan`
--
ALTER TABLE `jenis_bahan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kartu_stok`
--
ALTER TABLE `kartu_stok`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bahan_id` (`bahan_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bahan_id` (`bahan_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `user_id` (`user_id`);

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
  ADD KEY `supplier_id` (`supplier_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bahan_baku`
--
ALTER TABLE `bahan_baku`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `barang_keluar`
--
ALTER TABLE `barang_keluar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `barang_masuk`
--
ALTER TABLE `barang_masuk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `jenis_bahan`
--
ALTER TABLE `jenis_bahan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `kartu_stok`
--
ALTER TABLE `kartu_stok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD CONSTRAINT `audit_trail_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `bahan_baku`
--
ALTER TABLE `bahan_baku`
  ADD CONSTRAINT `bahan_baku_ibfk_1` FOREIGN KEY (`jenis_id`) REFERENCES `jenis_bahan` (`id`);

--
-- Constraints for table `barang_keluar`
--
ALTER TABLE `barang_keluar`
  ADD CONSTRAINT `barang_keluar_ibfk_1` FOREIGN KEY (`bahan_id`) REFERENCES `bahan_baku` (`id`),
  ADD CONSTRAINT `barang_keluar_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `kartu_stok`
--
ALTER TABLE `kartu_stok`
  ADD CONSTRAINT `kartu_stok_ibfk_1` FOREIGN KEY (`bahan_id`) REFERENCES `bahan_baku` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
