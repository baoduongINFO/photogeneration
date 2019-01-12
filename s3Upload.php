<?php
	error_reporting(0);
	if (!class_exists('S3')) require_once 'S3.class.php';
	
	if (!defined('awsAccessKey')) define('awsAccessKey', 'AKIAIA5SYNSDY3KCEF7Q');
	if (!defined('awsSecretKey')) define('awsSecretKey', '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');
	
	$uploadFile = dirname(__FILE__).'/temp/tmp5baa3ccb4e3378.98958095.png';
	$bucketName = 'images.fotocadeau.nl';
	
	
	if (!file_exists($uploadFile) || !is_file($uploadFile))
		exit("\nERROR: No such file: $uploadFile\n\n");
	
	if (!extension_loaded('curl') && !@dl(PHP_SHLIB_SUFFIX == 'so' ? 'curl.so' : 'php_curl.dll'))
		exit("\nERROR: CURL extension not loaded\n\n");
	
	
	$s3 = new S3(awsAccessKey, awsSecretKey);
	$s3->setSignatureVersion('v4');
	$s3->setRegion('eu-central-1');
	if ($s3->putObjectFile($uploadFile, $bucketName, baseName($uploadFile), S3::ACL_PUBLIC_READ)) {
		echo "S3::putObjectFile(): File copied to {$bucketName}/".baseName($uploadFile).PHP_EOL;
	}else{
		die('There was a error uploading the file to AWS.S3.');
	}