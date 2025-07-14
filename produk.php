<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

$title = 'Produk';

// Filter kategori
$kategori_filter = isset($_GET['kategori']) ? $_GET['kategori'] : '';

// Pencarian
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Query produk
$query = "SELECT p.*, t.nama_toko, k.nama as kategori 
          FROM produk p 
          JOIN toko t ON p.toko_id = t.id 
          JOIN kategori k ON p.kategori_id = k.id 
          WHERE 1=1";

$params = [];

if($kategori_filter) {
    $query .= " AND p.kategori_id = ?";
    $params[] = $kategori_filter;
}

if($search) {
    $query .= " AND (p.nama LIKE ? OR p.deskripsi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY p.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $produk_list = $stmt->fetchAll();
} catch(PDOException $e) {
    $produk_list = [];
    $db_error = $e->getMessage();
}

// Ambil kategori untuk filter
try {
    $stmt = $pdo->query("SELECT * FROM kategori");
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
        
        /* Search and Filter */
        .search-filter {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .search-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .search-input {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .filter-chips {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .chip {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #E8F5E9;
            color: #2E7D32;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .chip:hover,
        .chip.active {
            background: #2E7D32;
            color: white;
        }
        
        /* Product Grid */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
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
        }
        
        .btn-accent {
            background-color: #FFC107;
            color: #333;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 8px;
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
                    <li><a href="/taniasli/produk.php" style="font-weight: bold;">Produk</a></li>
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

    <main class="main-content">
        <div class="container">
            <h1 style="margin-bottom: 2rem;">Semua Produk</h1>
            
            <!-- Search and Filter -->
            <div class="search-filter">
                <form method="GET" action="" class="search-bar">
                    <input type="text" name="search" class="search-input" 
                           placeholder="Cari produk..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">Cari</button>
                </form>
                
                <div class="filter-chips">
                    <a href="produk.php" class="chip <?php echo !$kategori_filter ? 'active' : ''; ?>">
                        Semua Kategori
                    </a>
                    <?php foreach($kategori_list as $kat): ?>
                        <a href="?kategori=<?php echo $kat['id']; ?>" 
                           class="chip <?php echo $kategori_filter == $kat['id'] ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($kat['nama']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Products Grid -->
            <?php if(isset($db_error)): ?>
                <div class="empty-state">
                    <p style="color: red;">Error: <?php echo htmlspecialchars($db_error); ?></p>
                </div>
            <?php elseif(empty($produk_list)): ?>
                <div class="empty-state">
                    <h2>Tidak ada produk ditemukan</h2>
                    <p style="margin: 1rem 0;">
                        <?php if($search): ?>
                            Coba ubah kata kunci pencarian Anda
                        <?php else: ?>
                            Belum ada produk dalam kategori ini
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="grid">
                    <?php foreach($produk_list as $produk): ?>
                        <div class="product-card">
                            <?php if($produk['gambar'] && file_exists('uploads/' . $produk['gambar'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($produk['gambar']); ?>" 
                                     alt="<?php echo htmlspecialchars($produk['nama']); ?>" 
                                     class="product-image">
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
                                <p style="color: #666; font-size: 0.9rem;">📁 <?php echo htmlspecialchars($produk['kategori']); ?></p>
                                <a href="produk-detail.php?id=<?php echo $produk['id']; ?>" 
                                   class="btn btn-primary" 
                                   style="width: 100%; text-align: center; margin-top: 1rem;">
                                    Lihat Detail
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Mitra Tani Jaya. Memberdayakan Pertanian Lokal.</p>
            <p style="margin-top: 0.5rem; opacity: 0.8;">
                Platform e-commerce terpercaya untuk produk pertanian Indonesia
            </p>
        </div>
    </footer>
</body>
</html>
