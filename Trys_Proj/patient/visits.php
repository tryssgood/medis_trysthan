<?php
session_start();
include '../config/database.php';
include '../includes/functions.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'pasien') {
    header("Location: ../login.php");
    exit();
}

$page_title = "Riwayat Kunjungan";
$patient_id = getPatientId($conn, $_SESSION['user_id']);

if (!$patient_id) {
    header("Location: ../index.php");
    exit();
}

// Get patient information
$patient_info = getPatientInfo($conn, $patient_id);

// Get visits with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total records
$total_query = "SELECT COUNT(*) as count FROM kunjungan WHERE id_pasien = ?";
$stmt = $conn->prepare($total_query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['count'];
$total_pages = ceil($total_records / $limit);

// Get visits
$sql = "SELECT k.*, p.nm_poli, d.nm_dokter,
               CASE 
                   WHEN rm.id_rekam_medis IS NOT NULL THEN 'Sudah Diperiksa'
                   WHEN k.tgl_kunjungan < CURDATE() THEN 'Terlewat'
                   WHEN k.tgl_kunjungan = CURDATE() THEN 'Hari Ini'
                   ELSE 'Akan Datang'
               END as status
        FROM kunjungan k
        LEFT JOIN poliklinik p ON k.id_poli = p.id_poli
        LEFT JOIN dokter d ON d.id_poli = p.id_poli
        LEFT JOIN rekam_medis rm ON rm.id_pasien = k.id_pasien AND DATE(rm.tgl_pemeriksaan) = k.tgl_kunjungan
        WHERE k.id_pasien = ?
        ORDER BY k.tgl_kunjungan DESC, k.jam_kunjungan DESC
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $patient_id, $offset, $limit);
$stmt->execute();
$visits = $stmt->get_result();
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
    
    <div class="container">
       
        
        <div class="content">
            <div class="content-header">
                <h2><i class="fas fa-calendar-check"></i> <?php echo $page_title; ?></h2>
                <a href="appointments.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Buat Janji Baru
                </a>
            </div>
            
            <!-- Patient Info Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user"></i> Informasi Pasien</h3>
                </div>
                <div class="card-body">
                    <div class="patient-info-grid">
                        <div class="info-item">
                            <span class="info-label">No. Rekam Medis:</span>
                            <span class="info-value"><?php echo $patient_info['no_rm']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Nama:</span>
                            <span class="info-value"><?php echo $patient_info['nm_pasien']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Visits List -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-check"></i> Daftar Kunjungan</h3>
                </div>
                <div class="card-body">
                    <?php if ($visits->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Jam</th>
                                    <th>Poliklinik</th>
                                    <th>Dokter</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($visit = $visits->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($visit['tgl_kunjungan'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($visit['jam_kunjungan'])); ?></td>
                                    <td><?php echo $visit['nm_poli'] ?? 'Tidak diketahui'; ?></td>
                                    <td><?php echo $visit['nm_dokter'] ?? 'Belum ditentukan'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $visit['status'])); ?>">
                                            <?php echo $visit['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($visit['status'] == 'Sudah Diperiksa'): ?>
                                        <a href="medical_history.php" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> Lihat Rekam Medis
                                        </a>
                                        <?php elseif ($visit['status'] == 'Akan Datang'): ?>
                                        <button class="btn btn-sm btn-warning" onclick="cancelVisit(<?php echo $visit['id_kunjungan']; ?>)">
                                            <i class="fas fa-times"></i> Batal
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="pagination-link">
                            <i class="fas fa-chevron-left"></i> Sebelumnya
                        </a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="pagination-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="pagination-link">
                            Selanjutnya <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-calendar-times"></i>
                        <h3>Belum Ada Kunjungan</h3>
                        <p>Anda belum memiliki riwayat kunjungan. Silakan buat janji dengan dokter.</p>
                        <a href="appointments.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Buat Janji Sekarang
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        function cancelVisit(visitId) {
            if (confirm('Apakah Anda yakin ingin membatalkan kunjungan ini?')) {
                // Implement cancel visit functionality
                window.location.href = `cancel_visit.php?id=${visitId}`;
            }
        }
    </script>
    
    <style>
        .patient-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .info-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .info-value {
            font-size: 1.1rem;
            color: #333;
            font-weight: 500;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-sudah-diperiksa {
            background-color: #28a745;
            color: white;
        }
        
        .status-hari-ini {
            background-color: #17a2b8;
            color: white;
        }
        
        .status-akan-datang {
            background-color: #ffc107;
            color: #212529;
        }
        
        .status-terlewat {
            background-color: #dc3545;
            color: white;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .no-data i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #e9ecef;
        }
        
        .no-data h3 {
            margin-bottom: 10px;
            color: #495057;
        }
        
        .no-data p {
            margin-bottom: 20px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            gap: 5px;
        }
        
        .pagination-link {
            padding: 8px 12px;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            color: #537D5D;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .pagination-link:hover {
            background-color: #f8f9fa;
        }
        
        .pagination-link.active {
            background-color: #537D5D;
            color: white;
            border-color: #537D5D;
        }
        
        @media (max-width: 768px) {
            .patient-info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
