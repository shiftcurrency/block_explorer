<?php
global $config;

if($config['debug'])
{
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
}