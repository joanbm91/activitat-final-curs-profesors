<?php
// --- Configuració d'errors (per evitar 500 silenciosos)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Ruta al fitxer de log
$logFile = '/var/log/app_s3_upload.log';

// --- Funció per escriure missatges de log
function logMsg($msg) {
    global $logFile;
    $line = "[" . date("Y-m-d H:i:s") . "] " . $msg . "\n";
    file_put_contents($logFile, $line, FILE_APPEND);
}

// --- Crea el fitxer de log si no existeix
if (!file_exists($logFile)) {
    @touch($logFile);
    @chmod($logFile, 0666);
}

// --- Requereix dependències
try {
    require 'vendor/autoload.php';   // SDK AWS
    require 'config.php';            // Config de la base de dades
    require 'db.php';                // Funcions de base de dades
} catch (Throwable $e) {
    logMsg("❌ Error carregant fitxers PHP: " . $e->getMessage());
    die("Error carregant dependències: " . htmlspecialchars($e->getMessage()));
}

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// --- Comprovem si s’ha rebut un fitxer
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    echo "<h3>No s'ha rebut cap fitxer.</h3>";
    exit;
}

$file = $_FILES['file'];
$filePath = $file['tmp_name'];
$fileName = basename($file['name']);
$bucket = 'test-123456'; // 👈 Canvia-ho o fes-ho configurable
$region = 'eu-west-1';

logMsg("Rebut fitxer '$fileName' per pujar a S3 ($bucket).");

// --- Comprovem que AWS CLI / SDK funciona
try {
    $identity = shell_exec('aws sts get-caller-identity 2>&1');
    logMsg("Identitat IAM detectada: " . trim($identity));
} catch (Throwable $e) {
    logMsg("⚠️ No s'ha pogut obtenir identitat IAM: " . $e->getMessage());
}

// --- Pujada a S3
try {
    $s3 = new S3Client([
        'version' => 'latest',
        'region'  => $region
    ]);

    logMsg("Intentant pujar fitxer a s3://$bucket/$fileName");

    $result = $s3->putObject([
        'Bucket' => $bucket,
        'Key'    => $fileName,
        'SourceFile' => $filePath,
        'ACL'    => 'public-read'
    ]);

    $url = $result['ObjectURL'];
    logMsg("✅ Pujada correcta a S3: $url");

} catch (AwsException $e) {
    logMsg("❌ Error AWS: " . $e->getAwsErrorMessage());
    logMsg("Detall complet: " . $e->getMessage());
    echo "<h3>Error pujant fitxer a S3:</h3><pre>" . htmlspecialchars($e->getAwsErrorMessage()) . "</pre>";
    exit;
} catch (Throwable $e) {
    logMsg("❌ Error general S3: " . $e->getMessage());
    echo "<h3>Error general pujant fitxer:</h3><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}

// --- Guarda a la base de dades
try {
    if (saveFileToDB($fileName, $url)) {
        logMsg("📦 Fitxer registrat a la base de dades correctament.");
        echo "<h3>✅ Fitxer pujat correctament i registrat a la base de dades.</h3>";
        echo "<p><a href='$url' target='_blank'>Veure fitxer a S3</a></p>";
    } else {
        logMsg("⚠️ No s'ha pogut guardar el registre a la BD.");
        echo "<h3>⚠️ Fitxer pujat, però no s'ha pogut registrar a la base de dades.</h3>";
    }
} catch (Throwable $e) {
    logMsg("❌ Error inserint a la BD: " . $e->getMessage());
    echo "<h3>Error afegint a la base de dades:</h3><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}

echo "<hr><pre>Consulta el log a $logFile</pre>";
?>