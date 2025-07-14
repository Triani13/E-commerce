<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Cek login pembeli
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'pembeli') {
    header('Location: login.php');
    exit;
}

$title = 'Checkout';
$error = '';

// Ambil item keranjang
try {
    $stmt = $pdo->prepare("
        SELECT k.*, p.nama, p.harga, p.stok, p.gambar, t.nama_toko, t.id as toko_id
        FROM keranjang k 
        JOIN produk p ON k.produk_id = p.id 
        JOIN toko t ON p.toko_id = t.id 
        WHERE k.user_id = ? 
        ORDER BY t.id, k.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $keranjang_items = $stmt->fetchAll();
    
    if(empty($keranjang_items)) {
        header('Location: keranjang.php');
        exit;
    }
    
    // Group by toko
    $items_by_toko = [];
    $total = 0;
    foreach($keranjang_items as $item) {
        $toko_id = $item['toko_id'];
        if(!isset($items_by_toko[$toko_id])) {
            $items_by_toko[$toko_id] = [
                'nama_toko' => $item['nama_toko'],
                'items' => []
            ];
        }
        $items_by_toko[$toko_id]['items'][] = $item;
        $total += $item['harga'] * $item['jumlah'];
    }
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Ambil data user untuk pre-fill
try {
    $stmt = $pdo->prepare("SELECT nama, email, no_hp FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch();
} catch(PDOException $e) {
    $user_data = [];
}

// Handle checkout
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_penerima = $_POST['nama_penerima'] ?? '';
    $no_hp_penerima = $_POST['no_hp_penerima'] ?? '';
    $alamat = $_POST['alamat'] ?? '';
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? '';
    
    if(empty($nama_penerima) || empty($no_hp_penerima) || empty($alamat) || empty($metode_pembayaran)) {
        $error = "Semua field harus diisi!";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Buat alamat lengkap dengan nama dan no HP
            $alamat_lengkap = "Penerima: " . $nama_penerima . "\n";
            $alamat_lengkap .= "No. HP: " . $no_hp_penerima . "\n";
            $alamat_lengkap .= "Alamat: " . $alamat;
            
            // Buat pesanan
            $stmt = $pdo->prepare("INSERT INTO pesanan (user_id, total, metode_pembayaran, alamat_kirim) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $total, $metode_pembayaran, $alamat_lengkap]);
            $pesanan_id = $pdo->lastInsertId();
            
            // Insert detail pesanan
            foreach($keranjang_items as $item) {
                $stmt = $pdo->prepare("INSERT INTO detail_pesanan (pesanan_id, produk_id, jumlah, harga) VALUES (?, ?, ?, ?)");
                $stmt->execute([$pesanan_id, $item['produk_id'], $item['jumlah'], $item['harga']]);
                
                // Update stok
                $stmt = $pdo->prepare("UPDATE produk SET stok = stok - ? WHERE id = ?");
                $stmt->execute([$item['jumlah'], $item['produk_id']]);
            }
            
            // Hapus keranjang
            $stmt = $pdo->prepare("DELETE FROM keranjang WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            $pdo->commit();
            
            // Redirect ke halaman sukses
            header('Location: pesanan-sukses.php?id=' . $pesanan_id);
            exit;
            
        } catch(Exception $e) {
            $pdo->rollBack();
            $error = "Terjadi kesalahan saat memproses pesanan.";
        }
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
        
        /* Checkout Layout */
        .checkout-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            align-items: start;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        
        .card h2 {
            margin-bottom: 1rem;
            color: #2E7D32;
        }
        
        .card h3 {
            margin-bottom: 1rem;
            color: #333;
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
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2E7D32;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Payment Info */
        .payment-info {
            margin-top: 1rem;
            padding: 1rem;
            background: #E3F2FD;
            border-radius: 4px;
            display: none;
        }
        
        .payment-info.show {
            display: block;
        }
        
        .payment-info strong {
            display: block;
            margin-bottom: 0.5rem;
            color: #1976D2;
        }
        
        .payment-info p {
            margin: 0.25rem 0;
            font-size: 0.9rem;
        }
        
        .payment-info small {
            display: block;
            margin-top: 0.5rem;
            color: #666;
        }
        
        /* Order Items */
        .toko-group {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .toko-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .toko-header {
            font-weight: 600;
            color: #2E7D32;
            margin-bottom: 0.5rem;
        }
        
        .order-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
        }
        
        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .item-qty {
            font-size: 0.9rem;
            color: #666;
        }
        
        .item-price {
            text-align: right;
            font-weight: 600;
        }
        
        /* Order Summary */
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .summary-total {
            font-size: 1.25rem;
            font-weight: bold;
            color: #2E7D32;
            padding-top: 1rem;
            border-top: 2px solid #eee;
            margin-top: 1rem;
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .btn-primary {
            background-color: #FFC107;
            color: #333;
            width: 100%;
            font-size: 1.1rem;
            padding: 1rem;
        }
        
        .btn-primary:hover {
            background-color: #FFB300;
            transform: translateY(-2px);
        }
        
        /* Alert */
        .alert-error {
            background: #FFEBEE;
            color: #C62828;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            border: 1px solid #EF9A9A;
        }
        
        /* Info Box */
        .info-box {
            background: #FFF9C4;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            border-left: 4px solid #FFC107;
        }
        
        .info-box p {
            margin: 0;
            font-size: 0.9rem;
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
            <h1 style="margin-bottom: 2rem;">Checkout</h1>
            
            <div class="checkout-layout">
                <!-- Form Checkout -->
                <div>
                    <?php if($error): ?>
                        <div class="alert-error">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <h2>Informasi Pengiriman</h2>
                        
                        <form method="POST">
                            <div class="form-group">
                                <label>Nama Penerima *</label>
                                <input type="text" name="nama_penerima" required 
                                       value="<?php echo isset($_POST['nama_penerima']) ? htmlspecialchars($_POST['nama_penerima']) : htmlspecialchars($user_data['nama'] ?? ''); ?>"
                                       placeholder="Nama lengkap penerima barang">
                            </div>
                            
                            <div class="form-group">
                                <label>No. HP/WhatsApp Penerima *</label>
                                <input type="tel" name="no_hp_penerima" required 
                                       value="<?php echo isset($_POST['no_hp_penerima']) ? htmlspecialchars($_POST['no_hp_penerima']) : htmlspecialchars($user_data['no_hp'] ?? ''); ?>"
                                       placeholder="+62812345678">
                                <small style="color: #666;">Untuk koordinasi pengiriman dengan kurir</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Alamat Lengkap *</label>
                                <textarea name="alamat" rows="4" required placeholder="Jl. Nama Jalan No. XX, RT/RW, Kelurahan, Kecamatan, Kota/Kabupaten, Provinsi, Kode Pos"><?php echo isset($_POST['alamat']) ? htmlspecialchars($_POST['alamat']) : ''; ?></textarea>
                                <small style="color: #666;">Sertakan patokan jika diperlukan</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Metode Pembayaran *</label>
                                <select name="metode_pembayaran" id="metode_pembayaran" required onchange="updatePaymentInfo()">
                                    <option value="">Pilih Metode Pembayaran</option>
                                    <option value="transfer">Transfer Bank</option>
                                    <option value="cash">Cash on Delivery (COD)</option>
                                </select>
                                
                                <div id="transfer-info" class="payment-info">
                                    <strong>Informasi Transfer:</strong>
                                    <p>Bank BCA - 1234567890</p>
                                    <p>a.n. Mitra Tani Jaya</p>
                                    <small>Anda akan diminta upload bukti pembayaran setelah pesanan dibuat</small>
                                </div>
                                
                                <div id="cod-info" class="payment-info">
                                    <strong>Pembayaran COD:</strong>
                                    <p>Bayar saat barang diterima</p>
                                    <p>Siapkan uang tunai sesuai total pesanan</p>
                                    <small>Pembayaran dilakukan kepada kurir saat pengiriman</small>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Buat Pesanan</button>
                        </form>
                    </div>
                    
                    <div class="info-box">
                        <p><strong>Info:</strong> Pesanan akan diproses setelah pembayaran dikonfirmasi (untuk transfer) atau langsung diproses (untuk COD)</p>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div>
                    <div class="card">
                        <h3>Ringkasan Pesanan</h3>
                        
                        <?php foreach($items_by_toko as $toko_id => $toko_data): ?>
                            <div class="toko-group">
                                <div class="toko-header">🏪 <?php echo htmlspecialchars($toko_data['nama_toko']); ?></div>
                                
                                <?php foreach($toko_data['items'] as $item): ?>
                                    <div class="order-item">
                                        <?php if($item['gambar'] && file_exists('uploads/' . $item['gambar'])): ?>
                                            <img src="uploads/<?php echo htmlspecialchars($item['gambar']); ?>" 
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
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span>Rp <?php echo number_format($total, 0, ',', '.'); ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Biaya Pengiriman</span>
                            <span style="color: #666;">Gratis</span>
                        </div>
                        
                        <div class="summary-row summary-total">
                            <span>Total Pembayaran</span>
                            <span>Rp <?php echo number_format($total, 0, ',', '.'); ?></span>
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
    
    <script>
    function updatePaymentInfo() {
        var method = document.getElementById('metode_pembayaran').value;
        var transferInfo = document.getElementById('transfer-info');
        var codInfo = document.getElementById('cod-info');
        
        transferInfo.classList.remove('show');
        codInfo.classList.remove('show');
        
        if(method === 'transfer') {
            transferInfo.classList.add('show');
        } else if(method === 'cash') {
            codInfo.classList.add('show');
        }
    }
    </script>
</body>
</html>
