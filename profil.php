<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

// Cek login pembeli
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'pembeli') {
    header('Location: ../login.php');
    exit;
}

$title = 'Profil Saya';
$error = '';
$success = '';

// Ambil data user
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle update profil
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $_POST['nama'] ?? '';
    $email = $_POST['email'] ?? '';
    $no_hp = $_POST['no_hp'] ?? '';
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    
    try {
        // Cek email duplikat
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        
        if($stmt->fetch()) {
            $error = "Email sudah digunakan pengguna lain!";
        } else {
            // Update nama, email, dan no_hp
            $stmt = $pdo->prepare("UPDATE users SET nama = ?, email = ?, no_hp = ? WHERE id = ?");
            $stmt->execute([$nama, $email, $no_hp, $_SESSION['user_id']]);
            
            // Update password jika diisi
            if(!empty($password_lama) && !empty($password_baru)) {
                if(password_verify($password_lama, $user['password'])) {
                    $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$password_hash, $_SESSION['user_id']]);
                    $success = "Profil dan password berhasil diperbarui!";
                } else {
                    $error = "Password lama tidak sesuai!";
                }
            } else {
                $success = "Profil berhasil diperbarui!";
            }
            
            // Update session
            $_SESSION['nama'] = $nama;
            
            // Refresh data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Statistik
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pesanan WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total_pesanan = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pesanan WHERE user_id = ? AND status = 'selesai'");
    $stmt->execute([$_SESSION['user_id']]);
    $pesanan_selesai = $stmt->fetch()['total'];
} catch(PDOException $e) {
    $total_pesanan = 0;
    $pesanan_selesai = 0;
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
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #2E7D32;
        }
        
        .form-divider {
            border-top: 2px solid #eee;
            margin: 2rem 0;
            padding-top: 2rem;
        }
        
        .form-note {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        /* Buttons */
        .btn-primary {
            padding: 0.75rem 2rem;
            background: #2E7D32;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
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
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .stat-card {
            text-align: center;
            padding: 2rem;
            background: #f5f5f5;
            border-radius: 8px;
        }
        
        .stat-card.green {
            background: #E8F5E9;
        }
        
        .stat-card.blue {
            background: #E3F2FD;
        }
        
        .stat-value {
            font-size: 3rem;
            font-weight: bold;
            color: #333;
        }
        
        .stat-card.green .stat-value {
            color: #2E7D32;
        }
        
        .stat-card.blue .stat-value {
            color: #1976D2;
        }
        
        .stat-label {
            color: #666;
            margin-top: 0.5rem;
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
            <h1 style="margin-bottom: 2rem;">Profil Saya</h1>
            
            <div class="dashboard-layout">
                <!-- Sidebar -->
                <div class="sidebar">
                    <h3>Menu</h3>
                    <nav class="sidebar-menu">
                        <a href="pembeli.php">Riwayat Pesanan</a>
                        <a href="profil.php" class="active">Profil Saya</a>
                    </nav>
                </div>
                
                <!-- Content -->
                <div class="content-area">
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
                    
                    <!-- Form Edit Profil -->
                    <div class="card">
                        <h2>Informasi Akun</h2>
                        
                        <form method="POST">
                            <div class="form-group">
                                <label>Nama Lengkap</label>
                                <input type="text" name="nama" value="<?php echo htmlspecialchars($user['nama']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>No. HP/WhatsApp</label>
                                <input type="tel" name="no_hp" value="<?php echo htmlspecialchars($user['no_hp'] ?? ''); ?>" placeholder="+62812345678">
                            </div>
                            
                            <div class="form-group">
                                <label>Role</label>
                                <input type="text" value="<?php echo ucfirst($user['role']); ?>" disabled style="background: #f5f5f5;">
                            </div>
                            
                            <div class="form-divider">
                                <h3 style="margin-bottom: 1rem;">Ganti Password</h3>
                                <p class="form-note">Kosongkan jika tidak ingin mengubah password</p>
                                
                                <div class="form-group">
                                    <label>Password Lama</label>
                                    <input type="password" name="password_lama">
                                </div>
                                
                                <div class="form-group">
                                    <label>Password Baru</label>
                                    <input type="password" name="password_baru" minlength="6">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-primary">Simpan Perubahan</button>
                        </form>
                    </div>
                    
                    <!-- Statistik Akun -->
                    <div class="card">
                        <h2>Statistik Belanja</h2>
                        
                        <div class="stats-grid">
                            <div class="stat-card green">
                                <div class="stat-value"><?php echo $total_pesanan; ?></div>
                                <div class="stat-label">Total Pesanan</div>
                            </div>
                            <div class="stat-card blue">
                                <div class="stat-value"><?php echo $pesanan_selesai; ?></div>
                                <div class="stat-label">Pesanan Selesai</div>
                            </div>
                        </div>
                        
                        <p style="margin-top: 2rem; text-align: center; color: #666;">
                            Bergabung sejak: <?php echo date('d F Y', strtotime($user['created_at'])); ?>
                        </p>
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
