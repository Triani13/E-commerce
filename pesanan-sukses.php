<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Cek login pembeli
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'pembeli') {
    header('Location: login.php');
    exit;
}

$title = 'Pesanan Berhasil';

// Ambil ID pesanan
$pesanan_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Ambil detail pesanan
try {
    $stmt = $pdo->prepare("SELECT * FROM pesanan WHERE id = ? AND user_id = ?");
    $stmt->execute([$pesanan_id, $_SESSION['user_id']]);
    $pesanan = $stmt->fetch();
    
    if(!$pesanan) {
        header('Location: dashboard/pembeli.php');
        exit;
    }
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
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
            display: flex;
            align-items: center;
        }
        
        /* Success Container */
        .success-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            padding: 3rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .success-icon {
            font-size: 5rem;
            color: #4CAF50;
            margin-bottom: 1rem;
            animation: checkmark 0.5s ease-in-out;
        }
        
        @keyframes checkmark {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .success-container h1 {
            color: #2E7D32;
            margin-bottom: 1rem;
        }
        
        .order-number {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 2rem;
        }
        
        .order-number strong {
            color: #2E7D32;
        }
        
        /* Payment Info */
        .payment-info {
            background: #FFF9C4;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: left;
        }
        
        .payment-info h3 {
            color: #F57C00;
            margin-bottom: 1rem;
        }
        
        .payment-info .amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2E7D32;
            margin: 1rem 0;
        }
        
        .bank-info {
            background: white;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }
        
        .bank-info p {
            margin: 0.25rem 0;
        }
        
        /* COD Info */
        .cod-info {
            background: #E3F2FD;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .cod-info h3 {
            color: #1976D2;
            margin-bottom: 1rem;
        }
        
        /* Next Steps */
        .next-steps {
            background: #E8F5E9;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: left;
        }
        
        .next-steps h3 {
            color: #2E7D32;
            margin-bottom: 1rem;
        }
        
        .next-steps ol {
            margin-left: 1.5rem;
        }
        
        .next-steps li {
            margin-bottom: 0.5rem;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .btn-primary {
            background: #2E7D32;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1B5E20;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #ddd;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #ccc;
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
            <div class="success-container">
                <div class="success-icon">✓</div>
                <h1>Pesanan Berhasil Dibuat!</h1>
                <p class="order-number">
                    Nomor Pesanan: <strong>#<?php echo $pesanan['id']; ?></strong>
                </p>
                
                <?php if($pesanan['metode_pembayaran'] == 'cash'): ?>
                    <!-- COD Info -->
                    <div class="cod-info">
                        <h3>Pembayaran COD</h3>
                        <p><strong>Total yang harus dibayar:</strong></p>
                        <p class="amount">Rp <?php echo number_format($pesanan['total'], 0, ',', '.'); ?></p>
                        <p>Pembayaran dilakukan saat barang diterima. Siapkan uang tunai dengan nominal tepat.</p>
                    </div>
                <?php else: ?>
                    <!-- Transfer Info -->
                    <div class="payment-info">
                        <h3>Instruksi Pembayaran</h3>
                        <p>Silakan lakukan pembayaran dengan total:</p>
                        <p class="amount">Rp <?php echo number_format($pesanan['total'], 0, ',', '.'); ?></p>
                        
                        <div class="bank-info">
                            <p><strong>Transfer ke:</strong></p>
                            <p>Bank BCA - 1234567890</p>
                            <p>a.n. Mitra Tani Jaya</p>
                        </div>
                        
                        <p style="color: #666; font-size: 0.9rem; margin-top: 1rem;">
                            * Harap transfer sesuai nominal di atas untuk memudahkan verifikasi
                        </p>
                    </div>
                <?php endif; ?>
                
                <div class="next-steps">
                    <h3>Langkah Selanjutnya:</h3>
                    <?php if($pesanan['metode_pembayaran'] == 'cash'): ?>
                        <ol>
                            <li>Pesanan Anda akan segera diproses oleh penjual</li>
                            <li>Anda akan menerima notifikasi saat pesanan dikirim</li>
                            <li>Siapkan uang tunai saat kurir tiba</li>
                            <li>Periksa kondisi barang sebelum membayar</li>
                        </ol>
                    <?php else: ?>
                        <ol>
                            <li>Lakukan pembayaran sesuai instruksi di atas</li>
                            <li>Upload bukti pembayaran di halaman detail pesanan</li>
                            <li>Pesanan akan diproses setelah pembayaran terverifikasi</li>
                            <li>Anda akan menerima notifikasi saat pesanan dikirim</li>
                        </ol>
                    <?php endif; ?>
                </div>
                
                <div class="action-buttons">
                    <a href="dashboard/pesanan-detail.php?id=<?php echo $pesanan['id']; ?>" class="btn btn-primary">
                        Lihat Detail Pesanan
                    </a>
                    <a href="produk.php" class="btn btn-secondary">
                        Lanjut Belanja
                    </a>
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
