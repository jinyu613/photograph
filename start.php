<?php
use Workerman\Worker;
require_once __DIR__ . '/Workerman/Autoloader.php';
// 初始化一个worker容器，监听1234端口
$worker = new Worker('websocket://10.100.115.179:2000');
// ====这里进程数必须必须必须设置为1====
$worker->count = 1;
// 新增加一个属性，用来保存uid到connection的映射(uid是用户id或者客户端唯一标识)
$worker->uidConnections = array();
// 当有客户端发来消息时执行的回调函数
$worker->onConnect = function($connection)
{
   // echo $connection->getRemoteIp();
};
$worker->onMessage = function($connection, $data)
{
    $db = mysqli_connect("localhost","root","root","photography");
    global $worker;

    // 判断当前客户端是否已经验证,即是否设置了uid
    if(!isset($connection->uid))
    {
        $connection->uid = $data;
        $worker->uidConnections[$connection->uid] = $connection;
    }
    $sql = "SELECT message_content,message_id FROM p_message WHERE message_bid=".$connection->uid;
    $desql = "DELETE FROM p_message WHERE message_bid=".$connection->uid;
     $content = $db->query($sql);
     if($content !=array()){
         $db->query($desql);
         foreach ($content as $k=>$v){
             $arr[$k] = $v['message_content']."&^&".$v['message_id'];
         }
         $arr = implode('&*&',$arr);
         sendMessageByUid($connection->uid,strval($arr));
     }
   $data = explode("&&&&&&&",$data);
    if(!empty($data[1])){
        $arr = $connection->uid."#$%_^*^&(*&".$data[1];
        foreach($worker->connections as $connection)
        {
         if($connection->uid==strval($data[0])){
             sendMessageByUid(strval($data[0]), strval($arr));
         }else{
             $sql ="INSERT INTO p_message (message_content,message_id,message_bid) VALUES ('".$data[1]."',$connection->uid,$data[0])";
             $db->query($sql);
         }
        }
    }
};
function object_array($array) {
    if(is_object($array)) {
        $array = (array)$array;
    } if(is_array($array)) {
        foreach($array as $key=>$value) {
            $array[$key] = object_array($value);
        }
    }
    return $array;
}
// 当有客户端连接断开时
$worker->onClose = function($connection)
{
    global $worker;
    if(isset($connection->uid))
    {
        // 连接断开时删除映射
        unset($worker->uidConnections[$connection->uid]);
    }
};


// 向所有验证的用户推送数据
function broadcast($message)
{
    global $worker;
    foreach($worker->uidConnections as $connection)
    {
        $connection->send($message);
    }
}

// 针对uid推送数据
function sendMessageByUid($uid, $message)
{
    global $worker;
    if(isset($worker->uidConnections[$uid]))
    {
        $connection = $worker->uidConnections[$uid];
        $connection->send($message);
    }
}

// 运行所有的worker（其实当前只定义了一个）
Worker::runAll();