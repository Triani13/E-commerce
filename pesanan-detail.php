<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

// Cek login pembeli
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'pembeli') {
    header('Location: ../login.php');
    exit;
}

$title = 'Detail Pesanan';
$success = '';

// Ambil ID pesanan
$pesanan_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Ambil detail pesanan
try {
    $stmt = $pdo->prepare("SELECT * FROM pesanan WHERE id = ? AND user_id = ?");
    $stmt->execute([$pesanan_id, $_SESSION['user_id']]);
    $pesanan = $stmt->fetch();
    
    if(!$pesanan) {
        header('Location: pembeli.php');
        exit;
    }
    
    // Ambil detail item
    $stmt = $pdo->prepare("
        SELECT dp.*, p.nama, p.gambar, t.nama_toko 
        FROM detail_pesanan dp 
        JOIN produk p ON dp.produk_id = p.id 
        JOIN toko t ON p.toko_id = t.id 
        WHERE dp.pesanan_id = ?
    ");
    $stmt->execute([$pesanan_id]);
    $items = $stmt->fetchAll();
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle upload bukti pembayaran
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['bukti_pembayaran'])) {
    $upload_dir = '../uploads/bukti/';
    if(!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_name = 'bukti_' . $pesanan_id . '_' . time() . '_' . $_FILES['bukti_pembayaran']['name'];
    $file_path = $upload_dir . $file_name;
    
    if(move_uploaded_file($_FILES['bukti_pembayaran']['tmp_name'], $file_path)) {
        $stmt = $pdo->prepare("UPDATE pesanan SET bukti_pembayaran = ? WHERE id = ?");
        $stmt->execute([$file_name, $pesanan_id]);
        
        $success = "Bukti pembayaran berhasil diupload! Menunggu verifikasi dari penjual.";
        
        // Refresh data
        $stmt = $pdo->prepare("SELECT * FROM pesanan WHERE id = ?");
        $stmt->execute([$pesanan_id]);
        $pesanan = $stmt->fetch();
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
        
        /* Order Info */
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
        
        .item-toko {
            color: #666;
            font-size: 0.9rem;
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
        
        .timeline-content h4 {
            margin-bottom: 0.25rem;
            color: #333;
            font-weight: 600;
        }
        
        .timeline-item.active .timeline-content h4 {
            color: #2E7D32;
        }
        
        .timeline-content p {
            color: #666;
            font-size: 0.9rem;
            margin: 0;
        }
        
        /* Upload Section */
        .upload-section {
            background: #FFF9C4;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .upload-section h3 {
            color: #F57C00;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .form-group input[type="file"] {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
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
        
        /* Alert */
        .alert-success {
            background: #E8F5E9;
            color: #2E7D32;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            border: 1px solid #A5D6A7;
        }
        
        /* Info Box */
        .info-box {
            background: #f5f5f5;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .info-box p {
            margin: 0.25rem 0;
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
                    <li><a href="/taniasli/dashboard/pembeli.php">Dashboard</a></li>
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
                        
                        <h3>Item Pesanan</h3>
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
                                    <div class="item-toko">🏪 <?php echo htmlspecialchars($item['nama_toko']); ?></div>
                                    <div class="item-qty"><?php echo $item['jumlah']; ?> x Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></div>
                                </div>
                                
                                <div class="item-price">
                                    Rp <?php echo number_format($item['harga'] * $item['jumlah'], 0, ',', '.'); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid #eee;">
                            <div style="display: flex; justify-content: space-between; font-size: 1.25rem; font-weight: bold;">
                                <span>Total Pembayaran</span>
                                <span style="color: #2E7D32;">Rp <?php echo number_format($pesanan['total'], 0, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Shipping Info -->
                    <div class="card">
                        <h3>Informasi Pengiriman</h3>
                        <div class="info-box">
                            <p><strong>Alamat Pengiriman:</strong></p>
                            <p><?php echo nl2br(htmlspecialchars($pesanan['alamat_kirim'])); ?></p>
                        </div>
                        <div class="info-box">
                            <p><strong>Metode Pembayaran:</strong> 
                                <?php echo $pesanan['metode_pembayaran'] == 'cash' ? 'Cash on Delivery (COD)' : 'Transfer Bank'; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div>
                    <!-- Timeline -->
                    <div class="card">
                        <h3>Status Pesanan</h3>
                        <div class="timeline">
                            <?php
                            $statuses = $pesanan['metode_pembayaran'] == 'cash' 
                                ? ['pending' => 'Pesanan Dibuat', 'dikemas' => 'Dikemas', 'dikirim' => 'Dikirim', 'selesai' => 'Selesai']
                                : ['pending' => 'Menunggu Pembayaran', 'dibayar' => 'Dibayar', 'dikemas' => 'Dikemas', 'dikirim' => 'Dikirim', 'selesai' => 'Selesai'];
                            
                            $current_index = array_search($pesanan['status'], array_keys($statuses));
                            $i = 0;
                            
                            foreach($statuses as $key => $label):
                                $is_active = $i <= $current_index;
                                $is_current = $i == $current_index;
                            ?>
                                <div class="timeline-item <?php echo $is_active ? 'active completed' : ''; ?>">
                                    <div class="timeline-dot <?php echo $is_active ? 'active' : ''; ?>"></div>
                                    <div class="timeline-content">
                                        <h4><?php echo $label; ?></h4>
                                        <?php if($is_current): ?>
                                            <p>Status saat ini</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php 
                                $i++;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                    
                    <!-- Upload Bukti -->
                    <?php if($pesanan['status'] == 'pending' && $pesanan['metode_pembayaran'] == 'transfer'): ?>
                        <div class="card">
                            <div class="upload-section">
                                <h3>Upload Bukti Pembayaran</h3>
                                
                                <?php if($pesanan['bukti_pembayaran']): ?>
                                    <div class="alert-success" style="margin-bottom: 1rem;">
                                        ✓ Bukti pembayaran sudah diupload, menunggu verifikasi.
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label>File Bukti Transfer</label>
                                        <input type="file" name="bukti_pembayaran" accept="image/*" required>
                                        <small style="display: block; margin-top: 0.5rem; color: #666;">
                                            Format: JPG, PNG. Maksimal 2MB
                                        </small>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <?php echo $pesanan['bukti_pembayaran'] ? 'Upload Ulang' : 'Upload Bukti'; ?>
                                    </button>
                                </form>
                            </div>
                        </div>
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
