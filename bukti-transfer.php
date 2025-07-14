<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

// Cek login penjual
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'penjual') {
    header('Location: ../login.php');
    exit;
}

$title = 'Bukti Transfer Pending';
$success = '';

// Ambil toko_id
try {
    $stmt = $pdo->prepare("SELECT id FROM toko WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $toko = $stmt->fetch();
    $toko_id = $toko['id'];
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle verifikasi pembayaran
if(isset($_POST['verifikasi'])) {
    $pesanan_id = $_POST['pesanan_id'];
    
    try {
        // Validasi pesanan
        $stmt = $pdo->prepare("
            SELECT p.id 
            FROM pesanan p 
            JOIN detail_pesanan dp ON p.id = dp.pesanan_id 
            JOIN produk pr ON dp.produk_id = pr.id 
            WHERE p.id = ? AND pr.toko_id = ?
        ");
        $stmt->execute([$pesanan_id, $toko_id]);
        
        if($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE pesanan SET status = 'dibayar' WHERE id = ?");
            $stmt->execute([$pesanan_id]);
            $success = "Pembayaran berhasil diverifikasi!";
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Query pesanan dengan bukti transfer yang pending
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.*, u.nama as nama_pembeli, u.email,
               (SELECT SUM(dp2.jumlah * dp2.harga) 
                FROM detail_pesanan dp2 
                JOIN produk pr2 ON dp2.produk_id = pr2.id 
                WHERE dp2.pesanan_id = p.id AND pr2.toko_id = ?) as total_toko
        FROM pesanan p 
        JOIN users u ON p.user_id = u.id
        JOIN detail_pesanan dp ON p.id = dp.pesanan_id 
        JOIN produk pr ON dp.produk_id = pr.id 
        WHERE pr.toko_id = ? 
        AND p.status = 'pending' 
        AND p.metode_pembayaran = 'transfer'
        AND p.bukti_pembayaran IS NOT NULL
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$toko_id, $toko_id]);
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
        
        /* Alert */
        .alert-success {
            background: #E8F5E9;
            color: #2E7D32;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            border: 1px solid #A5D6A7;
        }
        
        /* Grid Cards */
        .proof-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .proof-card {
            border: 2px solid #FF5722;
            border-radius: 8px;
            padding: 1.5rem;
            background: white;
        }
        
        .proof-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .proof-info h4 {
            margin: 0;
            color: #333;
        }
        
        .proof-info small {
            color: #666;
        }
        
        .proof-amount {
            text-align: right;
        }
        
        .proof-amount strong {
            color: #2E7D32;
            font-size: 1.2rem;
        }
        
        .buyer-info {
            margin-bottom: 1rem;
        }
        
        .buyer-info p {
            margin: 0.25rem 0;
            font-size: 0.9rem;
        }
        
        .proof-image-box {
            background: #f5f5f5;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .proof-image {
            max-width: 100%;
            max-height: 300px;
            border-radius: 4px;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .proof-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
            flex: 1;
        }
        
        .btn-primary {
            background: #2E7D32;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1B5E20;
        }
        
        .btn-secondary {
            background: #ddd;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #ccc;
        }
        
        /* Info Box */
        .info-box {
            background: #E3F2FD;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 2rem;
        }
        
        .info-box h3 {
            color: #1976D2;
            margin-bottom: 0.75rem;
        }
        
        .info-box ul {
            margin-left: 1.5rem;
            color: #333;
        }
        
        .info-box li {
            margin-bottom: 0.5rem;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
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
                    <li><a href="/taniasli/dashboard/penjual.php">Dashboard</a></li>
                    <li><a href="/taniasli/logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <div class="container">
            <h1 style="margin-bottom: 2rem;">Verifikasi Bukti Transfer</h1>
            
            <div class="dashboard-layout">
                <!-- Sidebar -->
                <div class="sidebar">
                    <h3>Menu</h3>
                    <nav class="sidebar-menu">
                        <a href="penjual.php">Dashboard</a>
                        <a href="produk-manage.php">Manajemen Produk</a>
                        <a href="pesanan-manage.php">Manajemen Pesanan</a>
                        <a href="bukti-transfer.php" class="active">Bukti Transfer</a>
                        <a href="toko-setting.php">Pengaturan Toko</a>
                    </nav>
                </div>
                
                <!-- Content -->
                <div class="content-area">
                    <?php if($success): ?>
                        <div class="alert-success">
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <h2>Pesanan Menunggu Verifikasi Transfer</h2>
                    
                    <?php if(empty($pesanan_list)): ?>
                        <div class="empty-state">
                            <h3>Tidak Ada Bukti Transfer</h3>
                            <p>Saat ini tidak ada bukti transfer yang perlu diverifikasi.</p>
                            <p style="margin-top: 1rem;">Semua pembayaran transfer sudah diverifikasi ✓</p>
                        </div>
                    <?php else: ?>
                        <div class="proof-grid">
                            <?php foreach($pesanan_list as $pesanan): ?>
                                <div class="proof-card">
                                    <div class="proof-header">
                                        <div class="proof-info">
                                            <h4>Pesanan #<?php echo $pesanan['id']; ?></h4>
                                            <small><?php echo date('d M Y H:i', strtotime($pesanan['created_at'])); ?></small>
                                        </div>
                                        <div class="proof-amount">
                                            <small>Total</small><br>
                                            <strong>Rp <?php echo number_format($pesanan['total_toko'], 0, ',', '.'); ?></strong>
                                        </div>
                                    </div>
                                    
                                    <div class="buyer-info">
                                        <p><strong>Pembeli:</strong> <?php echo htmlspecialchars($pesanan['nama_pembeli']); ?></p>
                                        <p><small><?php echo htmlspecialchars($pesanan['email']); ?></small></p>
                                    </div>
                                    
                                    <div class="proof-image-box">
                                        <p style="margin-bottom: 0.5rem; font-weight: 600;">Bukti Transfer:</p>
                                        <img src="../uploads/bukti/<?php echo $pesanan['bukti_pembayaran']; ?>" 
                                             class="proof-image"
                                             onclick="window.open(this.src, '_blank')"
                                             alt="Bukti Transfer">
                                        <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #666;">
                                            Klik gambar untuk memperbesar
                                        </p>
                                    </div>
                                    
                                    <div class="proof-actions">
                                        <form method="POST" style="flex: 1;">
                                            <input type="hidden" name="pesanan_id" value="<?php echo $pesanan['id']; ?>">
                                            <button type="submit" name="verifikasi" class="btn btn-primary" style="width: 100%;">
                                                ✓ Verifikasi
                                            </button>
                                        </form>
                                        <a href="pesanan-detail-penjual.php?id=<?php echo $pesanan['id']; ?>" 
                                           class="btn btn-secondary">
                                            Detail
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Tips Verifikasi -->
                    <div class="info-box">
                        <h3>Tips Verifikasi Transfer</h3>
                        <ul>
                            <li>Periksa nominal transfer sesuai dengan total pesanan</li>
                            <li>Pastikan nama pengirim sesuai atau masuk akal dengan nama pembeli</li>
                            <li>Periksa tanggal dan waktu transfer</li>
                            <li>Jika ragu, hubungi pembeli untuk konfirmasi melalui email/telepon</li>
                            <li>Simpan screenshot bukti transfer untuk arsip Anda</li>
                        </ul>
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
