<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

// Cek login penjual
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'penjual') {
    header('Location: ../login.php');
    exit;
}

$title = 'Dashboard Penjual';

// Ambil atau buat toko
try {
    $stmt = $pdo->prepare("SELECT * FROM toko WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $toko = $stmt->fetch();
    
    if(!$toko) {
        // Buat toko otomatis
        $stmt = $pdo->prepare("INSERT INTO toko (user_id, nama_toko) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], "Toko " . $_SESSION['nama']]);
        $toko_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("SELECT * FROM toko WHERE id = ?");
        $stmt->execute([$toko_id]);
        $toko = $stmt->fetch();
    }
    $toko_id = $toko['id'];
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Statistik
try {
    // Total produk
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM produk WHERE toko_id = ?");
    $stmt->execute([$toko_id]);
    $total_produk = $stmt->fetch()['total'];
    
    // Pesanan baru (pending)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT p.id) as total 
        FROM pesanan p 
        JOIN detail_pesanan dp ON p.id = dp.pesanan_id 
        JOIN produk pr ON dp.produk_id = pr.id 
        WHERE pr.toko_id = ? AND p.status = 'pending'
    ");
    $stmt->execute([$toko_id]);
    $pesanan_baru = $stmt->fetch()['total'];
    
    // Pendapatan bulan ini
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(dp.jumlah * dp.harga), 0) as total 
        FROM pesanan p 
        JOIN detail_pesanan dp ON p.id = dp.pesanan_id 
        JOIN produk pr ON dp.produk_id = pr.id 
        WHERE pr.toko_id = ? 
        AND p.status IN ('dibayar', 'dikemas', 'dikirim', 'selesai')
        AND MONTH(p.created_at) = MONTH(CURRENT_DATE)
        AND YEAR(p.created_at) = YEAR(CURRENT_DATE)
    ");
    $stmt->execute([$toko_id]);
    $pendapatan_bulan = $stmt->fetch()['total'];
    
    // Pesanan terbaru
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.*, u.nama as nama_pembeli
        FROM pesanan p 
        JOIN users u ON p.user_id = u.id
        JOIN detail_pesanan dp ON p.id = dp.pesanan_id 
        JOIN produk pr ON dp.produk_id = pr.id 
        WHERE pr.toko_id = ?
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$toko_id]);
    $pesanan_terbaru = $stmt->fetchAll();
} catch(PDOException $e) {
    $total_produk = 0;
    $pesanan_baru = 0;
    $pendapatan_bulan = 0;
    $pesanan_terbaru = [];
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
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid;
        }
        
        .stat-card.green {
            border-left-color: #2E7D32;
            background-color: #E8F5E9;
        }
        
        .stat-card.yellow {
            border-left-color: #FFC107;
            background-color: #FFF9C4;
        }
        
        .stat-card.blue {
            border-left-color: #1976D2;
            background-color: #E3F2FD;
        }
        
        .stat-card h3 {
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            color: #666;
        }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
        
        .stat-card.green h3 { color: #2E7D32; }
        .stat-card.yellow h3 { color: #F57C00; }
        .stat-card.blue h3 { color: #1976D2; }
        
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
        
        .badge-selesai {
            background-color: #E8F5E9;
            color: #2E7D32;
        }
        
        /* Links */
        .link-primary {
            color: #2E7D32;
            text-decoration: none;
        }
        
        .link-primary:hover {
            text-decoration: underline;
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
                    <li><a href="/taniasli/dashboard/penjual.php" style="font-weight: bold;">Dashboard</a></li>
                    <li><a href="/taniasli/logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <div class="container">
            <h1 style="margin-bottom: 2rem;">Dashboard Penjual - <?php echo htmlspecialchars($toko['nama_toko']); ?></h1>
            
            <div class="dashboard-layout">
                <!-- Sidebar -->
                <div class="sidebar">
                    <h3>Menu</h3>
                    <nav class="sidebar-menu">
                        <a href="penjual.php" class="active">Dashboard</a>
                        <a href="produk-manage.php">Manajemen Produk</a>
                        <a href="pesanan-manage.php">Manajemen Pesanan</a>
                        <a href="bukti-transfer.php">Bukti Transfer</a>
                        <a href="toko-setting.php">Pengaturan Toko</a>
                    </nav>
                </div>
                
                <!-- Content -->
                <div>
                    <!-- Statistics Cards -->
                    <div class="stats-grid">
                        <div class="stat-card green">
                            <h3>Total Produk</h3>
                            <div class="value"><?php echo $total_produk; ?></div>
                        </div>
                        <div class="stat-card yellow">
                            <h3>Pesanan Baru</h3>
                            <div class="value"><?php echo $pesanan_baru; ?></div>
                        </div>
                        <div class="stat-card blue">
                            <h3>Pendapatan Bulan Ini</h3>
                            <div class="value">Rp <?php echo number_format($pendapatan_bulan, 0, ',', '.'); ?></div>
                        </div>
                    </div>
                    
                    <!-- Recent Orders -->
                    <div class="content-area">
                        <h2 style="margin-bottom: 1.5rem;">Pesanan Terbaru</h2>
                        
                        <?php if(empty($pesanan_terbaru)): ?>
                            <p style="text-align: center; color: #666; padding: 2rem;">
                                Belum ada pesanan masuk.
                            </p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Pembeli</th>
                                        <th>Tanggal</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($pesanan_terbaru as $pesanan): ?>
                                        <tr>
                                            <td>#<?php echo $pesanan['id']; ?></td>
                                            <td><?php echo htmlspecialchars($pesanan['nama_pembeli']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($pesanan['created_at'])); ?></td>
                                            <td>Rp <?php echo number_format($pesanan['total'], 0, ',', '.'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $pesanan['status']; ?>">
                                                    <?php echo ucfirst($pesanan['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <div style="text-align: right; margin-top: 1rem;">
                                <a href="pesanan-manage.php" class="link-primary">
                                    Lihat Semua Pesanan →
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
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
