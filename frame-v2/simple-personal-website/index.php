<?php

// Load configuration from JSON file
try {
    $configFile = 'config/setting.json';

    // Check if the file exists and is readable
    if (!file_exists($configFile) || !is_readable($configFile)) {
        throw new Exception("Configuration file is missing or unreadable: {$configFile}");
    }

    // Decode the JSON file
    $config = json_decode(file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR);

    // Validate that the config is an array (to avoid unexpected formats)
    if (!is_array($config)) {
        throw new Exception("Configuration file contains invalid JSON.");
    }
} catch (Exception $e) {
    die("Error loading configuration: " . $e->getMessage());
}

$appUrl = filter_var($config['appUrl'], FILTER_SANITIZE_URL);
$targetUrl = filter_var($config['targetUrl'], FILTER_SANITIZE_URL);

// Define the frame data
$frame = $config['frame'];
$frame['imageUrl'] = $appUrl . $frame['imageUrl'];
$frame['button']['action']['url'] = $appUrl;
$frame['button']['action']['splashImageUrl'] = $appUrl . $frame['button']['action']['splashImageUrl'];

// CDN
$cdn = $config['cdn'] ?? '';
$fcsdk = $cdn['farcaster-sdk'] ?? '';

// Metadata function
function generateMetadata($frame, $config, $appUrl) {
    $imageUrl = $frame['imageUrl'];
    return [
        "title" => $config['metadata']['openGraph']['title'],
        "openGraph" => array_merge($config['metadata']['openGraph'], [
            "url" => $appUrl,
            "image" => $imageUrl
        ]),
        "twitter" => array_merge($config['metadata']['twitter'], [
            "image" => $imageUrl
        ]),
        "other" => [
            "fc:frame" => json_encode($frame),
        ],
    ];
}

$defaultMetadata = [
    'title' => 'Default Title',
    'description' => 'Default Description',
];

$metadata = generateMetadata($frame, $config, $appUrl);
$fcTag = htmlspecialchars($metadata['other']['fc:frame'], ENT_QUOTES, 'UTF-8');

echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <title>{$metadata['openGraph']['title']}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f0f0f0;
        }
        iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
    <!-- Open Graph Metadata -->
    <meta property="og:title" content="{$metadata['openGraph']['title']}">
    <meta property="og:description" content="{$metadata['openGraph']['description']}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{$metadata['openGraph']['url']}">
    <meta property="og:image" content="{$metadata['openGraph']['image']}">
    <meta property="og:image:alt" content="{$metadata['openGraph']['imageAlt']}">
    <meta property="og:image:width" content="{$metadata['openGraph']['imageWidth']}">
    <meta property="og:image:height" content="{$metadata['openGraph']['imageHeight']}">
    <meta property="og:image:type" content="{$metadata['openGraph']['imageType']}">

    <!-- Twitter Metadata -->
    <meta name="twitter:image" content="{$metadata['twitter']['image']}">
    <meta name="twitter:image:alt" content="{$metadata['twitter']['imageAlt']}">
    <meta name="twitter:image:width" content="{$metadata['twitter']['imageWidth']}">
    <meta name="twitter:image:height" content="{$metadata['twitter']['imageHeight']}">
    <meta name="twitter:image:type" content="{$metadata['twitter']['imageType']}">

    <!-- Frame Metadata -->
    <meta name="fc:frame" content="{$fcTag}">
    <title>{$metadata['title']}</title>
</head>
<body>
<iframe src="{$targetUrl}" id="main-frame" title="{$metadata['openGraph']['title']}" aria-label="Main content frame"></iframe>
</body>
<script src="{$fcsdk}"></script>
<script>
    // Wait for the iframe to fully load before signaling readiness
    document.getElementById('main-frame').addEventListener('load', () => {
        frame.sdk.actions.ready();
    });
</script>
</html>
HTML;
?>
