<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);


require __DIR__ . '/vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Common\SharedAccessSignatureHelper;
use MicrosoftAzure\Storage\Common\Models\SharedAccessSignatureHelper as ModelsSASHelper;
use MicrosoftAzure\Storage\Common\Models\SharedAccessBlobPermissions;

// Configuració
$accountName = getenv("AZURE_STORAGE_ACCOUNT");
$accountKey = getenv("AZURE_STORAGE_KEY");
$connectionString = getenv("AZURE_STORAGE_CONNECTION_STRING");
$containerName = "comprimidos";

$blobClient = BlobRestProxy::createBlobService($connectionString);

// Eliminar
if (isset($_GET["delete"])) {
    $blobToDelete = $_GET["delete"];
    try {
        $blobClient->deleteBlob($containerName, $blobToDelete);
        echo "<p style='color:green;'>Archivo $blobToDelete eliminado correctamente.</p>";
    } catch (ServiceException $e) {
        echo "<p style='color:red;'>Error al eliminar: " . $e->getMessage() . "</p>";
    }
}

// Subida
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["zipfile"])) {
    $uploadedFile = $_FILES["zipfile"];
    $blobName = basename($uploadedFile["name"]);
    $extension = strtolower(pathinfo($blobName, PATHINFO_EXTENSION));

    if ($extension !== "zip") {
        echo "<p style='color:red;'>Solo se permiten archivos ZIP.</p>";
    } else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $uploadedFile["tmp_name"]);
        finfo_close($finfo);

        if ($mimeType !== "application/zip" && $mimeType !== "application/x-zip-compressed") {
            echo "<p style='color:red;'>Tipo MIME no válido: $mimeType</p>";
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

// Llistar
try {
    $listOptions = new ListBlobsOptions();
    $blobList = $blobClient->listBlobs($containerName, $listOptions);
    $blobs = $blobList->getBlobs();
} catch (ServiceException $e) {
    die("Error al listar archivos: " . $e->getMessage());
}

// SAS Helper
$sasHelper = new SharedAccessSignatureHelper($accountName, $accountKey);

// Funció per generar SAS per a cada blob
function generateBlobSasUrl($accountName, $containerName, $blobName, $sasHelper) {
    $expiry = gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 hour'));

    $sasToken = $sasHelper->generateBlobServiceSharedAccessSignatureToken(
        'b', // tipus de recurs: blob
        "$containerName/$blobName",
        'r', // permís: lectura
        gmdate('Y-m-d\TH:i:s\Z'), // start
        $expiry
    );

    return "https://$accountName.blob.core.windows.net/$containerName/$blobName?$sasToken";
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

<?php
if (!empty($blobs)) {
    $primerBlob = $blobs[0]->getName();
    $sasTest = generateBlobSasUrl($accountName, $containerName, $primerBlob, $sasHelper);
    echo "<p>Prova d'enllaç SAS per al primer blob: <a href='$sasTest' target='_blank'>$primerBlob</a></p>";
}
?>
        
        <?php foreach ($blobs as $blob): ?>
            <?php
                $blobName = $blob->getName();
                $sasUrl = generateBlobSasUrl($accountName, $containerName, $blobName, $sasHelper);
            ?>
            <li>
                <a href="<?= htmlspecialchars($sasUrl) ?>" target="_blank">
                    <?= htmlspecialchars($blobName) ?>
                </a>
                [<a href="?delete=<?= urlencode($blobName) ?>" onclick="return confirm('¿Eliminar este archivo?')">Eliminar</a>]
            </li>
        <?php endforeach; ?>
    </ul>

    <h2>Subir nuevo archivo ZIP</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="zipfile" accept=".zip" required>
        <button type="submit">Subir</button>
    </form>
</body>
</html>
