<?php
require 'vendor/autoload.php'; // si usas Composer
use App\Helpers\RedisHelper;

$redis = new RedisHelper();
$redis->flush('field_map:*');

echo "✅ Cache limpiado\n";
