<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

// Cek login penjual
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'penjual') {
    header('Location: ../login.php');
    exit;
}

$title = 'Tambah Produk';
$error = '';
$success = '';

// Ambil toko_id dan cek apakah toko sudah lengkap
try {
    $stmt = $pdo->prepare("SELECT * FROM toko WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $toko = $stmt->fetch();
    
    if(!$toko) {
        // Buat toko otomatis
        $stmt = $pdo->prepare("INSERT INTO toko (user_id, nama_toko) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], "Toko " . $_SESSION['nama']]);
        $toko_id = $pdo->lastInsertId();
        
        header('Location: toko-setting.php?new=1');
        exit;
    }
    
    $toko_id = $toko['id'];
    
    // Cek kelengkapan data toko
    if(empty($toko['no_hp']) || empty($toko['alamat'])) {
        $_SESSION['toko_warning'] = "Lengkapi informasi toko Anda terlebih dahulu untuk pengalaman jual beli yang lebih baik.";
    }
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Ambil kategori
try {
    $stmt = $pdo->query("SELECT * FROM kategori ORDER BY nama");
    $kategori_list = $stmt->fetchAll();
} catch(PDOException $e) {
    $kategori_list = [];
}

// Cek mode edit
$edit_mode = false;
$produk = null;
if(isset($_GET['id'])) {
    $edit_mode = true;
    $title = 'Edit Produk';
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM produk WHERE id = ? AND toko_id = ?");
        $stmt->execute([$_GET['id'], $toko_id]);
        $produk = $stmt->fetch();
        
        if(!$produk) {
            header('Location: produk-manage.php');
            exit;
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $_POST['nama'] ?? '';
    $deskripsi = $_POST['deskripsi'] ?? '';
    $harga = $_POST['harga'] ?? 0;
    $stok = $_POST['stok'] ?? 0;
    $kategori_id = $_POST['kategori_id'] ?? '';
    
    // Validasi
    if(empty($nama) || empty($deskripsi) || empty($harga) || empty($kategori_id)) {
        $error = "Semua field harus diisi!";
    } else {
        try {
            // Handle upload gambar
            $gambar_nama = $edit_mode ? $produk['gambar'] : null;
            
            if(isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
                $upload_dir = '../uploads/';
                if(!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
                $gambar_nama = 'produk_' . time() . '.' . $file_ext;
                $upload_path = $upload_dir . $gambar_nama;
                
                if(move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path)) {
                    // Hapus gambar lama jika edit
                    if($edit_mode && $produk['gambar'] && file_exists($upload_dir . $produk['gambar'])) {
                        unlink($upload_dir . $produk['gambar']);
                    }
                } else {
                    $error = "Gagal upload gambar!";
                }
            }
            
            if(empty($error)) {
                if($edit_mode) {
                    // Update produk
                    $stmt = $pdo->prepare("UPDATE produk SET nama = ?, deskripsi = ?, harga = ?, stok = ?, kategori_id = ?, gambar = ? WHERE id = ? AND toko_id = ?");
                    $stmt->execute([$nama, $deskripsi, $harga, $stok, $kategori_id, $gambar_nama, $_GET['id'], $toko_id]);
                    $success = "Produk berhasil diperbarui!";
                } else {
                    // Insert produk baru
                    $stmt = $pdo->prepare("INSERT INTO produk (toko_id, kategori_id, nama, deskripsi, harga, stok, gambar) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$toko_id, $kategori_id, $nama, $deskripsi, $harga, $stok, $gambar_nama]);
                    $success = "Produk berhasil ditambahkan!";
                    
                    // Clear form
                    $nama = $deskripsi = '';
                    $harga = $stok = 0;
                    $kategori_id = '';
                }
            }
        } catch(PDOException $e) {
            $error = "Error: " . $e->getMessage();
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
        
        /* Form Styles */
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
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
        
        /* File Upload */
        .file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .file-upload input[type="file"] {
            padding: 0.5rem;
            background: #f5f5f5;
            border: 2px dashed #ddd;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload input[type="file"]:hover {
            border-color: #2E7D32;
            background: #E8F5E9;
        }
        
        .current-image {
            margin-top: 1rem;
        }
        
        .current-image img {
            max-width: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Buttons */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
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
        
        .btn-secondary {
            background: #ddd;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #ccc;
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
        
        /* Warning Box */
        .warning-box {
            background: #FFF9C4;
            border-left: 4px solid #FFC107;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        
        .warning-box a {
            color: #F57C00;
            font-weight: bold;
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
            <h1 style="margin-bottom: 2rem;"><?php echo $title; ?></h1>
            
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
                    <?php if(isset($_SESSION['toko_warning'])): ?>
                        <div class="warning-box">
                            <?php echo $_SESSION['toko_warning']; ?>
                            <a href="toko-setting.php">Lengkapi sekarang →</a>
                        </div>
                        <?php unset($_SESSION['toko_warning']); ?>
                    <?php endif; ?>
                    
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
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Nama Produk *</label>
                            <input type="text" name="nama" required 
                                   value="<?php echo htmlspecialchars($edit_mode ? $produk['nama'] : ($_POST['nama'] ?? '')); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Kategori *</label>
                            <select name="kategori_id" required>
                                <option value="">Pilih Kategori</option>
                                <?php foreach($kategori_list as $kat): ?>
                                    <option value="<?php echo $kat['id']; ?>" 
                                            <?php echo ($edit_mode ? $produk['kategori_id'] : ($_POST['kategori_id'] ?? '')) == $kat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($kat['nama']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Harga (Rp) *</label>
                            <input type="number" name="harga" min="0" required 
                                   value="<?php echo $edit_mode ? $produk['harga'] : ($_POST['harga'] ?? ''); ?>">
                            <small>Masukkan harga dalam rupiah tanpa titik atau koma</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Stok *</label>
                            <input type="number" name="stok" min="0" required 
                                   value="<?php echo $edit_mode ? $produk['stok'] : ($_POST['stok'] ?? ''); ?>">
                            <small>Jumlah produk yang tersedia</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Deskripsi Produk *</label>
                            <textarea name="deskripsi" required><?php echo htmlspecialchars($edit_mode ? $produk['deskripsi'] : ($_POST['deskripsi'] ?? '')); ?></textarea>
                            <small>Jelaskan detail produk, manfaat, cara penggunaan, dll</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Gambar Produk <?php echo $edit_mode ? '' : '*'; ?></label>
                            <div class="file-upload">
                                <input type="file" name="gambar" accept="image/*" <?php echo $edit_mode ? '' : 'required'; ?>>
                            </div>
                            <small>Format: JPG, PNG. Maksimal 2MB. Gunakan gambar berkualitas baik.</small>
                            
                            <?php if($edit_mode && $produk['gambar'] && file_exists('../uploads/' . $produk['gambar'])): ?>
                                <div class="current-image">
                                    <p style="margin-bottom: 0.5rem;">Gambar saat ini:</p>
                                    <img src="../uploads/<?php echo htmlspecialchars($produk['gambar']); ?>" alt="Current product image">
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $edit_mode ? 'Perbarui Produk' : 'Tambah Produk'; ?>
                            </button>
                            <a href="produk-manage.php" class="btn btn-secondary">Batal</a>
                        </div>
                    </form>
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
