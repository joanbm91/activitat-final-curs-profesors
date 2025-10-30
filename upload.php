<?php
// upload.php
require_once 'db.php';

$config_file = __DIR__ . '/config.json';
if (!file_exists($config_file)) {
    die("No configurat. Accedeix a config.php");
}
$config = json_decode(file_get_contents($config_file), true);
$bucket = $config['s3_bucket'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    die("No file uploaded.");
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    die("Upload error code: " . $file['error']);
}

$orig_name = basename($file['name']);
$ext = pathinfo($orig_name, PATHINFO_EXTENSION);
$allowed = ['jpg','jpeg','png','gif'];
if (!in_array(strtolower($ext), $allowed)) {
    die("Format no permès.");
}

// Generar nom únic (per evitar col·lisions)
$unique = time() . '-' . preg_replace('/[^A-Za-z0-9\-_\.]/', '_', $orig_name);
$tmp_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $unique;

if (!move_uploaded_file($file['tmp_name'], $tmp_path)) {
    die("No s'ha pogut moure el fitxer.");
}

// Pujar a S3 amb aws cli (requereix aws cli instal·lat i rol d'instància)
$cmd = 'aws s3 cp ' . escapeshellarg($tmp_path) . ' ' . escapeshellarg("s3://{$bucket}/{$unique}") . ' --only-show-errors --acl public-read';
exec($cmd, $output, $ret);
if ($ret !== 0) {
    unlink($tmp_path);
    die("Error pujant a S3. Comanda: $cmd");
}

// Inserir a la base de dades
$conn = get_db_conn();
if (!$conn) {
    unlink($tmp_path);
    die("Error DB connect");
}

$fn_esc = mysqli_real_escape_string($conn, $unique);
$sql = "INSERT INTO uploads (filename) VALUES ('{$fn_esc}')";
mysqli_query($conn, $sql);

mysqli_close($conn);
unlink($tmp_path);

header("Location: index.php");
exit;
?>