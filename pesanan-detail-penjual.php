<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

// Cek login penjual
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'penjual') {
    header('Location: ../login.php');
    exit;
}

$title = 'Detail Pesanan';
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

// Ambil ID pesanan
$pesanan_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Ambil detail pesanan dengan JOIN yang benar
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.nama as nama_pembeli, u.email, u.no_hp
        FROM pesanan p 
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pesanan_id]);
    $pesanan = $stmt->fetch();
    
    if(!$pesanan) {
        header('Location: pesanan-manage.php');
        exit;
    }
    
    // Hitung total dari toko ini saja
    $stmt = $pdo->prepare("
        SELECT SUM(dp.jumlah * dp.harga) as total_toko
        FROM detail_pesanan dp 
        JOIN produk pr ON dp.produk_id = pr.id 
        WHERE dp.pesanan_id = ? AND pr.toko_id = ?
    ");
    $stmt->execute([$pesanan_id, $toko_id]);
    $result = $stmt->fetch();
    $pesanan['total_toko'] = $result['total_toko'] ?? 0;
    
    // Cek apakah ada produk dari toko ini
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM detail_pesanan dp 
        JOIN produk pr ON dp.produk_id = pr.id 
        WHERE dp.pesanan_id = ? AND pr.toko_id = ?
    ");
    $stmt->execute([$pesanan_id, $toko_id]);
    $result = $stmt->fetch();
    
    if($result['count'] == 0) {
        header('Location: pesanan-manage.php');
        exit;
    }
    
    // Ambil detail item dari toko ini saja
    $stmt = $pdo->prepare("
        SELECT dp.*, p.nama, p.gambar 
        FROM detail_pesanan dp 
        JOIN produk p ON dp.produk_id = p.id 
        WHERE dp.pesanan_id = ? AND p.toko_id = ?
    ");
    $stmt->execute([$pesanan_id, $toko_id]);
    $items = $stmt->fetchAll();
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle update status
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE pesanan SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $pesanan_id]);
        
        $success = "Status pesanan berhasil diperbarui!";
        
        // Refresh data
        $stmt = $pdo->prepare("SELECT * FROM pesanan WHERE id = ?");
        $stmt->execute([$pesanan_id]);
        $pesanan = $stmt->fetch();
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle verifikasi pembayaran
if(isset($_POST['verifikasi_pembayaran'])) {
    try {
        $stmt = $pdo->prepare("UPDATE pesanan SET status = 'dibayar' WHERE id = ?");
        $stmt->execute([$pesanan_id]);
        
        $success = "Pembayaran berhasil diverifikasi!";
        
        // Refresh data
        header("Location: pesanan-detail-penjual.php?id=$pesanan_id");
        exit;
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
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
        
        /* Detail Layout */
        .detail-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
            align-items: start;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        
        .card h2 {
            margin-bottom: 1.5rem;
            color: #2E7D32;
        }
        
        .card h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        /* Order Header */
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 2rem;
        }
        
        .order-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2E7D32;
        }
        
        .order-date {
            color: #666;
            margin-top: 0.5rem;
        }
        
        /* Status Badge */
        .badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
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
        
        /* Customer Info */
        .info-box {
            background: #f5f5f5;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .info-box p {
            margin: 0.5rem 0;
        }
        
        .info-box strong {
            color: #333;
        }
        
        /* Order Items */
        .order-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .item-qty {
            color: #666;
            font-size: 0.9rem;
        }
        
        .item-price {
            text-align: right;
            font-weight: 600;
        }
        
        /* Status Update Form */
        .status-update {
            margin-top: 1.5rem;
            padding: 1.5rem;
            background: #E8F5E9;
            border-radius: 8px;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
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
        
        .btn-warning {
            background: #FFC107;
            color: #333;
        }
        
        .btn-warning:hover {
            background: #FFB300;
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
        
        /* Payment Proof */
        .payment-proof {
            background: #FFF9C4;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .payment-proof h3 {
            color: #F57C00;
            margin-bottom: 1rem;
        }
        
        .proof-image {
            max-width: 100%;
            max-height: 400px;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
        }
        
        /* Timeline - Updated */
        .timeline {
            position: relative;
            padding-left: 40px;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 2rem;
            display: flex;
            align-items: center;
        }
        
        .timeline-item:last-child {
            padding-bottom: 0;
        }
        
        .timeline-dot {
            position: absolute;
            left: -40px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #ddd;
            border: 3px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 2;
        }
        
        .timeline-dot.active {
            background: #2E7D32;
        }
        
        .timeline-dot.inactive {
            background: #ddd;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 30px;
            bottom: -10px;
            width: 2px;
            background: #ddd;
            z-index: 1;
        }
        
        .timeline-item:last-child::before {
            display: none;
        }
        
        .timeline-item.completed::before {
            background: #2E7D32;
        }
        
        .timeline-label {
            font-weight: 500;
            color: #333;
            margin-left: 0.5rem;
        }
        
        .timeline-item.active .timeline-label {
            color: #2E7D32;
            font-weight: 600;
        }
        
        .timeline-date {
            font-size: 0.85rem;
            color: #666;
            margin-left: 0.5rem;
        }
        
        /* Sidebar Card - Updated */
        .sidebar-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        
        .sidebar-card h3 {
            margin-bottom: 1.5rem;
            color: #333;
            font-size: 1.1rem;
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
            <h1 style="margin-bottom: 2rem;">Detail Pesanan</h1>
            
            <?php if($success): ?>
                <div class="alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <div class="detail-layout">
                <!-- Order Details -->
                <div>
                    <div class="card">
                        <div class="order-header">
                            <div>
                                <div class="order-number">Pesanan #<?php echo $pesanan['id']; ?></div>
                                <div class="order-date">
                                    <?php echo date('d F Y, H:i', strtotime($pesanan['created_at'])); ?>
                                </div>
                            </div>
                            <div>
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
                            </div>
                        </div>
                        
                        <!-- Customer Info -->
                        <h3>Informasi Pembeli</h3>
                        <div class="info-box">
                            <p><strong>Nama:</strong> <?php echo htmlspecialchars($pesanan['nama_pembeli'] ?? 'Tidak tersedia'); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($pesanan['email'] ?? 'Tidak tersedia'); ?></p>
                            <?php if(!empty($pesanan['no_hp'])): ?>
                                <p><strong>No. HP:</strong> <?php echo htmlspecialchars($pesanan['no_hp']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Shipping Address -->
                        <h3>Alamat Pengiriman</h3>
                        <div class="info-box">
                            <p><?php echo nl2br(htmlspecialchars($pesanan['alamat_kirim'])); ?></p>
                        </div>
                        
                        <!-- Payment Method -->
                        <h3>Metode Pembayaran</h3>
                        <div class="info-box">
                            <p><strong><?php echo $pesanan['metode_pembayaran'] == 'cash' ? 'Cash on Delivery (COD)' : 'Transfer Bank'; ?></strong></p>
                        </div>
                        
                        <!-- Order Items -->
                        <h3>Item Pesanan (dari toko Anda)</h3>
                        <?php foreach($items as $item): ?>
                            <div class="order-item">
                                <?php if($item['gambar'] && file_exists('../uploads/' . $item['gambar'])): ?>
                                    <img src="../uploads/<?php echo htmlspecialchars($item['gambar']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['nama']); ?>" 
                                         class="item-image">
                                <?php else: ?>
                                    <div class="item-image" style="background: #E8F5E9; display: flex; align-items: center; justify-content: center;">
                                        <span>🌾</span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="item-details">
                                    <div class="item-name"><?php echo htmlspecialchars($item['nama']); ?></div>
                                    <div class="item-qty"><?php echo $item['jumlah']; ?> x Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></div>
                                </div>
                                
                                <div class="item-price">
                                    Rp <?php echo number_format($item['harga'] * $item['jumlah'], 0, ',', '.'); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid #eee;">
                            <div style="display: flex; justify-content: space-between; font-size: 1.25rem; font-weight: bold;">
                                <span>Total (dari toko Anda)</span>
                                <span style="color: #2E7D32;">Rp <?php echo number_format($pesanan['total_toko'] ?? 0, 0, ',', '.'); ?></span>
                            </div>
                        </div>
                        
                        <!-- Payment Proof -->
                        <?php if($pesanan['metode_pembayaran'] == 'transfer' && $pesanan['bukti_pembayaran']): ?>
                            <div class="payment-proof">
                                <h3>Bukti Transfer</h3>
                                <img src="../uploads/bukti/<?php echo $pesanan['bukti_pembayaran']; ?>" 
                                     class="proof-image"
                                     onclick="window.open(this.src, '_blank')"
                                     alt="Bukti Transfer">
                                <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #666;">
                                    Klik gambar untuk memperbesar
                                </p>
                                
                                <?php if($pesanan['status'] == 'pending'): ?>
                                    <form method="POST" style="margin-top: 1rem;">
                                        <button type="submit" name="verifikasi_pembayaran" class="btn btn-primary">
                                            ✓ Verifikasi Pembayaran
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Status Update -->
                        <?php if($pesanan['status'] != 'selesai'): ?>
                            <div class="status-update">
                                <h3>Update Status Pesanan</h3>
                                <form method="POST">
                                    <div class="form-group">
                                        <label>Pilih Status:</label>
                                        <select name="status" required>
                                            <option value="">-- Pilih Status --</option>
                                            <?php if($pesanan['metode_pembayaran'] == 'cash'): ?>
                                                <?php if($pesanan['status'] == 'pending'): ?>
                                                    <option value="dikemas">Dikemas</option>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if($pesanan['status'] == 'dibayar'): ?>
                                                    <option value="dikemas">Dikemas</option>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if($pesanan['status'] == 'dikemas'): ?>
                                                <option value="dikirim">Dikirim</option>
                                            <?php endif; ?>
                                            <?php if($pesanan['status'] == 'dikirim'): ?>
                                                <option value="selesai">Selesai</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="update_status" class="btn btn-primary">
                                        Update Status
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div>
                    <!-- Actions -->
                    <div class="sidebar-card">
                        <h3 style="margin-bottom: 1rem;">Aksi Cepat</h3>
                        <a href="pesanan-manage.php" class="btn btn-secondary" style="width: 100%; display: block; text-align: center; text-decoration: none; margin-bottom: 0.5rem;">
                            ← Kembali ke Daftar Pesanan
                        </a>
                        <?php if($pesanan['metode_pembayaran'] == 'transfer' && $pesanan['status'] == 'pending' && !$pesanan['bukti_pembayaran']): ?>
                            <a href="bukti-transfer.php" class="btn btn-warning" style="width: 100%; display: block; text-align: center; text-decoration: none;">
                                Lihat Bukti Transfer Pending
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Timeline Status -->
                    <div class="sidebar-card">
                        <h3>Timeline Status</h3>
                        <div class="timeline">
                            <?php 
                            if($pesanan['metode_pembayaran'] == 'cash') {
                                $status_flow = [
                                    'pending' => 'Pesanan Baru',
                                    'dikemas' => 'Dikemas',
                                    'dikirim' => 'Dikirim',
                                    'selesai' => 'Selesai'
                                ];
                            } else {
                                $status_flow = [
                                    'pending' => 'Menunggu Pembayaran',
                                    'dibayar' => 'Dibayar',
                                    'dikemas' => 'Dikemas',
                                    'dikirim' => 'Dikirim',
                                    'selesai' => 'Selesai'
                                ];
                            }
                            
                            $current_status_index = array_search($pesanan['status'], array_keys($status_flow));
                            $i = 0;
                            foreach($status_flow as $key => $label):
                                $is_active = $i <= $current_status_index;
                                $is_current = $i == $current_status_index;
                            ?>
                                <div class="timeline-item <?php echo $is_active ? 'active completed' : ''; ?>">
                                    <div class="timeline-dot <?php echo $is_active ? 'active' : 'inactive'; ?>"></div>
                                    <div>
                                        <span class="timeline-label"><?php echo $label; ?></span>
                                        <?php if($is_current): ?>
                                            <span class="timeline-date">(Status saat ini)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php 
                                $i++;
                            endforeach; 
                            ?>
                        </div>
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
