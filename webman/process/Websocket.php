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


    private $cols = 80;
    private $rows = 24;


    /**
     * @var false|resource
     */
    private $connection;
    /**
     * @var resource
     */
    private $shell;
    /**
     * @var true
     */
    private bool $conectado = false;

    private  $timer_id;


    public function onConnect(TcpConnection $connection){
        // 开启websocket
        echo "New WebSocket Connection\n";
    }

    public function onWebSocketConnect(TcpConnection $connection, $http_buffer){
        echo "客户端连接 onWebSocketConnect $http_buffer\n";




    }

    public function onMessage(TcpConnection $connection, $data)
    {

        if(!isset($this->timer_id)){
            // 开启定时器，定时发送数据-就是不断发送shell返回的数据
            $this->timer_id = Timer::add(0.1, function() use($connection){

                if(is_resource($this->shell) && $this->conectado){
                    //echo "循环发送数据到客户端\n";
                    while($line = fgets($this->shell)) {
                        $connection->send(mb_convert_encoding($line, "UTF-8"));
                    }
                }


            });
        }

        //echo "onMessage [$data] \n";


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
                $this->close_resource();


                $sendstring = mb_convert_encoding("Connecting to ".$arr_data['server']."....\r\n", "UTF-8");
                $connection->send($sendstring);

                ini_set('default_socket_timeout', 3);

                //连接ssh服务器
                try {
                    $this->connection = ssh2_connect($arr_data['server'], $arr_data['port']);

                } catch (ErrorException $exception) {
                    //连接失败
                    $connection->send(mb_convert_encoding('连接失败，请检查服务器地址或端口', "UTF-8"). "\r\n");
                    return;
                }




                $auth = false;

                try {
                $auth = ssh2_auth_password(
                    $this->connection,
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
                    $this->shell = ssh2_shell($this->connection, 'xterm', null, $this->cols, $this->rows, SSH2_TERM_UNIT_CHARS);
                    sleep(1);
                    $this->conectado = true;




                    return;
                }else{
                    //认证失败
                    $connection->send(mb_convert_encoding('认证失败，请检查用户名或密码', "UTF-8"). "\r\n");
                    return;
                }
            }


            if(isset($arr_data['resize'])){
                $this->cols = $arr_data['cols'];
                $this->rows = $arr_data['rows'];

                echo "调整窗口大小\n";
                //print_r($arr_data);

                //调整窗口大小
                //$connection->send(mb_convert_encoding($resize_data, "UTF-8"));
                if($this->conectado && is_resource($this->shell)){
                    $this->shell = ssh2_shell($this->connection, 'xterm', null, $this->cols, $this->rows, SSH2_TERM_UNIT_CHARS);
                }

                return;
            }





        }else{
            if($this->conectado){
                //echo "发送数据到ssh服务器\n";
                //发送数据到ssh服务器
                fwrite($this->shell, $data);
            }

        }







    }

    public function onClose(TcpConnection $connection)
    {
        echo "onClose\n";

        //关闭资源
        $this->close_resource();


        // 关闭websocket
        $connection->close();

        // 关闭定时器
        Timer::delAll();
    }

    private function close_resource()
    {
        if(isset($this->shell) && is_resource($this->shell)){
            fclose($this->shell);

            $this->shell = null;
            $this->conectado = false;
        }
    }


}