<?php

use Vreasy\NullObject;

if (!function_exists('apache_request_headers')) {
    function apache_request_headers()
    {
        foreach($_SERVER as $key => $value) {
            if (substr($key,0,5)=="HTTP_") {
                $key=str_replace(" ","-",ucwords(strtolower(str_replace("_"," ",substr($key,5)))));
                $out[$key]=$value;
            }
            else {
                $out[$key]=$value;
                $key = str_replace(" ","-",ucwords(strtolower(str_replace("_"," ",$key))));
                $out[$key]=$value;
            }
        }
        return $out;
    }
}

if (!function_exists('hash_equals')) {
  function hash_equals($str1, $str2)
  {
    if (strlen($str1) != strlen($str2)) {
      return false;
    } else {
      $res = $str1 ^ $str2;
      $ret = 0;
      for($i = strlen($res) - 1; $i >= 0; $i--) $ret |= ord($res[$i]);
      return !$ret;
    }
  }
}

function array_flatten($array)
{
  $it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($array));
  return iterator_to_array($it);
}

function current_http_scheme($scheme = '')
{
  if (!$scheme) {
      $scheme = (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on') ? 'http' : 'https';
      $scheme = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) || isset($_SERVER['HTTP_FORWARDED_PROTO'])
          ? (@$_SERVER['HTTP_X_FORWARDED_PROTO'] ?: @$_SERVER['HTTP_FORWARDED_PROTO'])
          : $scheme;
      $scheme = $scheme.'://';

  }
  return $scheme;
}

function getActingApiKey()
{
  try {
      return \Zend_Registry::get('ActingApiKey');
  } catch (\Zend_Exception $e) {
    return null;
  }
}
