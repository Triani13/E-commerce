<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

// Cek login pembeli
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'pembeli') {
    header('Location: ../login.php');
    exit;
}

$title = 'Dashboard Pembeli';

// Ambil data pesanan
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               (SELECT COUNT(*) FROM detail_pesanan WHERE pesanan_id = p.id) as total_item
        FROM pesanan p 
        WHERE p.user_id = ? 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $pesanan_list = $stmt->fetchAll();
} catch(PDOException $e) {
    $pesanan_list = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - Mitra Tani Jaya</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            height: 100%;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            min-height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        /* Navbar */
        .navbar {
            background-color: #2E7D32;
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            text-decoration: none;
            color: white;
        }
        
        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-menu a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }
        
        .nav-menu a:hover {
            opacity: 0.8;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem 0;
        }
        
        /* Dashboard Layout */
        .dashboard-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
        }
        
        /* Sidebar */
        .sidebar {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            height: fit-content;
        }
        
        .sidebar h3 {
            margin-bottom: 1rem;
            color: #2E7D32;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 0.75rem 1rem;
            color: #333;
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all 0.3s;
            margin-bottom: 0.25rem;
        }
        
        .sidebar-menu a:hover {
            background-color: #f5f5f5;
            border-left-color: #2E7D32;
        }
        
        .sidebar-menu a.active {
            background-color: #E8F5E9;
            border-left-color: #2E7D32;
            color: #2E7D32;
            font-weight: bold;
        }
        
        /* Content Area */
        .content-area {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background-color: #f5f5f5;
            font-weight: bold;
            text-align: left;
            padding: 0.75rem;
            border-bottom: 2px solid #ddd;
        }
        
        td {
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
        }
        
        tr:hover {
            background-color: #f9f9f9;
        }
        
        /* Status Badge */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .badge-pending {
            background-color: #FFF3E0;
            color: #F57C00;
        }
        
        .badge-dibayar {
            background-color: #E3F2FD;
            color: #1976D2;
        }
        
        .badge-dikemas {
            background-color: #F3E5F5;
            color: #7B1FA2;
        }
        
        .badge-dikirim {
            background-color: #E0F2F1;
            color: #00796B;
        }
        
        .badge-selesai {
            background-color: #E8F5E9;
            color: #2E7D32;
        }
        
        /* Links */
        .action-links a {
            color: #2E7D32;
            text-decoration: none;
            margin-right: 1rem;
        }
        
        .action-links a:hover {
            text-decoration: underline;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .empty-state h3 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        /* Footer */
        footer {
            background-color: #2E7D32;
            color: white;
            padding: 2rem 0;
            text-align: center;
            margin-top: auto;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-content">
                <a href="/taniasli/" class="logo">🌾 Mitra Tani Jaya</a>
                <ul class="nav-menu">
                    <li><a href="/taniasli/">Beranda</a></li>
                    <li><a href="/taniasli/produk.php">Produk</a></li>
                    <li><a href="/taniasli/keranjang.php">🛒 Keranjang</a></li>
                    <li><a href="/taniasli/dashboard/pembeli.php" style="font-weight: bold;">Dashboard</a></li>
                    <li><a href="/taniasli/logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <div class="container">
            <h1 style="margin-bottom: 2rem;">Dashboard Pembeli</h1>
            
            <div class="dashboard-layout">
                <!-- Sidebar -->
                <div class="sidebar">
                    <h3>Menu</h3>
                    <nav class="sidebar-menu">
                        <a href="pembeli.php" class="active">Riwayat Pesanan</a>
                        <a href="profil.php">Profil Saya</a>
                    </nav>
                </div>
                
                <!-- Content -->
                <div class="content-area">
                    <h2 style="margin-bottom: 1.5rem;">Riwayat Pesanan</h2>
                    
                    <?php if(empty($pesanan_list)): ?>
                        <div class="empty-state">
                            <h3>Belum Ada Pesanan</h3>
                            <p>Anda belum memiliki riwayat pesanan.</p>
                            <a href="../produk.php" style="color: #2E7D32; text-decoration: none; font-weight: bold;">
                                Mulai Belanja →
                            </a>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID Pesanan</th>
                                    <th>Tanggal</th>
                                    <th>Total Item</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($pesanan_list as $pesanan): ?>
                                    <tr>
                                        <td>#<?php echo $pesanan['id']; ?></td>
                                        <td><?php echo date('d M Y', strtotime($pesanan['created_at'])); ?></td>
                                        <td><?php echo $pesanan['total_item']; ?> produk</td>
                                        <td>Rp <?php echo number_format($pesanan['total'], 0, ',', '.'); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $pesanan['status']; ?>">
                                                <?php 
                                                $status_text = [
                                                    'pending' => 'Menunggu Pembayaran',
                                                    'dibayar' => 'Dibayar',
                                                    'dikemas' => 'Dikemas',
                                                    'dikirim' => 'Dikirim',
                                                    'selesai' => 'Selesai'
                                                ];
                                                echo $status_text[$pesanan['status']] ?? ucfirst($pesanan['status']);
                                                ?>
                                            </span>
                                        </td>
                                        <td class="action-links">
                                            <a href="pesanan-detail.php?id=<?php echo $pesanan['id']; ?>">Lihat Detail</a>
                                            <?php if($pesanan['status'] == 'selesai'): ?>
                                                <a href="ulasan.php?pesanan_id=<?php echo $pesanan['id']; ?>" style="color: #FFC107;">Beri Ulasan</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Mitra Tani Jaya. Memberdayakan Pertanian Lokal.</p>
        </div>
    </footer>
</body>
</html>
