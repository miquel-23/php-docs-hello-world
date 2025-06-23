<?php
require __DIR__ . '/vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Common\SharedAccessSignatureHelper;

// Configuració
$connectionString = getenv("AZURE_STORAGE_CONNECTION_STRING");
$containerName = "comprimidos";

// Connexió
$blobClient = BlobRestProxy::createBlobService($connectionString);

// Extreure AccountName i AccountKey
preg_match("/AccountName=([^;]+)/", $connectionString, $match1);
preg_match("/AccountKey=([^;]+)/", $connectionString, $match2);
$accountName = $match1[1] ?? null;
$accountKey = $match2[1] ?? null;

if (!$accountName || !$accountKey) {
    die("No s'han pogut extreure AccountName o AccountKey.");
}

$sasHelper = new SharedAccessSignatureHelper($accountName, $accountKey);

// Obtenir blobs
try {
    $listOptions = new ListBlobsOptions();
    $result = $blobClient->listBlobs($containerName, $listOptions);
    $blobs = $result->getBlobs();
} catch (ServiceException $e) {
    die("Error al llistar blobs: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Azure Blob - Descàrrega ZIP</title>
</head>
<body>
    <h1>Archivos ZIP en el contenedor '<?= htmlspecialchars($containerName) ?>'</h1>
    <?php if (empty($blobs)): ?>
        <p>No hi ha cap fitxer.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($blobs as $blob): 
                $blobName = $blob->getName();
                $expiry = gmdate('Y-m-d\TH:i:s\Z', time() + 600); // 10 minuts
                $resourcePath = "$containerName/$blobName";

                // Generar SAS token
                $sasToken = $sasHelper->generateBlobServiceSharedAccessSignatureToken(
                    'b', // Tipus 'blob'
                    $resourcePath,
                    'r', // Lectura
                    $expiry
                );

                $sasUrl = "https://{$accountName}.blob.core.windows.net/{$containerName}/" . rawurlencode($blobName) . "?$sasToken";
            ?>
                <li>
                    <a href="<?= htmlspecialchars($sasUrl) ?>" target="_blank">
                        <?= htmlspecialchars($blobName) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
