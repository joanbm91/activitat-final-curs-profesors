<?php
// config.php
$msg = '';
$config = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $config = [
        'db_host' => trim($_POST['db_host']),
        'db_user' => trim($_POST['db_user']),
        'db_pass' => trim($_POST['db_pass']),
        'db_name' => trim($_POST['db_name']),
        's3_bucket' => trim($_POST['s3_bucket']),
        's3_url' => rtrim(trim($_POST['s3_url']), '/') . '/'
    ];

    // Intentar connectar al servidor MySQL (sense seleccionar BD)
    $conn = @mysqli_connect($config['db_host'], $config['db_user'], $config['db_pass']);
    if ($conn) {
        // Crear base de dades si no existeix
        $db = mysqli_real_escape_string($conn, $config['db_name']);
        mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `{$db}`");
        mysqli_select_db($conn, $db);

        // Crear taula uploads si no existeix
        $create = "CREATE TABLE IF NOT EXISTS uploads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        mysqli_query($conn, $create);

        // Desa config.json
        file_put_contents(__DIR__ . '/config.json', json_encode($config, JSON_PRETTY_PRINT));
        $msg = "<p style='color:green;'>✅ Connexió correcta, base de dades i taula creades (si cal). Configuració desada.</p>";
        mysqli_close($conn);
    } else {
        $msg = "<p style='color:red;'>❌ Error connectant: " . htmlspecialchars(mysqli_connect_error()) . "</p>";
    }
} else {
    // si ja existeix config.json, carregar-lo per mostrar al formulari
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
    Endpoint RDS: <input name="db_host" value="<?= isset($config['db_host'])?htmlspecialchars($config['db_host']):'' ?>" required><br>
    Usuari DB: <input name="db_user" value="<?= isset($config['db_user'])?htmlspecialchars($config['db_user']):'' ?>" required><br>
    Contrasenya DB: <input name="db_pass" type="password" value="<?= isset($config['db_pass'])?htmlspecialchars($config['db_pass']):'' ?>" required><br>
    Nom BD (s'es crearà si no existeix): <input name="db_name" value="<?= isset($config['db_name'])?htmlspecialchars($config['db_name']):'' ?>" required><br>
    Bucket S3 (nom): <input name="s3_bucket" value="<?= isset($config['s3_bucket'])?htmlspecialchars($config['s3_bucket']):'' ?>" required><br>
    URL pública / CloudFront (ex: https://dxxxxxx.cloudfront.net/): <input name="s3_url" value="<?= isset($config['s3_url'])?htmlspecialchars($config['s3_url']):'' ?>" required><br>
    <button type="submit">Guardar i provar</button>
  </form>

  <?php if (isset($config) && isset($config['s3_url'])): ?>
    <h3>Imatges d'exemple a S3</h3>
    <?php
      $images = ['foto1.jpg','foto2.jpg','foto3.jpg'];
      foreach ($images as $img) {
          $url = htmlspecialchars($config['s3_url'] . $img);
          echo "<img src='{$url}' width='200' style='margin:8px;border:1px solid #ccc' />";
      }
    ?>
  <?php endif; ?>

  <p><a href="index.php">Anar a la pàgina principal</a></p>
</body>
</html>