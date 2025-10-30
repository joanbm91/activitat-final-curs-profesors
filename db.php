<?php
// db.php
// Llegeix config.json i retorna una connexió mysqli
function get_config() {
    $cfg_file = __DIR__ . '/config.json';
    if (!file_exists($cfg_file)) return null;
    $json = file_get_contents($cfg_file);
    return json_decode($json, true);
}

function get_db_conn() {
    $cfg = get_config();
    if (!$cfg) return null;
    $host = $cfg['db_host'];
    $user = $cfg['db_user'];
    $pass = $cfg['db_pass'];
    $db   = $cfg['db_name'];
    $conn = @mysqli_connect($host, $user, $pass, $db);
    return $conn;
}

function saveFileToDB($filename, $url) {
    $conn = get_db_conn();
    if (!$conn) {
        return false;
    }
    
    $filename = mysqli_real_escape_string($conn, $filename);
    $url = mysqli_real_escape_string($conn, $url);
    
    $sql = "INSERT INTO uploads (filename, url) VALUES ('$filename', '$url')";
    $result = mysqli_query($conn, $sql);
    
    mysqli_close($conn);
    return $result;
}
?>