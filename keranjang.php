<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Cek login pembeli
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'pembeli') {
    header('Location: login.php');
    exit;
}

$title = 'Keranjang Belanja';

// Handle update quantity
if(isset($_POST['update_qty'])) {
    $keranjang_id = $_POST['keranjang_id'];
    $jumlah = $_POST['jumlah'];
    
    if($jumlah > 0) {
        $stmt = $pdo->prepare("UPDATE keranjang SET jumlah = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$jumlah, $keranjang_id, $_SESSION['user_id']]);
    }
}

// Handle hapus item
if(isset($_GET['hapus'])) {
    $keranjang_id = $_GET['hapus'];
    $stmt = $pdo->prepare("DELETE FROM keranjang WHERE id = ? AND user_id = ?");
    $stmt->execute([$keranjang_id, $_SESSION['user_id']]);
    header('Location: keranjang.php');
    exit;
}

// Ambil isi keranjang
try {
    $stmt = $pdo->prepare("
        SELECT k.*, p.nama, p.harga, p.stok, p.gambar, t.nama_toko 
        FROM keranjang k 
        JOIN produk p ON k.produk_id = p.id 
        JOIN toko t ON p.toko_id = t.id 
        WHERE k.user_id = ? 
        ORDER BY k.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $keranjang_items = $stmt->fetchAll();
    
    // Hitung total
    $total = 0;
    foreach($keranjang_items as $item) {
        $total += $item['harga'] * $item['jumlah'];
    }
} catch(PDOException $e) {
    $keranjang_items = [];
    $total = 0;
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
        
        /* Cart Layout */
        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
            align-items: start;
        }
        
        /* Cart Items */
        .cart-items {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .cart-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-weight: 600;
            color: #2E7D32;
            margin-bottom: 0.25rem;
        }
        
        .item-seller {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }
        
        .item-price {
            font-weight: bold;
            color: #333;
        }
        
        .item-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .qty-input {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .qty-input input {
            width: 60px;
            padding: 0.5rem;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .btn-update {
            padding: 0.5rem 1rem;
            background: #2E7D32;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn-update:hover {
            background: #1B5E20;
        }
        
        .btn-remove {
            color: #f44336;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .btn-remove:hover {
            text-decoration: underline;
        }
        
        /* Order Summary */
        .order-summary {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 100px;
        }
        
        .summary-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }
        
        .summary-total {
            font-size: 1.25rem;
            font-weight: bold;
            color: #2E7D32;
            padding-top: 1rem;
            border-top: 2px solid #eee;
        }
        
        .btn-checkout {
            display: block;
            width: 100%;
            padding: 1rem;
            background: #FFC107;
            color: #333;
            border: none;
            border-radius: 4px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            margin-top: 1.5rem;
            transition: all 0.3s;
        }
        
        .btn-checkout:hover {
            background: #FFB300;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        /* Empty State */
        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .empty-cart-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .btn-primary {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #2E7D32;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 1rem;
        }
        
        .btn-primary:hover {
            background: #1B5E20;
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
                    <li><a href="/taniasli/keranjang.php" style="font-weight: bold;">🛒 Keranjang</a></li>
                    <li><a href="/taniasli/dashboard/pembeli.php">Dashboard</a></li>
                    <li><a href="/taniasli/logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <div class="container">
            <h1 style="margin-bottom: 2rem;">Keranjang Belanja</h1>
            
            <?php if(empty($keranjang_items)): ?>
                <div class="empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <h2>Keranjang Anda Kosong</h2>
                    <p style="color: #666; margin: 1rem 0;">Belum ada produk di keranjang belanja Anda.</p>
                    <a href="produk.php" class="btn-primary">Mulai Belanja</a>
                </div>
            <?php else: ?>
                <div class="cart-layout">
                    <!-- Cart Items -->
                    <div class="cart-items">
                        <h2 style="margin-bottom: 1rem;">Item di Keranjang (<?php echo count($keranjang_items); ?>)</h2>
                        
                        <?php foreach($keranjang_items as $item): ?>
                            <div class="cart-item">
                                <?php if($item['gambar'] && file_exists('uploads/' . $item['gambar'])): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($item['gambar']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['nama']); ?>" 
                                         class="item-image">
                                <?php else: ?>
                                    <div class="item-image" style="background: #E8F5E9; display: flex; align-items: center; justify-content: center;">
                                        <span style="font-size: 2rem;">🌾</span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="item-details">
                                    <h3 class="item-name"><?php echo htmlspecialchars($item['nama']); ?></h3>
                                    <p class="item-seller">🏪 <?php echo htmlspecialchars($item['nama_toko']); ?></p>
                                    <p class="item-price">Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></p>
                                    
                                    <div class="item-actions">
                                        <form method="POST" class="qty-input">
                                            <input type="hidden" name="keranjang_id" value="<?php echo $item['id']; ?>">
                                            <label>Jumlah:</label>
                                            <input type="number" name="jumlah" value="<?php echo $item['jumlah']; ?>" 
                                                   min="1" max="<?php echo $item['stok']; ?>">
                                            <button type="submit" name="update_qty" class="btn-update">Update</button>
                                        </form>
                                        <a href="?hapus=<?php echo $item['id']; ?>" 
                                           class="btn-remove" 
                                           onclick="return confirm('Hapus produk dari keranjang?')">Hapus</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="order-summary">
                        <h3 class="summary-title">Ringkasan Pesanan</h3>
                        
                        <div class="summary-row">
                            <span>Subtotal (<?php echo count($keranjang_items); ?> produk)</span>
                            <span>Rp <?php echo number_format($total, 0, ',', '.'); ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Biaya Pengiriman</span>
                            <span style="color: #666;">Dihitung saat checkout</span>
                        </div>
                        
                        <div class="summary-row summary-total">
                            <span>Total</span>
                            <span>Rp <?php echo number_format($total, 0, ',', '.'); ?></span>
                        </div>
                        
                        <a href="checkout.php" class="btn-checkout">
                            Lanjutkan ke Pembayaran
                        </a>
                        
                        <p style="text-align: center; margin-top: 1rem;">
                            <a href="produk.php" style="color: #2E7D32;">← Lanjut Belanja</a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Mitra Tani Jaya. Memberdayakan Pertanian Lokal.</p>
        </div>
    </footer>
</body>
</html>
