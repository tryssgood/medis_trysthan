<?php
// General functions for the application

// Get count of records in a table
function getCount($conn, $table) {
    $sql = "SELECT COUNT(*) as count FROM $table";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Get doctor ID from user ID
function getDoctorId($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id_dokter FROM dokter WHERE id_user = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['id_dokter'];
    }
    
    return null;
}

// Get patient ID from user ID
function getPatientId($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id_pasien FROM pasien WHERE id_user = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['id_pasien'];
    }
    
    return null;
}

// Get patient information
function getPatientInfo($conn, $patient_id) {
    $stmt = $conn->prepare("SELECT * FROM pasien WHERE id_pasien = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Get today's patients for a doctor
function getTodayPatients($conn, $doctor_id) {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM kunjungan k
        JOIN poliklinik p ON k.id_poli = p.id_poli
        JOIN dokter d ON d.id_poli = p.id_poli
        WHERE d.id_dokter = ? AND k.tgl_kunjungan = ?
    ");
    $stmt->bind_param("is", $doctor_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Get pending medical records for a doctor
function getPendingRecords($conn, $doctor_id) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM rekam_medis rm
        JOIN dokter d ON rm.id_user = d.id_user
        WHERE d.id_dokter = ? AND rm.diagnosa IS NULL
    ");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Display today's patients list for a doctor
function displayTodayPatientsList($conn, $doctor_id) {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT p.id_pasien, p.nm_pasien, p.no_rm, k.jam_kunjungan, pl.nm_poli
        FROM kunjungan k
        JOIN pasien p ON k.id_pasien = p.id_pasien
        JOIN poliklinik pl ON k.id_poli = pl.id_poli
        JOIN dokter d ON d.id_poli = pl.id_poli
        WHERE d.id_dokter = ? AND k.tgl_kunjungan = ?
        ORDER BY k.jam_kunjungan
    ");
    $stmt->bind_param("is", $doctor_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo '<table class="data-table">
                <thead>
                    <tr>
                        <th>No. RM</th>
                        <th>Nama Pasien</th>
                        <th>Jam Kunjungan</th>
                        <th>Poliklinik</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>';
        
        while ($row = $result->fetch_assoc()) {
            echo '<tr>
                    <td>' . $row['no_rm'] . '</td>
                    <td>' . $row['nm_pasien'] . '</td>
                    <td>' . $row['jam_kunjungan'] . '</td>
                    <td>' . $row['nm_poli'] . '</td>
                    <td>
                        <a href="rekam_medis_form.php?id=' . $row['id_pasien'] . '" class="btn btn-sm btn-primary">
                            <i class="fas fa-notes-medical"></i> Input Rekam Medis
                        </a>
                    </td>
                </tr>';
        }
        
        echo '</tbody></table>';
    } else {
        echo '<p class="no-data">Tidak ada pasien untuk hari ini.</p>';
    }
}

// Display patient visit history
function displayPatientVisitHistory($conn, $patient_id) {
    $stmt = $conn->prepare("
        SELECT k.tgl_kunjungan, p.nm_poli, d.nm_dokter, rm.diagnosa, rm.resep
        FROM kunjungan k
        LEFT JOIN poliklinik p ON k.id_poli = p.id_poli
        LEFT JOIN dokter d ON d.id_poli = p.id_poli
        LEFT JOIN rekam_medis rm ON rm.id_pasien = k.id_pasien AND DATE(rm.tgl_pemeriksaan) = k.tgl_kunjungan
        WHERE k.id_pasien = ?
        ORDER BY k.tgl_kunjungan DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo '<table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Poliklinik</th>
                        <th>Dokter</th>
                        <th>Diagnosa</th>
                        <th>Resep</th>
                    </tr>
                </thead>
                <tbody>';
        
        while ($row = $result->fetch_assoc()) {
            echo '<tr>
                    <td>' . date('d/m/Y', strtotime($row['tgl_kunjungan'])) . '</td>
                    <td>' . $row['nm_poli'] . '</td>
                    <td>' . $row['nm_dokter'] . '</td>
                    <td>' . ($row['diagnosa'] ?? '-') . '</td>
                    <td>' . ($row['resep'] ?? '-') . '</td>
                </tr>';
        }
        
        echo '</tbody></table>';
    } else {
        echo '<p class="no-data">Tidak ada riwayat kunjungan.</p>';
    }
}

// Get upcoming visits for a patient
function getUpcomingVisits($conn, $patient_id) {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM kunjungan
        WHERE id_pasien = ? AND tgl_kunjungan >= ?
    ");
    $stmt->bind_param("is", $patient_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Display upcoming visits for a patient
function displayUpcomingVisits($conn, $patient_id) {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT k.tgl_kunjungan, k.jam_kunjungan, p.nm_poli
        FROM kunjungan k
        JOIN poliklinik p ON k.id_poli = p.id_poli
        WHERE k.id_pasien = ? AND k.tgl_kunjungan >= ?
        ORDER BY k.tgl_kunjungan, k.jam_kunjungan
    ");
    $stmt->bind_param("is", $patient_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo '<table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jam</th>
                        <th>Poliklinik</th>
                    </tr>
                </thead>
                <tbody>';
        
        while ($row = $result->fetch_assoc()) {
            echo '<tr>
                    <td>' . date('d/m/Y', strtotime($row['tgl_kunjungan'])) . '</td>
                    <td>' . $row['jam_kunjungan'] . '</td>
                    <td>' . $row['nm_poli'] . '</td>
                </tr>';
        }
        
        echo '</tbody></table>';
    } else {
        echo '<p class="no-data">Tidak ada jadwal kunjungan mendatang.</p>';
    }
}

// Format date to Indonesian format
function formatDate($date) {
    $timestamp = strtotime($date);
    $months = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $day = date('d', $timestamp);
    $month = $months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);
    
    return $day . ' ' . $month . ' ' . $year;
}

// Check if a record exists
function recordExists($conn, $table, $column, $value) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table WHERE $column = ?");
    $stmt->bind_param("s", $value);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] > 0;
}

// Generate a unique medical record number
function generateMedicalRecordNumber($conn) {
    $prefix = "RM-";
    $year = date('Y');
    $month = date('m');
    
    // Get the last record number
    $sql = "SELECT MAX(SUBSTRING_INDEX(no_rm, '-', -1)) as last_number FROM pasien WHERE no_rm LIKE '$prefix$year$month-%'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    
    $last_number = $row['last_number'] ?? 0;
    $next_number = str_pad($last_number + 1, 4, '0', STR_PAD_LEFT);
    
    return $prefix . $year . $month . '-' . $next_number;
}
