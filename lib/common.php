<?php
	class NodeSocketCommon
	{
		public static $nodesocketSignature = "nsockv01";
		
		public static $EnumConnectionState = array(
			'Disconnected' => 0x0,
			'Connected' => 0x1,
			'Verified' => 0x2,
			'Processing' => 0x3,
			'_max' => 0x4
		);
		
		public static $EnumExecutionCode = array(
			'ClientReady' => 0x0,
			'ExecFunction' => 0x1,
			'_max' => 0x2
		);
		
		public static $EnumDataType = array(
			'byte' => 0x0,
			'ubyte' => 0x1,
			'short' => 0x2,
			'ushort' => 0x3,
			'int' => 0x4,
			'uint' => 0x5,
			'float' => 0x6,
			'double' => 0x7,
			'string' => 0x8,
			'boolean' => 0x9,
			'_max' => 0xA
		);
		
		public static $EnumServerResponse = array(
			'Okay' => 0x0,
			'NoResult' => 0x1,
			'InvalidFunction' => 0x2,
			'ServerError' => 0x3,
			'_max' => 0x4
		);
		
		public static function createExecutePayload($identifier, $typemap, $args)
		{
			$buffer = pack('cV', NodeSocketCommon::$EnumExecutionCode['ExecFunction'], strlen($identifier)) . $identifier;
			
			for($i = 0; $i < count($args); $i++)
			{
				$iStr = strval($i);
				$argtype = null;
				if(!array_key_exists($iStr, $typemap))
				{
					switch(gettype($args[$i]))
					{
						case 'integer':
							$argtype = 'int';
							break;
						case 'double':
							$argtype = 'float';
							break;
						case 'string':
							$argtype = 'string';
							break;
						case 'boolean':
							$argtype = 'boolean';
							break;
					}
					
					if($argtype !== null)
					{
						$typemap[$iStr] = $argtype;
					}
				}
				else
				{
					$argtype = $typemap[$iStr];
				}
				
				if(array_key_exists($argtype, NodeSocketCommon::$EnumDataType))
				{
					$tempBuf = null;
					$size = 0;
					$dataType = NodeSocketCommon::$EnumDataType[$argtype];
					switch($dataType)
					{
						case NodeSocketCommon::$EnumDataType['byte']:
							$tempBuf = pack('c', $args[$i]);
							$size = 1;
							break;
						case NodeSocketCommon::$EnumDataType['ubyte']:
							$tempBuf = pack('C', $args[$i]);
							$size = 1;
							break;
						case NodeSocketCommon::$EnumDataType['short']:
							$tempBuf = pack('s', $args[$i]);
							$size = 2;
							break;
						case NodeSocketCommon::$EnumDataType['ushort']:
							$tempBuf = pack('S', $args[$i]);
							$size = 2;
							break;
						case NodeSocketCommon::$EnumDataType['int']:
							$tempBuf = pack('l', $args[$i]);
							$size = 4;
							break;
						case NodeSocketCommon::$EnumDataType['uint']:
							$tempBuf = pack('L', $args[$i]);
							$size = 4;
							break;
						case NodeSocketCommon::$EnumDataType['float']:
							$tempBuf = pack('f', $args[$i]);
							$size = 4;
							break;
						case NodeSocketCommon::$EnumDataType['double']:
							$tempBuf = pack('d', $args[$i]);
							$size = 8;
							break;
						case NodeSocketCommon::$EnumDataType['string']:
							$tempBuf = utf8_encode($args[$i]);
							$size = strlen($tempBuf);
							break;
						case NodeSocketCommon::$EnumDataType['boolean']:
							$tempBuf = pack('C', $args[$i] ? 0x1 : 0x0);
							$size = 1;
							break;
					}
					$buffer .= pack('CL', $dataType, $size) . $tempBuf;
				}
				else
				{
					throw new Exception('Unsupported data type argument passed to remote function');
				}
			}
			
			return $buffer;
		}
	}