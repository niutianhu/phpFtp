<?php

$config = [
	'host' => '',		//地址
	'user' => '',		//用户名
	'pass' => '',	//密码
	'port' => 21,					//端口号
	'remoteDir' => '/',				//远程地址
	'exception' => [				//例外
		'.git','.vscode','runtime','thinkphp','extend','public','route','vendor','.gitignore','.project','sftp-config.json'
	],
];

//ftp类
class Ftp{
	
	private $config = [
		'port' => 21,
		'exception' => [],
		'remoteDir' => '/',
		'localDir' => '/'
	];

	private $fileList = [];
	private $ftpConn;

	//构造方法
	function __construct( $param ){
		//应用配置
		$this -> config['localDir'] = preg_replace("/\\\/i","/",__DIR__);		//本地路径
		foreach( $param as $key => $val ){
			$this -> config[$key] = $val;
		}

		//链接ftp
		$this -> connect();

		$fileList = $this -> scan($this -> config['localDir']);
		echo "共监听".count($fileList)."个文件\n";
		//监听文件变化
		$this -> listenFileChange($fileList);
	}

	//连接ftp
	private function connect(){
		//链接
		$this -> ftpConn = ftp_connect( $this -> config['host'] ) or die("ftp链接失败");
		ftp_login($this -> ftpConn,$this -> config['user'],$this -> config['pass']);						//登陆
		ftp_chdir($this -> ftpConn,$this -> config['remoteDir']);											//改变当前目录
		return $this -> ftpConn;
	}

	//缓存文件状态//扫描
	private function scan($dir){    
		$dirArr = scandir($dir);
		//$list = [];
		foreach($dirArr as $v){
			if( in_array($v,$this -> config['exception']) ) continue;
			if($v!='.' && $v!='..'){
				$dirname = $dir."/".$v;  //子文件夹的目录地址
				if(is_dir($dirname)){
					$this -> fileList = array_merge($this -> fileList,$this -> scan($dirname));
				}else{
					clearstatcache();
					$this -> fileList[$dirname] = filemtime($dirname);
				}
			}
		}
		return $this -> fileList;
	}

	//监听文件变化
	private function listenFileChange($fileList){
		$num = 0;		//遍历次数
		while( true ){	//开始一个死循环
			foreach( $fileList as $dirname => $updateTime ){
				clearstatcache();		//清除缓存
				$thisTime = filemtime($dirname);
				if( $thisTime > $updateTime ){
					$fileList[$dirname] = $thisTime;
					$isUpload = $this -> ftpUpLoad( $dirname );
					$num = 0;
				}
			}
			echo ".";
			$num ++;
			if( $num >= 20 ){
				$num = 0;
				//保持连接
				ftp_close($this -> ftpConn);
				$conn = $this -> connect();
				echo "保持连接\n";
			}
			sleep(1);
		}
	}

	//上传ftp
	private function ftpUpLoad( $dirname ){
		$jdDir = str_replace($this -> config['localDir'],'',$dirname);
		if( mb_substr($jdDir,0,1,"UTF-8") == '/' ){
			$jdDir = mb_substr($jdDir,1,null,"UTF-8");
		}
		echo $dirname . ' => ' . $jdDir;
		$success = ftp_put($this -> ftpConn,$jdDir,$dirname,FTP_BINARY);
		echo "上传成功\n";
		return $success;
	}



}
$ftp = new Ftp( $config );
