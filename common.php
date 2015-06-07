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
		
		public static function createExecutePayload($identifier, $typemap, $args)
		{
			
		}
	}