<?php
namespace HostPanel;

class CSelectel
{

	const API_KEY = '****************************';
	const API_URL = 'https://api.selectel.ru/servers/v2';
	const IBLOCK_SETTING = 64; 

	public static function request($method, $params) 
	{
	 
	    if(is_array($params))
	    {
			$arParams = [key($params)=> $params[key($params)]];
			$arParams = json_encode($arParams);				
	    }
	    
	    $ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, self::API_URL . $method); 
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Token: '. self::API_KEY, 'Content-Type: application/json']);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if(is_array($params) && key($params) == 'power_state')
		{
		    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
		    curl_setopt($ch, CURLOPT_POSTFIELDS, $arParams); 
		}
		elseif(is_array($params) && key($params) == 'reboot')
		{
		    curl_setopt($ch, CURLOPT_POST, true);
		    curl_setopt($ch, CURLOPT_POSTFIELDS, $arParams); 
		}

		$obData = curl_exec($ch);
		curl_close($ch);

		$response = json_decode($obData, true);
		return $response;
	}


	
	public function getServerList() 
	{
	   	    
	  $serverList = self::request('/resource', false);
  
	  if(is_array($serverList['result']) && !empty($serverList['result']))
	  {

	      foreach($serverList['result'] as $key => $arServer)
	      {
			$status = self::getServerStatus($arServer['uuid']);
			$consoleInfo = self::getConsoleAccess($arServer['uuid']);
			
			$url = parse_url($consoleInfo['console_info']['url']);
			parse_str($url['query'], $query);
					
			$serverList['result'][$key]['CURRENT_STATUS'] = $status['result']['driver_status']['power_state'];
			$serverList['result'][$key]['CONSOLE_INFO'] = $query['token'];
			
			$rsItems = \CIBlockElement::GetList([], ['IBLOCK_ID'=>self::IBLOCK_SETTING, 'PROPERTY_SERVER_UUID'=>$arServer['uuid']], false, false, ['*']);
			$arItem = $rsItems->fetch();
			$serverList['result'][$key]['user_desc'] = $arItem['NAME'];		  
	      }
	
	    return $serverList;  
	  }
	  
	  
	   $serverList['errors'] = "Unknown error, check this later";
	   return $serverList;
	}
	
	public function getServerDetailInfo($uuid) 
	{
	   	    
	  $serverList = self::request('/resource', false);

	  if(is_array($serverList['result']) && !empty($serverList['result']))
	  {
	     
	      foreach($serverList['result'] as $key => $arServer)
	      {
			if($arServer['uuid'] == $uuid)
			{ 
				$status = self::getServerStatus($arServer['uuid']);
				$serverList['result'][$key]['CURRENT_STATUS'] = $status['result']['driver_status']['power_state'];
			}
			else
			{
				unset($serverList['result'][$key]);
			} 
	      }
	      $serverList['result'] = array_values($serverList['result']);

	    return $serverList['result']; 
	  }
	  
	  
	   $serverList['errors'] = "Unknown error, check this later";
	   return $serverList;
	}
	
	public function getServerStatus($uuid) 
	{	     
	  $serverStatus = self::request('/power/'.$uuid, false);

	  if(is_array($serverStatus['result']) && !empty($serverStatus['result']))
	    return $serverStatus;  
	  
	   $serverList['errors'] = "Unknown error, check this later";
	   return $serverStatus;
	}
	
	public function setServerPowerStatus($uuid) 
	{	 
	  $currentStatus = self::getServerStatus($uuid);
	   
	  if($currentStatus['result']['driver_status']['power_state'] == 'power off')
	  {
		$arParams = [
		    "power_state"=> true
		];
	  }
	  else
	  {
		$arParams = [
		    "power_state"=> false
		];
	  }
	  
	  $serverStatusPowerMode = self::request("/power/".$uuid, $arParams);

	 
	   return $serverStatusPowerMode;
	}
	
	public function initServerReboot($uuid) 
	{	  
		$arParams = [
		    "reboot"=> true
		];
	    
	  $serverRebootStatus = self::request("/power/".$uuid. '/reboot', $arParams);

	   return $serverRebootStatus;
	}
	
	public function GetNetworksList() 
	{
	    $netWorkList = self::request('/network', false);
	    
	    if(is_array($netWorkList['result']) && !empty($netWorkList['result']))
	    {
				
			return $netWorkList['result'];
		
	    }
	}
	
	public function GetIPList($uuid) 
	{
	    $ipList = self::request('/network/ipam/ip', false);   
	    
	    if(is_array($ipList['result']) && !empty($ipList['result']))
	    {
			foreach ($ipList['result'] as $key => $ip)
			{
				if($ip["resource_uuid"] != $uuid)
				{
					continue;
				}
				else
				{	
					return $ipData = [
						'subnet' => $ip['subnet'],
						'ip' => $ip['ip']
					];
				}
			}
		
		return "�� ������� ������������ ip";
	    }
	}
	
	public function GetLocationDetail($uuid) 
	{
	    $netWorkDetail = self::request('/location/'.$uuid, false); 

	    return $netWorkDetail;
	}
	public function getServerDetail($uuid) 
	{
	     
	  $serverInfo = self::request('/resource/'.$uuid, false);
	  
	  if(is_array($serverInfo['result']) && !empty($serverInfo['result']))
	  {
		  \CModule::IncludeModule('iblock');

		  $location = self::GetLocationDetail($serverInfo['result']['location_uuid']);
		  $consoleInfo = self::getConsoleAccess($uuid); 
		  $url = parse_url($consoleInfo['console_info']['url']);
		  parse_str($url['query'], $query);
		  
		  $serverInfo['result']['location'] = $location['result']['name'];
		  $serverInfo['result']['console'] = $query['token'];
		  
		  $rsItems = \CIBlockElement::GetList([], ['IBLOCK_ID'=>self::IBLOCK_SETTING, 'PROPERTY_SERVER_UUID'=>$serverInfo['result']['uuid']], false, false, ['*']);
           
		  $arItem = $rsItems->fetch();
		  	  
		  $serverInfo['result']['user_desc'] = $arItem['NAME'];
		  

		    
		  return $serverInfo['result'];  
	  }
	  
	
	   $serverInfo['errors'] = "Unknown error, check this later";
	   return $serverInfo;
	}
	
	public function getConsumptionDetail($uuid) 
	{
	   	    
	  $consumptionInfo = self::request('/consumption/traffic/resource/'.$uuid, false);
	
	  if(is_array($consumptionInfo['result']) && !empty($consumptionInfo['result']))
	  {
	     
	    $consumptionInfo['result']["traffic_spent"] = round($consumptionInfo['result']["traffic_spent"] / 1024 / 1024 / 1024, 2);	    
	    return $consumptionInfo['result'];  
	  }
	  
	  
	   $serverInfo['errors'] = "Unknown error, check this later";
	   return $serverInfo;
	}
	
	public function getServerConfiguration($uuid) 
	{
	   	    
	  $serverInfo = self::request('/resource/'.$uuid.'/info/server', false);
	
	  if(is_array($serverInfo['result']) && !empty($serverInfo['result']))
	  {
	      
	    $arConfig = explode(",", $serverInfo['result']['config_name']);
	    
	    foreach($arConfig as $key => $config)
	    {
			$arConfig[$key] = str_replace("×", "�", $config);		
	    }
	    
	    $serverInfo['result']['config_name'] = $arConfig;
	     
	    return $serverInfo['result'];  
	  }
	  
	  
	   $serverInfo['errors'] = "Unknown error, check this later";
	   return $serverInfo;
	}
	
	public function getServerServices($uuid) 
	{
	   	     
	  $servicesInfo = self::request('/resource/'.$uuid, false);
	  
	
	  if(is_array($servicesInfo['ordered'])
	  {
	    return $servicesInfo['ordered'];  
	  }

	   return $servicesInfo['ordered'];
	}
	
	public function getStatisticsInfo($uuid, $local = false) 
	{

		$timestamp = time()-86400;
		if($local)
		{
			$servicesStat = self::request('/consumption/speed/resource/'.$uuid.'?local=true&interval=hour&from='.$timestamp . '&till='.time(), false);
		}
		else
		{
			$servicesStat = self::request('/consumption/speed/resource/'.$uuid.'?interval=hour&from='.$timestamp . '&till='.time(), false);
		}

		if(is_array($servicesStat['result']))
		{
			return $servicesStat['result'];  
		}
		return false;
	}
	
	public function getConsoleAccess($uuid) 
	{

	  $consoleAccess = self::request('/power/'.$uuid.'/console', false);
	
	  if(is_array($consoleAccess['result']) && $consoleAccess['result']['console_enabled'] == true)
	  {
	    return $consoleAccess['result'];  
	  }

	   return false;
	}
}
?>
