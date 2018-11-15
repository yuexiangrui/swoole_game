<?php
namespace  Swoole;

class Swoole_game {

    public  $iport = 9505;
    public  $ip = '0.0.0.0';
    public  $serv = '';
    public  $pid =  '';
    public  $process_name = 'test';
    public  $config = [];
    public function __construct($ip='127.0.0.1',$iport=9505,$process_name = 'swoole_game')
    {
        $this->ip = $ip;
        $this->iport = $iport;
        $this->process_name = $process_name;
        $this->setConfig();
    }

    public function setConfig($config = []){
        $swoole_config = array(
            'reactor_num' => $config['reactor_num'] ?? 2,//通过此参数来调节poll线程的数量，以充分利用多核
            'worker_num' => $config['worker_num'] ?? 8,// 开启进程
//            'daemonize' => $config['daemonize'] ?? 0,//是否开启守护进程 0否 1 是
            'max_request' => $config['max_request'] ?? 10000,//最大请求处理1万个 //此参数表示worker进程在处理完n次请求后结束运行。manager会重新创建一个worker进程。此选项用来防止worker进程内存溢出。
            // "task_ipc_mode " => 3 ,  //使用消息队列通信，并设置为争抢模式
            'backlog' => $config['backlog'] ?? 128,//Listen队列长度
            'pid_file'=> $config['pid_file'] ?? __DIR__.'/server.pid',//进程唯一标识ID
            'log_level' => $config['log_level'] ?? 1,
//            'tcp_defer_accept' => $config['reactor_num'] ?? 5,//此参数设定一个秒数，当客户端连接连接到服务器时，在约定秒数内并不会触发accept，直到有数据发送，或者超时时才会触发。
//            'heartbeat_check_interval' => $config['reactor_num'] ?? 5,//心跳检测 遍历所有连接，如果该连接在60秒内，没有向服务器发送任何数据，此连接将被强制关闭。
//            'heartbeat_idle_time' => $config['reactor_num'] ?? 10,//一个TCP连接如果在10秒内未向服务器端发送数据，将会被切断。
        );
//        if(isset($config['task_worker_num']) && $config['task_worker_num']){
        $swoole_config['dispatch_mode'] =  $config['dispatch_mode'] ?? 2;
        $swoole_config['task_worker_num'] =  $config['task_worker_num'] ?? 8;  //task进程的数量
//        }

        if(isset($config['tcp_defer_accept']) && $config['tcp_defer_accept']){
            $swoole_config['tcp_defer_accept'] =  $config['tcp_defer_accept']; //此参数设定一个秒数，当客户端连接连接到服务器时，在约定秒数内并不会触发accept，直到有数据发送，或者超时时才会触发。
        }

        if(isset($config['heartbeat_check_interval']) && $config['heartbeat_check_interval']){
            $swoole_config['heartbeat_check_interval'] =  $config['heartbeat_check_interval']; //心跳检测 遍历所有连接，如果该连接在60秒内，没有向服务器发送任何数据，此连接将被强制关闭。
        }

        if(isset($config['heartbeat_idle_time']) && $config['heartbeat_idle_time']){
            $swoole_config['heartbeat_idle_time'] =  $config['heartbeat_idle_time']; //一个TCP连接如果在10秒内未向服务器端发送数据，将会被切断。
        }

        $this->config = $swoole_config;
    }

    public function run(){
        if(empty($this->pid)){
            $this->pid = __DIR__.'/server.pid';
        }
        global $argv;
        if (empty($argv[1]) or isset($opt['help']))
        {
            echo "php {$argv[0]}  {start|stop|reload}\n\r" ;
            echo "php {$argv[0]}  start -d 守护进程\n\r";
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
            if(isset($argv[2]) && $argv[2] == '-d'){
               $this->config['daemonize'] = 1;
            }
            $this->start();
        }else{
            echo "php {$argv[0]} start|stop|reload";
        }
    }

    /**
     * 开启 worker
     */
    public function start(){
        $this->serv = new server($this->ip, $this->iport, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);

        $this->serv->set($this->config);
        $this->pid = __DIR__.'/server.pid';
        $flag = 0;
        $os_name=PHP_OS;
        if(strpos($os_name,"Linux")!==false){
            $flag = 1;
        }
        if($flag > 0){
            swoole_set_process_name($this->process_name); //这个设置进程数的 名称 不支持MAC
        }
        $this->serv->on('Receive', array($this, 'onReceive'));
        $this->serv->on('Task', array($this, 'onTask'));
        $this->serv->on('Finish', array($this, 'onFinish'));
        $this->serv->on('Close',array($this,'onClose'));
        $this->serv->on('WorkerStart', array($this,'workStart'));
        $this->serv->start();

    }

    /**
     * workstart
     */
    public function workStart($serv,$worker_id){
        if($worker_id >= $serv->setting['worker_num']) {
            swoole_set_process_name("$this->process_name tasker");
        } else {
            swoole_set_process_name("$this->process_name worker");
        }
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
            echo '请设置PID存放位置';
        }
        $this->pid = $dir;
    }

    /**
     * onReceive 时间
     */
    public function onReceive($serv, $fd, $from_id, $data){
        $data = json_decode($data,true);
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
