<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

// Cek login penjual
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'penjual') {
    header('Location: ../login.php');
    exit;
}

$title = 'Pengaturan Toko';
$success = '';
$error = '';

// Ambil data toko
try {
    $stmt = $pdo->prepare("SELECT * FROM toko WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $toko = $stmt->fetch();
    
    if(!$toko) {
        // Buat toko otomatis jika belum ada
        $stmt = $pdo->prepare("INSERT INTO toko (user_id, nama_toko) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], "Toko " . $_SESSION['nama']]);
        $toko_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("SELECT * FROM toko WHERE id = ?");
        $stmt->execute([$toko_id]);
        $toko = $stmt->fetch();
    }
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle update toko
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_toko = $_POST['nama_toko'] ?? '';
    $deskripsi = $_POST['deskripsi'] ?? '';
    $no_hp_toko = $_POST['no_hp_toko'] ?? '';
    $alamat_toko = $_POST['alamat_toko'] ?? '';
    
    if(empty($nama_toko)) {
        $error = "Nama toko harus diisi!";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE toko SET nama_toko = ?, deskripsi = ?, no_hp = ?, alamat = ? WHERE id = ?");
            $stmt->execute([$nama_toko, $deskripsi, $no_hp_toko, $alamat_toko, $toko['id']]);
            $success = "Pengaturan toko berhasil disimpan!";
            
            // Refresh data
            $stmt = $pdo->prepare("SELECT * FROM toko WHERE id = ?");
            $stmt->execute([$toko['id']]);
            $toko = $stmt->fetch();
        } catch(PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Statistik toko
try {
    // Total produk
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM produk WHERE toko_id = ?");
    $stmt->execute([$toko['id']]);
    $total_produk = $stmt->fetch()['total'];
    
    // Total pesanan
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT p.id) as total 
        FROM pesanan p 
        JOIN detail_pesanan dp ON p.id = dp.pesanan_id 
        JOIN produk pr ON dp.produk_id = pr.id 
        WHERE pr.toko_id = ?
    ");
    $stmt->execute([$toko['id']]);
    $total_pesanan = $stmt->fetch()['total'];
    
    // Total pendapatan
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(dp.jumlah * dp.harga), 0) as total 
        FROM pesanan p 
        JOIN detail_pesanan dp ON p.id = dp.pesanan_id 
        JOIN produk pr ON dp.produk_id = pr.id 
        WHERE pr.toko_id = ? 
        AND p.status IN ('dibayar', 'dikemas', 'dikirim', 'selesai')
    ");
    $stmt->execute([$toko['id']]);
    $total_pendapatan = $stmt->fetch()['total'];
} catch(PDOException $e) {
    $total_produk = 0;
    $total_pesanan = 0;
    $total_pendapatan = 0;
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
        .content-grid {
            display: grid;
            gap: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .card h2 {
            margin-bottom: 1.5rem;
            color: #2E7D32;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: #f5f5f5;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid;
        }
        
        .stat-card.green {
            border-left-color: #2E7D32;
            background-color: #E8F5E9;
        }
        
        .stat-card.blue {
            border-left-color: #1976D2;
            background-color: #E3F2FD;
        }
        
        .stat-card.yellow {
            border-left-color: #FFC107;
            background-color: #FFF9C4;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-card.green .stat-value { color: #2E7D32; }
        .stat-card.blue .stat-value { color: #1976D2; }
        .stat-card.yellow .stat-value { color: #F57C00; }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Form */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2E7D32;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: #666;
            font-size: 0.875rem;
        }
        
        /* Buttons */
        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #2E7D32;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1B5E20;
            transform: translateY(-2px);
        }
        
        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #E8F5E9;
            color: #2E7D32;
            border: 1px solid #A5D6A7;
        }
        
        .alert-error {
            background: #FFEBEE;
            color: #C62828;
            border: 1px solid #EF9A9A;
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
            <h1 style="margin-bottom: 2rem;">Pengaturan Toko</h1>
            
            <div class="dashboard-layout">
                <!-- Sidebar -->
                <div class="sidebar">
                    <h3>Menu</h3>
                    <nav class="sidebar-menu">
                        <a href="penjual.php">Dashboard</a>
                        <a href="produk-manage.php">Manajemen Produk</a>
                        <a href="pesanan-manage.php">Manajemen Pesanan</a>
                        <a href="bukti-transfer.php">Bukti Transfer</a>
                        <a href="toko-setting.php" class="active">Pengaturan Toko</a>
                    </nav>
                </div>
                
                <!-- Content -->
                <div class="content-grid">
                    <?php if($success): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($error): ?>
                        <div class="alert alert-error">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Statistik Toko -->
                    <div class="card">
                        <h2>Statistik Toko</h2>
                        <div class="stats-grid">
                            <div class="stat-card green">
                                <div class="stat-value"><?php echo $total_produk; ?></div>
                                <div class="stat-label">Total Produk</div>
                            </div>
                            <div class="stat-card blue">
                                <div class="stat-value"><?php echo $total_pesanan; ?></div>
                                <div class="stat-label">Total Pesanan</div>
                            </div>
                            <div class="stat-card yellow">
                                <div class="stat-value">Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></div>
                                <div class="stat-label">Total Pendapatan</div>
                            </div>
                        </div>
                        <p style="text-align: center; color: #666; margin-top: 1rem;">
                            Bergabung sejak: <?php echo date('d F Y', strtotime($toko['created_at'])); ?>
                        </p>
                    </div>
                    
                    <!-- Form Pengaturan -->
                    <div class="card">
                        <h2>Informasi Toko</h2>
                        <form method="POST">
                            <div class="form-group">
                                <label>Nama Toko *</label>
                                <input type="text" name="nama_toko" value="<?php echo htmlspecialchars($toko['nama_toko']); ?>" required>
                                <small>Nama toko yang akan ditampilkan kepada pembeli</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Deskripsi Toko</label>
                                <textarea name="deskripsi" placeholder="Ceritakan tentang toko Anda..."><?php echo htmlspecialchars($toko['deskripsi'] ?? ''); ?></textarea>
                                <small>Deskripsi singkat tentang toko dan produk yang Anda jual</small>
                            </div>
                            
                            <div class="form-group">
                                <label>No. HP/WhatsApp Toko</label>
                                <input type="tel" name="no_hp_toko" value="<?php echo htmlspecialchars($toko['no_hp'] ?? ''); ?>" placeholder="+62812345678">
                                <small>Nomor yang dapat dihubungi pembeli untuk pertanyaan</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Alamat Toko</label>
                                <textarea name="alamat_toko" rows="3" placeholder="Alamat lengkap toko/gudang"><?php echo htmlspecialchars($toko['alamat'] ?? ''); ?></textarea>
                                <small>Alamat untuk pickup atau lokasi toko fisik</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </form>
                    </div>
                    
                    <!-- Info Box -->
                    <div class="info-box">
                        <h3>Tips Mengelola Toko</h3>
                        <ul style="margin-left: 1.5rem;">
                            <li>Gunakan nama toko yang mudah diingat dan mencerminkan produk Anda</li>
                            <li>Tulis deskripsi yang menarik dan informatif tentang toko Anda</li>
                            <li>Upload foto produk berkualitas tinggi untuk menarik pembeli</li>
                            <li>Update stok produk secara berkala</li>
                            <li>Respon pesanan dengan cepat untuk kepuasan pelanggan</li>
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
