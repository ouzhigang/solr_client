<?php

require("./Solr.php");

$solr = new \utility\Solr("http://127.0.0.1:8983");

//添加多行数据
//var_dump($solr->updateData("abc", [[ "f_id" => 1, "name" => "admin", "pwd" => "中文11" ], [ "f_id" => 2, "name" => "admin2", "pwd" => "中文22" ], [ "f_id" => 3, "name" => "admin3", "pwd" => "中文33" ]]));

//删除全部数据
//var_dump($solr->updateData("abc", [ "delete" => [ "query" => "*:*" ] ]));

//查询数据
$data = $solr->queryPage("abc", "*:*", [  ], "id desc", 1, 100, NULL, "json");
var_dump($data["response"]["docs"]);
