<?php
// Evitar warnings deprecated
error_reporting(E_ALL & ~E_DEPRECATED);

require __DIR__ . '/vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

// Configuración
$connectionString = getenv("AZURE_STORAGE_CONNECTION_STRING");
$containerName = "comprimidos"; // Canvia-ho si cal

$blobClient = BlobRestProxy::createBlobService($connectionString);

// Eliminar archivo si se solicita
if (isset($_GET["delete"])) {
    $blobToDelete = $_GET["delete"];
    try {
        $blobClient->deleteBlob($containerName, $blobToDelete);
        echo "<p style='color:green;'>Archivo $blobToDelete eliminado correctamente.</p>";
    } catch (ServiceException $e) {
        echo "<p style='color:red;'>Error al eliminar: " . $e->getMessage() . "</p>";
    }
}

// Subida de archivo ZIP
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["zipfile"])) {
    $uploadedFile = $_FILES["zipfile"];

    $blobName = basename($uploadedFile["name"]);
    $extension = strtolower(pathinfo($blobName, PATHINFO_EXTENSION));

    if ($extension !== "zip") {
        echo "<p style='color:red;'>Solo se permiten archivos con extensión .zip.</p>";
    } else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $uploadedFile["tmp_name"]);
        finfo_close($finfo);

        if (
            $mimeType !== "application/zip" &&
            $mimeType !== "application/x-zip-compressed"
        ) {
            echo "<p style='color:red;'>El archivo no parece ser un ZIP válido (MIME: $mimeType).</p>";
        } else {
            $content = fopen($uploadedFile["tmp_name"], "r");
            try {
                $blobClient->createBlockBlob($containerName, $blobName, $content);
                echo "<p style='color:green;'>Archivo $blobName subido correctamente.</p>";
            } catch (ServiceException $e) {
                echo "<p style='color:red;'>Error al subir: " . $e->getMessage() . "</p>";
            }
        }
    }
}

// Listar archivos
try {
    $listOptions = new ListBlobsOptions();
    $blobList = $blobClient->listBlobs($containerName, $listOptions);
    $blobs = $blobList->getBlobs();
} catch (ServiceException $e) {
    die("Error al listar archivos: " . $e->getMessage());
}

// Extraer credenciales per al SAS
preg_match("/AccountName=([^;]+)/", $connectionString, $matches1);
preg_match("/AccountKey=([^;]+)/", $connectionString, $matches2);

$accountName = $matches1[1] ?? null;
$accountKey = $matches2[1] ?? null;

function generateBlobSasUrl($accountName, $accountKey, $containerName, $blobName) {
    $expiry = gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 hour'));

    $stringToSign = "r\n" .
                    "\n" .
                    "$expiry\n" .
                    "/blob/$accountName/$containerName/$blobName\n" .
                    "\n" .
                    "2020-02-10\n" .
                    "\n\n\n\n\n\n";

    $decodedKey = base64_decode($accountKey);
    $signature = base64_encode(hash_hmac('sha256', $stringToSign, $decodedKey, true));

    $sas = http_build_query([
        'sv'  => '2020-02-10',
        'sr'  => 'b',
        'sig' => $signature,
        'se'  => $expiry,
        'sp'  => 'r'
    ]);

    return "https://$accountName.blob.core.windows.net/$containerName/$blobName?$sas";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gestor de archivos ZIP en Azure Blob</title>
</head>
<body>
    <h1>Archivos ZIP en el contenedor '<?= htmlspecialchars($containerName) ?>'</h1>
    <ul>
        <?php if (empty($blobs)): ?>
            <li>No hay archivos aún.</li>
        <?php else: ?>
            <?php foreach ($blobs as $blob): ?>
                <?php
                    $url = generateBlobSasUrl($accountName, $accountKey, $containerName, $blob->getName());
                ?>
                <li>
                    <a href="<?= htmlspecialchars($url) ?>" target="_blank">
                        <?= htmlspecialchars($blob->getName()) ?>
                    </a>
                    [<a href="?delete=<?= urlencode($blob->getName()) ?>" onclick="return confirm('¿Eliminar este archivo?')">Eliminar</a>]
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>

    <h2>Subir nuevo archivo ZIP</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="zipfile" accept=".zip" required>
        <button type="submit">Subir</button>
    </form>
</body>
</html>
