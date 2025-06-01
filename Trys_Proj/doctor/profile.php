<?php
session_start();
include '../config/database.php';
include '../includes/functions.php';

// Check if user is logged in and is doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'dokter') {
    header("Location: ../login.php");
    exit();
}

$page_title = "Daftar Pasien";
$message = '';
$doctor_id = getDoctorId($conn, $_SESSION['user_id']);

if (!$doctor_id) {
   
    $doctor_info = null;
} else {
    // Get doctor's polyclinic - only if doctor_id is valid
    $doctor_info_result = $conn->query("SELECT id_poli FROM dokter WHERE id_dokter = $doctor_id");
    if ($doctor_info_result && $doctor_info_result->num_rows > 0) {
        $doctor_info = $doctor_info_result->fetch_assoc();
    } else {
        $doctor_info = null;
      
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $nm_dokter = $_POST['nm_dokter'];
                $spesialisasi = $_POST['spesialisasi'];
                $alamat = $_POST['alamat'];
                $no_hp = $_POST['no_hp'];
                $email = $_POST['email'];
                $tgl_lahir = $_POST['tgl_lahir'];
                $jenis_kelamin = $_POST['jenis_kelamin'];
                $no_str = $_POST['no_str'];
                $pengalaman = $_POST['pengalaman'];
                $pendidikan = $_POST['pendidikan'];
                
                $stmt = $conn->prepare("UPDATE dokter SET nm_dokter=?, spesialisasi=?, alamat=?, no_hp=?, email=?, tgl_lahir=?, jenis_kelamin=?, no_str=?, pengalaman=?, pendidikan=? WHERE id_dokter=?");
                $stmt->bind_param("ssssssssssi", $nm_dokter, $spesialisasi, $alamat, $no_hp, $email, $tgl_lahir, $jenis_kelamin, $no_str, $pengalaman, $pendidikan, $doctor_id);
                
                if ($stmt->execute()) {
                    $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Profil berhasil diperbarui!</div>';
                } else {
                    $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Gagal memperbarui profil: ' . $conn->error . '</div>';
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                // Get current password from database
                $user_result = $conn->query("SELECT password FROM login WHERE id_user = {$_SESSION['user_id']}");
                $user_data = $user_result->fetch_assoc();
                
                if ($user_data && password_verify($current_password, $user_data['password'])) {
                    if ($new_password === $confirm_password) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        
                        if ($conn->query("UPDATE login SET password = '$hashed_password' WHERE id_user = {$_SESSION['user_id']}")) {
                            $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Password berhasil diubah!</div>';
                        } else {
                            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Gagal mengubah password!</div>';
                        }
                    } else {
                        $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Konfirmasi password tidak cocok!</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Password lama tidak benar!</div>';
                }
                break;
        }
    }
}

// Get doctor information
if ($doctor_id) {
    $sql = "SELECT d.*, p.nm_poli, l.username 
            FROM dokter d 
            JOIN poliklinik p ON d.id_poli = p.id_poli 
            JOIN login l ON d.id_user = l.id_user 
            WHERE d.id_dokter = $doctor_id";
    $result = $conn->query($sql);
    $doctor = $result ? $result->fetch_assoc() : [];
} else {
    $doctor = null;
}

// Get polyclinics for dropdown
$polyclinics = $conn->query("SELECT id_poli, nm_poli FROM poliklinik ORDER BY nm_poli");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Sistem Rekam Medis</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="main-container">
        <div class="content-header">
            <h2><i class="fas fa-user-cog"></i> <?php echo $page_title; ?></h2>
        </div>
        
        <?php echo $message; ?>
        
        <div class="profile-container">
            <!-- Profile Overview Card -->
            <div class="profile-overview">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="profile-info">
                        <h3><?php echo $doctor['nm_dokter'] ?? 'N/A'; ?></h3>
                        <p class="specialization"><?php echo $doctor['spesialisasi'] ?? 'N/A'; ?></p>
                        <p class="polyclinic"><i class="fas fa-hospital"></i> <?php echo $doctor['nm_poli'] ?? 'N/A'; ?></p>
                    </div>
                </div>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <i class="fas fa-calendar-check"></i>
                        <div>
                            <span class="stat-number"><?php echo getTodayPatients($conn, $doctor_id); ?></span>
                            <span class="stat-label">Pasien Hari Ini</span>
                        </div>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-notes-medical"></i>
                        <div>
                            <span class="stat-number"><?php echo getPendingRecords($conn, $doctor_id); ?></span>
                            <span class="stat-label">Rekam Medis Pending</span>
                        </div>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-clock"></i>
                        <div>
                            <span class="stat-number">
                                <?php echo isset($doctor['pengalaman']) ? $doctor['pengalaman'] : '0'; ?>
                            </span>
                            <span class="stat-label">Tahun Pengalaman</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Profile Tabs -->
            <div class="profile-tabs">
                <div class="tab-buttons">
                    <button class="tab-button active" onclick="showTab('personal')">
                        <i class="fas fa-user"></i> Informasi Personal
                    </button>
                    <button class="tab-button" onclick="showTab('professional')">
                        <i class="fas fa-briefcase"></i> Informasi Profesional
                    </button>
                    <button class="tab-button" onclick="showTab('security')">
                        <i class="fas fa-shield-alt"></i> Keamanan
                    </button>
                </div>
                
                <!-- Personal Information Tab -->
                <div id="personal" class="tab-content active">
                    <div class="form-card">
                        <h4><i class="fas fa-user"></i> Informasi Personal</h4>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Nama Lengkap</label>
                                    <input type="text" name="nm_dokter" value="<?php echo isset($doctor['nm_dokter']) ? $doctor['nm_dokter'] : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-envelope"></i> Email</label>
                                    <input type="email" name="email" value="<?php echo isset($doctor['email']) ? $doctor['email'] : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-phone"></i> No. HP</label>
                                    <input type="text" name="no_hp" value="<?php echo isset($doctor['no_hp']) ? $doctor['no_hp'] : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-calendar"></i> Tanggal Lahir</label>
                                    <input type="date" name="tgl_lahir" value="<?php echo isset($doctor['tgl_lahir']) ? $doctor['tgl_lahir'] : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-venus-mars"></i> Jenis Kelamin</label>
                                <select name="jenis_kelamin">
                                    <option value="">Pilih Jenis Kelamin</option>
                                    <option value="L" <?php echo isset($doctor['jenis_kelamin']) && $doctor['jenis_kelamin'] == 'L' ? 'selected' : ''; ?>>Laki-laki</option>
                                    <option value="P" <?php echo isset($doctor['jenis_kelamin']) && $doctor['jenis_kelamin'] == 'P' ? 'selected' : ''; ?>>Perempuan</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-map-marker-alt"></i> Alamat</label>
                                <textarea name="alamat" rows="3"><?php echo isset($doctor['alamat']) ? $doctor['alamat'] : ''; ?></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Professional Information Tab -->
                <div id="professional" class="tab-content">
                    <div class="form-card">
                        <h4><i class="fas fa-briefcase"></i> Informasi Profesional</h4>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-stethoscope"></i> Spesialisasi</label>
                                    <input type="text" name="spesialisasi" value="<?php echo $doctor['spesialisasi']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-id-card"></i> No. STR</label>
                                    <input type="text" name="no_str" value="<?php echo $doctor['no_str']; ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-graduation-cap"></i> Pendidikan</label>
                                <textarea name="pendidikan" rows="3" placeholder="Riwayat pendidikan..."><?php echo $doctor['pendidikan']; ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-clock"></i> Pengalaman (Tahun)</label>
                                <input type="number" name="pengalaman" value="<?php echo $doctor['pengalaman']; ?>" min="0">
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Security Tab -->
                <div id="security" class="tab-content">
                    <div class="form-card">
                        <h4><i class="fas fa-shield-alt"></i> Ubah Password</h4>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Password Lama</label>
                                <input type="password" name="current_password" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-key"></i> Password Baru</label>
                                <input type="password" name="new_password" required minlength="6">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-key"></i> Konfirmasi Password Baru</label>
                                <input type="password" name="confirm_password" required minlength="6">
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-key"></i> Ubah Password
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="form-card">
                        <h4><i class="fas fa-info-circle"></i> Informasi Akun</h4>
                        <div class="account-info">
                            <div class="info-item">
                                <label>Username:</label>
                                <span><?php echo $doctor['username']; ?></span>
                            </div>
                            <div class="info-item">
                                <label>Role:</label>
                                <span class="badge badge-primary">Dokter</span>
                            </div>
                            <div class="info-item">
                                <label>Status:</label>
                                <span class="badge badge-success">Aktif</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
        
        // Password confirmation validation
        document.querySelector('input[name="confirm_password"]').addEventListener('input', function() {
            const newPassword = document.querySelector('input[name="new_password"]').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Password tidak cocok');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
    
    <style>
        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .profile-overview {
            background: linear-gradient(135deg, #2c5aa0 0%, #3d6bb3 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(44, 90, 160, 0.2);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 25px;
            font-size: 2.5em;
        }
        
        .profile-info h3 {
            margin: 0 0 5px 0;
            font-size: 1.8em;
        }
        
        .specialization {
            font-size: 1.1em;
            opacity: 0.9;
            margin: 5px 0;
        }
        
        .polyclinic {
            opacity: 0.8;
            margin: 5px 0;
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
        }
        
        .stat-item i {
            font-size: 2em;
            margin-right: 15px;
            opacity: 0.8;
        }
        
        .stat-number {
            display: block;
            font-size: 1.8em;
            font-weight: bold;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.8;
        }
        
        .profile-tabs {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .tab-buttons {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .tab-button {
            flex: 1;
            padding: 15px 20px;
            border: none;
            background: transparent;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .tab-button:hover {
            background: #e9ecef;
        }
        
        .tab-button.active {
            background: #2c5aa0;
            color: white;
        }
        
        .tab-content {
            display: none;
            padding: 30px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .form-card h4 {
            color: #2c5aa0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2c5aa0;
            box-shadow: 0 0 0 2px rgba(44, 90, 160, 0.2);
        }
        
        .form-actions {
            margin-top: 25px;
            text-align: right;
        }
        
        .account-info {
            background: white;
            border-radius: 5px;
            padding: 20px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-item label {
            font-weight: 500;
            color: #495057;
        }
        
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .profile-stats {
                grid-template-columns: 1fr;
            }
            
            .tab-buttons {
                flex-direction: column;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .profile-container {
                margin: 0 15px;
            }
        }
    </style>
</body>
</html>
