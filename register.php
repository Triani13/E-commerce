<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

$title = 'Daftar';
$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $_POST['nama'] ?? '';
    $email = $_POST['email'] ?? '';
    $no_hp = $_POST['no_hp'] ?? '';
    $password = $_POST['password'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';
    $role = $_POST['role'] ?? '';
    
    // Validasi
    if(empty($nama) || empty($email) || empty($password) || empty($role)) {
        $error = "Semua field wajib harus diisi!";
    } elseif($password !== $konfirmasi_password) {
        $error = "Password dan konfirmasi password tidak sama!";
    } elseif(strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } else {
        try {
            // Cek email sudah terdaftar
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if($stmt->fetch()) {
                $error = "Email sudah terdaftar!";
            } else {
                // Insert user baru dengan no_hp
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (nama, email, no_hp, password, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nama, $email, $no_hp, $password_hash, $role]);
                
                // Jika penjual, buat toko otomatis
                if($role == 'penjual') {
                    $user_id = $pdo->lastInsertId();
                    $stmt = $pdo->prepare("INSERT INTO toko (user_id, nama_toko) VALUES (?, ?)");
                    $stmt->execute([$user_id, "Toko " . $nama]);
                }
                
                $success = "Registrasi berhasil! Silakan login.";
                // Clear form
                $nama = $email = '';
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Mitra Tani Jaya</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo h1 {
            color: #2E7D32;
            font-size: 2rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2E7D32;
        }
        
        .role-selection {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .role-option {
            flex: 1;
            padding: 1rem;
            border: 2px solid #ddd;
            border-radius: 4px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .role-option:hover {
            border-color: #2E7D32;
            background-color: #E8F5E9;
        }
        
        .role-option input {
            margin-right: 0.5rem;
        }
        
        .role-option.selected {
            border-color: #2E7D32;
            background-color: #E8F5E9;
        }
        
        .error-message {
            background-color: #f44336;
            color: white;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .success-message {
            background-color: #4CAF50;
            color: white;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 0.75rem;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: #2E7D32;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #1B5E20;
        }
        
        .links {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .links a {
            color: #2E7D32;
            text-decoration: none;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <h1>🌾 Mitra Tani Jaya</h1>
            <p>Daftar untuk memulai</p>
        </div>
        
        <?php if(!empty($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if(!empty($success)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="nama">Nama Lengkap *</label>
                <input type="text" id="nama" name="nama" required value="<?php echo isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="no_hp">No. HP/WhatsApp</label>
                <input type="tel" id="no_hp" name="no_hp" placeholder="+62812345678" value="<?php echo isset($_POST['no_hp']) ? htmlspecialchars($_POST['no_hp']) : ''; ?>">
                <small style="color: #666; font-size: 0.875rem;">Opsional - untuk notifikasi pesanan</small>
            </div>
            
            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="konfirmasi_password">Konfirmasi Password *</label>
                <input type="password" id="konfirmasi_password" name="konfirmasi_password" required>
            </div>
            
            <div class="form-group">
                <label>Pilih Peran Anda</label>
                <div class="role-selection">
                    <label class="role-option">
                        <input type="radio" name="role" value="pembeli" required>
                        <div>
                            <strong>Pembeli</strong>
                            <p style="font-size: 0.9rem; color: #666; margin-top: 0.25rem;">Saya ingin membeli produk</p>
                        </div>
                    </label>
                    <label class="role-option">
                        <input type="radio" name="role" value="penjual" required>
                        <div>
                            <strong>Penjual</strong>
                            <p style="font-size: 0.9rem; color: #666; margin-top: 0.25rem;">Saya ingin menjual produk</p>
                        </div>
                    </label>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Daftar</button>
        </form>
        
        <div class="links">
            <p>Sudah punya akun? <a href="login.php">Login sekarang</a></p>
            <p style="margin-top: 0.5rem;"><a href="index.php">← Kembali ke Beranda</a></p>
        </div>
    </div>
    
    <script>
        // Script untuk visual feedback role selection
        document.querySelectorAll('input[name="role"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.role-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                if(this.checked) {
                    this.closest('.role-option').classList.add('selected');
                }
            });
        });
    </script>
</body>
</html>
