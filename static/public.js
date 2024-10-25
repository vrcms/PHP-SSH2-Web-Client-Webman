
// 定义全局变量
var docinfo = {};
// 判断文件是否插入
function is_file_existence(name, type) {
    var arry = type ? docinfo.loadScript : docinfo.loadLink;
    for (var i = 0; i < arry.length; i++) {
        if (arry[i] === name) return false;
    }

    for (var arryKey in arry) {
        var item = arry[arryKey];
    }
    return true;
}

function loadScript(arry, param, callback) {
    var ready = 0;
    if (typeof param === 'function') callback = param;
    for (var i = 0; i < arry.length; i++) {
        if (!Array.isArray(docinfo['loadScript'])) docinfo['loadScript'] = [];
        if (!is_file_existence(arry[i], true)) {
            if (arry.length - 1 === i && callback) callback();
            continue;
        }
        var script = document.createElement('script'),
            _arry_split = arry[i].split('/');
        script.type = 'text/javascript';
        if (typeof callback != 'undefined') {
            if (script.readyState) {
                (function (i) {
                    script.onreadystatechange = function () {
                        if (script.readyState == 'loaded' || script.readyState == 'complete') {
                            script.onreadystatechange = null;
                            docinfo['loadScript'].push(arry[i]);
                            ready++;
                        }
                    };
                })(i);
            } else {
                (function (i) {
                    script.onload = function () {
                        docinfo['loadScript'].push(arry[i]);
                        ready++;
                    };
                })(i);
            }
        }
        script.src = arry[i];
        document.body.appendChild(script);
    }
    var time = setInterval(function () {
        if (ready === arry.length) {
            clearTimeout(time);
            callback();
        }
    }, 10);
}


var Term = {
    bws: null, //websocket对象
    route: '/webssh', //被访问的方法
    term: null,
    term_box: null,
    ssh_info: {},
    last_body: false,
    last_cd: null,
    config: {
        cols: 0,
        rows: 0,
        fontSize: 12,
    },


    //连接websocket
    connect: function () {
        if (!Term.bws || Term.bws.readyState == 3 || Term.bws.readyState == 2) {
            //连接


            Term.bws = new WebSocket(ws_url);
            //绑定事件
            Term.bws.addEventListener('message', Term.on_message);
            Term.bws.addEventListener('close', Term.on_close);
            Term.bws.addEventListener('error', Term.on_error);
            Term.bws.addEventListener('open', Term.on_open);
        }
    },

    //连接服务器成功
    on_open: function (ws_event) {


        if (JSON.stringify(Term.ssh_info) !== '{}') Term.send(JSON.stringify(Term.ssh_info));

        Term.resize();

    },

    //服务器消息事件
    on_message: function (ws_event) {
        //console.log("on_message",ws_event);
        let result = ws_event.data;
        if ((result.indexOf('@127.0.0.1:') != -1 || result.indexOf('@localhost:') != -1) && result.indexOf('Authentication failed') != -1) {
            Term.term.write(result);

            Term.close();
            return;
        }
        if (Term.last_cd) {
            if (result.indexOf(Term.last_cd) != -1 && result.length - Term.last_cd.length < 3) {
                Term.last_cd = null;
                return;
            }
        }
        if (result === '\r服务器连接失败!\r' || result == '\r用户名或密码错误!\r') {
            Term.close();
            return;
        }
        if (result.length > 1 && Term.last_body === false) {
            Term.last_body = true;
        }

        Term.term.write(result);
        if (result == '\r\n登出\r\n' || result == '\r\n注销\r\n' || result == '注销\r\n' || result == '登出\r\n' || result == '\r\nlogout\r\n' || result == 'logout\r\n') {
            setTimeout(function () {
                layer.close(Term.term_box);
                Term.term.dispose();
            }, 500);
            Term.close();
            Term.bws = null;
        }
    },

    //websocket关闭事件
    on_close: function (ws_event) {
        Term.bws = null;
    },

    //websocket错误事件
    on_error: function (ws_event) {

        console.log("on_error",ws_event);

        if (ws_event.target.readyState === 3) {
          if (Term.state === 3) return;
          Term.state = 3;
        } else {
          // console.log(ws_event)
        }


    },

    //关闭连接
    close: function () {
        if (Term.bws) {
            Term.bws.close();
        }
    },

    resize: function () {
        $('#term').height($('.term_box_all .layui-layer-content').height() - 30);
        setTimeout(function () {
            Term.term.FitAddon.fit();
            Term.send(JSON.stringify({ resize: 1, rows: Term.term.rows, cols: Term.term.cols }));
            Term.term.focus();
        }, 400);
    },

    //发送数据
    //@param event 唯一事件名称
    //@param data 发送的数据
    //@param callback 服务器返回结果时回调的函数,运行完后将被回收
    send: function (data, num) {
        //如果没有连接，则尝试连接服务器
        if (!Term.bws || Term.bws.readyState == 3 || Term.bws.readyState == 2) {
            Term.connect();
        }

        //判断当前连接状态,如果!=1，则100ms后尝试重新发送
        if (Term.bws.readyState === 1) {
            Term.bws.send(data);
            //console.log("send success",data);
        } else {
            if (Term.state === 3) return;
            if (!num) num = 0;
            if (num < 5) {
                num++;
                setTimeout(function () {
                    Term.send(data, num++);
                }, 100);
            }
        }
    },
    run: function (ssh_info) {
        var loadT = layer.msg('正在加载终端所需文件，请稍候...', { icon: 16, time: 0, shade: 0.3 });
        loadScript(['static/xterm.js'], function () {
            layer.close(loadT);
            Term.term = new Terminal({
                rendererType: 'canvas',
                cols: 100,
                rows: 34,
                fontSize: 15,
                screenKeys: true,
                useStyle: true,
            });
            Term.term.setOption('cursorBlink', true);
            Term.last_body = false;
            Term.term_box = layer.open({
                type: 1,
                title: 'SSH 终端',
                area: ['930px', '640px'],
                closeBtn: 2,
                shadeClose: false,
                skin: 'term_box_all',
                content:
                    '<div class="term-box" style="background-color:#000" id="term"></div>',
                cancel: function (index, lay) {




                    layer.confirm('关闭SSH会话后，当前命令行会话正在执行的命令可能被中止，确定关闭吗？',
                        {icon: 3, title:'提示'}, function(ix){

                        Term.send('close connection');
                        Term.term.dispose();
                        layer.close(index);

                        //用户点击了确认按钮
                        layer.close(ix);
                        Term.close();


                        //执行相关操作
                    }, function(ix){
                        //用户点击了取消按钮
                        layer.close(ix);
                    });

                    return false;



                },
                success: function () {
                    $('.term_box_all').css('background-color', '#000');
                    Term.term.open(document.getElementById('term'));
                    Term.term.FitAddon = new FitAddon.FitAddon();
                    Term.term.loadAddon(Term.term.FitAddon);
                    Term.term.WebLinksAddon = new WebLinksAddon.WebLinksAddon();
                    Term.term.loadAddon(Term.term.WebLinksAddon);
                    Term.term.focus();
                    //console.log("Term.term success",Term.term);
                },
            });
            Term.term.onData(function (data) {
                //console.log("onData",data);
                try {
                    Term.bws.send(data);
                } catch (e) {
                    Term.term.write('\r\n连接丢失,正在尝试重新连接!\r\n');
                    Term.connect();
                }
            });
            if (ssh_info) Term.ssh_info = ssh_info;
            Term.connect();
        });
    },


};

function web_shell() {
    Term.run();
}

// socket = {
//     emit: function (data, data2) {
//         if (data === 'webssh') {
//             data = data2;
//         }
//         if (typeof data === 'object') {
//             return;
//         }
//         Term.send(data);
//     },
// };
