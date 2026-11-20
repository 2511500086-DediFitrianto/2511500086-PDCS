
<?php

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../helpers/response.php";

// ============================================
// POST - Menambahkan mahasiswa
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Membaca JSON dari request body
    $raw = file_get_contents("php://input");
    $input = json_decode($raw, true);

    if (!is_array($input)) {
        sendResponse(false, "Format JSON tidak valid", null, 400);
        exit;
    }

    // Mengambil input
    $nama = trim($input['nama'] ?? '');
    $nim = trim($input['nim'] ?? '');

    $jurusan_id = filter_var(
        $input['jurusan_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    // Validasi input
    if (
        $nama === '' ||
        $nim === '' ||
        $jurusan_id === false ||
        $jurusan_id === null ||
        $jurusan_id < 1
    ) {
        sendResponse(
            false,
            "Nama, NIM, dan jurusan_id wajib diisi dengan benar",
            null,
            400
        );
        exit;
    }

    // Periksa apakah NIM sudah terdaftar
    $q = "SELECT id FROM mahasiswa WHERE nim = ?";
    $stmt = mysqli_prepare($koneksi, $q);

    if (!$stmt) {
        sendResponse(
            false,
            "Gagal menyiapkan query: " . mysqli_error($koneksi),
            null,
            500
        );
        exit;
    }

    mysqli_stmt_bind_param($stmt, "s", $nim);

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        sendResponse(false, "Gagal memeriksa NIM: " . $error, null, 500);
        exit;
    }

    $result = mysqli_stmt_get_result($stmt);
    $nim_ada = mysqli_num_rows($result) > 0;

    mysqli_stmt_close($stmt);

    if ($nim_ada) {
        sendResponse(false, "NIM sudah terdaftar", null, 409);
        exit;
    }

    // Periksa apakah jurusan tersedia
    $q = "SELECT id FROM jurusan WHERE id = ?";
    $stmt = mysqli_prepare($koneksi, $q);

    if (!$stmt) {
        sendResponse(
            false,
            "Gagal menyiapkan query jurusan: " .
            mysqli_error($koneksi),
            null,
            500
        );
        exit;
    }

    mysqli_stmt_bind_param($stmt, "i", $jurusan_id);

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        sendResponse(false, "Gagal memeriksa jurusan: " . $error, null, 500);
        exit;
    }

    $result = mysqli_stmt_get_result($stmt);
    $jurusan_ada = mysqli_num_rows($result) > 0;

    mysqli_stmt_close($stmt);

    if (!$jurusan_ada) {
        sendResponse(false, "Jurusan tidak ditemukan", null, 400);
        exit;
    }

    // Simpan mahasiswa
    $q = "INSERT INTO mahasiswa (nama, nim, jurusan_id)
          VALUES (?, ?, ?)";

    $stmt = mysqli_prepare($koneksi, $q);

    if (!$stmt) {
        sendResponse(
            false,
            "Gagal menyiapkan INSERT: " . mysqli_error($koneksi),
            null,
            500
        );
        exit;
    }

    mysqli_stmt_bind_param(
        $stmt,
        "ssi",
        $nama,
        $nim,
        $jurusan_id
    );

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);

        error_log("Gagal menyimpan mahasiswa: " . $error);

        mysqli_stmt_close($stmt);

        sendResponse(
            false,
            "Gagal menyimpan data: " . $error,
            null,
            500
        );
        exit;
    }

    $id_baru = mysqli_insert_id($koneksi);

    mysqli_stmt_close($stmt);

    sendResponse(true, "Mahasiswa berhasil ditambahkan", [
        "id" => $id_baru,
        "nama" => $nama,
        "nim" => $nim,
        "jurusan_id" => $jurusan_id
    ], 201);

    exit;
}

// ============================================
// GET - Berdasarkan ID
// ============================================
if (isset($_GET['id'])) {

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($id === false || $id === null || $id < 1) {
        sendResponse(false, "ID tidak valid", null, 400);
        exit;
    }

    $q = "SELECT
              m.id,
              m.nama,
              m.nim,
              j.nm_jurusan AS jurusan
          FROM mahasiswa m
          LEFT JOIN jurusan j ON m.jurusan_id = j.id
          WHERE m.id = ?";

    $stmt = mysqli_prepare($koneksi, $q);

    if (!$stmt) {
        sendResponse(false, "Query gagal", null, 500);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    if (!$data) {
        sendResponse(false, "Data tidak ditemukan", null, 404);
        exit;
    }

    sendResponse(true, "Berhasil", $data, 200);
    exit;
}

// ============================================
// GET - Pencarian
// ============================================
if (isset($_GET['search'])) {

    $search = "%" . trim($_GET['search']) . "%";

    $q = "SELECT
              m.id,
              m.nama,
              m.nim,
              j.nm_jurusan AS jurusan
          FROM mahasiswa m
          LEFT JOIN jurusan j ON m.jurusan_id = j.id
          WHERE m.nama LIKE ?
             OR m.nim LIKE ?
             OR j.nm_jurusan LIKE ?
             OR j.kd_jurusan LIKE ?
             OR j.fakultas LIKE ?
          ORDER BY m.id DESC";

    $stmt = mysqli_prepare($koneksi, $q);

    if (!$stmt) {
        sendResponse(false, "Query gagal", null, 500);
        exit;
    }

    mysqli_stmt_bind_param(
        $stmt,
        "sssss",
        $search,
        $search,
        $search,
        $search,
        $search
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $data = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }

    mysqli_stmt_close($stmt);

    sendResponse(true, "Berhasil", $data, 200);
    exit;
}

// ============================================
// GET - Pagination
// ============================================
if (isset($_GET['page']) && isset($_GET['limit'])) {

    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
    $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);

    if (
        $page === false || $page === null || $page < 1 ||
        $limit === false || $limit === null ||
        $limit < 1 || $limit > 100
    ) {
        sendResponse(
            false,
            "Page atau limit tidak valid",
            null,
            400
        );
        exit;
    }

    $offset = ($page - 1) * $limit;

    $q = "SELECT
              m.id,
              m.nama,
              m.nim,
              j.nm_jurusan AS jurusan
          FROM mahasiswa m
          LEFT JOIN jurusan j ON m.jurusan_id = j.id
          ORDER BY m.id DESC
          LIMIT ? OFFSET ?";

    $stmt = mysqli_prepare($koneksi, $q);

    if (!$stmt) {
        sendResponse(false, "Query gagal", null, 500);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $data = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }

    mysqli_stmt_close($stmt);

    sendResponse(true, "Berhasil", $data, 200);
    exit;
}

// ============================================
// GET - Semua data mahasiswa
// ============================================
$q = "SELECT
          m.id,
          m.nama,
          m.nim,
          j.nm_jurusan AS jurusan
      FROM mahasiswa m
      LEFT JOIN jurusan j ON m.jurusan_id = j.id
      ORDER BY m.id DESC";

$result = mysqli_query($koneksi, $q);

if (!$result) {
    sendResponse(
        false,
        "Query gagal: " . mysqli_error($koneksi),
        null,
        500
    );
    exit;
}

$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

sendResponse(true, "Berhasil", $data, 200);

?>