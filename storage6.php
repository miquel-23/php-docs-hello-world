<?php
require __DIR__ . '/vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Common\SharedAccessSignatureHelper;
use MicrosoftAzure\Storage\Blob\Models\SharedAccessBlobPermissions;

// Configuració
$connectionString = getenv("AZURE_STORAGE_CONNECTION_STRING");
$containerName = "comprimidos";

$blobClient = BlobRestProxy::createBlobService($connectionString);

// Extreure accountName i accountKey de la cadena de connexió
preg_match('/AccountName=([^;]+)/', $connectionString, $matchName);
preg_match('/AccountKey=([^;]+)/', $connectionString, $matchKey);
$accountName = $matchName[1] ?? null;
$accountKey = $matchKey[1] ?? null;

if (!$accountName || !$accountKey) {
    die("No se pudo obtener AccountName o AccountKey de la cadena de conexión.");
}

$sasHelper = new SharedAccessSignatureHelper($accountName, $accountKey);

// Eliminar fitxer
if (isset($_GET['delete'])) {
    $blobToDelete = $_GET['delete'];
    try {
        $blobClient->deleteBlob($containerName, $blobToDelete);
        echo "<p style='color:green;'>Archivo $blobToDelete eliminado correctamente.</p>";
    } catch (ServiceException $e) {
        echo "<p style='color:red;'>Error al eliminar: " . $e->getMessage() . "</p>";
    }
}

// Pujar fitxer ZIP
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["zipfile"])) {
    $uploadedFile = $_FILES["zipfile"];
    $blobName = basename($uploadedFile["name"]);
    $extension = strtolower(pathinfo($blobName, PATHINFO_EXTENSION));

    if ($extension !== "zip") {
        echo "<p style='color:red;'>Solo se permiten archivos ZIP.</p>";
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

// Llistar fitxers
try {
    $listOptions = new ListBlobsOptions();
    $blobList = $blobClient->listBlobs($containerName, $listOptions);
    $blobs = $blobList->getBlobs();
} catch (ServiceException $e) {
    die("Error al listar archivos: " . $e->getMessage());
}

function generarSasUrl($accountName, $accountKey, $containerName, $blobName, $sasHelper) {
    $start = (new DateTime('now -5 minutes'))->format('Y-m-d\TH:i:s\Z');
    $expiry = (new DateTime('+1 hour'))->format('Y-m-d\TH:i:s\Z');
    $permissions = SharedAccessBlobPermissions::READ;
    $resource = 'b'; // blob

    $sasToken = $sasHelper->generateBlobServiceSharedAccessSignature(
        $permissions,
        $resource,
        $containerName . '/' . $blobName,
        $start,
        $expiry
    );

    return "https://{$accountName}.blob.core.windows.net/{$containerName}/{$blobName}?{$sasToken}";
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
        <?php foreach ($blobs as $blob): ?>
            <li>
                <?php
                $url = generarSasUrl($accountName, $accountKey, $containerName, $blob->getName(), $sasHelper);
                ?>
                <a href="<?= htmlspecialchars($url) ?>" target="_blank">
                    <?= htmlspecialchars($blob->getName()) ?>
                </a>
                [<a href="?delete=<?= urlencode($blob->getName()) ?>" onclick="return confirm('¿Eliminar este archivo?')">Eliminar</a>]
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
