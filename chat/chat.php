<?php

/**
 * SPAChat - Simple PHP Angular Ajax Chat
 *
 * @date    2013-05-31
 * @author  Jonas Sciangula Street <joni2back {at} gmail.com>
 */

namespace SPA_Common;
define('DB_USERNAME',       'root');
define('DB_PASSWORD',       '');
define('DB_HOST',           'localhost');
define('DB_NAME',           'chat');
define('CHAT_HISTORY',      '150');
define('CHAT_ONLINE_RANGE', '1');
define('ADMIN_USERNAME_PREFIX', 'adm123_');

abstract class Model
{
    public $db;

    public function __construct()
    {
        $this->db = new \mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
    }
}

abstract class Controller
{

    private $_request, $_response, $_query, $_post, $_server, $_cookies;
    protected $_currentAction, $_defaultModel;

    const ACTION_POSTFIX = 'Action';
    const ACTION_DEFAULT = 'indexAction';

    public function __construct()
    {
        $this->_request  = &$_REQUEST;
        $this->_query    = &$_GET;
        $this->_post     = &$_POST;
        $this->_server   = &$_SERVER;
        $this->_cookies  = &$_COOKIE;
        $this->init();
    }

    public function init()
    {   session_start(); 
        $this->dispatchActions();
        $this->render();
    }

    public function dispatchActions()
    {
        $action = $this->getQuery('action');
        if ($action && $action .= self::ACTION_POSTFIX) {
            if (method_exists($this, $action)) {
                $this->setResponse(
                    call_user_func(array($this, $action), array())
                );
            } else {
                $this->setHeader("HTTP/1.0 404 Not Found");
            }
        } else {
            $this->setResponse(
                call_user_func(array($this, self::ACTION_DEFAULT), array())
            );
        }
        return $this->_response;
    }

    public function render()
    {
        if ($this->_response) {
            if (is_scalar($this->_response)) {
                echo $this->_response;
            } else {
                throw new \Exception('Response content must be type scalar');
            }
            exit;
        }
    }

    public function indexAction()
    {
        return null;
    }

    public function setResponse($content)
    {
        $this->_response = $content;
    }

    public function setHeader($params)
    {
        if (! headers_sent()) {
            if (is_scalar($params)) {
                header($params);
            } else {
                foreach($params as $key => $value) {
                    header(sprintf('%s: %s', $key, $value));
                }
            }
        }
        return $this;
    }

    public function setModel($namespace)
    {
        $this->_defaultModel = $namespace;
        return $this;
    }

    public function setSession($key, $value)
    {
        $_SESSION[$key] = $value;
        return $this;
    }

    public function setCookie($key, $value, $seconds = 3600)
    {
        $this->_cookies[$key] = $value;
        if (! headers_sent()) {
            setcookie($key, $value, time() + $seconds);
            return $this;
        }
    }

    public function getRequest($param = null, $default = null)
    {
        if ($param) {
            return isset($this->_request[$param]) ?
                $this->_request[$param] : $default;
        }
        return $this->_request;
    }

    public function getQuery($param = null, $default = null)
    {
        if ($param) {
            return isset($this->_query[$param]) ?
                $this->_query[$param] : $default;
        }
        return $this->_query;
    }

    public function getPost($param = null, $default = null)
    {
        if ($param) {
            return isset($this->_post[$param]) ?
                $this->_post[$param] : $default;
        }
        return $this->_post;
    }

    public function getServer($param = null, $default = null)
    {
        if ($param) {
            return isset($this->_server[$param]) ?
                $this->_server[$param] : $default;
        }
        return $this->_server;
    }

    public function getSession($param = null, $default = null)
    {
        if ($param) {
            return isset($this->_session[$param]) ?
                $this->_session[$param] : $default;
        }
        return $this->_session;
    }

    public function getCookie($param = null, $default = null)
    {
        if ($param) {
            return isset($this->_cookies[$param]) ?
                $this->_cookies[$param] : $default;
        }
        return $this->_cookies;
    }

    public function getModel()
    {
        if ($this->_defaultModel && class_exists($this->_defaultModel)) {
            return new $this->_defaultModel;
        }
    }

    public function sanitize($string, $quotes = ENT_QUOTES, $charset = 'utf-8')
    {
        return htmlentities($string, $quotes, $charset);
    }
}

abstract class Helper
{

}


namespace SPA_Chat;
use SPA_Common;
class Model extends SPA_Common\Model
{

    public function getMessages($to_username,$limit = CHAT_HISTORY, $reverse = true)
    {   
    	if ($to_username=='*') {
            $response = $this->db->query("(SELECT * FROM messages WHERE to_username='".$to_username."' ORDER BY `date` DESC LIMIT {$limit}) ORDER BY `date` ASC");
        } else {
        $response = $this->db->query("(SELECT * FROM messages WHERE (username='{$_SESSION['login_username']}' AND to_username='{$to_username}' ) OR (username='{$to_username}'AND to_username='{$_SESSION['login_username']}' ) 
            ORDER BY `date` DESC LIMIT {$limit}) ORDER BY `date` ASC");
    }
        $result = array();
        while($row = $response->fetch_object()) {
            $result[] = $row;
        }
        $response->free();
        return $result;
    }

    public function addMessage($username, $message, $ip,$to_username)
    {
        $username = addslashes($username);
        $message = addslashes($message);
        $to_username = addslashes($to_username);


        return (bool) $this->db->query("INSERT INTO messages
            VALUES (NULL, '{$username}', '{$message}', '{$ip}', NOW() ,'{$to_username}',1,1)");
    }

    public function removeMessages()
    {
        return (bool) $this->db->query("TRUNCATE TABLE messages");
    }

    public function removeOldMessages($limit = CHAT_HISTORY)
    {
        return (bool) $this->db->query("DELETE FROM messages
            WHERE id NOT IN (SELECT id FROM messages
                ORDER BY date DESC LIMIT {$limit})");
    }

    public function getOnline($count = true, $timeRange = CHAT_ONLINE_RANGE)
    {
        if ($count) {
            $response = $this->db->query("SELECT count(*) as total FROM online");
            return $response->fetch_object();
        }
        $response = $this->db->query("SELECT ip FROM online");
        $result = array();
        while($row = $response->fetch_object()) {
            $result[] = $row;
        }
        $response->free(); 
        return $result;
    }

    public function updateOnline($hash, $ip)
    {
        return (bool) $this->db->query("REPLACE INTO online
            VALUES ('{$hash}', '{$ip}', NOW())") or die(mysql_error());
    }

    public function clearOffline($timeRange = CHAT_ONLINE_RANGE)
    {
        return (bool) $this->db->query("DELETE FROM online
            WHERE last_update <= (NOW() - INTERVAL {$timeRange} MINUTE)");
    }

    public function __destruct()
    {
        if ($this->db) {
            $this->db->close();
        }
    }

}

class Controller extends SPA_Common\Controller
{
    protected $_model;

    public function __construct()
    {
        $this->setModel('SPA_Chat\Model');
        parent::__construct();
    }

    public function indexAction()
    {
    }

    public function listAction()
    {	$to_username=$_GET['to'];
        $this->setHeader(array('Content-Type' => 'application/json'));
        $messages = $this->getModel()->getMessages($to_username);
        foreach($messages as &$message) {
            $message->me = $this->getServer('REMOTE_ADDR') === $message->ip;
        }
        return json_encode($messages);
    }

    public function saveAction()
    {
        $username =$this->getPost('username');
        $message = $this->getPost('message');
        $to_username = $this->getPost('to');
        $ip = $this->getServer('REMOTE_ADDR');
        $this->setCookie('username', $username, 9999 * 9999);

        $result = array('success' => false);
        if ($username && $message) {
            $cleanUsername = preg_replace('/^'.ADMIN_USERNAME_PREFIX.'/', '', $username);
            $result = array(
                'success' => $this->getModel()->addMessage($cleanUsername, $message, $ip,$to_username)
            );
        }

        if ($this->_isAdmin($username)) {
            $this->_parseAdminCommand($message);
        }

        $this->setHeader(array('Content-Type' => 'application/json'));
        return json_encode($response);
    }

    private function _isAdmin($username) 
    {
        return preg_match('/^'.ADMIN_USERNAME_PREFIX.'/', $username);
    }

    private function _parseAdminCommand($message)
    {
        if ($message == '/clear') {
            $this->getModel()->removeMessages();
            return true;
        }
        if ($message == '/online') {
            $online = $this->getModel()->getOnline(false);
            $ipArr = array();
            foreach ($online as $item) {
                $ipArr[] = $item->ip;
            }
            $message = 'Online: ' . implode(", ", $ipArr);
            $this->getModel()->addMessage('Admin', $message, '0.0.0.0');
            return true;
        }
    }

    private function _getMyUniqueHash() 
    {
        $unique  = $this->getServer('REMOTE_ADDR');
        $unique .= $this->getServer('HTTP_USER_AGENT');
        $unique .= $this->getServer('HTTP_ACCEPT_LANGUAGE');
        return md5($unique);
    }

    public function pingAction()
    {
        $ip = $this->getServer('REMOTE_ADDR');
        $hash = $this->_getMyUniqueHash();

        $this->getModel()->updateOnline($hash, $ip);
        $this->getModel()->clearOffline();
        $this->getModel()->removeOldMessages();

        $onlines = $this->getModel()->getOnline();

        $this->setHeader(array('Content-Type' => 'application/json'));
        return json_encode($onlines);
    }
}

$chatApp = new Controller(); ?><!doctype html>
<html ng-app="ChatApp">
<head>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=no">
    <meta charset="utf-8">

    <title>Simple PHP Angular Ajax Chat</title>


    <script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.3.14/angular.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>


    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
</head>
<script type="text/javascript">
(function() {
    var ChatApp = angular.module('ChatApp', []);

    ChatApp.directive('ngEnter', function () {
        return function (scope, element, attrs) {
            element.bind("keydown keypress", function (event) {
                if (event.which === 13) {
                    scope.$apply(function (){
                        scope.$eval(attrs.ngEnter);
                    });
                    event.preventDefault();
                }
            });
        };
    });

    ChatApp.controller('ChatAppCtrl', ['$scope', '$http', function($scope, $http) {

        $scope.urlListMessages = '?action=list&to=<?php echo $_GET['to'] ?>';
        $scope.urlSaveMessage = '?action=save';
        $scope.urlListOnlines = '?action=ping';

        $scope.pidMessages = null;
        $scope.pidPingServer = null;

        $scope.beep = new Audio('beep.ogg');
        $scope.messages = [];
        $scope.online = null;
        $scope.lastMessageId = null;
        $scope.historyFromId = null;

        $scope.me = {
            username: "<?php echo $_SESSION['login_username'] ?>",
            message: null
        };

  

        $scope.saveMessage = function(form, callback) {
            var data = $.param($scope.me);
            data=data+'&to=<?php echo $_GET['to'] ?>';

          
            $scope.me.message = '';
            return $http({
                method: 'POST',
                url: $scope.urlSaveMessage,
                data: data,
                headers: {'Content-Type': 'application/x-www-form-urlencoded'}
            }).success(function(data) {
                $scope.listMessages(true);
            });
        };

        $scope.replaceShortcodes = function(message) {
            var msg = '';
            msg = message.toString().replace(/(\[img])(.*)(\[\/img])/, "<img src='$2' />");
            msg = msg.toString().replace(/(\[url])(.*)(\[\/url])/, "<a href='$2'>$2</a>");
            return msg;
        };

        $scope.notifyLastMessage = function() {
            if (typeof window.Notification === 'undefined') {
              return;
            }
            window.Notification.requestPermission(function (permission) {
            	console.log(permission);
                var lastMessage = $scope.getLastMessage();
                if (permission == 'granted' && lastMessage && lastMessage.username) {
                    var notify = new window.Notification(lastMessage.username + ' says:', {
                        body: lastMessage.message
                    });
                    notify.onclick = function() {
                        window.focus();
                    };
                    notify.onclose = function() {
                       
                    };
                    var timmer = setInterval(function() {
                        notify && notify.close();
                        typeof timmer !== 'undefined' && window.clearInterval(timmer);
                    }, 10000);
                }
            });
        };

        $scope.getLastMessage = function() {
            return $scope.messages[$scope.messages.length - 1];
        };

        $scope.listMessages = function(wasListingForMySubmission) {
        	console.log($scope.urlListMessages);
            return $http.post($scope.urlListMessages, {}).success(function(data) {
                $scope.messages = [];
                angular.forEach(data, function(message) {
                    message.message = $scope.replaceShortcodes(message.message);
                    $scope.messages.push(message);
                });

                var lastMessage = $scope.getLastMessage();
                var lastMessageId = lastMessage && lastMessage.id;

                if ($scope.lastMessageId !== lastMessageId) {
                    $scope.onNewMessage(wasListingForMySubmission);
                }
                $scope.lastMessageId = lastMessageId;
            });
        };

        $scope.onNewMessage = function(wasListingForMySubmission) {
            if ($scope.lastMessageId && !wasListingForMySubmission) {
                $scope.playAudio();
               
                $scope.notifyLastMessage();
            }

            $scope.scrollDown();
            window.addEventListener('focus', function() {
                
            });
        };

        $scope.pingServer = function(msgItem) {
            return $http.post($scope.urlListOnlines, {}).success(function(data) {
                $scope.online = data;
            });
        };

        $scope.init = function() {
            $scope.listMessages();
            $scope.pidMessages = window.setInterval($scope.listMessages, 3000);
            $scope.pidPingServer = window.setInterval($scope.pingServer, 8000);
        };

        $scope.scrollDown = function() {
            var pidScroll;
            pidScroll = window.setInterval(function() {
                $('.direct-chat-messages').scrollTop(window.Number.MAX_SAFE_INTEGER * 0.001);
                window.clearInterval(pidScroll);
            }, 100);
        };

        $scope.clearHistory = function() {
            var lastMessage = $scope.getLastMessage();
            var lastMessageId = lastMessage && lastMessage.id;
            lastMessageId && ($scope.historyFromId = lastMessageId);
        };

        $scope.openModal = function() {
            $('#choose-name').modal('show');
        };

        $scope.playAudio = function() {
            $scope.beep && $scope.beep.play();
        };

        $scope.init();
    }]);
})();
</script>
<style>
.direct-chat-text {
    border-radius:5px;
    position:relative;
    padding:5px 10px;
    background:#D2D6DE;
    border:1px solid #D2D6DE;
    margin:5px 0 0 50px;
    color:#444;
}
.direct-chat-msg,.direct-chat-text {
    display:block;
    word-wrap: break-word;
}
.direct-chat-img {
    border-radius:50%;
    float:left;
    width:40px;
    height:40px;
}
.direct-chat-info {
    display:block;
    margin-bottom:2px;
    font-size:12px;
}
.direct-chat-msg {
    margin-bottom:10px;
}
.direct-chat-messages,.direct-chat-contacts {
    -webkit-transition:-webkit-transform .5s ease-in-out;
    -moz-transition:-moz-transform .5s ease-in-out;
    -o-transition:-o-transform .5s ease-in-out;
    transition:transform .5s ease-in-out;
}
.direct-chat-messages {
    -webkit-transform:translate(0,0);
    -ms-transform:translate(0,0);
    -o-transform:translate(0,0);
    transform:translate(0,0);
    padding:10px;
    height:230px;
    overflow:auto;
    word-wrap: break-word;
}
.direct-chat-text:before {
    border-width:6px;
    margin-top:-6px;
}
.direct-chat-text:after {
    border-width:5px;
    margin-top:-5px;
}
.direct-chat-text:after,.direct-chat-text:before {
    position:absolute;
    right:100%;
    top:15px;
    border:solid rgba(0,0,0,0);
    border-right-color:#D2D6DE;
    content:' ';
    height:0;
    width:0;
    pointer-events:none;
}
.direct-chat-warning .right>.direct-chat-text {
    background:#F39C12;
    border-color:#F39C12;
    color:#FFF;
}
.right .direct-chat-text {
    margin-right:50px;
    margin-left:0;
}
.direct-chat-warning .right>.direct-chat-text:after,
.direct-chat-warning .right>.direct-chat-text:before {
    border-left-color:#F39C12;
}
.right .direct-chat-text:after,.right .direct-chat-text:before {
    right:auto;
    left:100%;
    border-right-color:rgba(0,0,0,0);
    border-left-color:#D2D6DE;
}
.right .direct-chat-img {
    float:right;
}
.box-footer {
    border-top-left-radius:0;
    border-top-right-radius:0;
    border-bottom-right-radius:3px;
    border-bottom-left-radius:3px;
    border-top:1px solid #F4F4F4;
    padding:10px 0;
    background-color:#FFF;
}
.direct-chat-name {
    font-weight:600;
}
.box-footer form {
    margin-bottom:10px;
}
input,button,.alert,.modal-content {
    border-radius: 0!important;
}
.ml10 {
    margin-left:10px;
}
</style>
<body ng-controller="ChatAppCtrl">
    <div class="container" style="width: 100%">
        <div class="box box-warning direct-chat direct-chat-warning">
            <div class="box-body">
                <div class="direct-chat-messages">
                    <div class="direct-chat-msg" ng-repeat="message in messages" ng-if="historyFromId < message.id" ng-class="{'right':message.me}">
                        <div class="direct-chat-info clearfix">
                           <!-- <span class="direct-chat-name" ng-class="{'pull-left':message.me, 'pull-right':!message.me}">{{ message.username }}</span>-->
                            <span class="direct-chat-timestamp " ng-class="{'pull-left':!message.me, 'pull-right':message.me}">{{ message.date }}</span>
                        </div>
                        <img class="direct-chat-img" src="http://upload.wikimedia.org/wikipedia/en/e/ee/Unknown-person.gif" alt="">
                        <div class="direct-chat-text right">
                            <span>{{ message.message }}</span>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    <form ng-submit="saveMessage()">
                        <div class="input-group">
                            <input type="text" placeholder="Type message..." autofocus="autofocus" class="form-control" ng-model="me.message" ng-enter="saveMessage()">
                            <span class="input-group-btn">
                            <button type="submit" class="btn btn-warning btn-flat">Send</button>
                            </span>
                        </div>
                    </form>
                  
                    </div>
                </div>
            </div>
        </div>
    </div>

    
   
    

</body>

</html>