<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$logFile = '/var/log/app_s3_upload.log';
function logMsg($msg) {
    global $logFile;
    $line = "[" . date("Y-m-d H:i:s") . "] " . $msg . "\n";
    file_put_contents($logFile, $line, FILE_APPEND);
}

if (!file_exists($logFile)) {
    @touch($logFile);
    @chmod($logFile, 0666);
}

try {
    require 'vendor/autoload.php';
    require 'db.php';
} catch (Throwable $e) {
    logMsg("❌ Error carregant fitxers PHP: " . $e->getMessage());
    die("Error carregant dependències: " . htmlspecialchars($e->getMessage() ?? 'Error desconegut'));
}

$configFile = __DIR__ . '/config.json';
if (!file_exists($configFile)) {
    die("<h3>❌ Error: config.json no existeix! Executa primer config.php.</h3>");
}
$config = json_decode(file_get_contents($configFile), true);

$db_host   = $config['db_host'] ?? '';
$db_user   = $config['db_user'] ?? '';
$db_pass   = $config['db_pass'] ?? '';
$db_name   = $config['db_name'] ?? '';
$s3_bucket = $config['s3_bucket'] ?? '';
$s3_region = $config['s3_region'] ?? '';
$s3_url    = $config['s3_url'] ?? '';

if (!$db_host || !$db_user || !$db_name || !$s3_bucket || !$s3_region) {
    die("<h3>❌ Configuració incompleta! Executa primer config.php.</h3>");
}

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    echo "<h3>No s'ha rebut cap fitxer.</h3>";
    exit;
}

$file = $_FILES['file'];
$filePath = $file['tmp_name'];
$fileName = basename($file['name']);

logMsg("Rebut fitxer '$fileName' per pujar a S3 ($s3_bucket).");

try {
    $identity = shell_exec('aws sts get-caller-identity 2>&1');
    logMsg("Identitat IAM detectada: " . trim($identity));
} catch (Throwable $e) {
    logMsg("⚠️ No s'ha pogut obtenir identitat IAM: " . ($e->getMessage() ?? 'Error desconegut'));
}

try {
    $s3 = new S3Client([
        'version' => 'latest',
        'region'  => $s3_region,
        'endpoint' => "https://s3.{$s3_region}.amazonaws.com"
    ]);

    $result = $s3->putObject([
        'Bucket' => $s3_bucket,
        'Key'    => $fileName,
        'SourceFile' => $filePath,
        'ACL'    => 'public-read'
    ]);

    $url = $result['ObjectURL'] ?? ($s3_url . $fileName);
    logMsg("✅ Pujada correcta a S3: $url");

} catch (AwsException $e) {
    $msg = $e->getAwsErrorMessage() ?? 'Error desconegut';
    logMsg("❌ Error AWS: " . $msg);
    logMsg("Detall complet: " . ($e->getMessage() ?? 'Sense detall'));
    echo "<h3>Error pujant fitxer a S3:</h3><pre>" . htmlspecialchars($msg) . "</pre>";
    exit;
} catch (Throwable $e) {
    $msg = $e->getMessage() ?? 'Error desconegut';
    logMsg("❌ Error general S3: " . $msg);
    echo "<h3>Error general pujant fitxer:</h3><pre>" . htmlspecialchars($msg) . "</pre>";
    exit;
}

try {
    if (saveFileToDB($fileName, $url)) {
        logMsg("📦 Fitxer registrat a la base de dades correctament.");
        echo "<h3>✅ Fitxer pujat correctament i registrat a la base de dades.</h3>";
        echo "<p><a href='" . htmlspecialchars($url) . "' target='_blank'>Veure fitxer a S3</a></p>";
    } else {
        logMsg("⚠️ No s'ha pogut guardar el registre a la BD.");
        echo "<h3>⚠️ Fitxer pujat, però no s'ha pogut registrar a la base de dades.</h3>";
    }
} catch (Throwable $e) {
    $msg = $e->getMessage() ?? 'Error desconegut';
    logMsg("❌ Error inserint a la BD: " . $msg);
    echo "<h3>Error afegint a la base de dades:</h3><pre>" . htmlspecialchars($msg) . "</pre>";
}

echo "<hr><pre>Consulta el log a $logFile</pre>";
?>