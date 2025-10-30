<?php
// index.php
require_once 'db.php';

$config = null;
if (file_exists(__DIR__ . '/config.json')) {
    $config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
}

if (!$config) {
    echo "<h3>No configurat. Vés a <a href='config.php'>config.php</a></h3>";
    exit;
}

$conn = get_db_conn();
if (!$conn) {
    echo "<h3>Error connectant a la base de dades. Revisa <a href='config.php'>config.php</a></h3>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8"/>
  <title>Mini App - Uploads</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <h1>Mini PHP App - Uploads</h1>

  <h3>Pujar fitxer a S3</h3>
  <form action="upload.php" method="post" enctype="multipart/form-data">
    Fitxer: <input type="file" name="file" accept="image/*" required>
    <button type="submit">Pujar</button>
  </form>

  <h3>Fitxers pujats</h3>
  <?php
    $res = mysqli_query($conn, "SELECT id, filename, uploaded_at FROM uploads ORDER BY uploaded_at DESC");
    echo "<ul>";
    while ($row = mysqli_fetch_assoc($res)) {
        $fn = htmlspecialchars($row['filename']);
        $time = htmlspecialchars($row['uploaded_at']);
        $url = htmlspecialchars($config['s3_url'] . $fn);
        echo "<li><a href='{$url}' target='_blank'>{$fn}</a> - {$time}</li>";
    }
    echo "</ul>";
    mysqli_close($conn);
  ?>
  <p><a href="config.php">Reconfigurar</a></p>
</body>
</html>