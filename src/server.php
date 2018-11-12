#!/usr/bin/env php
<?php
class swoole_new_server {

    public  $iport = 9505;
    public  $ip = '127.0.0.1';
    public  $serv = '';
    public  $pid =  '';

    public function __construct()
    {

    }

    public function run(){
        global $argv;
        if (empty($argv[1]) or isset($opt['help']))
        {
            echo "php {$argv[0]} start|stop|reload" ;
            exit;
        }else if($argv[1] == 'reload'){
            $server_pid = file_get_contents($this->pid);
            if(!$server_pid){
                exit('server进程不存在');
            }
            posix_kill($server_pid, SIGTERM);
        }else if($argv[1] == 'stop'){
            $this->stop();
        }else if($argv[1] == 'start'){
            $this->start();
        }else{
            echo "php {$argv[0]} start|stop|reload";
        }
    }
    /**
     * 开启 worker
     */
    public function start(){
        $this->serv = new swoole_server($this->ip, $this->iport, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
        $this->serv->set(array(
            'reactor_num' => 2,//通过此参数来调节poll线程的数量，以充分利用多核
            'worker_num' => 4,// 开启进程
            'daemonize' => 1,//是否开启守护进程 0否 1 是
            'max_request' => 10000,//最大请求处理1万个 //此参数表示worker进程在处理完n次请求后结束运行。manager会重新创建一个worker进程。此选项用来防止worker进程内存溢出。
            'dispatch_mode' => 2,
            'task_worker_num' => 8,  //task进程的数量
           // "task_ipc_mode " => 3 ,  //使用消息队列通信，并设置为争抢模式
            'backlog' => 128,//Listen队列长度
            'pid_file'=>__DIR__.'/server.pid',//进程唯一标识ID
            'log_level' => 1,
            'tcp_defer_accept' => 5,//此参数设定一个秒数，当客户端连接连接到服务器时，在约定秒数内并不会触发accept，直到有数据发送，或者超时时才会触发。
            'heartbeat_check_interval' => 5,//心跳检测 遍历所有连接，如果该连接在60秒内，没有向服务器发送任何数据，此连接将被强制关闭。
            'heartbeat_idle_time' => 10,//一个TCP连接如果在10秒内未向服务器端发送数据，将会被切断。
        ));
        $this->pid = __DIR__.'/server.pid';
//        swoole_set_process_name('test'); //这个设置进程数的 名称 不支持MAC
        $this->serv->on('Receive', array($this, 'onReceive'));
        $this->serv->on('Task', array($this, 'onTask'));
        $this->serv->on('Finish', array($this, 'onFinish'));
        $this->serv->on('Close',array($this,'onClose'));
        $this->serv->start();

    }

    /**
     * 关闭 worker 进程
     */
    public function stop(){
        $server_pid = file_get_contents($this->pid);
        if(!$server_pid){
            exit('server进程不存在');
        }
        posix_kill($server_pid, SIGTERM);
    }

    public function setPid($dir){
        if(!$dir){
            echo '请设置PID';
        }
        $this->pid = $dir;
    }

    /**
     * onReceive 时间
     */
    public function onReceive($serv, $fd, $from_id, $data){
        $data = json_decode($data,true);
        print_r($data);
        $fun = [$data['class'],$data['fun']];
        $param = $data['data'];
        $type = $data['type'];
        $result = call_user_func($fun,$param);
        if($type == 'send_all'){
            $this->onStaticClient();
        }else if($type == 'start_time'){
            $this->startime($serv,$fd);
        }else if($type == 'start_task'){
            $serv->task($data);
        }else{
            $fd = $fd ?? '';
            $flag = $this->serv->exist($fd);
            if($flag){
                $serv->send($fd, 'Swoole: '.$result.'---'.$fd);
            }else{
                echo '已断开';
            }

        }
        //$serv->close($fd);     //关闭客户端连接
    }

    /**
     *$serv server
     * task_id 任务ID
     * data  数据  投递数据
     */

    public function onTask($serv,$task_id,$from_id, $data){
       // echo '这是队列争抢模式'.$task_id."\n\r";
        var_dump('a');
        return $data;
    }

    /**
     * 处理完一个进程 调用此类函数
     */

     public function onFinish($serv, $task_id, $data){
         print_r($data);
         echo '我处理完了'."\n\r";
        return true;
     }

    /**
     * 强制断开连接 触发close 函数
     */
    public function onClose(){
        echo '我被关闭了';
    }

    /**
     * 统计有多少客户端在连接
     */
    public function onStaticClient(){
        //统计有多少客户端连接
//        echo 'a';
        $conn_list = $this->serv->getClientList(0, 50);
        print_r($conn_list);
    }

    /**
     * 开启定时器
     */
    public function startime($server,$fd){
        $server->tick(1000, function($id) use ($server, $fd) {
            $flag = $this->serv->exist($fd);
            if($flag){
                $server->send($fd, "hello worlds");
            }else{
                echo '已断开';
                $server->clearTimer($id);
            }
        });
    }

}
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
$pid = __DIR__.'/server.pid';//寻找进程id
$swoole = new swoole_new_server();

$data = [
    'class'=>'swoole\controllers\test',
    'fun'=>'test'
];

$swoole->setPid($pid);

$swoole->run();