<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

$app = \CorporateAid\Core\App::boot($basePath);
$c = $app->container();

$source = 'storage/uploads/candidate_docs/c8fa4ca3-3e76-4a56-813e-8ed1a4629523.webp';
$absolute = $basePath . '/' . $source;

echo "Source: {$absolute}\n";
echo "is_file: " . (is_file($absolute) ? 'YES' : 'NO') . "\n";
echo "size: " . filesize($absolute) . " bytes\n";
echo "mime: " . (mime_content_type($absolute) ?: 'unknown') . "\n";

// Direct GD probe
echo "\n--- Direct GD probe ---\n";
$img = @imagecreatefromwebp($absolute);
if ($img === false) {
    echo "imagecreatefromwebp() FAILED\n";
    $err = error_get_last();
    if ($err !== null) {
        echo "  last error: " . $err['message'] . "\n";
    }
} else {
    echo "imagecreatefromwebp() OK\n";
    echo "  dimensions: " . imagesx($img) . "x" . imagesy($img) . "\n";
    imagedestroy($img);
}

// Probe via MediaProcessor::load()
echo "\n--- MediaProcessor::load() probe ---\n";
$processor = $c->get(\CorporateAid\Commerce\Support\MediaProcessor::class);
$reflection = new \ReflectionClass($processor);
$method = $reflection->getMethod('load');
$method->setAccessible(true);
$result = $method->invoke($processor, $absolute, 'image/webp');
if ($result === false) {
    echo "MediaProcessor::load() returned FALSE\n";
} else {
    echo "MediaProcessor::load() returned an image resource\n";
    echo "  dimensions: " . imagesx($result) . "x" . imagesy($result) . "\n";
    imagedestroy($result);
}

// Probe via generateVariants() directly
echo "\n--- generateVariants() probe ---\n";
$spec = \CorporateAid\Commerce\Support\MediaVariantSet::for('general');
$outDir = $basePath . '/storage/uploads/candidate_docs/variants';
echo "  output dir: {$outDir}\n";
$generated = $processor->generateVariants(
    $absolute,
    'image/webp',
    $spec,
    $outDir,
    'diag-' . time()
);
echo "  generated: " . count($generated) . " variant(s)\n";
foreach ($generated as $v) {
    echo "    {$v->name}.{$v->extension}  ->  " . $v->absolutePath . "\n";
}
