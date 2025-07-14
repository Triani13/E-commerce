<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
$title = 'Beranda';

// Ambil produk terbaru dengan error handling
try {
    $stmt = $pdo->query("
        SELECT p.*, t.nama_toko, k.nama as kategori 
        FROM produk p 
        JOIN toko t ON p.toko_id = t.id 
        JOIN kategori k ON p.kategori_id = k.id 
        ORDER BY p.created_at DESC 
        LIMIT 8
    ");
    $produk_terbaru = $stmt->fetchAll();
} catch(PDOException $e) {
    $produk_terbaru = [];
    $db_error = $e->getMessage();
}

// Ambil kategori untuk showcase
try {
    $stmt = $pdo->query("SELECT * FROM kategori LIMIT 4");
    $kategori_list = $stmt->fetchAll();
} catch(PDOException $e) {
    $kategori_list = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? $title . ' - ' : ''; ?>Mitra Tani Jaya</title>
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
        
        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('https://cdn.pixabay.com/photo/2024/04/20/11/47/ai-generated-8708404_1280.jpg') no-repeat center center/cover;;
            color: white;
            padding: 4rem 0;
            text-align: center;
        }
        
        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem 0;
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
            background-color: #2E7D32;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #1B5E20;
            transform: translateY(-2px);
        }
        
        .btn-accent {
            background-color: #FFC107;
            color: #333;
        }
        
        .btn-accent:hover {
            background-color: #FFB300;
            transform: translateY(-2px);
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* Grid */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        /* Product Card */
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
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f0f0f0;
        }
        
        .product-info {
            padding: 1rem;
        }
        
        .product-name {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2E7D32;
        }
        
        .product-price {
            color: #333;
            font-weight: bold;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }
        
        .product-seller {
            color: #666;
            font-size: 0.9rem;
            margin: 0.5rem 0;
        }
        
        /* Category Cards */
        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .category-card {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
            background: #E8F5E9;
        }
        
        .category-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        /* Features Section */
        .features {
            background: white;
            padding: 3rem 0;
            margin: 3rem 0;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .feature-card {
            text-align: center;
            padding: 2rem;
        }
        
        .feature-icon {
            font-size: 3rem;
            color: #2E7D32;
            margin-bottom: 1rem;
        }
        
        /* Section Headers */
        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .section-header h2 {
            font-size: 2.5rem;
            color: #2E7D32;
            margin-bottom: 1rem;
        }
        
        .section-header p {
            font-size: 1.1rem;
            color: #666;
        }
        
        /* Footer */
        footer {
            background-color: #2E7D32;
            color: white;
            padding: 3rem 0 2rem 0;
            margin-top: auto;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .footer-section h3 {
            margin-bottom: 1rem;
        }
        
        .footer-section ul {
            list-style: none;
        }
        
        .footer-section a {
            color: white;
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.3s;
        }
        
        .footer-section a:hover {
            opacity: 1;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.2);
            opacity: 0.8;
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
                        <li><a href="/taniasli/register.php" class="btn btn-accent">Daftar</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Memberdayakan Pertanian Lokal</h1>
            <p>Menghubungkan petani dengan konsumen untuk masa depan pertanian Indonesia yang lebih baik</p>
            <a href="produk.php" class="btn btn-accent btn-large">Jelajahi Produk</a>
        </div>
    </section>

    <main class="main-content">
        <div class="container">
            <!-- Kategori Section -->
            <div class="section-header">
                <h2>Kategori Produk</h2>
                <p>Temukan berbagai kebutuhan pertanian Anda</p>
            </div>
            
            <div class="category-grid">
                <?php 
                $icons = ['🌱', '🌾', '🧪', '🛠️'];
                $i = 0;
                foreach($kategori_list as $kat): 
                ?>
                    <div class="category-card" onclick="window.location.href='produk.php?kategori=<?php echo $kat['id']; ?>'">
                        <div class="category-icon"><?php echo $icons[$i % 4]; ?></div>
                        <h3><?php echo htmlspecialchars($kat['nama']); ?></h3>
                    </div>
                <?php 
                    $i++;
                endforeach; 
                ?>
            </div>

            <!-- Produk Terbaru Section -->
            <div class="section-header" style="margin-top: 4rem;">
                <h2>Produk Terbaru</h2>
                <p>Produk segar dari petani dan produsen terpercaya</p>
            </div>
            
            <?php if(isset($db_error)): ?>
                <div class="card" style="background-color: #FFEBEE; color: #C62828;">
                    <p>Error loading products: <?php echo htmlspecialchars($db_error); ?></p>
                </div>
            <?php elseif(empty($produk_terbaru)): ?>
                <div class="card" style="text-align: center; padding: 3rem;">
                    <h3>Belum Ada Produk</h3>
                    <p style="margin: 1rem 0;">Jadilah yang pertama menambahkan produk!</p>
                    <?php if(!isset($_SESSION['user_id'])): ?>
                        <a href="register.php" class="btn btn-primary">Daftar Sebagai Penjual</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="grid">
                    <?php foreach($produk_terbaru as $produk): ?>
                        <div class="product-card">
                            <?php if($produk['gambar'] && file_exists('uploads/' . $produk['gambar'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($produk['gambar']); ?>" alt="<?php echo htmlspecialchars($produk['nama']); ?>" class="product-image">
                            <?php else: ?>
                                <div class="product-image" style="display: flex; align-items: center; justify-content: center; background: #E8F5E9;">
                                    <span style="font-size: 4rem;">🌾</span>
                                </div>
                            <?php endif; ?>
                            <div class="product-info">
                                <h3 class="product-name"><?php echo htmlspecialchars($produk['nama']); ?></h3>
                                <p class="product-price">Rp <?php echo number_format($produk['harga'], 0, ',', '.'); ?></p>
                                <p class="product-seller">🏪 <?php echo htmlspecialchars($produk['nama_toko']); ?></p>
                                <p style="color: #666; font-size: 0.9rem;">📦 Stok: <?php echo $produk['stok']; ?></p>
                                <a href="produk-detail.php?id=<?php echo $produk['id']; ?>" class="btn btn-primary" style="width: 100%; text-align: center; margin-top: 1rem;">Lihat Detail</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="text-align: center; margin-top: 3rem;">
                    <a href="produk.php" class="btn btn-primary">Lihat Semua Produk →</a>
                </div>
            <?php endif; ?>

            <!-- Features Section -->
            <section class="features">
                <div class="section-header">
                    <h2>Mengapa Mitra Tani Jaya?</h2>
                </div>
                
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">🌱</div>
                        <h3>Produk Berkualitas</h3>
                        <p>Produk pertanian terbaik langsung dari sumbernya dengan jaminan kualitas</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🚚</div>
                        <h3>Pengiriman Cepat</h3>
                        <p>Kerjasama dengan berbagai kurir untuk memastikan produk sampai tepat waktu</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">💰</div>
                        <h3>Harga Terbaik</h3>
                        <p>Beli langsung dari petani dan produsen untuk harga yang lebih kompetitif</p>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Tentang Kami</h3>
                    <p>Mitra Tani Jaya adalah platform yang menghubungkan petani dengan konsumen untuk mendukung pertanian lokal Indonesia.</p>
                </div>
                <div class="footer-section">
                    <h3>Kategori</h3>
                    <ul>
                        <li><a href="produk.php?kategori=1">Bibit</a></li>
                        <li><a href="produk.php?kategori=2">Pupuk</a></li>
                        <li><a href="produk.php?kategori=3">Pestisida</a></li>
                        <li><a href="produk.php?kategori=4">Alat Pertanian</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Layanan</h3>
                    <ul>
                        <li><a href="#">Cara Belanja</a></li>
                        <li><a href="#">Cara Berjualan</a></li>
                        <li><a href="#">Kebijakan Privasi</a></li>
                        <li><a href="#">Syarat & Ketentuan</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Kontak</h3>
                    <p>📧 info@mitratanijaya.com</p>
                    <p>📱 +62 812-3456-7890</p>
                    <p>📍 Luwu Timur, Indonesia</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Mitra Tani Jaya. Semua hak dilindungi.</p>
            </div>
        </div>
    </footer>
</body>
</html>
