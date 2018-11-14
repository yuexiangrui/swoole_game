<?php

spl_autoload_register(function ($class) { // class = os\Linux

    /* 限定类名路径映射 */
    $class_map = array(
        // 限定类名 => 文件路径
        'swoole\\swoole_game' => __DIR__ .'/src/server.php',
    );

    /* 根据类名确定文件名 */
    $file = $class_map[$class];
    /* 引入相关文件 */
    if (file_exists($file)) {
        include $file;
    }
});

$swoole_game = new \swoole\swoole_game();
$data = [
    'class'=>'swoole\controllers\test',
    'fun'=>'test'
];
$swoole_game->run();