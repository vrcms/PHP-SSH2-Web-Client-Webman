# PHP-SSH2-Web-Client-Webman
This is a project that uses Web to connect to SSH, written in PHP, with the front-end using xterm.js and the back-end created by Webman.

技术原理：后端实现一个websocket服务，前端通过xterm.js启动一个交互界面，后端通过php 的SSH2连接目标服务器，不断转发信息返回给前端的xterm.

![](https://raw.githubusercontent.com/vrcms/PHP-SSH2-Web-Client-Webman/master/screen_v1.gif)

## 安装/Install
### 后端服务/Back-end server
#### 后端修改端口
修改webman/config/process.php中的端口号为你想要的端口号。
```
'listen'  => 'websocket://0.0.0.0:2233',
```
请注意需要跟前端index.html中的websocket地址保持一致。

webman目录中后端代码，上传到服务器。

PHP版本要求：7.2+


#### 必须安装SSH2扩展
请查看是否已经安装生效，以下命令如果没有任何显示，表示你没安装ssh2
```
php -m | grep ssh2
```

#### 使用webman需要打开一些函数
具体查看https://www.workerman.net/doc/webman/others/disable-function-check.html
打开禁用函数
```
curl -Ss https://www.workerman.net/webman/fix-disable-functions | php
```


后端启动调试运行:
![](https://raw.githubusercontent.com/vrcms/PHP-SSH2-Web-Client-Webman/master/screen_snap01.png)
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


#### 可能遇到的问题
1. 后端服务启动失败，请检查端口是否被占用，或者是否有其他进程占用了端口。
2. 前端无法连接后端，请检查websocket地址是否正确。
3. 后端服务启动失败：
```
请检查是否安装了SSH2扩展，以及是否禁用了一些函数。
如果使用宝塔，安装ssh2扩展后，需要修改配置文件比如/www/server/php/72/etc/php-cli.ini，注意是要修改php-cli.ini，而不是php.ini。
```

