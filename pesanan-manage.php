<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

// Cek login penjual
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'penjual') {
    header('Location: ../login.php');
    exit;
}

$title = 'Manajemen Pesanan';
$success = '';

// Ambil toko_id
try {
    $stmt = $pdo->prepare("SELECT id FROM toko WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $toko = $stmt->fetch();
    $toko_id = $toko['id'];
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle update status
if(isset($_POST['update_status'])) {
    $pesanan_id = $_POST['pesanan_id'];
    $status = $_POST['status'];
    
    try {
        // Ambil info pesanan untuk cek metode pembayaran
        $stmt = $pdo->prepare("SELECT metode_pembayaran FROM pesanan WHERE id = ?");
        $stmt->execute([$pesanan_id]);
        $pesanan_info = $stmt->fetch();
        
        // Validasi bahwa pesanan ini memang untuk toko ini
        $stmt = $pdo->prepare("
            SELECT p.id 
            FROM pesanan p 
            JOIN detail_pesanan dp ON p.id = dp.pesanan_id 
            JOIN produk pr ON dp.produk_id = pr.id 
            WHERE p.id = ? AND pr.toko_id = ?
        ");
        $stmt->execute([$pesanan_id, $toko_id]);
        
        if($stmt->fetch()) {
            // Untuk COD, skip status 'dibayar' langsung ke 'dikemas'
            if($pesanan_info['metode_pembayaran'] == 'cash' && $status == 'dibayar') {
                $status = 'dikemas';
            }
            
            $stmt = $pdo->prepare("UPDATE pesanan SET status = ? WHERE id = ?");
            $stmt->execute([$status, $pesanan_id]);
            $success = "Status pesanan berhasil diperbarui!";
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Filter status
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Query pesanan
$query = "
    SELECT DISTINCT p.*, u.nama as nama_pembeli, u.email,
           (SELECT SUM(dp2.jumlah * dp2.harga) 
            FROM detail_pesanan dp2 
            JOIN produk pr2 ON dp2.produk_id = pr2.id 
            WHERE dp2.pesanan_id = p.id AND pr2.toko_id = ?) as total_toko,
           p.bukti_pembayaran,
           p.metode_pembayaran
    FROM pesanan p 
    JOIN users u ON p.user_id = u.id
    JOIN detail_pesanan dp ON p.id = dp.pesanan_id 
    JOIN produk pr ON dp.produk_id = pr.id 
    WHERE pr.toko_id = ?
";

$params = [$toko_id, $toko_id];

if($filter_status) {
    $query .= " AND p.status = ?";
    $params[] = $filter_status;
}

$query .= " ORDER BY p.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $pesanan_list = $stmt->fetchAll();
} catch(PDOException $e) {
    $pesanan_list = [];
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
        
        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #eee;
            padding-bottom: 1rem;
        }
        
        .filter-tab {
            padding: 0.5rem 1rem;
            text-decoration: none;
            color: #666;
            border-radius: 4px 4px 0 0;
            transition: all 0.3s;
        }
        
        .filter-tab:hover {
            background: #f5f5f5;
        }
        
        .filter-tab.active {
            background: #E8F5E9;
            color: #2E7D32;
            font-weight: 600;
        }
        
        /* Table */
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
        
        /* Status Badge */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .badge-pending {
            background-color: #FFF3E0;
            color: #F57C00;
        }
        
        .badge-dibayar {
            background-color: #E3F2FD;
            color: #1976D2;
        }
        
        .badge-dikemas {
            background-color: #F3E5F5;
            color: #7B1FA2;
        }
        
        .badge-dikirim {
            background-color: #E0F2F1;
            color: #00796B;
        }
        
        .badge-selesai {
            background-color: #E8F5E9;
            color: #2E7D32;
        }
        
        /* Payment Method */
        .payment-method {
            font-size: 0.875rem;
        }
        
        .payment-cod {
            color: #1976D2;
            font-weight: 600;
        }
        
        .payment-transfer {
            color: #2E7D32;
        }
        
        .has-proof {
            display: block;
            color: #FF5722;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        /* Action Links */
        .action-links {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .action-links a {
            color: #2E7D32;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .action-links a:hover {
            text-decoration: underline;
        }
        
        .action-links .urgent {
            color: #FF5722;
            font-weight: 600;
        }
        
        /* Alert */
        .alert-success {
            background: #E8F5E9;
            color: #2E7D32;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            border: 1px solid #A5D6A7;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
        }
        
        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            min-width: 400px;
        }
        
        .modal h3 {
            margin-bottom: 1rem;
        }
        
        .modal form {
            margin-top: 1rem;
        }
        
        .modal select {
            width: 100%;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .modal-actions {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #2E7D32;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1B5E20;
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
                    <li><a href="/taniasli/dashboard/penjual.php">Dashboard</a></li>
                    <li><a href="/taniasli/logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <div class="container">
            <h1 style="margin-bottom: 2rem;">Manajemen Pesanan</h1>
            
            <div class="dashboard-layout">
                <!-- Sidebar -->
                <div class="sidebar">
                    <h3>Menu</h3>
                    <nav class="sidebar-menu">
                        <a href="penjual.php">Dashboard</a>
                        <a href="produk-manage.php">Manajemen Produk</a>
                        <a href="pesanan-manage.php" class="active">Manajemen Pesanan</a>
                        <a href="bukti-transfer.php">Bukti Transfer</a>
                        <a href="toko-setting.php">Pengaturan Toko</a>
                    </nav>
                </div>
                
                <!-- Content -->
                <div class="content-area">
                    <?php if($success): ?>
                        <div class="alert-success">
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <h2>Daftar Pesanan</h2>
                    
                    <!-- Filter Tabs -->
                    <div class="filter-tabs">
                        <a href="?" class="filter-tab <?php echo !$filter_status ? 'active' : ''; ?>">Semua</a>
                        <a href="?status=pending" class="filter-tab <?php echo $filter_status == 'pending' ? 'active' : ''; ?>">Pending</a>
                        <a href="?status=dibayar" class="filter-tab <?php echo $filter_status == 'dibayar' ? 'active' : ''; ?>">Dibayar</a>
                        <a href="?status=dikemas" class="filter-tab <?php echo $filter_status == 'dikemas' ? 'active' : ''; ?>">Dikemas</a>
                        <a href="?status=dikirim" class="filter-tab <?php echo $filter_status == 'dikirim' ? 'active' : ''; ?>">Dikirim</a>
                        <a href="?status=selesai" class="filter-tab <?php echo $filter_status == 'selesai' ? 'active' : ''; ?>">Selesai</a>
                    </div>
                    
                    <?php if(empty($pesanan_list)): ?>
                        <p style="text-align: center; color: #666; padding: 3rem;">
                            Belum ada pesanan <?php echo $filter_status ? 'dengan status ' . $filter_status : ''; ?>.
                        </p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID Pesanan</th>
                                    <th>Pembeli</th>
                                    <th>Tanggal</th>
                                    <th>Total</th>
                                    <th>Metode</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($pesanan_list as $pesanan): ?>
                                    <tr>
                                        <td>#<?php echo $pesanan['id']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($pesanan['nama_pembeli']); ?><br>
                                            <small style="color: #666;"><?php echo htmlspecialchars($pesanan['email']); ?></small>
                                        </td>
                                        <td><?php echo date('d M Y H:i', strtotime($pesanan['created_at'])); ?></td>
                                        <td>Rp <?php echo number_format($pesanan['total_toko'], 0, ',', '.'); ?></td>
                                        <td>
                                            <?php if($pesanan['metode_pembayaran'] == 'cash'): ?>
                                                <span class="payment-cod">COD</span>
                                            <?php else: ?>
                                                <span class="payment-transfer">Transfer</span>
                                                <?php if($pesanan['bukti_pembayaran']): ?>
                                                    <span class="has-proof">✓ Ada bukti</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $pesanan['status']; ?>">
                                                <?php echo ucfirst($pesanan['status']); ?>
                                            </span>
                                        </td>
                                        <td class="action-links">
                                            <a href="pesanan-detail-penjual.php?id=<?php echo $pesanan['id']; ?>">Detail</a>
                                            
                                            <?php if($pesanan['status'] == 'pending' && $pesanan['metode_pembayaran'] == 'transfer' && $pesanan['bukti_pembayaran']): ?>
                                                <a href="pesanan-detail-penjual.php?id=<?php echo $pesanan['id']; ?>" class="urgent">Verifikasi!</a>
                                            <?php elseif($pesanan['status'] != 'selesai'): ?>
                                                <a href="#" onclick="showUpdateStatus(<?php echo $pesanan['id']; ?>, '<?php echo $pesanan['status']; ?>', '<?php echo $pesanan['metode_pembayaran']; ?>')">Update</a>
                                            <?php endif; ?>
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
    
    <!-- Modal Update Status -->
    <div id="updateStatusModal" class="modal">
        <div class="modal-content">
            <h3>Update Status Pesanan</h3>
            <form method="POST">
                <input type="hidden" name="pesanan_id" id="pesanan_id">
                <div>
                    <label>Status Baru:</label>
                    <select name="status" id="status_select" required>
                        <!-- Options akan di-update via JavaScript -->
                    </select>
                    <small id="status_hint" style="color: #666; display: block; margin-top: 0.5rem;"></small>
                </div>
                <div class="modal-actions">
                    <button type="submit" name="update_status" class="btn btn-primary">Update</button>
                    <button type="button" onclick="closeUpdateStatus()" class="btn btn-secondary">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; 2024 Mitra Tani Jaya. Memberdayakan Pertanian Lokal.</p>
        </div>
    </footer>
    
    <script>
    function showUpdateStatus(id, currentStatus, metodePembayaran) {
        document.getElementById('pesanan_id').value = id;
        var statusSelect = document.getElementById('status_select');
        var statusHint = document.getElementById('status_hint');
        
        // Clear existing options
        statusSelect.innerHTML = '';
        
        if(metodePembayaran === 'cash') {
            // Untuk COD
            var statuses = {
                'pending': 'Pesanan Baru',
                'dikemas': 'Sedang Dikemas',
                'dikirim': 'Sedang Dikirim',
                'selesai': 'Selesai & Dibayar'
            };
            statusHint.textContent = 'Metode COD: Pembayaran dilakukan saat barang diterima';
        } else {
            // Untuk Transfer
            var statuses = {
                'pending': 'Menunggu Pembayaran',
                'dibayar': 'Sudah Dibayar',
                'dikemas': 'Sedang Dikemas',
                'dikirim': 'Sedang Dikirim',
                'selesai': 'Selesai'
            };
            statusHint.textContent = 'Metode Transfer: Pastikan pembayaran sudah diverifikasi';
        }
        
        for(var key in statuses) {
            var option = document.createElement('option');
            option.value = key;
            option.textContent = statuses[key];
            if(key === currentStatus) {
                option.selected = true;
            }
            statusSelect.appendChild(option);
        }
        
        document.getElementById('updateStatusModal').style.display = 'block';
    }
    
    function closeUpdateStatus() {
        document.getElementById('updateStatusModal').style.display = 'none';
    }
    </script>
</body>
</html>
