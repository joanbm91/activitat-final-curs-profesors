<?php
// config.php
$msg = '';
$config = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $config = [
        'db_host'    => trim($_POST['db_host']),
        'db_user'    => trim($_POST['db_user']),
        'db_pass'    => trim($_POST['db_pass']),
        'db_name'    => trim($_POST['db_name']),
        's3_bucket'  => trim($_POST['s3_bucket']),
        's3_region'  => trim($_POST['s3_region'])
    ];

    // Intentar connectar al servidor MySQL (sense seleccionar BD)
    $conn = @mysqli_connect($config['db_host'], $config['db_user'], $config['db_pass']);
    if ($conn) {
        $db = mysqli_real_escape_string($conn, $config['db_name']);
        mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `{$db}`");
        mysqli_select_db($conn, $db);

        $create = "CREATE TABLE IF NOT EXISTS uploads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            url VARCHAR(500) NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        mysqli_query($conn, $create);
        
        // Añadir columna url si no existe (compatible con todas las versiones de MySQL)
        $checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM uploads LIKE 'url'");
        if (mysqli_num_rows($checkColumn) == 0) {
            mysqli_query($conn, "ALTER TABLE uploads ADD COLUMN url VARCHAR(500) NOT NULL AFTER filename");
        }

        file_put_contents(__DIR__ . '/config.json', json_encode($config, JSON_PRETTY_PRINT));
        $msg = "<p style='color:green;'>✅ Connexió correcta, base de dades i taula creades (si cal). Configuració desada.</p>";
        mysqli_close($conn);
    } else {
        $msg = "<p style='color:red;'>❌ Error connectant: " . htmlspecialchars(mysqli_connect_error() ?? 'Error desconegut') . "</p>";
    }
} else {
    if (file_exists(__DIR__ . '/config.json')) {
        $config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8"/>
  <title>Configuració Mini App</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <h2>Configuració inicial - Mini PHP App</h2>
  <?php if ($msg) echo $msg; ?>
  <form method="POST">
    Endpoint RDS: <input name="db_host" value="<?= htmlspecialchars($config['db_host'] ?? '') ?>" required><br>
    Usuari DB: <input name="db_user" value="<?= htmlspecialchars($config['db_user'] ?? '') ?>" required><br>
    Contrasenya DB: <input name="db_pass" type="password" value="<?= htmlspecialchars($config['db_pass'] ?? '') ?>" required><br>
    Nom BD (s'es crearà si no existeix): <input name="db_name" value="<?= htmlspecialchars($config['db_name'] ?? '') ?>" required><br>
    Bucket S3 (nom): <input name="s3_bucket" value="<?= htmlspecialchars($config['s3_bucket'] ?? '') ?>" required><br>
    Regió S3 (ex: eu-south-2): <input name="s3_region" value="<?= htmlspecialchars($config['s3_region'] ?? 'eu-south-2') ?>" required><br>
    <button type="submit">Guardar i provar</button>
  </form>



  <p><a href="index.php">Anar a la pàgina principal</a></p>
</body>
</html>