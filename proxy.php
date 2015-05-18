<?php

// Config
$config['onlinenet_api_key'] = "changeme";
$config['proxy_api_key'] = "changeme";
$config['filter']['server_id'] = ["1234"];
$config['filter']['address'] = ["127.0.0.1"];

$config['log']['enabled'] = false;
$config['log']['file'] = 'online-api-proxy.json'; // Make sure this is not accessible.

// You shouldn't need to change these.
$config['allowed_methods'] = ["GET", "POST", "PUT", "DELETE"];
$config['required_values'] = ["endpointrequest", "method"];


$config['allowed'][] = [ "/server/backup/edit",                             ["server_id","password","autologin","acl_enabled"], ["server_id"]];
$config['allowed'][] = [ "/server/backup/{server_id}",                      ["server_id"],                                      NULL];
$config['allowed'][] = [ "/server/bmc/session",                             ["server_id", "ip"],                                ["server_id"]];
$config['allowed'][] = [ "/server/bmc/session/{server_id}",                 NULL,                                               NULL];
$config['allowed'][] = [ "/server/ip/edit",                                 ["address", "reverse", "destination"],              ["address"]];
$config['allowed'][] = [ "/server/ip/{address}",                            NULL,                                               NULL];
$config['allowed'][] = [ "/server/{server_id}",                             NULL,                                               NULL];
$config['allowed'][] = [ "/server/boot/normal/{server_id}",                 NULL,                                               NULL];
$config['allowed'][] = [ "/server/boot/rescue/{server_id}",                 NULL,                                               NULL];
$config['allowed'][] = [ "/server/boot/test/{server_id}",                   NULL,                                               NULL];
$config['allowed'][] = [ "/server/boot/hardwareWatch/disable/{server_id}",  NULL,                                               NULL];
$config['allowed'][] = [ "/server/boot/hardwareWatch/enable/{server_id}",   NULL,                                               NULL];
$config['allowed'][] = [ "/server/reboot/{server_id}",                      NULL,                                               NULL];
$config['allowed'][] = [ "/server/shutdown/{server_id}",                    NULL,                                               NULL];
$config['allowed'][] = [ "r/^\/server\/bmc\/session\/[0-9A-Za-z]+$/",       NULL,                                               NULL];

$config['api_endpoint'] = "https://api.online.net/api/v1";
$config['api_headers'][] = "X-Pretty-JSON: 1";
$config['api_headers'][] = "Authentication: Bearer ".$config['onlinenet_api_key'];

function call_online_api($method, $endpoint, $values = [])
{
    global $config;

    $call = curl_init();

    switch($method)
    {
        case 'GET':
            $endpoint .= '?' . http_build_query($values);
            break;
        case 'POST':
            curl_setopt($call, CURLOPT_POST, true);
            break;
        default:
            curl_setopt($call, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($call, CURLOPT_POSTFIELDS, http_build_query($values));
            break;
    }

    curl_setopt($call, CURLOPT_URL, $config['api_endpoint'] . $endpoint);
    curl_setopt($call, CURLOPT_HTTPHEADER, $config['api_headers']);
    curl_setopt($call, CURLOPT_RETURNTRANSFER, true);

    $exec = curl_exec($call);
    if(!$exec)
    {
        return_error(21, "Could not contact online API.");
    }

    return $exec;
}

function return_error($errcode, $message)
{
    echo json_encode(["fromproxy" => 1, "success" => false, "errcode" => $errcode, "message" => $message]).PHP_EOL;
    exit;
}


function is_allowed($request)
{
    global $config;
    global $aValues;

    foreach($config['allowed'] as $allowedarray)
    {
        $str = $allowedarray[0];
        foreach($config['filter'] as $key => $filterarray)
        {
            foreach($filterarray as $filter)
            {
                $str = str_replace("{".$key."}", $filter, $str);
            }
        }

        if($str[0] == "r" && is_regex_match($str, $request) || $request == $str)
        {
            if($allowedarray[1] != NULL)
            {
                // required
                foreach($allowedarray[1] as $required)
                {
                    if(!isset($aValues[$required]))
                    {
                        return_error(11, $required." not set.");
                    }
                }
            }

            if($allowedarray[2] != NULL)
            {
                // filter
                foreach($allowedarray[2] as $filter)
                {
                    if(!in_array($aValues[$filter], $config['filter'][$filter]))
                    {
                        return_error(12, $filter." not allowed.");
                    }
                }
            }
            return true;
        }
    }
    return false;
}

function is_regex_match($regex, $request)
{
    if($regex[0] == "r")
    {
        $regex = substr($regex, 1);
    }

    if(preg_match($regex, $request))
    {
        return true;
    }

    return false;
}

function log_to_file( $array )
{
    global $config;

    if($config['log']['enabled'] == false)
    {
        return;
    }
    $file = $config['log']['file'];

    if(!is_readable($file) || !touch($file))
    {
        return_error(51, "Cannot open file.");
    }

    if(!file_put_contents($file, json_encode($array, JSON_PRETTY_PRINT).PHP_EOL, FILE_APPEND))
    {
        return_error(52, "Cannot write to file.");
    }
}

//main

if($config['onlinenet_api_key'] == 'changeme' || $config['proxy_api_key'] == "changeme")
{
    return_error(99, "Proxy has not been setup.");
}


if(!isset($_GET['api']) || $_GET['api'] != $config['proxy_api_key'])
{
    return_error(2, "wrong api key");
}

foreach($config['required_values'] as &$name)
{
    if(!isset($_GET[$name]))
    {
        return_error(1, $name." not set");
    }
}


if(!in_array($_GET['method'], $config['allowed_methods']))
{
    return_error(3, "method not allowed");
}

$aValues = [];
if(isset($_GET['key']) && isset($_GET['value']))
{
    if(is_array($_GET['key']) && is_array($_GET['value']))
    {
        $count = count($_GET['key']);
        if($count == count($_GET['value']) && $count > 0)
        {
            for($i = 0; $i < $count; $i++)
            {
                $aValues[$_GET['key'][$i]] = $_GET['value'][$i];
            }
        }
        else
        {
            return_error(5, "key and value have different array sizes.");
        }
    }
    else
    {
        return_error(4, "key and value are not arrays");
    }
}
$_GET['method'] = strtoupper($_GET['method']);

$CallValues = [
    'ip'        => $_REQUEST['REMOTE_ADDR'],
    'time'      => time(),
    'method'    => $_GET['method'],
    'values'    => $aValues,
    'endpoint'  => $_GET['endpointrequest']
];

if( !is_allowed( $CallValues['endpoint'] ) )
{
    return_error(33, "Endpoint not allowed.");
}

log_to_file( $CallValues );

if($CallValues['method'] == "GET")
{
    return call_online_api($CallValues['method'], $CallValues['endpoint'], $CallValues['values'], NULL);
}

return call_online_api($CallValues['method'], $CallValues['endpoint'], NULL, $CallValues['values']);