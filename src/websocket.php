#!/usr/bin/env php
<?php
/**
 * Yii console bootstrap file.
 */

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/common/config/bootstrap.php';
require __DIR__ . '/console/config/bootstrap.php';

$config = yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/common/config/main.php',
    require __DIR__ . '/common/config/main-local.php',
    require __DIR__ . '/swoole/config/main.php',
    require __DIR__ . '/swoole/config/main-local.php'
);

$serv = new swoole_websocket_server("127.0.0.1", 9502);

$serv->set([
    'worker_num' => 4,// 开启进程
    'daemonize' => 1,//是否开启守护进程 0否 1 是
    'max_request' => 10000,//最大请求处理1万个
    'backlog' => 128,
    'pid_file'=>__DIR__.'/web_socket.pid',//进程唯一标识ID
    'log_level' => 1,
//    'heartbeat_check_interval' => 3600,//心跳检测 遍历所有连接，如果该连接在60秒内，没有向服务器发送任何数据，此连接将被强制关闭。
    'heartbeat_idle_time' => 5,//一个TCP连接如果在10秒内未向服务器端发送数据，将会被切断。
]);

$serv->on('open', function ($server, $request) {
    echo "server: handshake success with fd{$request->fd}\n";
});

$serv->on('message', function ($server, $frame) {
    echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
    $start_fd = 0;
    while(true)
    {
        $conn_list = $server->getClientList($start_fd, 10);
        echo $conn_list;
        if ($conn_list===false or count($conn_list) === 0)
        {
            echo "finish\n";
            break;
        }
        $start_fd = end($conn_list);
        foreach($conn_list as $fd)
        {
            $server->push($fd, $frame->data);
        }
    }
});

$serv->on('close', function ($ser, $fd) {
    echo "client {$fd} closed\n";
});

$serv->start();
