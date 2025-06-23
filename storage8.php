<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Common\SharedAccessSignatureHelper;

$connectionString = getenv("AZURE_STORAGE_CONNECTION_STRING");
$containerName = "comprimidos";

$blobClient = BlobRestProxy::createBlobService($connectionString);

// 🔐 Obtenir AccountName i AccountKey
preg_match("/AccountName=([^;]+)/", $connectionString, $matchName);
preg_match("/AccountKey=([^;]+)/", $connectionString, $matchKey);
$accountName = $matchName[1] ?? null;
$accountKey = $matchKey[1] ?? null;

$sasHelper = null;
if ($accountName && $accountKey) {
    $sasHelper = new SharedAccessSignatureHelper($accountName, $accountKey);
}

// 🔥 Eliminar fitxer si cal
if (isset($_GET["delete"])) {
    try {
        $blobClient->deleteBlob($containerName, $_GET["delete"]);
        echo "<p style='color:green;'>Fitxer eliminat correctament.</p>";
    } catch (ServiceException $e) {
        echo "<p style='color:red;'>Error eliminant: " . $e->getMessage() . "</p>";
    }
}

// 📤 Pujar ZIP
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["zipfile"])) {
    $uploadedFile = $_FILES["zipfile"];
    $blobName = basename($uploadedFile["name"]);
    $extension = strtolower(pathinfo($blobName, PATHINFO_EXTENSION));

    if ($extension !== "zip") {
        echo "<p style='color:red;'>Només s'admeten fitxers ZIP.</p>";
    } else {
        $content = fopen($uploadedFile["tmp_name"], "r");
        try {
            $blobClient->createBlockBlob($containerName, $blobName, $content);
            echo "<p style='color:green;'>Fitxer $blobName pujat correctament.</p>";
        } catch (ServiceException $e) {
            echo "<p style='color:red;'>Error pujant: " . $e->getMessage() . "</p>";
        }
    }
}

// 📂 Obtenir llistat de fitxers
$blobs = [];
try {
    $result = $blobClient->listBlobs($containerName, new ListBlobsOptions());
    $blobs = $result->getBlobs();
} catch (ServiceException $e) {
    echo "<p style='color:red;'>Error llistant blobs: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gestor de fitxers ZIP en Azure Blob</title>
</head>
<body>
    <h1>Archivos ZIP en el contenedor '<?= htmlspecialchars($containerName) ?>'</h1>

    <ul>
        <?php foreach ($blobs as $blob): ?>
            <?php
                $blobName = $blob->getName();
                $expiry = (new DateTime('+1 hour'))->format('Y-m-d\TH:i:s\Z');
                $start = (new DateTime('-5 minutes'))->format('Y-m-d\TH:i:s\Z');

                $sasToken = $sasHelper->generateBlobServiceSharedAccessSignatureToken(
                    'b',
                    "$containerName/$blobName",
                    'r',
                    $start,
                    $expiry
                );
                $url = $blob->getUrl() . '?' . $sasToken;
            ?>
            <li>
                <a href="<?= htmlspecialchars($url) ?>" target="_blank">
                    <?= htmlspecialchars($blobName) ?>
                </a>
                [<a href="?delete=<?= urlencode($blobName) ?>" onclick="return confirm('Eliminar aquest fitxer?')">Eliminar</a>]
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
