
<?php

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../helpers/response.php";

// GET berdasarkan ID
if (isset($_GET['id'])) {

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($id === false || $id === null || $id < 1) {
        sendResponse(false, "ID tidak valid", null, 400);
    }

    $q = "SELECT m.id, m.nama, m.nim,
                 j.nm_jurusan AS jurusan
          FROM mahasiswa m
          LEFT JOIN jurusan j ON m.jurusan_id = j.id
          WHERE m.id = ?";

    $stmt = mysqli_prepare($koneksi, $q);

    if (!$stmt) {
        sendResponse(false, "Query gagal", null, 500);
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $r = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($r);

    if (!$data) {
        sendResponse(false, "Data tidak ditemukan", null, 404);
    }

    sendResponse(true, "Berhasil", $data, 200);
}

// GET pencarian
if (isset($_GET['search'])) {

    $search = "%" . $_GET['search'] . "%";

    $q = "SELECT m.id, m.nama, m.nim,
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
    $r = mysqli_stmt_get_result($stmt);

    $data = [];

    while ($row = mysqli_fetch_assoc($r)) {
        $data[] = $row;
    }

    sendResponse(true, "Berhasil", $data, 200);
}

// GET pagination
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
    }

    $offset = ($page - 1) * $limit;

    $q = "SELECT m.id, m.nama, m.nim,
                 j.nm_jurusan AS jurusan
          FROM mahasiswa m
          LEFT JOIN jurusan j ON m.jurusan_id = j.id
          ORDER BY m.id DESC
          LIMIT ? OFFSET ?";

    $stmt = mysqli_prepare($koneksi, $q);

    if (!$stmt) {
        sendResponse(false, "Query gagal", null, 500);
    }

    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
    mysqli_stmt_execute($stmt);

    $r = mysqli_stmt_get_result($stmt);
    $data = [];

    while ($row = mysqli_fetch_assoc($r)) {
        $data[] = $row;
    }

    sendResponse(true, "Berhasil", $data, 200);
}

// GET semua data
$q = "SELECT m.id, m.nama, m.nim,
             j.nm_jurusan AS jurusan
      FROM mahasiswa m
      LEFT JOIN jurusan j ON m.jurusan_id = j.id
      ORDER BY m.id DESC";

$r = mysqli_query($koneksi, $q);

if (!$r) {
    sendResponse(
        false,
        "Query gagal: " . mysqli_error($koneksi),
        null,
        500
    );
}

$data = [];

while ($row = mysqli_fetch_assoc($r)) {
    $data[] = $row;
}

sendResponse(true, "Berhasil", $data, 200);