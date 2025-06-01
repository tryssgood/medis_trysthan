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
                $id_poli = $_POST['id_poli'];
                $tgl_kunjungan = $_POST['tgl_kunjungan'];
                $jam_kunjungan = $_POST['jam_kunjungan'];
                
                // Check if columns exist before using them
                $check_keluhan = $conn->query("SHOW COLUMNS FROM kunjungan LIKE 'keluhan'");
                $has_keluhan = $check_keluhan->num_rows > 0;

                $check_status = $conn->query("SHOW COLUMNS FROM kunjungan LIKE 'status'");
                $has_status = $check_status->num_rows > 0;

                $fields = "id_pasien, id_poli, tgl_kunjungan, jam_kunjungan";
                $values = "?, ?, ?, ?";
                $params = [$id_pasien, $id_poli, $tgl_kunjungan, $jam_kunjungan];
                $types = "iiss";
                
                if ($has_keluhan && isset($_POST['keluhan'])) {
                    $fields .= ", keluhan";
                    $values .= ", ?";
                    $params[] = $_POST['keluhan'];
                    $types .= "s";
                }
                
                if ($has_status && isset($_POST['status'])) {
                    $fields .= ", status";
                    $values .= ", ?";
                    $params[] = $_POST['status'];
                    $types .= "s";
                }
                
                $stmt = $conn->prepare("INSERT INTO kunjungan ($fields) VALUES ($values)");
                $stmt->bind_param($types, ...$params);
                
                if ($stmt->execute()) {
                    $success = "Kunjungan berhasil ditambahkan!";
                } else {
                    $error = "Error: " . $stmt->error;
                }
                break;
                
            case 'update':
                $id_kunjungan = $_POST['id_kunjungan'];
                $id_pasien = $_POST['id_pasien'];
                $id_poli = $_POST['id_poli'];
                $tgl_kunjungan = $_POST['tgl_kunjungan'];
                $jam_kunjungan = $_POST['jam_kunjungan'];
                $keluhan = $_POST['keluhan'];
                $status = $_POST['status'];
                
                $stmt = $conn->prepare("UPDATE kunjungan SET id_pasien = ?, id_poli = ?, tgl_kunjungan = ?, jam_kunjungan = ?, keluhan = ?, status = ? WHERE id_kunjungan = ?");
                $stmt->bind_param("iissssi", $id_pasien, $id_poli, $tgl_kunjungan, $jam_kunjungan, $keluhan, $status, $id_kunjungan);
                
                if ($stmt->execute()) {
                    $success = "Kunjungan berhasil diperbarui!";
                } else {
                    $error = "Error: " . $stmt->error;
                }
                break;
                
            case 'delete':
                $id_kunjungan = $_POST['id_kunjungan'];
                
                $stmt = $conn->prepare("DELETE FROM kunjungan WHERE id_kunjungan = ?");
                $stmt->bind_param("i", $id_kunjungan);
                
                if ($stmt->execute()) {
                    $success = "Kunjungan berhasil dihapus!";
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
    $search_condition = "WHERE p.nm_pasien LIKE ? OR p.no_rm LIKE ? OR pol.nm_poli LIKE ? OR k.keluhan LIKE ?";
    $search_term = "%$search%";
    $search_params = [$search_term, $search_term, $search_term, $search_term];
    $param_types = "ssss";
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM kunjungan k 
              JOIN pasien p ON k.id_pasien = p.id_pasien 
              JOIN poliklinik pol ON k.id_poli = pol.id_poli 
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

// Get visits data - check if keluhan and status columns exist
$check_columns = $conn->query("SHOW COLUMNS FROM kunjungan LIKE 'keluhan'");
$has_keluhan = $check_columns->num_rows > 0;

$check_status = $conn->query("SHOW COLUMNS FROM kunjungan LIKE 'status'");
$has_status = $check_status->num_rows > 0;

$select_fields = "k.*, p.nm_pasien, p.no_rm, pol.nm_poli";
if ($has_keluhan) {
    $select_fields .= ", k.keluhan";
}
if ($has_status) {
    $select_fields .= ", k.status";
}

$sql = "SELECT $select_fields 
        FROM kunjungan k 
        JOIN pasien p ON k.id_pasien = p.id_pasien 
        JOIN poliklinik pol ON k.id_poli = pol.id_poli 
        $search_condition 
        ORDER BY k.tgl_kunjungan DESC, k.jam_kunjungan DESC 
        LIMIT ? OFFSET ?";

if (!empty($search_params)) {
    $stmt = $conn->prepare($sql);
    $search_params[] = $limit;
    $search_params[] = $offset;
    $param_types .= "ii";
    $stmt->bind_param($param_types, ...$search_params);
    $stmt->execute();
    $visits = $stmt->get_result();
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $visits = $stmt->get_result();
}

// Get patients for dropdown
$patients = $conn->query("SELECT id_pasien, nm_pasien, no_rm FROM pasien ORDER BY nm_pasien");

// Get polyclinics for dropdown
$polyclinics = $conn->query("SELECT id_poli, nm_poli FROM poliklinik ORDER BY nm_poli");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kunjungan - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-badge.status-default {
            background: #6b7280;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content">
        <div class="content-header">
            <div class="header-left">
                <h1><i class="fas fa-calendar-check"></i> Manajemen Kunjungan</h1>
                <p>Kelola data kunjungan pasien</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="openModal('addModal')">
                    <i class="fas fa-plus"></i> Tambah Kunjungan
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
                        <input type="text" name="search" placeholder="Cari pasien, no. RM, poliklinik..." 
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
            <table class="data-table" id="visitsTable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>No. RM</th>
                        <th>Nama Pasien</th>
                        <th>Poliklinik</th>
                        <th>Tanggal</th>
                        <th>Jam</th>
                        <th>Keluhan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = $offset + 1;
                    while ($visit = $visits->fetch_assoc()): 
                    ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo $visit['no_rm']; ?></td>
                        <td><?php echo $visit['nm_pasien']; ?></td>
                        <td><?php echo $visit['nm_poli']; ?></td>
                        <td><?php echo date('d/m/Y', strtotime($visit['tgl_kunjungan'])); ?></td>
                        <td><?php echo $visit['jam_kunjungan']; ?></td>
                        <td><?php echo isset($visit['keluhan']) ? $visit['keluhan'] : '-'; ?></td>
                        <td>
                            <?php if (isset($visit['status'])): ?>
                                <span class="status-badge status-<?php echo strtolower($visit['status']); ?>">
                                    <?php echo ucfirst($visit['status']); ?>
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-default">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-warning" onclick="editVisit(<?php echo htmlspecialchars(json_encode($visit)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteVisit(<?php echo $visit['id_kunjungan']; ?>)">
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

    <!-- Add Visit Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Tambah Kunjungan</h3>
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
                        <label for="id_poli">Poliklinik</label>
                        <select name="id_poli" id="id_poli" required>
                            <option value="">Pilih Poliklinik</option>
                            <?php 
                            $polyclinics->data_seek(0);
                            while ($poly = $polyclinics->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $poly['id_poli']; ?>">
                                    <?php echo $poly['nm_poli']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tgl_kunjungan">Tanggal Kunjungan</label>
                        <input type="date" name="tgl_kunjungan" id="tgl_kunjungan" required>
                    </div>
                    <div class="form-group">
                        <label for="jam_kunjungan">Jam Kunjungan</label>
                        <input type="time" name="jam_kunjungan" id="jam_kunjungan" required>
                    </div>
                    <div class="form-group full-width">
                        <label for="keluhan">Keluhan</label>
                        <textarea name="keluhan" id="keluhan" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" required>
                            <option value="menunggu">Menunggu</option>
                            <option value="sedang_diperiksa">Sedang Diperiksa</option>
                            <option value="selesai">Selesai</option>
                            <option value="batal">Batal</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Visit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Kunjungan</h3>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id_kunjungan" id="edit_id_kunjungan">
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
                        <label for="edit_id_poli">Poliklinik</label>
                        <select name="id_poli" id="edit_id_poli" required>
                            <option value="">Pilih Poliklinik</option>
                            <?php 
                            $polyclinics->data_seek(0);
                            while ($poly = $polyclinics->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $poly['id_poli']; ?>">
                                    <?php echo $poly['nm_poli']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_tgl_kunjungan">Tanggal Kunjungan</label>
                        <input type="date" name="tgl_kunjungan" id="edit_tgl_kunjungan" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_jam_kunjungan">Jam Kunjungan</label>
                        <input type="time" name="jam_kunjungan" id="edit_jam_kunjungan" required>
                    </div>
                    <div class="form-group full-width">
                        <label for="edit_keluhan">Keluhan</label>
                        <textarea name="keluhan" id="edit_keluhan" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select name="status" id="edit_status" required>
                            <option value="menunggu">Menunggu</option>
                            <option value="sedang_diperiksa">Sedang Diperiksa</option>
                            <option value="selesai">Selesai</option>
                            <option value="batal">Batal</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Batal</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
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
                <p>Apakah Anda yakin ingin menghapus kunjungan ini?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Batal</button>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_kunjungan" id="delete_id_kunjungan">
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        function editVisit(visit) {
            document.getElementById('edit_id_kunjungan').value = visit.id_kunjungan;
            document.getElementById('edit_id_pasien').value = visit.id_pasien;
            document.getElementById('edit_id_poli').value = visit.id_poli;
            document.getElementById('edit_tgl_kunjungan').value = visit.tgl_kunjungan;
            document.getElementById('edit_jam_kunjungan').value = visit.jam_kunjungan;
            document.getElementById('edit_keluhan').value = visit.keluhan;
            document.getElementById('edit_status').value = visit.status;
            openModal('editModal');
        }

        function deleteVisit(id) {
            document.getElementById('delete_id_kunjungan').value = id;
            openModal('deleteModal');
        }

        function printTable() {
            window.print();
        }

        function exportData() {
            // Implementation for data export
            alert('Fitur export akan segera tersedia');
        }
    </script>
</body>
</html>
