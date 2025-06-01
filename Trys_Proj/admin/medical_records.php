<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $id_pasien = $_POST['id_pasien'];
                $id_user = $_SESSION['user_id']; // Admin creating the record
                $tgl_pemeriksaan = $_POST['tgl_pemeriksaan'];
                $keluhan = $_POST['keluhan'];
                $diagnosa = $_POST['diagnosa'];
                $resep = $_POST['resep'];
                $catatan = $_POST['ket'];
                
                $stmt = $conn->prepare("INSERT INTO rekam_medis (id_pasien, id_user, tgl_pemeriksaan, keluhan, diagnosa, resep, ket) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iisssss", $id_pasien, $id_user, $tgl_pemeriksaan, $keluhan, $diagnosa, $resep, $catatan);
                
                if ($stmt->execute()) {
                    $success = "Rekam medis berhasil ditambahkan!";
                } else {
                    $error = "Error: " . $stmt->error;
                }
                break;
                
            case 'update':
                $id_rekam_medis = $_POST['id_rekam_medis'];
                $id_pasien = $_POST['id_pasien'];
                $tgl_pemeriksaan = $_POST['tgl_pemeriksaan'];
                $keluhan = $_POST['keluhan'];
                $diagnosa = $_POST['diagnosa'];
                $resep = $_POST['resep'];
                $catatan = $_POST['ket'];
                
                $stmt = $conn->prepare("UPDATE rekam_medis SET id_pasien = ?, tgl_pemeriksaan = ?, keluhan = ?, diagnosa = ?, resep = ?, ket = ? WHERE id_rekam_medis = ?");
                $stmt->bind_param("isssssi", $id_pasien, $tgl_pemeriksaan, $keluhan, $diagnosa, $resep, $catatan, $id_rekam_medis);
                
                if ($stmt->execute()) {
                    $success = "Rekam medis berhasil diperbarui!";
                } else {
                    $error = "Error: " . $stmt->error;
                }
                break;
                
            case 'delete':
                $id_rekam_medis = $_POST['id_rekam_medis'];
                
                $stmt = $conn->prepare("DELETE FROM rekam_medis WHERE id_rekam_medis = ?");
                $stmt->bind_param("i", $id_rekam_medis);
                
                if ($stmt->execute()) {
                    $success = "Rekam medis berhasil dihapus!";
                } else {
                    $error = "Error: " . $stmt->error;
                }
                break;
        }
    }
}

// Pagination and search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build search query
$search_condition = "";
$search_params = [];
$param_types = "";

if (!empty($search)) {
    $search_condition = "WHERE p.nm_pasien LIKE ? OR p.no_rm LIKE ? OR rm.diagnosa LIKE ? OR rm.keluhan LIKE ?";
    $search_term = "%$search%";
    $search_params = [$search_term, $search_term, $search_term, $search_term];
    $param_types = "ssss";
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM rekam_medis rm 
              JOIN pasien p ON rm.id_pasien = p.id_pasien 
              JOIN login u ON rm.id_user = u.id_user 
              $search_condition";

if (!empty($search_params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($param_types, ...$search_params);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_records = $conn->query($count_sql)->fetch_assoc()['total'];
}

$total_pages = ceil($total_records / $limit);

// Get medical records data
$sql = "SELECT rm.*, p.nm_pasien, p.no_rm, u.username as doctor_name 
        FROM rekam_medis rm 
        JOIN pasien p ON rm.id_pasien = p.id_pasien 
        JOIN login u ON rm.id_user = u.id_user 
        $search_condition 
        ORDER BY rm.tgl_pemeriksaan DESC 
        LIMIT ? OFFSET ?";

if (!empty($search_params)) {
    $stmt = $conn->prepare($sql);
    $search_params[] = $limit;
    $search_params[] = $offset;
    $param_types .= "ii";
    $stmt->bind_param($param_types, ...$search_params);
    $stmt->execute();
    $medical_records = $stmt->get_result();
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $medical_records = $stmt->get_result();
}

// Get patients for dropdown
$patients = $conn->query("SELECT id_pasien, nm_pasien, no_rm FROM pasien ORDER BY nm_pasien");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Rekam Medis - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content">
        <div class="content-header">
            <div class="header-left">
                <h1><i class="fas fa-notes-medical"></i> Manajemen Rekam Medis</h1>
                <p>Kelola data rekam medis pasien</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="openModal('addModal')">
                    <i class="fas fa-plus"></i> Tambah Rekam Medis
                </button>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Search and Filter -->
        <div class="search-filter-bar">
            <div class="search-box">
                <form method="GET" action="">
                    <div class="input-group">
                        <input type="text" name="search" placeholder="Cari pasien, no. RM, diagnosa..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-secondary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            <div class="filter-actions">
                <button class="btn btn-outline" onclick="printTable()">
                    <i class="fas fa-print"></i> Cetak
                </button>
                <button class="btn btn-outline" onclick="exportData()">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>

        <!-- Data Table -->
        <div class="table-container">
            <table class="data-table" id="medicalRecordsTable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>No. RM</th>
                        <th>Nama Pasien</th>
                        <th>Tanggal</th>
                        <th>Keluhan</th>
                        <th>Diagnosa</th>
                        <th>Resep</th>
                        <th>Dokter</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = $offset + 1;
                    while ($record = $medical_records->fetch_assoc()): 
                    ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo $record['no_rm']; ?></td>
                        <td><?php echo $record['nm_pasien']; ?></td>
                        <td><?php echo date('d/m/Y', strtotime($record['tgl_pemeriksaan'])); ?></td>
                        <td>
                            <div class="text-truncate" title="<?php echo htmlspecialchars($record['keluhan']); ?>">
                                <?php echo substr($record['keluhan'], 0, 50) . (strlen($record['keluhan']) > 50 ? '...' : ''); ?>
                            </div>
                        </td>
                        <td>
                            <div class="text-truncate" title="<?php echo htmlspecialchars($record['diagnosa']); ?>">
                                <?php echo substr($record['diagnosa'], 0, 50) . (strlen($record['diagnosa']) > 50 ? '...' : ''); ?>
                            </div>
                        </td>
                        <td>
                            <div class="text-truncate" title="<?php echo htmlspecialchars($record['resep']); ?>">
                                <?php echo substr($record['resep'], 0, 30) . (strlen($record['resep']) > 30 ? '...' : ''); ?>
                            </div>
                        </td>
                        <td><?php echo $record['doctor_name']; ?></td>
                        <td>
                            <div class="action-buttons">

                                <button class="btn btn-sm btn-warning" onclick="editRecord(<?php echo htmlspecialchars(json_encode($record)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteRecord(<?php echo $record['id_rekam_medis']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
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
                <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-outline">
                    <i class="fas fa-chevron-left"></i> Sebelumnya
                </a>
            <?php endif; ?>
            
            <div class="page-numbers">
                <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                       class="btn <?php echo $i == $page ? 'btn-primary' : 'btn-outline'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-outline">
                    Selanjutnya <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Add Medical Record Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Tambah Rekam Medis</h3>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="id_pasien">Pasien</label>
                        <select name="id_pasien" id="id_pasien" required>
                            <option value="">Pilih Pasien</option>
                            <?php 
                            $patients->data_seek(0);
                            while ($patient = $patients->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $patient['id_pasien']; ?>">
                                    <?php echo $patient['no_rm'] . ' - ' . $patient['nm_pasien']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tgl_pemeriksaan">Tanggal Pemeriksaan</label>
                        <input type="date" name="tgl_pemeriksaan" id="tgl_pemeriksaan" required>
                    </div>
                    <div class="form-group full-width">
                        <label for="keluhan">Keluhan</label>
                        <textarea name="keluhan" id="keluhan" rows="3" required></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="diagnosa">Diagnosa</label>
                        <textarea name="diagnosa" id="diagnosa" rows="3" required></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="resep">Resep</label>
                        <textarea name="resep" id="resep" rows="3"></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="catatan">Catatan</label>
                        <textarea name="ket" id="ket" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Medical Record Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Rekam Medis</h3>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id_rekam_medis" id="edit_id_rekam_medis">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_id_pasien">Pasien</label>
                        <select name="id_pasien" id="edit_id_pasien" required>
                            <option value="">Pilih Pasien</option>
                            <?php 
                            $patients->data_seek(0);
                            while ($patient = $patients->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $patient['id_pasien']; ?>">
                                    <?php echo $patient['no_rm'] . ' - ' . $patient['nm_pasien']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_tgl_pemeriksaan">Tanggal Pemeriksaan</label>
                        <input type="date" name="tgl_pemeriksaan" id="edit_tgl_pemeriksaan" required>
                    </div>
                    <div class="form-group full-width">
                        <label for="edit_keluhan">Keluhan</label>
                        <textarea name="keluhan" id="edit_keluhan" rows="3" required></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="edit_diagnosa">Diagnosa</label>
                        <textarea name="diagnosa" id="edit_diagnosa" rows="3" required></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="edit_resep">Resep</label>
                        <textarea name="resep" id="edit_resep" rows="3"></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="edit_catatan">Catatan</label>
                        <textarea name="ket" id="edit_ket" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Batal</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Medical Record Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Detail Rekam Medis</h3>
                <span class="close" onclick="closeModal('viewModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="record-details">
                    <div class="detail-row">
                        <label>Pasien:</label>
                        <span id="view_patient"></span>
                    </div>
                    <div class="detail-row">
                        <label>Tanggal Pemeriksaan:</label>
                        <span id="view_date"></span>
                    </div>
                    <div class="detail-row">
                        <label>Keluhan:</label>
                        <div id="view_keluhan" class="detail-text"></div>
                    </div>
                    <div class="detail-row">
                        <label>Diagnosa:</label>
                        <div id="view_diagnosa" class="detail-text"></div>
                    </div>
                    <div class="detail-row">
                        <label>Resep:</label>
                        <div id="view_resep" class="detail-text"></div>
                    </div>
                    <div class="detail-row">
                        <label>Catatan:</label>
                        <div id="view_catatan" class="detail-text"></div>
                    </div>
                    <div class="detail-row">
                        <label>Dokter:</label>
                        <span id="view_doctor"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('viewModal')">Tutup</button>
                <button type="button" class="btn btn-primary" onclick="printRecord()">
                    <i class="fas fa-print"></i> Cetak
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Konfirmasi Hapus</h3>
                <span class="close" onclick="closeModal('deleteModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus rekam medis ini?</p>
                <p class="text-danger"><strong>Peringatan:</strong> Data yang dihapus tidak dapat dikembalikan!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Batal</button>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_rekam_medis" id="delete_id_rekam_medis">
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        function viewRecord(record) {
            document.getElementById('view_patient').textContent = record.no_rm + ' - ' + record.nm_pasien;
            document.getElementById('view_date').textContent = new Date(record.tgl_pemeriksaan).toLocaleDateString('id-ID');
            document.getElementById('view_keluhan').textContent = record.keluhan;
            document.getElementById('view_diagnosa').textContent = record.diagnosa;
            document.getElementById('view_resep').textContent = record.resep || '-';
            document.getElementById('view_catatan').textContent = record.ket || '-';
            document.getElementById('view_doctor').textContent = record.doctor_name;
            openModal('viewModal');
        }

        function editRecord(record) {
            document.getElementById('edit_id_rekam_medis').value = record.id_rekam_medis;
            document.getElementById('edit_id_pasien').value = record.id_pasien;
            document.getElementById('edit_tgl_pemeriksaan').value = record.tgl_pemeriksaan;
            document.getElementById('edit_keluhan').value = record.keluhan;
            document.getElementById('edit_diagnosa').value = record.diagnosa;
            document.getElementById('edit_resep').value = record.resep;
            document.getElementById('edit_catatan').value = record.ket;
            openModal('editModal');
        }

        function deleteRecord(id) {
            document.getElementById('delete_id_rekam_medis').value = id;
            openModal('deleteModal');
        }

        function printTable() {
            window.print();
        }

        function printRecord() {
            // Implementation for printing individual record
            window.print();
        }

        function exportData() {
            // Implementation for data export
            alert('Fitur export akan segera tersedia');
        }

        // Set default date to today
        document.getElementById('tgl_pemeriksaan').value = new Date().toISOString().split('T')[0];
    </script>

    <style>
        .modal-large {
            max-width: 800px;
        }
        
        .record-details {
            padding: 20px 0;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 15px;
            align-items: flex-start;
        }
        
        .detail-row label {
            font-weight: 600;
            min-width: 150px;
            color: var(--text-secondary);
        }
        
        .detail-text {
            background: var(--bg-light);
            padding: 10px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
            white-space: pre-wrap;
            flex: 1;
        }
        
        .text-truncate {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .text-danger {
            color: #dc3545;
        }
    </style>
</body>
</html>
