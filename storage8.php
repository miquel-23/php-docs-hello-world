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

// Extreure AccountName i AccountKey
preg_match("/AccountName=([^;]+)/", $connectionString, $m1);
preg_match("/AccountKey=([^;]+)/", $connectionString, $m2);
$accountName = $m1[1];
$accountKey = $m2[1];

// Crear helper SAS
$sasHelper = new SharedAccessSignatureHelper($accountName, $accountKey);

// Listar blobs
try {
    $listOptions = new ListBlobsOptions();
    $blobList = $blobClient->listBlobs($containerName, $listOptions);
    $blobs = $blobList->getBlobs();
} catch (ServiceException $e) {
    die("Error al listar blobs: " . $e->getMessage());
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
        <?php foreach ($blobs as $blob): 
            $blobName = $blob->getName();

            // Crear URL SAS
            $expiry = gmdate('Y-m-d\TH:i:s\Z', strtotime('+10 minutes'));
            $resourcePath = $containerName . '/' . $blobName;

            $sasToken = $sasHelper->generateBlobServiceSharedAccessSignatureToken(
                'b',               // 'b' = blob
                $resourcePath,    // container/blob
                'r',              // permisos de lectura
                $expiry
            );

            $sasUrl = sprintf(
                "https://%s.blob.core.windows.net/%s/%s?%s",
                $accountName,
                $containerName,
                rawurlencode($blobName),
                $sasToken
            );
        ?>
            <li>
                <a href="<?= htmlspecialchars($sasUrl) ?>" target="_blank">
                    <?= htmlspecialchars($blobName) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</body>
</html>
