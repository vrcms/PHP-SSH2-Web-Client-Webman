# PHP-SSH2-Web-Client-Webman
This is a project that uses Web to connect to SSH, written in PHP, with the front-end using xterm.js and the back-end created by Webman.

技术原理：后端实现一个websocket服务，前端通过xterm.js启动一个交互界面，后端通过php 的SSH2连接目标服务器，不断转发信息返回给前端的xterm.

## 安装/Install
### 后端服务/Back-end server
#### 后端修改端口
修改webman/config/process.php中的端口号为你想要的端口号。
```
'listen'  => 'websocket://0.0.0.0:2233',
```
请注意需要跟前端index.html中的websocket地址保持一致。

webman目录中后端代码，上传到服务器。

PHP版本要求：8.0+


#### 必须安装SSH2扩展
请查看是否已经安装生效，以下命令如果没有任何显示，表示你没安装ssh2
```
php -m | grep ssh2
```


后端启动调试运行:
```
cd webman
php start.php start
```

后端启动守护运行:
```
cd webman
php start.php start -d
```


### 前端安装/Front-end
修改index.html中的websocket地址为后端websocket地址即可。

```
ws://你的域名:端口号
```
入口文件index.html，以及static目录的静态文件，上传到服务器即可。

