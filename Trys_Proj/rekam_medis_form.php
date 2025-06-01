<?php
session_start();
include 'config/database.php';
include 'includes/functions.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'dokter') {
    header("Location: login.php");
    exit();
}

$page_title = "Input Rekam Medis";
$message = '';
$doctor_id = getDoctorId($conn, $_SESSION['user_id']);

// Check if patient ID is provided
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$patient_id = $_GET['id'];

// Get patient information
$stmt = $conn->prepare("SELECT * FROM pasien WHERE id_pasien = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: index.php");
    exit();
}

$patient = $result->fetch_assoc();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $diagnosa = $_POST['diagnosa'];
    $keluhan = $_POST['keluhan'];
    $resep = $_POST['resep'];
    $tindakan_id = $_POST['tindakan_id'];
    $obat_ids = isset($_POST['obat_id']) ? $_POST['obat_id'] : [];
    $tgl_pemeriksaan = date('Y-m-d H:i:s');
    $keterangan = $_POST['keterangan'];
    $tekanan_darah = $_POST['tekanan_darah'] ?? '';
    $suhu_tubuh = $_POST['suhu_tubuh'] ?? '';
    $berat_badan = $_POST['berat_badan'] ?? '';
    $tinggi_badan = $_POST['tinggi_badan'] ?? '';
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Insert medical record
        if (!empty($obat_ids)) {
            foreach ($obat_ids as $obat_id) {
                $stmt = $conn->prepare("INSERT INTO rekam_medis (no_rm, id_tindakan, id_obat, id_user, id_pasien, diagnosa, resep, keluhan, tgl_pemeriksaan, ket) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("siiiiissss", $patient['no_rm'], $tindakan_id, $obat_id, $_SESSION['user_id'], $patient_id, $diagnosa, $resep, $keluhan, $tgl_pemeriksaan, $keterangan);
                $stmt->execute();
            }
        } else {
            // If no medicine is selected, still create a record
            $stmt = $conn->prepare("INSERT INTO rekam_medis (no_rm, id_tindakan, id_user, id_pasien, diagnosa, resep, keluhan, tgl_pemeriksaan, ket) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siiiissss", $patient['no_rm'], $tindakan_id, $_SESSION['user_id'], $patient_id, $diagnosa, $resep, $keluhan, $tgl_pemeriksaan, $keterangan);
            $stmt->execute();
        }
        
        // Insert vital signs if provided
        if (!empty($tekanan_darah) || !empty($suhu_tubuh) || !empty($berat_badan) || !empty($tinggi_badan)) {
            $vital_signs = json_encode([
                'tekanan_darah' => $tekanan_darah,
                'suhu_tubuh' => $suhu_tubuh,
                'berat_badan' => $berat_badan,
                'tinggi_badan' => $tinggi_badan
            ]);
            
            // Update the latest record with vital signs
            $stmt = $conn->prepare("UPDATE rekam_medis SET ket = CONCAT(IFNULL(ket, ''), '\nVital Signs: ', ?) WHERE id_pasien = ? AND tgl_pemeriksaan = ? ORDER BY id_rekam_medis DESC LIMIT 1");
            $stmt->bind_param("sis", $vital_signs, $patient_id, $tgl_pemeriksaan);
            $stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        $message = "Data rekam medis berhasil disimpan.";
        
        // Clear form data
        $_POST = array();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
    }
}

// Get treatments
$treatments = $conn->query("SELECT * FROM tindakan ORDER BY nm_tindakan");

// Get medicines
$medicines = $conn->query("SELECT * FROM obat ORDER BY nm_obat");

// Get patient's medical history
$stmt = $conn->prepare("
    SELECT rm.*, t.nm_tindakan, o.nm_obat, d.nm_dokter, l.username
    FROM rekam_medis rm
    LEFT JOIN tindakan t ON rm.id_tindakan = t.id_tindakan
    LEFT JOIN obat o ON rm.id_obat = o.id_obat
    LEFT JOIN dokter d ON rm.id_user = d.id_user
    LEFT JOIN login l ON rm.id_user = l.id_user
    WHERE rm.id_pasien = ?
    ORDER BY rm.tgl_pemeriksaan DESC
    LIMIT 5
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$medical_history = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Rekam Medis - <?php echo $page_title; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="sidebar">
            <?php include 'includes/sidebar.php'; ?>
        </div>
        
        <div class="content">
            <div class="content-header">
                <h2><?php echo $page_title; ?></h2>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-info">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3>Data Pasien</h3>
                </div>
                <div class="card-body">
                    <div class="patient-info-grid">
                        <div class="info-item">
                            <span class="info-label">No. Rekam Medis:</span>
                            <span class="info-value"><?php echo $patient['no_rm']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Nama Pasien:</span>
                            <span class="info-value"><?php echo $patient['nm_pasien']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Tanggal Lahir:</span>
                            <span class="info-value"><?php echo date('d/m/Y', strtotime($patient['tgl_lhr'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Jenis Kelamin:</span>
                            <span class="info-value"><?php echo $patient['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan'; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Umur:</span>
                            <span class="info-value"><?php echo date_diff(date_create($patient['tgl_lhr']), date_create('today'))->y; ?> tahun</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Alamat:</span>
                            <span class="info-value"><?php echo $patient['alamat']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h3>Form Rekam Medis</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="medicalRecordForm">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="keluhan"><i class="fas fa-comment-medical"></i> Keluhan Utama</label>
                                <textarea id="keluhan" name="keluhan" rows="3" required placeholder="Deskripsikan keluhan utama pasien..."><?php echo isset($_POST['keluhan']) ? $_POST['keluhan'] : ''; ?></textarea>
                            </div>
                            
                            <div class="form-group col-md-6">
                                <label for="diagnosa"><i class="fas fa-stethoscope"></i> Diagnosa</label>
                                <textarea id="diagnosa" name="diagnosa" rows="3" required placeholder="Masukkan diagnosa berdasarkan pemeriksaan..."><?php echo isset($_POST['diagnosa']) ? $_POST['diagnosa'] : ''; ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h4><i class="fas fa-heartbeat"></i> Vital Signs</h4>
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label for="tekanan_darah">Tekanan Darah (mmHg)</label>
                                    <input type="text" id="tekanan_darah" name="tekanan_darah" placeholder="120/80" value="<?php echo isset($_POST['tekanan_darah']) ? $_POST['tekanan_darah'] : ''; ?>">
                                </div>
                                
                                <div class="form-group col-md-3">
                                    <label for="suhu_tubuh">Suhu Tubuh (°C)</label>
                                    <input type="number" id="suhu_tubuh" name="suhu_tubuh" step="0.1" min="35" max="45" placeholder="36.5" value="<?php echo isset($_POST['suhu_tubuh']) ? $_POST['suhu_tubuh'] : ''; ?>">
                                </div>
                                
                                <div class="form-group col-md-3">
                                    <label for="berat_badan">Berat Badan (kg)</label>
                                    <input type="number" id="berat_badan" name="berat_badan" step="0.1" min="0" max="300" placeholder="70" value="<?php echo isset($_POST['berat_badan']) ? $_POST['berat_badan'] : ''; ?>">
                                </div>
                                
                                <div class="form-group col-md-3">
                                    <label for="tinggi_badan">Tinggi Badan (cm)</label>
                                    <input type="number" id="tinggi_badan" name="tinggi_badan" min="0" max="250" placeholder="170" value="<?php echo isset($_POST['tinggi_badan']) ? $_POST['tinggi_badan'] : ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="tindakan_id"><i class="fas fa-procedures"></i> Tindakan Medis</label>
                            <select id="tindakan_id" name="tindakan_id" required>
                                <option value="">-- Pilih Tindakan --</option>
                                <?php 
                                $treatments->data_seek(0); // Reset pointer
                                while ($row = $treatments->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $row['id_tindakan']; ?>" <?php echo (isset($_POST['tindakan_id']) && $_POST['tindakan_id'] == $row['id_tindakan']) ? 'selected' : ''; ?>>
                                    <?php echo $row['nm_tindakan']; ?>
                                    <?php if (!empty($row['ket'])): ?>
                                        - <?php echo $row['ket']; ?>
                                    <?php endif; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-pills"></i> Obat yang Diberikan</label>
                            <div class="medicine-selection">
                                <div class="search-medicine">
                                    <input type="text" id="medicineSearch" placeholder="Cari obat..." class="form-control">
                                </div>
                                <div class="checkbox-group" id="medicineList">
                                    <?php 
                                    $medicines->data_seek(0); // Reset pointer
                                    while ($row = $medicines->fetch_assoc()): 
                                    ?>
                                    <div class="checkbox-item medicine-item" data-name="<?php echo strtolower($row['nm_obat']); ?>">
                                        <input type="checkbox" id="obat_<?php echo $row['id_obat']; ?>" name="obat_id[]" value="<?php echo $row['id_obat']; ?>" 
                                               <?php echo (isset($_POST['obat_id']) && in_array($row['id_obat'], $_POST['obat_id'])) ? 'checked' : ''; ?>>
                                        <label for="obat_<?php echo $row['id_obat']; ?>">
                                            <strong><?php echo $row['nm_obat']; ?></strong> (<?php echo $row['ukuran']; ?>)
                                            <br><small class="text-muted">Stok: <?php echo $row['jml_obat']; ?> | Harga: Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?></small>
                                        </label>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="resep"><i class="fas fa-prescription"></i> Resep & Aturan Pakai</label>
                            <textarea id="resep" name="resep" rows="4" placeholder="Contoh: Paracetamol 500mg - 3x1 tablet setelah makan&#10;Amoxicillin 500mg - 3x1 kapsul sebelum makan selama 7 hari"><?php echo isset($_POST['resep']) ? $_POST['resep'] : ''; ?></textarea>
                            <small class="form-text text-muted">Tuliskan aturan pakai obat dengan jelas</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="keterangan"><i class="fas fa-sticky-note"></i> Keterangan Tambahan</label>
                            <textarea id="keterangan" name="keterangan" rows="3" placeholder="Catatan tambahan, anjuran, atau instruksi khusus untuk pasien..."><?php echo isset($_POST['keterangan']) ? $_POST['keterangan'] : ''; ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Rekam Medis
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                <i class="fas fa-undo"></i> Reset Form
                            </button>
                            <button type="button" class="btn btn-info" onclick="previewPrescription()">
                                <i class="fas fa-eye"></i> Preview Resep
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Medical History Section -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Riwayat Rekam Medis</h3>
                </div>
                <div class="card-body">
                    <?php if ($medical_history->num_rows > 0): ?>
                    <div class="medical-history">
                        <?php while ($history = $medical_history->fetch_assoc()): ?>
                        <div class="history-item">
                            <div class="history-header">
                                <div class="history-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($history['tgl_pemeriksaan'])); ?>
                                </div>
                                <div class="history-doctor">
                                    <i class="fas fa-user-md"></i>
                                    <?php echo $history['nm_dokter'] ?? $history['username']; ?>
                                </div>
                            </div>
                            <div class="history-content">
                                <div class="history-row">
                                    <strong>Keluhan:</strong> <?php echo $history['keluhan']; ?>
                                </div>
                                <div class="history-row">
                                    <strong>Diagnosa:</strong> <?php echo $history['diagnosa']; ?>
                                </div>
                                <div class="history-row">
                                    <strong>Tindakan:</strong> <?php echo $history['nm_tindakan']; ?>
                                </div>
                                <?php if (!empty($history['nm_obat'])): ?>
                                <div class="history-row">
                                    <strong>Obat:</strong> <?php echo $history['nm_obat']; ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($history['resep'])): ?>
                                <div class="history-row">
                                    <strong>Resep:</strong> <?php echo nl2br($history['resep']); ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($history['ket'])): ?>
                                <div class="history-row">
                                    <strong>Keterangan:</strong> <?php echo nl2br($history['ket']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <p class="no-data">Belum ada riwayat rekam medis untuk pasien ini.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Prescription Preview Modal -->
    <div id="prescriptionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-prescription"></i> Preview Resep</h3>
                <span class="close" onclick="closeModal('prescriptionModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="prescriptionPreview">
                    <!-- Prescription content will be generated here -->
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-primary" onclick="printPrescription()">
                        <i class="fas fa-print"></i> Cetak Resep
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('prescriptionModal')">
                        <i class="fas fa-times"></i> Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Medicine search functionality
        document.getElementById('medicineSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const medicineItems = document.querySelectorAll('.medicine-item');
            
            medicineItems.forEach(item => {
                const medicineName = item.getAttribute('data-name');
                if (medicineName.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
        
        // Form validation
        document.getElementById('medicalRecordForm').addEventListener('submit', function(e) {
            const keluhan = document.getElementById('keluhan').value.trim();
            const diagnosa = document.getElementById('diagnosa').value.trim();
            const tindakan = document.getElementById('tindakan_id').value;
            
            if (!keluhan || !diagnosa || !tindakan) {
                e.preventDefault();
                alert('Mohon lengkapi keluhan, diagnosa, dan tindakan medis.');
                return false;
            }
            
            // Validate vital signs format
            const tekananDarah = document.getElementById('tekanan_darah').value;
            if (tekananDarah && !tekananDarah.match(/^\d{2,3}\/\d{2,3}$/)) {
                e.preventDefault();
                alert('Format tekanan darah tidak valid. Gunakan format: 120/80');
                return false;
            }
            
            return confirm('Apakah Anda yakin data rekam medis sudah benar?');
        });
        
        // Reset form function
        function resetForm() {
            if (confirm('Apakah Anda yakin ingin mereset form?')) {
                document.getElementById('medicalRecordForm').reset();
                document.getElementById('medicineSearch').value = '';
                
                // Show all medicine items
                document.querySelectorAll('.medicine-item').forEach(item => {
                    item.style.display = 'flex';
                });
            }
        }
        
        // Preview prescription function
        function previewPrescription() {
            const patientName = '<?php echo $patient['nm_pasien']; ?>';
            const patientRM = '<?php echo $patient['no_rm']; ?>';
            const doctorName = '<?php echo $_SESSION['username']; ?>';
            const currentDate = new Date().toLocaleDateString('id-ID');
            
            const diagnosa = document.getElementById('diagnosa').value;
            const resep = document.getElementById('resep').value;
            const keterangan = document.getElementById('keterangan').value;
            
            // Get selected medicines
            const selectedMedicines = [];
            document.querySelectorAll('input[name="obat_id[]"]:checked').forEach(checkbox => {
                const label = document.querySelector(`label[for="${checkbox.id}"]`);
                selectedMedicines.push(label.innerHTML);
            });
            
            let prescriptionHTML = `
                <div class="prescription-header">
                    <h2>RESEP DOKTER</h2>
                    <div class="prescription-info">
                        <p><strong>Nama Pasien:</strong> ${patientName}</p>
                        <p><strong>No. RM:</strong> ${patientRM}</p>
                        <p><strong>Tanggal:</strong> ${currentDate}</p>
                        <p><strong>Dokter:</strong> ${doctorName}</p>
                    </div>
                </div>
                
                <div class="prescription-content">
                    <div class="prescription-section">
                        <h4>Diagnosa:</h4>
                        <p>${diagnosa || '-'}</p>
                    </div>
                    
                    <div class="prescription-section">
                        <h4>Obat yang Diberikan:</h4>
                        <ul>
            `;
            
            if (selectedMedicines.length > 0) {
                selectedMedicines.forEach(medicine => {
                    prescriptionHTML += `<li>${medicine}</li>`;
                });
            } else {
                prescriptionHTML += '<li>Tidak ada obat yang dipilih</li>';
            }
            
            prescriptionHTML += `
                        </ul>
                    </div>
                    
                    <div class="prescription-section">
                        <h4>Aturan Pakai:</h4>
                        <pre>${resep || '-'}</pre>
                    </div>
                    
                    <div class="prescription-section">
                        <h4>Catatan:</h4>
                        <pre>${keterangan || '-'}</pre>
                    </div>
                </div>
                
                <div class="prescription-footer">
                    <p>Dokter yang merawat,</p>
                    <br><br>
                    <p><strong>${doctorName}</strong></p>
                </div>
            `;
            
            document.getElementById('prescriptionPreview').innerHTML = prescriptionHTML;
            document.getElementById('prescriptionModal').style.display = 'block';
        }
        
        // Print prescription function
        function printPrescription() {
            const prescriptionContent = document.getElementById('prescriptionPreview').innerHTML;
            const printWindow = window.open('', '_blank');
            
            printWindow.document.write(`
                <html>
                <head>
                    <title>Resep Dokter</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .prescription-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                        .prescription-header h2 { margin: 0; color: #537D5D; }
                        .prescription-info { margin-top: 20px; text-align: left; }
                        .prescription-section { margin: 20px 0; }
                        .prescription-section h4 { color: #537D5D; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
                        .prescription-footer { margin-top: 50px; text-align: right; }
                        ul { padding-left: 20px; }
                        pre { white-space: pre-wrap; font-family: Arial, sans-serif; }
                        @media print {
                            body { margin: 0; }
                        }
                    </style>
                </head>
                <body>
                    ${prescriptionContent}
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.print();
        }
        
        // Close modal function
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
        
        // Auto-save draft functionality (optional)
        let autoSaveTimer;
        function autoSaveDraft() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => {
                const formData = new FormData(document.getElementById('medicalRecordForm'));
                const draftData = {};
                
                for (let [key, value] of formData.entries()) {
                    if (draftData[key]) {
                        if (Array.isArray(draftData[key])) {
                            draftData[key].push(value);
                        } else {
                            draftData[key] = [draftData[key], value];
                        }
                    } else {
                        draftData[key] = value;
                    }
                }
                
                localStorage.setItem('medicalRecordDraft_<?php echo $patient_id; ?>', JSON.stringify(draftData));
                console.log('Draft saved automatically');
            }, 5000); // Save after 5 seconds of inactivity
        }
        
        // Add event listeners for auto-save
        document.querySelectorAll('#medicalRecordForm input, #medicalRecordForm textarea, #medicalRecordForm select').forEach(element => {
            element.addEventListener('input', autoSaveDraft);
            element.addEventListener('change', autoSaveDraft);
        });
        
        // Load draft on page load
        window.addEventListener('load', function() {
            const savedDraft = localStorage.getItem('medicalRecordDraft_<?php echo $patient_id; ?>');
            if (savedDraft && confirm('Ditemukan draft yang tersimpan. Apakah Anda ingin memuat draft tersebut?')) {
                const draftData = JSON.parse(savedDraft);
                
                for (let [key, value] of Object.entries(draftData)) {
                    const element = document.querySelector(`[name="${key}"]`);
                    if (element) {
                        if (element.type === 'checkbox') {
                            if (Array.isArray(value)) {
                                value.forEach(val => {
                                    const checkbox = document.querySelector(`[name="${key}"][value="${val}"]`);
                                    if (checkbox) checkbox.checked = true;
                                });
                            } else {
                                element.checked = true;
                            }
                        } else {
                            element.value = value;
                        }
                    }
                }
            }
        });
        
        // Clear draft after successful submission
        <?php if (!empty($message) && strpos($message, 'berhasil') !== false): ?>
        localStorage.removeItem('medicalRecordDraft_<?php echo $patient_id; ?>');
        <?php endif; ?>
    </script>
    
    <style>
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        .col-md-3 { flex: 0 0 23%; }
        .col-md-6 { flex: 0 0 48%; }
        
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #537D5D;
        }
        
        .form-section h4 {
            color: #537D5D;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        .medicine-selection {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            background: #f8f9fa;
        }
        
        .search-medicine {
            margin-bottom: 15px;
        }
        
        .search-medicine input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        
        .medicine-item {
            padding: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .medicine-item:last-child {
            border-bottom: none;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-start;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }
        
        .medical-history {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .history-item {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .history-header {
            background: linear-gradient(135deg, #537D5D, #73946B);
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .history-date, .history-doctor {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .history-content {
            padding: 20px;
        }
        
        .history-row {
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .history-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .prescription-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        
        .prescription-header h2 {
            margin: 0;
            color: #537D5D;
        }
        
        .prescription-info {
            margin-top: 20px;
            text-align: left;
        }
        
        .prescription-section {
            margin: 20px 0;
        }
        
        .prescription-section h4 {
            color: #537D5D;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        
        .prescription-footer {
            margin-top: 50px;
            text-align: right;
        }
        
        .modal-actions {
            margin-top: 20px;
            text-align: center;
            border-top: 1px solid #e9ecef;
            padding-top: 20px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .form-row .form-group {
                margin-bottom: 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .history-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
</body>
</html>
