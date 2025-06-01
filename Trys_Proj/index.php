<?php
session_start();
include 'config/database.php';
include 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$page_title = "Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Rekam Medis - <?php echo $page_title; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
 
    <div class="container">
        <div class="sidebar">
            <?php include 'includes/sidebar.php'; ?>
        </div>

        <div class="content">
            <div class="dashboard">
                <h2>Selamat Datang di Sistem Rekam Medis</h2>
                
                <?php if ($role == 'admin'): ?>
                <div class="dashboard-stats">
                    <?php
                    // Get statistics for admin dashboard
                    $total_patients = getCount($conn, "pasien");
                    $total_doctors = getCount($conn, "dokter");
                    $total_records = getCount($conn, "rekam_medis");
                    $total_visits = getCount($conn, "kunjungan");
                    ?>
                    <div class="stat-card">
                        <i class="fas fa-user-injured"></i>
                        <h3>Total Pasien</h3>
                        <p><?php echo $total_patients; ?></p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-user-md"></i>
                        <h3>Total Dokter</h3>
                        <p><?php echo $total_doctors; ?></p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-notes-medical"></i>
                        <h3>Rekam Medis</h3>
                        <p><?php echo $total_records; ?></p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-calendar-check"></i>
                        <h3>Kunjungan</h3>
                        <p><?php echo $total_visits; ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($role == 'dokter'): ?>
                <div class="dashboard-stats">
                    <?php
                    // Get statistics for doctor dashboard
                    $doctor_id = getDoctorId($conn, $user_id);
                    $today_patients = getTodayPatients($conn, $doctor_id);
                    $pending_records = getPendingRecords($conn, $doctor_id);
                    ?>
                    <div class="stat-card">
                        <i class="fas fa-user-injured"></i>
                        <h3>Pasien Hari Ini</h3>
                        <p><?php echo $today_patients; ?></p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>Rekam Medis Tertunda</h3>
                        <p><?php echo $pending_records; ?></p>
                    </div>
                </div>
                <div class="recent-section">
                    <h3>Daftar Pasien Hari Ini</h3>
                    <?php displayTodayPatientsList($conn, $doctor_id); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($role == 'pasien'): ?>
                <?php
                $patient_id = getPatientId($conn, $user_id);
                $patient_info = getPatientInfo($conn, $patient_id);
                $upcoming_visits = getUpcomingVisits($conn, $patient_id);
                ?>
                <div class="patient-info">
                    <h3>Informasi Pasien</h3>
                    <div class="info-card">
                        <p><strong>Nama:</strong> <?php echo $patient_info['nm_pasien']; ?></p>
                        <p><strong>No. Rekam Medis:</strong> <?php echo $patient_info['no_rm']; ?></p>
                        <p><strong>Tanggal Lahir:</strong> <?php echo $patient_info['tgl_lhr']; ?></p>
                        <p><strong>Alamat:</strong> <?php echo $patient_info['alamat']; ?></p>
                    </div>
                </div>
                <div class="recent-section">
                    <h3>Riwayat Kunjungan Terakhir</h3>
                    <?php displayPatientVisitHistory($conn, $patient_id); ?>
                </div>
                <?php if ($upcoming_visits > 0): ?>
                <div class="upcoming-visits">
                    <h3>Jadwal Kunjungan Mendatang</h3>
                    <?php displayUpcomingVisits($conn, $patient_id); ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/script.js"></script>
</body>
</html>
