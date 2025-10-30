<?php
require 'vendor/autoload.php';
require 'config.php'; // per agafar variables de connexió
require 'db.php';     // per registrar el fitxer a la BD

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

$logFile = '/var/log/app_s3_upload.log';

// funció per escriure logs
function logMsg($msg, $logFile) {
    file_put_contents($logFile, "[".date("Y-m-d H:i:s")."] $msg\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $filePath = $file['tmp_name'];
    $fileName = basename($file['name']);
    $bucket = 'test-123456';  // 👈 el bucket del professor (o que defineixi l’alumne)

    logMsg("Rebut fitxer $fileName per pujar a S3.", $logFile);

    try {
        // client S3 (usa IAM Role de la instància)
        $s3 = new S3Client([
            'version' => 'latest',
            'region'  => 'eu-west-1'
        ]);

        logMsg("Intentant pujar s3://$bucket/$fileName", $logFile);

        $result = $s3->putObject([
            'Bucket' => $bucket,
            'Key'    => $fileName,
            'SourceFile' => $filePath,
            'ACL'    => 'public-read'
        ]);

        $url = $result['ObjectURL'];
        logMsg("✅ Pujada correcta a S3: $url", $logFile);

        // Guarda el nom a la base de dades
        if (saveFileToDB($fileName, $url)) {
            logMsg("Fitxer $fileName desat a la base de dades correctament.", $logFile);
            echo "✅ Fitxer pujat i registrat!";
        } else {
            logMsg("⚠️ No s'ha pogut registrar a la BD.", $logFile);
            echo "Fitxer pujat però no registrat a la BD.";
        }

    } catch (AwsException $e) {
        logMsg("❌ Error AWS: ".$e->getAwsErrorMessage(), $logFile);
        logMsg("Detall: ".$e->getMessage(), $logFile);
        echo "Error pujant fitxer. Revisa el log.";
    } catch (Exception $e) {
        logMsg("❌ Error general: ".$e->getMessage(), $logFile);
        echo "Error inesperat. Revisa el log.";
    }
} else {
    echo "Cap fitxer rebut.";
}
?>