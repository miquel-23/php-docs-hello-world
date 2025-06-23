<?php
require __DIR__ . '/vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Common\SharedAccessSignatureHelper;

// Configuració
$connectionString = getenv("AZURE_STORAGE_CONNECTION_STRING");
$containerName = "comprimidos";

// Crear client
$blobClient = BlobRestProxy::createBlobService($connectionString);

// Obtenir nom i clau del compte
preg_match("/AccountName=([^;]+)/", $connectionString, $matchName);
preg_match("/AccountKey=([^;]+)/", $connectionString, $matchKey);
$accountName = $matchName[1] ?? null;
$accountKey = $matchKey[1] ?? null;

// Funció per generar URL SAS temporal
function generarSasUrl($accountName, $accountKey, $containerName, $blobName) {
    $sasHelper = new SharedAccessSignatureHelper($accountName, $accountKey);

    $start = gmdate('Y-m-d\TH:i:s\Z', strtotime('-5 minutes'));
    $expiry = gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 hour'));

    $permissions = 'r'; // lectura
    $resourceType = 'b'; // blob

    $sasToken = $sasHelper->generateBlobServiceSharedAccessSignatureToken(
        $resourceType,
        $containerName . '/' . $blobName,
        $permissions,
        $start,
        $expiry
    );

    return "https://{$accountName}.blob.core.windows.net/{$containerName}/{$blobName}?{$sasToken}";
}

// Esborrar fitxer
if (isset($_GET["delete"])) {
    try {
        $blobClient->deleteBlob($containerName, $_GET["delete"]);
        echo "<p style='color:green;'>Fitxer esborrat correctament.</p>";
    } catch (ServiceException $e) {
        echo "<p style='color:red;'>Error en esborrar: " . $e->getMessage() . "</p>";
    }
}

// Pujar fitxer ZIP
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["zipfile"])) {
    $file = $_FILES["zipfile"];
    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

    if ($ext !== "zip") {
        echo "<p style='color:red;'>Només es permeten fitxers .zip</p>";
    } else {
        $content = fopen($file["tmp_name"], "r");
        try {
            $blobClient->createBlockBlob($containerName, $file["name"], $content);
            echo "<p style='color:green;'>Fitxer pujat correctament.</p>";
        } catch (ServiceException $e) {
            echo "<p style='color:red;'>Error en pujar: " . $e->getMessage() . "</p>";
        }
    }
}

// Llistar fitxers
try {
    $blobList = $blobClient->listBlobs($containerName);
    $blobs = $blobList->getBlobs();
} catch (ServiceException $e) {
    die("Error en llistar: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Gestor ZIP en Azure</title>
</head>
<body>
    <h1>Arxius ZIP en el contenidor '<?= htmlspecialchars($containerName) ?>'</h1>
    <ul>
        <?php foreach ($blobs as $blob): ?>
            <?php $url = generarSasUrl($accountName, $accountKey, $containerName, $blob->getName()); ?>
            <li>
                <a href="<?= htmlspecialchars($url) ?>" target="_blank">
                    <?= htmlspecialchars($blob->getName()) ?>
                </a>
                [<a href="?delete=<?= urlencode($blob->getName()) ?>" onclick="return confirm('Eliminar fitxer?')">Eliminar</a>]
            </li>
        <?php endforeach; ?>
    </ul>

    <h2>Pujar nou fitxer ZIP</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="zipfile" accept=".zip" required>
        <button type="submit">Pujar</button>
    </form>
</body>
</html>
