<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

$title = 'Detail Produk';
$error = '';

// Ambil ID produk
$produk_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Ambil detail produk
try {
    $stmt = $pdo->prepare("
        SELECT p.*, t.nama_toko, t.id as toko_id, k.nama as kategori 
        FROM produk p 
        JOIN toko t ON p.toko_id = t.id 
        JOIN kategori k ON p.kategori_id = k.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$produk_id]);
    $produk = $stmt->fetch();
    
    if(!$produk) {
        header('Location: produk.php');
        exit;
    }
    
    $title = $produk['nama'] . ' - Detail Produk';
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle tambah ke keranjang
if(isset($_POST['tambah_keranjang'])) {
    if(!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    
    if($_SESSION['role'] != 'pembeli') {
        $error = "Hanya pembeli yang dapat menambahkan produk ke keranjang!";
    } else {
        $jumlah = $_POST['jumlah'] ?? 1;
        
        if($jumlah > $produk['stok']) {
            $error = "Jumlah melebihi stok yang tersedia!";
        } else {
            try {
                // Cek apakah sudah ada di keranjang
                $stmt = $pdo->prepare("SELECT id, jumlah FROM keranjang WHERE user_id = ? AND produk_id = ?");
                $stmt->execute([$_SESSION['user_id'], $produk_id]);
                $existing = $stmt->fetch();
                
                if($existing) {
                    // Update jumlah
                    $new_jumlah = $existing['jumlah'] + $jumlah;
                    if($new_jumlah > $produk['stok']) {
                        $error = "Total jumlah di keranjang melebihi stok!";
                    } else {
                        $stmt = $pdo->prepare("UPDATE keranjang SET jumlah = ? WHERE id = ?");
                        $stmt->execute([$new_jumlah, $existing['id']]);
                        header('Location: keranjang.php');
                        exit;
                    }
                } else {
                    // Insert baru
                    $stmt = $pdo->prepare("INSERT INTO keranjang (user_id, produk_id, jumlah) VALUES (?, ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $produk_id, $jumlah]);
                    header('Location: keranjang.php');
                    exit;
                }
            } catch(PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Ambil produk lain dari toko yang sama
try {
    $stmt = $pdo->prepare("
        SELECT * FROM produk 
        WHERE toko_id = ? AND id != ? 
        ORDER BY RAND() 
        LIMIT 4
    ");
    $stmt->execute([$produk['toko_id'], $produk_id]);
    $produk_lain = $stmt->fetchAll();
} catch(PDOException $e) {
    $produk_lain = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - Mitra Tani Jaya</title>
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
        
        /* Product Detail Layout */
        .product-detail {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }
        
        /* Image Section */
        .image-section {
            position: relative;
        }
        
        .main-image {
            width: 100%;
            height: 500px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .no-image {
            width: 100%;
            height: 500px;
            background: #E8F5E9;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 6rem;
            color: #2E7D32;
        }
        
        /* Info Section */
        .info-section h1 {
            font-size: 2rem;
            color: #2E7D32;
            margin-bottom: 1rem;
        }
        
        .price {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 1rem;
        }
        
        .stock-info {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #E8F5E9;
            color: #2E7D32;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }
        
        .stock-low {
            background: #FFF3E0;
            color: #F57C00;
        }
        
        .category-tag {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: #E3F2FD;
            color: #1976D2;
            border-radius: 4px;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            margin-left: 1rem;
        }
        
        /* Purchase Box */
        .purchase-box {
            background: #f5f5f5;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .quantity-selector label {
            font-weight: 600;
        }
        
        .qty-controls {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .qty-btn {
            padding: 0.5rem 1rem;
            background: white;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            transition: background 0.3s;
        }
        
        .qty-btn:hover {
            background: #f0f0f0;
        }
        
        .qty-input {
            width: 60px;
            padding: 0.5rem;
            border: none;
            text-align: center;
            font-size: 1rem;
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .btn-primary {
            background: #FFC107;
            color: #333;
            width: 100%;
        }
        
        .btn-primary:hover {
            background: #FFB300;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-primary:disabled {
            background: #ddd;
            color: #999;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Store Info */
        .store-info {
            border-top: 1px solid #eee;
            padding-top: 1.5rem;
            margin-top: 2rem;
        }
        
        .store-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f9f9f9;
            border-radius: 8px;
        }
        
        .store-icon {
            font-size: 2rem;
        }
        
        .store-name {
            font-weight: 600;
            color: #2E7D32;
            margin-bottom: 0.25rem;
        }
        
        /* Description */
        .description-section {
            margin-top: 2rem;
        }
        
        .description-section h2 {
            color: #2E7D32;
            margin-bottom: 1rem;
        }
        
        .description-text {
            white-space: pre-wrap;
            line-height: 1.8;
            color: #555;
        }
        
        /* Related Products */
        .related-section {
            margin-top: 3rem;
        }
        
        .related-section h2 {
            color: #2E7D32;
            margin-bottom: 1.5rem;
        }
        
        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .product-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        
        .product-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .product-card-info {
            padding: 1rem;
        }
        
        .product-card-name {
            font-weight: 600;
            color: #2E7D32;
            margin-bottom: 0.5rem;
        }
        
        .product-card-price {
            font-weight: bold;
            color: #333;
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
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <?php if($_SESSION['role'] == 'pembeli'): ?>
                            <li><a href="/taniasli/keranjang.php">🛒 Keranjang</a></li>
                            <li><a href="/taniasli/dashboard/pembeli.php">Dashboard</a></li>
                        <?php else: ?>
                            <li><a href="/taniasli/dashboard/penjual.php">Dashboard</a></li>
                        <?php endif; ?>
                        <li><a href="/taniasli/logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="/taniasli/login.php">Login</a></li>
                        <li><a href="/taniasli/register.php" class="btn btn-accent" style="background: #FFC107; color: #333; padding: 0.5rem 1rem;">Daftar</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <div class="container">
            <div class="product-detail">
                <?php if($error): ?>
                    <div class="alert-error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="product-grid">
                    <!-- Image Section -->
                    <div class="image-section">
                        <?php if($produk['gambar'] && file_exists('uploads/' . $produk['gambar'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($produk['gambar']); ?>" 
                                 alt="<?php echo htmlspecialchars($produk['nama']); ?>" 
                                 class="main-image">
                        <?php else: ?>
                            <div class="no-image">🌾</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Info Section -->
                    <div class="info-section">
                        <h1><?php echo htmlspecialchars($produk['nama']); ?></h1>
                        
                        <div class="price">
                            Rp <?php echo number_format($produk['harga'], 0, ',', '.'); ?>
                        </div>
                        
                        <div>
                            <span class="stock-info <?php echo $produk['stok'] <= 10 ? 'stock-low' : ''; ?>">
                                📦 Stok: <?php echo $produk['stok']; ?> unit
                            </span>
                            <span class="category-tag">
                                📁 <?php echo htmlspecialchars($produk['kategori']); ?>
                            </span>
                        </div>
                        
                        <!-- Purchase Box -->
                        <div class="purchase-box">
                            <form method="POST">
                                <div class="quantity-selector">
                                    <label>Jumlah:</label>
                                    <div class="qty-controls">
                                        <button type="button" class="qty-btn" onclick="changeQty(-1)">−</button>
                                        <input type="number" name="jumlah" id="jumlah" class="qty-input" 
                                               value="1" min="1" max="<?php echo $produk['stok']; ?>">
                                        <button type="button" class="qty-btn" onclick="changeQty(1)">+</button>
                                    </div>
                                    <span style="color: #666; font-size: 0.9rem;">
                                        Maks: <?php echo $produk['stok']; ?> unit
                                    </span>
                                </div>
                                
                                <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'pembeli'): ?>
                                    <button type="submit" name="tambah_keranjang" class="btn btn-primary" 
                                            <?php echo $produk['stok'] == 0 ? 'disabled' : ''; ?>>
                                        <?php echo $produk['stok'] == 0 ? 'Stok Habis' : '🛒 Tambah ke Keranjang'; ?>
                                    </button>
                                <?php elseif(isset($_SESSION['user_id']) && $_SESSION['role'] == 'penjual'): ?>
                                    <button type="button" class="btn btn-primary" disabled>
                                        Penjual tidak dapat membeli produk
                                    </button>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-primary">
                                        Login untuk Membeli
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                        
                        <!-- Store Info -->
                        <div class="store-info">
                            <div class="store-card">
                                <div class="store-icon">🏪</div>
                                <div>
                                    <div class="store-name"><?php echo htmlspecialchars($produk['nama_toko']); ?></div>
                                    <div style="font-size: 0.9rem; color: #666;">
                                        <a href="produk.php?toko=<?php echo $produk['toko_id']; ?>" style="color: #2E7D32;">
                                            Lihat produk lainnya →
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Description -->
                <div class="description-section">
                    <h2>Deskripsi Produk</h2>
                    <div class="description-text"><?php echo htmlspecialchars($produk['deskripsi']); ?></div>
                </div>
                
                <!-- Related Products -->
                <?php if(!empty($produk_lain)): ?>
                    <div class="related-section">
                        <h2>Produk Lain dari <?php echo htmlspecialchars($produk['nama_toko']); ?></h2>
                        <div class="related-grid">
                            <?php foreach($produk_lain as $p): ?>
                                <a href="produk-detail.php?id=<?php echo $p['id']; ?>" style="text-decoration: none;">
                                    <div class="product-card">
                                        <?php if($p['gambar'] && file_exists('uploads/' . $p['gambar'])): ?>
                                            <img src="uploads/<?php echo htmlspecialchars($p['gambar']); ?>" 
                                                 alt="<?php echo htmlspecialchars($p['nama']); ?>">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 200px; background: #E8F5E9; display: flex; align-items: center; justify-content: center;">
                                                <span style="font-size: 3rem;">🌾</span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="product-card-info">
                                            <div class="product-card-name"><?php echo htmlspecialchars($p['nama']); ?></div>
                                            <div class="product-card-price">Rp <?php echo number_format($p['harga'], 0, ',', '.'); ?></div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Mitra Tani Jaya. Memberdayakan Pertanian Lokal.</p>
        </div>
    </footer>
    
    <script>
    function changeQty(delta) {
        var input = document.getElementById('jumlah');
        var newVal = parseInt(input.value) + delta;
        var max = parseInt(input.max);
        
        if(newVal >= 1 && newVal <= max) {
            input.value = newVal;
        }
    }
    </script>
</body>
</html>
