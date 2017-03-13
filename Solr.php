<?php
//简单solr客户端

//单机
//$solr = new \utility\Solr("http://127.0.0.1:8983");
//主从
//$solr = new \utility\Solr("http://127.0.0.1:8983", "http://127.0.0.1:8984", "http://127.0.0.1:8985");

//================
//json

//添加1行数据
//var_dump($solr->updateData("abc", [ "f_id" => 1, "name" => "admin", "pwd" => "中文11" ]));

//添加多行数据
//var_dump($solr->updateData("abc", [[ "f_id" => 1, "name" => "admin", "pwd" => "中文11" ], [ "f_id" => 2, "name" => "admin2", "pwd" => "中文22" ], [ "f_id" => 3, "name" => "admin3", "pwd" => "中文33" ]]));

//删除全部数据
//var_dump($solr->updateData("abc", [ "delete" => [ "query" => "*:*" ] ]));

//查询数据
//$data = $solr->queryPage("abc", "*:*", [  ], "id desc", 1, 100, NULL, "json");
//var_dump($data["response"]["docs"]);
//================

//================
//xml
/*
添加1行或多行数据
$dom = new \DOMDocument('1.0', 'UTF-8');

$add = $dom->createElement('add');
$add->setAttribute("overwrite", "true");
$add->setAttribute("commitWithin", "10000");

$doc = $dom->createElement('doc');
$f_id = $dom->createElement('field', "1");
$f_id->setAttribute("name", "f_id");
$name = $dom->createElement('field', "xmlxml");
$name->setAttribute("name", "name");
$pwd = $dom->createElement('field', "aaaaaaaa1111");
$pwd->setAttribute("name", "pwd");

$doc->appendChild($f_id);
$doc->appendChild($name);
$doc->appendChild($pwd);

$add->appendChild($doc);

$dom->appendChild($add);
var_dump($solr->updateData("abc", $dom, "xml"));
*/

/*
删除全部数据
$dom = new \DOMDocument('1.0', 'UTF-8');

$delete = $dom->createElement('delete');

$query = $dom->createElement('query');
$query->nodeValue = "*:*";
$delete->appendChild($query);

$dom->appendChild($delete);
var_dump($solr->updateData("abc", $dom, "xml"));
*/

//查询数据
//$data = $solr->queryPage("abc", "*:*", [  ], "f_id desc", 1, 100, NULL, "xml");
//var_dump($data->documentElement->tagName);
//================

namespace utility;

class Solr {
    
	private $server_cfg;
	
	public function __construct($server_cfg) {
        
		//支持集群访问，第一个为主solr,后面的是从solr
		//$server_cfg = "http://127.0.0.1:8983";
		//$server_cfg = [ "http://127.0.0.1:8983", "http://127.0.0.1:8984", "http://127.0.0.1:8985" ];
		
		if(is_string($server_cfg)) {
			$this->server_cfg = [ $server_cfg ];
		}
		else {
			$this->server_cfg = $server_cfg;
		}
		
    }	
	
	public function updateData($collection_name, $data, $type = "json") {
		//更新索引
		$res = NULL;
		$url = $this->server_cfg[0] . "/solr/" . $collection_name . "/update?commit=true";
		if($type == "json") {
			//json			
			$res = $this->httpPostData($url, $data);
			$res = json_decode($res, true);
		}
		else {
			//xml
			$res = $this->httpPostData($url, $data, "text/xml; charset=utf-8");
			
			$dom = new \DOMDocument('1.0', 'UTF-8');
			$dom->loadXML($res);
			$res = $dom;
		}
		return $res;
	}
	
	public function query($collection_name, $q = "*:*", $fq = [], $sort = NULL, $start = 0, $rows = 10, $fl = NULL, $wt = "json") {
		//一般查询
		
		//如果solr不止1个，则随机访问第2个到最后1个
		$server_arr = [];
		if(count($this->server_cfg) > 1) {
			foreach($this->server_cfg as $k => $v) {
				if($k == 0) {
					continue;
				}
				$server_arr[] = $v;
			}
		}
		else {
			$server_arr[] = $this->server_cfg[0];
		}
		$i = array_rand($server_arr);
		
		$url = $server_arr[$i] . "/solr/" . $collection_name . "/select?indent=on&q=" . $q . "&rows=" . $rows . "&start=" . $start . "&wt=" . $wt;
		if($sort != NULL) {
			$url .= "&sort=" . urlencode($sort);
		}
		foreach($fq as $v) {
			$url .= "&fq=" . urlencode($v);
		}
		if($fl != NULL) {
			$url .= "&fl=" . urlencode($fl);
		}
		
		$data = file_get_contents($url);
		
		if($wt == "json") {
			//json
			$data = json_decode($data, true);
		}
		else {
			//xml
			$dom = new \DOMDocument('1.0', 'UTF-8');
			$dom->loadXML($data);
			$data = $dom;
		}
			
		return $data;
	}
	
	public function queryPage($collection_name, $q = "*:*", $fq = [], $sort = NULL, $page = 1, $page_size = 10, $fl = NULL, $wt = "json") {
		//分页查询
		
		$start = ($page - 1) * $page_size;
		return $this->query($collection_name, $q, $fq, $sort, $start, $page_size, $fl, $wt);
	}
	
	protected function httpPostData($url, $data, $type = "application/json; charset=utf-8") {
		$data_str = NULL;
		if(strpos($type, "json") !== false) {
			//json
			$json_str = json_encode($data);
			
			//如果没有包含有关键字的key，就视为添加数据
			if(!isset($data["add"]) && !isset($data["delete"])) {
				if($json_str{0} == "{" && $json_str{strlen($json_str) - 1} == "}") {
					$json_str = "[" . $json_str . "]";
				}
			}
			
			$data_str = $json_str;
		}
		else {
			//xml
			$data_str = $data->saveXML();
			
		}
		
		$header = array(
			"User-Agent:Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36",
			"Content-Type: " . $type,
			"Content-Length: " . strlen($data_str)
		);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_str);
		$data = curl_exec($ch);
		curl_close($ch);

		return $data;
	}
	
}
