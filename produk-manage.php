<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

// Cek login penjual
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'penjual') {
    header('Location: ../login.php');
    exit;
}

$title = 'Manajemen Produk';

// Ambil toko_id
try {
    $stmt = $pdo->prepare("SELECT id FROM toko WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $toko = $stmt->fetch();
    $toko_id = $toko['id'];
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle hapus produk
if(isset($_GET['hapus'])) {
    $produk_id = $_GET['hapus'];
    try {
        $stmt = $pdo->prepare("DELETE FROM produk WHERE id = ? AND toko_id = ?");
        $stmt->execute([$produk_id, $toko_id]);
        header('Location: produk-manage.php');
        exit;
    } catch(PDOException $e) {
        $error = "Error hapus produk: " . $e->getMessage();
    }
}

// Ambil produk
try {
    $stmt = $pdo->prepare("
        SELECT p.*, k.nama as kategori 
        FROM produk p 
        JOIN kategori k ON p.kategori_id = k.id 
        WHERE p.toko_id = ? 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$toko_id]);
    $produk_list = $stmt->fetchAll();
} catch(PDOException $e) {
    $produk_list = [];
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
        
        /* Header Actions */
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .btn-add {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #2E7D32;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .btn-add:hover {
            background: #1B5E20;
            transform: translateY(-2px);
        }
        
        /* Product Table */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background-color: #f5f5f5;
            font-weight: bold;
            text-align: left;
            padding: 1rem;
            border-bottom: 2px solid #ddd;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        tr:hover {
            background-color: #f9f9f9;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .product-name {
            font-weight: 600;
            color: #2E7D32;
        }
        
        .price {
            font-weight: bold;
            color: #333;
        }
        
        .stock {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
        }
        
        .stock.low {
            background: #FFF3E0;
            color: #F57C00;
        }
        
        .stock.good {
            background: #E8F5E9;
            color: #2E7D32;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-edit,
        .btn-delete {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.3s;
        }
        
        .btn-edit {
            background: #FFC107;
            color: #333;
        }
        
        .btn-edit:hover {
            background: #FFB300;
        }
        
        .btn-delete {
            background: #f44336;
            color: white;
        }
        
        .btn-delete:hover {
            background: #d32f2f;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
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
            <h1 style="margin-bottom: 2rem;">Manajemen Produk</h1>
            
            <div class="dashboard-layout">
                <!-- Sidebar -->
                <div class="sidebar">
                    <h3>Menu</h3>
                    <nav class="sidebar-menu">
                        <a href="penjual.php">Dashboard</a>
                        <a href="produk-manage.php" class="active">Manajemen Produk</a>
                        <a href="pesanan-manage.php">Manajemen Pesanan</a>
                        <a href="bukti-transfer.php">Bukti Transfer</a>
                        <a href="toko-setting.php">Pengaturan Toko</a>
                    </nav>
                </div>
                
                <!-- Content -->
                <div class="content-area">
                    <div class="header-actions">
                        <h2>Daftar Produk</h2>
                        <a href="produk-form.php" class="btn-add">+ Tambah Produk Baru</a>
                    </div>
                    
                    <?php if(empty($produk_list)): ?>
                        <div class="empty-state">
                            <h3>Belum Ada Produk</h3>
                            <p>Anda belum menambahkan produk apapun.</p>
                            <a href="produk-form.php" class="btn-add" style="margin-top: 1rem;">Tambah Produk Pertama</a>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Gambar</th>
                                    <th>Nama Produk</th>
                                    <th>Kategori</th>
                                    <th>Harga</th>
                                    <th>Stok</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($produk_list as $produk): ?>
                                    <tr>
                                        <td>
                                            <?php if($produk['gambar'] && file_exists('../uploads/' . $produk['gambar'])): ?>
                                                <img src="../uploads/<?php echo htmlspecialchars($produk['gambar']); ?>" 
                                                     alt="<?php echo htmlspecialchars($produk['nama']); ?>" 
                                                     class="product-image">
                                            <?php else: ?>
                                                <div class="product-image" style="background: #E8F5E9; display: flex; align-items: center; justify-content: center;">
                                                    <span>🌾</span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="product-name"><?php echo htmlspecialchars($produk['nama']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($produk['kategori']); ?></td>
                                        <td class="price">Rp <?php echo number_format($produk['harga'], 0, ',', '.'); ?></td>
                                        <td>
                                            <span class="stock <?php echo $produk['stok'] <= 10 ? 'low' : 'good'; ?>">
                                                <?php echo $produk['stok']; ?> unit
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="produk-form.php?id=<?php echo $produk['id']; ?>" class="btn-edit">Edit</a>
                                                <a href="?hapus=<?php echo $produk['id']; ?>" 
                                                   class="btn-delete" 
                                                   onclick="return confirm('Hapus produk ini?')">Hapus</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
