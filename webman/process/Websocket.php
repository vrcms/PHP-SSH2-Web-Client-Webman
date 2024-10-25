<?php
/**
 * Created by web ssh client.
 * User: Jason.lu
 * Date: 2024/10/22
 * Time: 13:59
 */

namespace process;

use ErrorException;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;


/***
 * 开启一个websocket
 * Class Export
 * @package process
 */
class Websocket
{


    const COLS = 80;
    const ROWS = 24;

    private $cols = [];
    private $rows = [];


    /**
     * @var false|resource
     */
    private $connection = [];

    private $shell=[];

    private $conectado = [];

    private  $timer_id = [];


    public function onConnect(TcpConnection $connection){
        // 开启websocket
        echo "New WebSocket Connection\n";
    }

    public function onWebSocketConnect(TcpConnection $connection, $http_buffer){
        //$http_buffer中有连接信息
        echo "客户端连接 onWebSocketConnect\n";
        echo "connection->id :". $connection->id. "\n";

        $this->shell[$connection->id] = null;
        $this->conectado[$connection->id] = false;
        if(isset($this->timer_id[$connection->id])){
            Timer::del($this->timer_id[$connection->id]);
            $this->timer_id[$connection->id] = null;
        }
        $this->connection[$connection->id] = null;
        $this->cols[$connection->id] = self::COLS;
        $this->rows[$connection->id] = self::ROWS;




    }

    public function onMessage(TcpConnection $connection, $data)
    {

        //先删除后添加定时器
        if(isset($this->timer_id[$connection->id])){
            Timer::del($this->timer_id[$connection->id]);
        }

        // 开启定时器，定时发送数据-就是不断发送shell返回的数据
        $this->timer_id[$connection->id] = Timer::add(0.1, function() use($connection){

            if(is_resource($this->shell[$connection->id]) && isset($this->conectado[$connection->id]) && $this->conectado[$connection->id]){
                //echo "循环发送数据到客户端\n";
                while($line = fgets($this->shell[$connection->id])) {
                    $connection->send(mb_convert_encoding($line, "UTF-8"));
                }
            }


        });


        //echo $connection->id.".onMessage [$data] [$connection->id] \n";


        $arr_data = json_decode($data, true);

        $stringdata = trim($data);

        if($stringdata == 'close connection'){
            echo "客户端主动关闭连接\n";
            //关闭连接
            $connection->close();
            return;
        }


        if(is_array($arr_data) && !empty($arr_data)){


            //登录处理
            if(isset($arr_data['server']) && isset($arr_data['port']) && isset($arr_data['user']) && isset($arr_data['password'])){

                //关闭之前的连接
                $this->close_resource($connection);


                $sendstring = mb_convert_encoding("Connecting to ".$arr_data['server']."....\r\n", "UTF-8");
                $connection->send($sendstring);

                ini_set('default_socket_timeout', 3);

                //连接ssh服务器
                try {
                    $this->connection[$connection->id] = ssh2_connect($arr_data['server'], $arr_data['port']);

                } catch (ErrorException $exception) {
                    //连接失败
                    $connection->send(mb_convert_encoding('连接失败，请检查服务器地址或端口', "UTF-8"). "\r\n");
                    return;
                }




                $auth = false;

                try {
                $auth = ssh2_auth_password(
                    $this->connection[$connection->id],
                    $arr_data['user'],
                    $arr_data['password']
                );
                } catch (ErrorException $exception) {
                    //认证失败
                    $connection->send(mb_convert_encoding('认证失败，请检查用户名或密码', "UTF-8"). "\r\n");
                    return;
                }

                if($auth){

                    $connection->send(mb_convert_encoding("认证成功.... \r\n", "UTF-8"));
                    //认证成功
                    $this->shell[$connection->id] = ssh2_shell($this->connection[$connection->id], 'xterm', null, $this->cols[$connection->id], $this->rows[$connection->id], SSH2_TERM_UNIT_CHARS);
                    sleep(1);
                    $this->conectado[$connection->id] = true;




                    return;
                }else{
                    //认证失败
                    $connection->send(mb_convert_encoding('认证失败，请检查用户名或密码', "UTF-8"). "\r\n");
                    return;
                }
            }


            if(isset($arr_data['resize'])){
                $this->cols[$connection->id] = $arr_data['cols'];
                $this->rows[$connection->id] = $arr_data['rows'];

                echo "调整窗口大小\n";
                //print_r($arr_data);

                //调整窗口大小
                //$connection->send(mb_convert_encoding($resize_data, "UTF-8"));
                if($this->conectado[$connection->id] && is_resource($this->shell[$connection->id])){
                    $this->shell[$connection->id] = ssh2_shell($this->connection[$connection->id], 'xterm', null, $this->cols[$connection->id], $this->rows[$connection->id], SSH2_TERM_UNIT_CHARS);
                }

                return;
            }





        }else{
            if($this->conectado[$connection->id]){
                //echo "发送数据到ssh服务器\n";
                //发送数据到ssh服务器
                fwrite($this->shell[$connection->id], $data);
            }

        }







    }

    public function onClose(TcpConnection $connection)
    {
        echo "onClose 关闭事件 $connection->id\n";

        //关闭资源
        $this->close_resource($connection);


        // 关闭websocket
        $connection->close();


    }

    private function close_resource($connection)
    {
        // 关闭定时器
        Timer::del($this->timer_id[$connection->id]);

        if(isset($this->shell[$connection->id]) && is_resource($this->shell[$connection->id])){
            fclose($this->shell[$connection->id]);

            $this->shell[$connection->id] = null;
            $this->conectado[$connection->id] = false;
        }
    }


}