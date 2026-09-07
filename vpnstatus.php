<?php
// History: OpenVPN (php-based) web status script, based on Pablo Hoffmann's script of 2007
// Old: This script has been released to the public domain by Pablo Hoffman 
// on February 28, 2007.
// Original location: 
// http://pablohoffman.com/software/vpnstatus/vpnstatus.txt
// Current:
// this version should work with OpenVPN 2.7, where the client connection, profile is visible
// current released under BSD 2

// Configuration values --------
$page_title = "VPN";
$page_refresh = 60;
// the array of VPNs to report
// for each VPN required: name + url
// if pw is provided the openvpn management needs to require it
// URL: unix://</absolute_path> in case of socket
//	  tcp://<ip_or_name>:<port> in case of ...
$vpns = array (
  array (
    'name' => 'server1',
    'url' => 'unix:///var/run/openvpn/...',
#		'pw' => 's3cret',
	),
#  array (
#	  'name' => 'server2',
#	  'url' => 'tcp://fqdn.or.ip:port',
#	  'pw' => 'also_s3cret',
#  ),
);
// -----------------------------
//

// prettyprint KB/MB/GB -- Source - https://stackoverflow.com/a/2510540
// Posted by John Himmelman, modified by community. See post 'Timeline' for change history
// Retrieved 2026-08-27, License - CC BY-SA 4.0
function formatBytes($size, $precision = 2)
{
	$base = log($size, 1024);
	$suffixes = ['', 'KB', 'MB', 'GB', 'TB'];

	return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[(int) floor($base)];
}

$headers = array('VPN address', 'Name', 'Proto', 'Real address', 'Connected since', 'Bytes rcvd', 'Bytes sent', 'Duration (hms)', 'Data cipher');
$tdalign = array('left', 'left', 'left', 'left', 'left', 'right', 'right', 'left', 'left');
$i = 0;
foreach ($vpns as $vpn) {
	$vpnmgmt_url = $vpn['url'];
#	print $vpn['name']." ".$vpnmgmt_url.PHP_EOL;
	$fp = stream_socket_client($vpnmgmt_url, $errno, $errstr, 30);
	if (!$fp) {
#		echo "$errstr ($errno)<br />\n";
#		exit;
		$errstr= "access to \"".$vpnmgmt_url."\" of server \"".$vpns[$i]['name']."\" failed (".$errstr.")";
		break;
	}

	$line = fgets($fp, 16);
	if (substr($line, 0, 15) == "ENTER PASSWORD:") {
		if (isset($vpns[$i]['pw'])) {
			fwrite($fp, $vpns[$i]['pw']."\r\n");
		}
		else {
			$errstr= "access to \"".$vpnmgmt_url."\" of server \"".$vpns[$i]['name']."\" requires a password";
			break;
		}
		$line = fgets($fp, 9);
		if (substr($line, 0, 8) != "SUCCESS:") {
			$errstr= "access to \"".$vpnmgmt_url."\" of server \"".$vpns[$i]['name']."\" failed: pw incorrect";
#			print $errstr.PHP_EOL;
			break;
		}
	}

	sleep(1);
	fwrite($fp, "status\r\n");
	sleep(1);
	fwrite($fp, "quit\r\n");
	sleep(1);


	$vpns[$i]['clients']= array();
	$j=0;
	while (!feof($fp)) {
		$line = fgets($fp, 256);
		if (substr($line, 0, 4) == "TIME") {
			$vpns[$i]['time'] = (explode(',', substr($line,5))[1]);
		}
		if (substr($line, 0, 5) == "TITLE") {
			$vpns[$i]['serverinfo']= (explode(',', substr($line,6))[0]);
		}
		if (substr($line, 0, 11) == "CLIENT_LIST") {
			$cdata = explode(',', substr($line,12));
			$haveproto = false;
			if (substr($cdata[1],0,1) == "u" || substr($cdata[1],0,1) == "t") {
				$haveproto = true;
			}
			if ($haveproto) {
				$proto= preg_replace('/^(....)[^:]*:(.*):\d+$/', '$1', $cdata[1]);
				$rip= preg_replace('/^[^:]*:(.*):\d+$/', '$1', $cdata[1]);
			}
			else {
				$proto = "N/A";
				$rip= preg_replace('/^(.*):\d+$/', '$1', $cdata[1]);
			}
			$client= array($cdata[2], $cdata[0], $proto, $rip, $cdata[7], $cdata[4], $cdata[5], $cdata[7], $cdata[11]); 
			$vpns[$i]['clients'][$j]= $client;
			$j++;
		}
		if (substr($line, 0, 3) == "END") {
			break;
		}
	}

/* DEBUG
print "<pre>";
print_r($client);
print_r($vpns);
print "</pre>".PHP_EOL;
*/
	fclose($fp);
	$i++;
}

if ($errstr !== "") {
	print <<<EOF
	<!DOCTYPE html>
	<html lang="en">
	<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>$page_title status - error</title>
	</head>
	<body>
	<pre>
	$errstr
	</pre>
	</body>
	</html>
	EOF;
	exit;
}
?> 

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?php echo $page_title ?> status</title>
<meta http-equiv="refresh" content="<?php echo $page_refresh ?>" />

<style type="text/css">
body {
	font-family: Verdana, Arial, Helvetica, sans-serif;
	font-size: 0.9em;
	background-color: #E5EAF0;
}
h1 {
	color: green;
	font-size: 1.2em;
	text-align: center;
	padding-bottom: 0;
	margin-bottom: 0;
}
table caption {
	background: maroon;
	color: white;
	font-size: 1.1em;
}
p.info {
	text-align: center;
	font-size: 0.7em;
}
table, th, td {
	border: 1px solid maroon;
	border-collapse: collapse;
}
table {
	margin-top: 0;
	margin-bottom: 1em;
	width: 100%;
}
th {
	background: maroon;
	color: white;
}
tr {
	border-bottom: 1px solid silver;
}
tr:nth-child(odd) { background-color: #ACDCDC; }
tr:hover {background-color: #FFFF99;  }
td {
	padding: 0px 10px 0px 10px;
}
</style>
</head>

<body>
<?php foreach ($vpns as $vpn) { ?>
<h1><?php echo $vpn['name']?></h1>
<table>
<caption><?php echo $vpn['serverinfo'] ?></caption>
<tr>
<?php foreach ($headers as $th) { ?>
<th><?php echo $th?></th>
<?php } ?>
</tr>

<?php foreach ($vpn['clients'] as $client) { 
	$client[4] = date ('Y-m-d H:i:s T', $client[4]);
	$client[7] = date ('H:i:s', ($vpn['time']-$client[7]));
	$client[5] = formatBytes($client[5], 2);
	$client[6] = formatBytes($client[6], 2);
	$i = 0;
?>
<tr>
<?php foreach ($client as $td) { ?>
<td align="<?php echo $tdalign[$i++] ?>"><?php echo $td?></td>
<?php } ?>
</tr>
<?php } ?>

</table>
<?php } ?>
<p class="info">This page gets reloaded every <?php echo $page_refresh ?>seconds.<br />Last update: 
<b><?php echo date ("Y-m-d H:i:s T") ?></b></p>
</body>

</html>
