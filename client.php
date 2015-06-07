<?php
	class NodeSocketClient
	{
		private $port;
		private $ipaddress;
		private $socket;
		private $state;
		private $functions = array();
		
		public function __construct($port, $address) // $address can be either a DNS name or an IP (v4 or v6) address
		{
			$this->port = $port;
			if(filter_var($address, FILTER_VALIDATE_IP) !== false)
			{
				$this->ipaddress = $address;
			}
			else
			{
				$this->ipaddress = gethostbyname($address);
			}
			$this->state = NodeSocketCommon::$EnumConnectionState['Disconnected'];
		}
		
		protected function read()
		{
			$ret = '';
			$length = 0;
			while(($readBuffer = socket_read($this->socket, 1)) !== false)
			{
				if($length++ === 0)
				{
					socket_set_nonblock($this->socket);
				}
				$ret .= $readBuffer;
			}
			socket_set_block($this->socket);
			return $ret;
		}
		
		public function remoteExecute(&$identifier, &$typemap, $args)
		{
			if($this->state === NodeSocketCommon::$EnumConnectionState['Verified']) {
				$buffer = NodeSocketCommon::createExecutePayload($identifier, $typemap, $args);
				$this->state = NodeSocketCommon::$EnumConnectionState['Processing'];

				socket_write($this->socket, utf8_encode($buffer));
				
				$responseBuffer = $this->read();
				
				$serverResponse = unpack('Cvalue', substr($responseBuffer, 0, 1));
				if($serverResponse['value'] === NodeSocketCommon::$EnumServerResponse['Okay'])
				{
					$dataType = unpack('Cvalue', substr($responseBuffer, 1, 1));
					$result = null;
					switch($dataType['value']) {
						case NodeSocketCommon::$EnumDataType['byte']:
							$result = unpack('cvalue', substr($responseBuffer, 2, 1));
							$result = $result['value'];
							break;
						case NodeSocketCommon::$EnumDataType['ubyte']:
							$result = unpack('Cvalue', substr($responseBuffer, 2, 1));
							$result = $result['value'];
							break;
						case NodeSocketCommon::$EnumDataType['short']:
							$result = unpack('svalue', substr($responseBuffer, 2, 2));
							$result = $result['value'];
							break;
						case NodeSocketCommon::$EnumDataType['ushort']:
							$result = unpack('Svalue', substr($responseBuffer, 2, 2));
							$result = $result['value'];
							break;
						case NodeSocketCommon::$EnumDataType['int']:
							$result = unpack('lvalue', substr($responseBuffer, 2, 4));
							$result = $result['value'];
							break;
						case NodeSocketCommon::$EnumDataType['uint']:
							$result = unpack('Lvalue', substr($responseBuffer, 2, 4));
							$result = $result['value'];
							break;
						case NodeSocketCommon::$EnumDataType['float']:
							$result = unpack('fvalue', substr($responseBuffer, 2, 4));
							$result = $result['value'];
							break;
						case NodeSocketCommon::$EnumDataType['double']:
							$result = unpack('dvalue', substr($responseBuffer, 2, 8));
							$result = $result['value'];
							break;
						case NodeSocketCommon::$EnumDataType['string']:
							$result = substr($responseBuffer, 2);
							break;
						case NodeSocketCommon::$EnumDataType['ubyte']:
							$result = unpack('Cvalue', substr($responseBuffer, 2, 1));
							$result = $result['value'] > 0;
							break;
						default:
							throw new Exception('Unrecognized data type ' . $dataType . ' returned from server');
							return;
					}
				
					return $result;
				}
				else if(serverResponse === NodeSocketCommon.EnumServerResponse.NoResult) {
					return;
				}
				else if(serverResponse === NodeSocketCommon.EnumServerResponse.InvalidFunction) {
					throw new Exception('NodeSocket server returned an invalid function status code');
				}
				else if(serverResponse === NodeSocketCommon.EnumServerResponse.ServerError) {
					throw new Exception('Server reported an error');
				}
				else {
					throw new Exception('Unknown response received from server');
				}
			}
			else {
				throw new Exception('Unable to execute remote function on an unverified/disconnected server');
			}
		}
		
		public function linkFunction($identifier, $typemap = array())
		{
			return function() use (&$identifier, &$typemap) {
				return $this->remoteExecute($identifier, $typemap, func_get_args());
			};
		}
		
		public function start()
		{
			$domain = null;
			if(filter_var($this->ipaddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false)
			{
				$domain = AF_INET;
			}
			else
			{
				$domain = AF_INET6;
			}
			
			if(($this->socket = socket_create($domain, SOCK_STREAM, SOL_TCP)) !== false)
			{
				$result = socket_connect($this->socket, $this->ipaddress, $this->port);
				if ($result !== false)
				{
					$this->state = NodeSocketCommon::$EnumConnectionState['Connected'];
					socket_write($this->socket, utf8_encode(NodeSocketCommon::$nodesocketSignature));
					if($this->read() === utf8_encode(NodeSocketCommon::$nodesocketSignature))
					{
						$this->state = NodeSocketCommon::$EnumConnectionState['Verified'];

						socket_set_option($this->socket, SOL_SOCKET, SO_KEEPALIVE, 1);
						socket_write($this->socket, pack('C', NodeSocketCommon::$EnumExecutionCode['ClientReady']));
					}
					else
					{
						throw new Exception('Unable to connect to remote socket \'' . $this->ipaddress . ':' . $this->port . '\': Could not verify NodeSocket protocol');
					}
				}
				else
				{
					throw new Exception('Unable to connect to remote socket \'' . $this->ipaddress . ':' . $this->port . '\': ' . socket_strerror(socket_last_error($this->socket)));
				}
			}
			else
			{
				throw new Exception('Unable to create socket');
			}
		}
	}