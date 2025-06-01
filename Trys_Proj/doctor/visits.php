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

// Get doctor's polyclinic
$doctor_info = null;
if (!empty($doctor_id) && is_numeric($doctor_id)) {
    $stmt = $conn->prepare("SELECT id_poli FROM dokter WHERE id_dokter = ?");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $doctor_info = $result->fetch_assoc();
    }
    $stmt->close();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $id_pasien = $_POST['id_pasien'];
                $tgl_kunjungan = $_POST['tgl_kunjungan'];
                $jam_kunjungan = $_POST['jam_kunjungan'];
                $id_poli = $doctor_info['id_poli'];
                
                $stmt = $conn->prepare("INSERT INTO kunjungan (id_pasien, id_poli, tgl_kunjungan, jam_kunjungan) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiss", $id_pasien, $id_poli, $tgl_kunjungan, $jam_kunjungan);
                
                if ($stmt->execute()) {
                    $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Jadwal kunjungan berhasil ditambahkan!</div>';
                } else {
                    $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Gagal menambahkan jadwal: ' . $conn->error . '</div>';
                }
                break;
                
            case 'edit':
                $id_kunjungan = $_POST['id_kunjungan'];
                $id_pasien = $_POST['id_pasien'];
                $tgl_kunjungan = $_POST['tgl_kunjungan'];
                $jam_kunjungan = $_POST['jam_kunjungan'];
                
                $stmt = $conn->prepare("UPDATE kunjungan SET id_pasien=?, tgl_kunjungan=?, jam_kunjungan=? WHERE id_kunjungan=?");
                $stmt->bind_param("issi", $id_pasien, $tgl_kunjungan, $jam_kunjungan, $id_kunjungan);
                
                if ($stmt->execute()) {
                    $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Jadwal kunjungan berhasil diperbarui!</div>';
                } else {
                    $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Gagal memperbarui jadwal: ' . $conn->error . '</div>';
                }
                break;
                
            case 'delete':
                $id_kunjungan = $_POST['id_kunjungan'];
                
                if ($conn->query("DELETE FROM kunjungan WHERE id_kunjungan = $id_kunjungan")) {
                    $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Jadwal kunjungan berhasil dihapus!</div>';
                } else {
                    $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Gagal menghapus jadwal: ' . $conn->error . '</div>';
                }
                break;
        }
    }
}

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';

$search_condition = "";
if (!empty($doctor_info) && isset($doctor_info['id_poli'])) {
    $search_condition = "WHERE k.id_poli = {$doctor_info['id_poli']}";
}

if (!empty($search)) {
    $search_condition .= " AND (p.nm_pasien LIKE '%$search%' OR p.no_rm LIKE '%$search%')";
}

if (!empty($date_filter)) {
    $search_condition .= " AND k.tgl_kunjungan = '$date_filter'";
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$total_records_query = "SELECT COUNT(*) as count 
                       FROM kunjungan k
                       JOIN pasien p ON k.id_pasien = p.id_pasien
                       $search_condition";
$total_records_result = $conn->query($total_records_query);
$total_records = $total_records_result->fetch_assoc()['count'];
$total_pages = ceil($total_records / $limit);

// Get visits
$sql = "SELECT k.*, p.nm_pasien, p.no_rm, pol.nm_poli
        FROM kunjungan k
        JOIN pasien p ON k.id_pasien = p.id_pasien
        JOIN poliklinik pol ON k.id_poli = pol.id_poli
        $search_condition
        ORDER BY k.tgl_kunjungan DESC, k.jam_kunjungan DESC
        LIMIT $offset, $limit";
$result = $conn->query($sql);

// Get patients for dropdown
$patients = $conn->query("SELECT id_pasien, nm_pasien, no_rm FROM pasien ORDER BY nm_pasien");
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
            <h2><i class="fas fa-calendar-check"></i> <?php echo $page_title; ?></h2>
            <button class="btn btn-primary" onclick="openModal('addModal')">
                <i class="fas fa-plus"></i> Tambah Jadwal
            </button>
        </div>
        
        <?php echo $message; ?>
        
        <!-- Search and Filter -->
        <div class="table-filters">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Cari pasien..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-group">
                <input type="date" id="dateFilter" value="<?php echo htmlspecialchars($date_filter); ?>" onchange="filterByDate()">
                <button class="btn btn-outline-primary" onclick="exportData()">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>
        
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No. RM</th>
                        <th>Nama Pasien</th>
                        <th>Poliklinik</th>
                        <th>Tanggal</th>
                        <th>Jam</th>
                        
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo $row['no_rm']; ?></strong></td>
                            <td><?php echo $row['nm_pasien']; ?></td>
                            <td><?php echo $row['nm_poli']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['tgl_kunjungan'])); ?></td>
                            <td><?php echo $row['jam_kunjungan']; ?></td>
                           
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">Tidak ada data kunjungan</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&date_filter=<?php echo urlencode($date_filter); ?>" class="page-link">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&date_filter=<?php echo urlencode($date_filter); ?>" 
               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&date_filter=<?php echo urlencode($date_filter); ?>" class="page-link">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Add Visit Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-plus"></i> Tambah Jadwal Kunjungan</h3>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Pasien</label>
                        <select name="id_pasien" required>
                            <option value="">Pilih Pasien</option>
                            <?php while ($patient = $patients->fetch_assoc()): ?>
                            <option value="<?php echo $patient['id_pasien']; ?>">
                                <?php echo $patient['no_rm'] . ' - ' . $patient['nm_pasien']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Tanggal Kunjungan</label>
                        <input type="date" name="tgl_kunjungan" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> Jam Kunjungan</label>
                        <input type="time" name="jam_kunjungan" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Visit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-edit"></i> Edit Jadwal Kunjungan</h3>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id_kunjungan" id="edit_id_kunjungan">
                    
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Pasien</label>
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
                        <label><i class="fas fa-calendar"></i> Tanggal Kunjungan</label>
                        <input type="date" name="tgl_kunjungan" id="edit_tgl_kunjungan" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> Jam Kunjungan</label>
                        <input type="time" name="jam_kunjungan" id="edit_jam_kunjungan" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update
                    </button>
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
                <p>Apakah Anda yakin ingin menghapus jadwal kunjungan ini?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Batal</button>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_kunjungan" id="delete_id_kunjungan">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value;
            if (searchTerm.length > 2 || searchTerm.length === 0) {
                const currentUrl = new URL(window.location);
                currentUrl.searchParams.set('search', searchTerm);
                window.location.href = currentUrl.toString();
            }
        });
        
        // Date filter
        function filterByDate() {
            const dateValue = document.getElementById('dateFilter').value;
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('date_filter', dateValue);
            window.location.href = currentUrl.toString();
        }
        
        // Edit visit
        function editVisit(id) {
            fetch(`get_visit.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const visit = data.visit;
                        document.getElementById('edit_id_kunjungan').value = visit.id_kunjungan;
                        document.getElementById('edit_id_pasien').value = visit.id_pasien;
                        document.getElementById('edit_tgl_kunjungan').value = visit.tgl_kunjungan;
                        document.getElementById('edit_jam_kunjungan').value = visit.jam_kunjungan;
                        openModal('editModal');
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        // Delete visit
        function deleteVisit(id) {
            document.getElementById('delete_id_kunjungan').value = id;
            openModal('deleteModal');
        }
        
        function exportData() {
            window.open('export_visits.php', '_blank');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
