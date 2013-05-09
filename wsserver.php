#!/usr/bin/php
<?php
	define('NO_SESSION_AUTOSTART', true);

	set_include_path(get_include_path() . PATH_SEPARATOR .
		__DIR__ ."/include");

	require_once "config.php";
	require_once "functions.php";
	require_once "sessions.php";

	require_once __DIR__ . '/vendor/autoload.php';

	if (!defined('WEBSOCKET_PORT')) {
		print "Error: you need to define WEBSOCKET_PORT and WEBSOCKET_URL in config.php for this to work.\n";
		exit;
	}

	use Ratchet\MessageComponentInterface;
	use Ratchet\ConnectionInterface;
	use Ratchet\Server\IoServer;
	use Ratchet\WebSocket\WsServer;

	/* class WebSocketClient {
		private $conn;

		function __construct(ConnectionInterface $conn) {
			print "WebSocketClient constructed";

			$this->conn = $conn;
		}
	} */

	class WebSocketServer implements MessageComponentInterface {
		protected $link;

		public function __construct() {
			$this->link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
		}

		public function onOpen(ConnectionInterface $conn) {
			_debug("Connection established: {$conn->resourceId}");
			//$conn->send(json_encode(array("hello", "Tiny Tiny IRC")));
		}

		public function onMessage(ConnectionInterface $from, $msg) {
			//print "onMessage: {$msg}\n";

			$message = @json_decode($msg, true);

			if ($message) {
				switch ($message['method']) {
				case 'hello':
					_debug("Got hello");
					break;
				case 'update':
					_debug("Got update: {$message['sid']}");
					session_id($message['sid']);
					@session_start();

					if ($_SESSION["uid"]) {

						$_REQUEST = $message;

						$from->send(update_common_tasks($this->link, true));

						session_write_close();
					} else {
						session_destroy();
					}
					break;
				default:
					_debug("Unknown method: {$message['method']}");
				}
			}
		}

		public function onClose(ConnectionInterface $conn) {
			_debug("Connection closed: {$conn->resourceId}");
		}

		public function onError(ConnectionInterface $conn, \Exception $e) {
			_debug("Connection error: {$conn->resourceId}: {$e->getMessage()}");
			$conn->close();
		}
	};

	_debug("Tiny Tiny IRC WebSocket Server: " . WEBSOCKET_URL);
	_debug("Port: " . WEBSOCKET_PORT);

	$server = IoServer::factory(
		new WsServer(new WebSocketServer()), WEBSOCKET_PORT);

	$server->run();
?>
