<?php
	class NodeSocketClient
	{
		private $context;
		private $host;
		private $socket;
		private $state;
		private $options;
		private $functions = array();
		
		public function __construct($port, $address, $options = array()) // $address can be either a DNS name or an IP (v4 or v6) address
		{
			if(array_key_exists('secure', $options) && $options['secure'] === true)
			{
				$this->host = 'tls://';
			}
			else
			{
				$this->host = 'tcp://';
			}
			
			$this->host = $address . ':' . $port;
			
			if(array_key_exists('socketOptions', $options))
			{
				$this->context = stream_context_create($options['socketOptions']);
			}
			else
			{
				$this->context = stream_context_create();
			}
			
			$this->options = $options;
			$this->state = NodeSocketCommon::$EnumConnectionState['Disconnected'];
		}
		
		protected function read()
		{
			$ret = '';
			$length = 0;
			while(($readBuffer = fread($this->socket, 1)) !== '')
			{
				if($length++ === 0)
				{
					stream_set_blocking($this->socket, 0);
				}
				
				$ret .= $readBuffer;
			}
			stream_set_blocking($this->socket, 1);
			return $ret;
		}
		
		protected function write($buffer)
		{
			fwrite($this->socket, $buffer);
		}
		
		public function remoteExecute(&$identifier, &$typemap, $args)
		{
			if($this->state === NodeSocketCommon::$EnumConnectionState['Verified']) {
				$buffer = NodeSocketCommon::createExecutePayload($identifier, $typemap, $args);
				$this->state = NodeSocketCommon::$EnumConnectionState['Processing'];

				$this->write(utf8_encode($buffer));
				
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
						case NodeSocketCommon::$EnumDataType['boolean']:
							$result = unpack('Cvalue', substr($responseBuffer, 2, 1));
							$result = $result['value'] > 0;
							break;
						default:
							throw new Exception('Unrecognized data type ' . $dataType['value'] . ' returned from server');
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
		
		public function connect()
		{
			$error;
			$errorMessage;
			
			$this->socket = stream_socket_client($this->host, $error, $errorMessage, array_key_exists('timeout', $this->options) ? $this->options['timeout'] : 30, STREAM_CLIENT_CONNECT, $this->context);
			if($this->socket !== false)
			{
				$this->state = NodeSocketCommon::$EnumConnectionState['Connected'];
				
				$this->write(utf8_encode(NodeSocketCommon::$nodesocketSignature));
				if($this->read() === utf8_encode(NodeSocketCommon::$nodesocketSignature))
				{
					$this->state = NodeSocketCommon::$EnumConnectionState['Verified'];
					$this->write(pack('C', NodeSocketCommon::$EnumExecutionCode['RequestMaster']));
				}
			}
			else
			{
				throw new Exception('Socket Error: ' . $errno . ' - ' . $errstr);
			}
		}
	}