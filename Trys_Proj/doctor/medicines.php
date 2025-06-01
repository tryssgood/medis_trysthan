<?php
session_start();
include '../config/database.php';
include '../includes/functions.php';

// Check if user is logged in and is doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'dokter') {
    header("Location: ../login.php");
    exit();
}
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

$page_title = "Daftar Obat";
$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $nm_obat = $_POST['nm_obat'];
                $kemasan = $_POST['kemasan'];
                $harga = $_POST['harga'];
                $keterangan = $_POST['keterangan'];
                
                $stmt = $conn->prepare("INSERT INTO obat (nm_obat, kemasan, harga, keterangan) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssds", $nm_obat, $kemasan, $harga, $keterangan);
                
                if ($stmt->execute()) {
                    $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Obat berhasil ditambahkan!</div>';
                } else {
                    $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Gagal menambahkan obat: ' . $conn->error . '</div>';
                }
                break;
                
            case 'edit':
                $id_obat = $_POST['id_obat'];
                $nm_obat = $_POST['nm_obat'];
                $kemasan = $_POST['kemasan'];
                $harga = $_POST['harga'];
                $keterangan = $_POST['keterangan'];
                
                $stmt = $conn->prepare("UPDATE obat SET nm_obat=?, kemasan=?, harga=?, keterangan=? WHERE id_obat=?");
                $stmt->bind_param("ssdsi", $nm_obat, $kemasan, $harga, $keterangan, $id_obat);
                
                if ($stmt->execute()) {
                    $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Obat berhasil diperbarui!</div>';
                } else {
                    $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Gagal memperbarui obat: ' . $conn->error . '</div>';
                }
                break;
                
            case 'delete':
                $id_obat = $_POST['id_obat'];
                
                if ($conn->query("DELETE FROM obat WHERE id_obat = $id_obat")) {
                    $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Obat berhasil dihapus!</div>';
                } else {
                    $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Gagal menghapus obat: ' . $conn->error . '</div>';
                }
                break;
        }
    }
}

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_condition = "";

if (!empty($search)) {
    $search_condition = "WHERE nm_obat LIKE '%$search%' OR kemasan LIKE '%$search%'";
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$total_records_query = "SELECT COUNT(*) as count FROM obat $search_condition";
$total_records_result = $conn->query($total_records_query);
$total_records = $total_records_result->fetch_assoc()['count'];
$total_pages = ceil($total_records / $limit);

// Get medicines
$sql = "SELECT * FROM obat $search_condition ORDER BY nm_obat LIMIT $offset, $limit";
$result = $conn->query($sql);
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
            <h2><i class="fas fa-pills"></i> <?php echo $page_title; ?></h2>
            <button class="btn btn-primary" onclick="openModal('addModal')">
                <i class="fas fa-plus"></i> Tambah Obat
            </button>
        </div>
        
        <?php echo $message; ?>
        
        <!-- Search and Filter -->
        <div class="table-filters">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Cari obat..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-group">
                <button class="btn btn-outline-primary" onclick="exportData()">
                    <i class="fas fa-download"></i> Export
                </button>
                <button class="btn btn-outline-primary" onclick="printData()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
        
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Obat</th>
                        <th>Kemasan</th>
                        <th>Harga</th>
                        <th>Keterangan</th>
                       
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id_obat']; ?></td>
                            <td><strong><?php echo $row['nm_obat']; ?></strong></td>
                            <td><?php echo isset($row['kemasan']) ? $row['kemasan'] : '-'; ?></td>
                            <td>Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?></td>
                            <td><?php echo isset($row['keterangan']) ? substr($row['keterangan'], 0, 50) . (strlen($row['keterangan']) > 50 ? '...' : '') : '-'; ?></td>
                        
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">Tidak ada data obat</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Add Medicine Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Tambah Obat Baru</h3>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label><i class="fas fa-pills"></i> Nama Obat</label>
                        <input type="text" name="nm_obat" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-box"></i> Kemasan</label>
                        <input type="text" name="kemasan" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-money-bill"></i> Harga</label>
                        <input type="number" name="harga" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> Keterangan</label>
                        <textarea name="keterangan" rows="3"></textarea>
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
    
    <!-- Edit Medicine Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Obat</h3>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id_obat" id="edit_id_obat">
                    
                    <div class="form-group">
                        <label><i class="fas fa-pills"></i> Nama Obat</label>
                        <input type="text" name="nm_obat" id="edit_nm_obat" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-box"></i> Kemasan</label>
                        <input type="text" name="kemasan" id="edit_kemasan" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-money-bill"></i> Harga</label>
                        <input type="number" name="harga" id="edit_harga" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> Keterangan</label>
                        <textarea name="keterangan" id="edit_keterangan" rows="3"></textarea>
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
    
    <!-- View Medicine Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-pills"></i> Detail Obat</h3>
                <span class="close" onclick="closeModal('viewModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="medicineDetails"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('viewModal')">Tutup</button>
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
                <p>Apakah Anda yakin ingin menghapus obat ini?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Batal</button>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_obat" id="delete_id_obat">
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
                window.location.href = '?search=' + encodeURIComponent(searchTerm);
            }
        });
        
        // View medicine details
        function viewMedicine(id) {
            fetch(`get_medicine.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const medicine = data.medicine;
                        document.getElementById('medicineDetails').innerHTML = `
                            <div class="info-card">
                                <h4><i class="fas fa-pills"></i> Informasi Obat</h4>
                                <p><strong>ID:</strong> ${medicine.id_obat}</p>
                                <p><strong>Nama Obat:</strong> ${medicine.nm_obat}</p>
                                <p><strong>Kemasan:</strong> ${medicine.kemasan}</p>
                                <p><strong>Harga:</strong> Rp ${new Intl.NumberFormat('id-ID').format(medicine.harga)}</p>
                                <p><strong>Keterangan:</strong> ${medicine.keterangan || '-'}</p>
                            </div>
                        `;
                        openModal('viewModal');
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        // Edit medicine
        function editMedicine(id) {
            fetch(`get_medicine.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const medicine = data.medicine;
                        document.getElementById('edit_id_obat').value = medicine.id_obat;
                        document.getElementById('edit_nm_obat').value = medicine.nm_obat;
                        document.getElementById('edit_kemasan').value = medicine.kemasan;
                        document.getElementById('edit_harga').value = medicine.harga;
                        document.getElementById('edit_keterangan').value = medicine.keterangan;
                        openModal('editModal');
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        // Delete medicine
        function deleteMedicine(id) {
            document.getElementById('delete_id_obat').value = id;
            openModal('deleteModal');
        }
        
        function exportData() {
            window.open('export_medicines.php', '_blank');
        }
        
        function printData() {
            window.print();
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
