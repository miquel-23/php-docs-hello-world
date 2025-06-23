<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;

$connectionString = getenv("AZURE_STORAGE_CONNECTION_STRING");
$containerName = "comprimidos";

echo "<h2>Test de connexió a Azure Blob Storage</h2>";

if (!$connectionString) {
    die("<p style='color:red;'>ERROR: No s'ha trobat la variable d'entorn AZURE_STORAGE_CONNECTION_STRING</p>");
}

echo "<p>Connexió trobada.</p>";

$blobClient = BlobRestProxy::createBlobService($connectionString);

// Mostrar AccountName i AccountKey
preg_match("/AccountName=([^;]+)/", $connectionString, $matchName);
preg_match("/AccountKey=([^;]+)/", $connectionString, $matchKey);
$accountName = $matchName[1] ?? null;
$accountKey = $matchKey[1] ?? null;

echo "<p><strong>AccountName:</strong> $accountName</p>";
echo "<p><strong>AccountKey (primeres 6 lletres):</strong> " . substr($accountKey, 0, 6) . "...</p>";

try {
    $result = $blobClient->listBlobs($containerName);
    $blobs = $result->getBlobs();
    echo "<p><strong>Blobs trobats:</strong> " . count($blobs) . "</p>";
    echo "<ul>";
    foreach ($blobs as $blob) {
        echo "<li>" . htmlspecialchars($blob->getName()) . "</li>";
    }
    echo "</ul>";
} catch (ServiceException $e) {
    echo "<p style='color:red;'>Error en llistar blobs: " . $e->getMessage() . "</p>";
}
?>
