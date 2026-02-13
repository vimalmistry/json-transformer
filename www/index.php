<?php

require_once __DIR__ . "/../vendor/autoload.php";

use O360Main\JsonTransformer\Transformer;

// Configure once (e.g. in bootstrap/init)
Transformer::getInstance()->setPath(__DIR__);

// Use anywhere
$source = json_decode(file_get_contents(__DIR__ . "/source.json"), true);
$result = Transformer::getInstance()->apply("final.tpl", $source);

header("Content-Type: application/json");
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
