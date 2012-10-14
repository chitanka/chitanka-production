<?php

$wikipedia_en = array (
  'name'     => 'English-language Wikipedia',
  'api_url'  => 'http://en.wikipedia.org/w/api.php',
  'retries'  => array (
    'link_error' => 10,
    'bad_login'  => 3,
  ),
  'interval' => array (
    'link_error' => 10,
    'submit'     => 5,
  ),
  'limits' => array (
    'DL'    => NULL,  // max speed limits for this wiki, in bytes / sec; NULL - no limit
    'UL'    => NULL,
    'total' => NULL,
  ),
/*
  'http-auth' => array (  // HTTP transfer user and password, NOT wiki ones! if you don't know what these are, leave this commented out.
    'user' => "my_http_username",
    'pass' => "my_http_password",
  ),
*/
);


$logins = array (
  'Grigor@Wikipedia' => array (
    'user'            => 'Grigor Gatchev',
    'password'        => "<my Wikipedia password>",
    'domain'          => NULL,
    'remember_login'  => false,
    'mark_bot'        => false,
    'move_noredirect' => NULL,
    'move_withtalk'   => true,
    'wiki'            => $wikipedia_en,
  ),
  'Apibot@Wikipedia' => array (
    'user'            => 'Apibot',
    'password'        => "<my bot's Wikipedia password>",
    'domain'          => NULL,
    'remember_login'  => false,
    'mark_bot'        => true,
    'move_noredirect' => NULL,
    'move_withtalk'   => true,
    'wiki'            => $wikipedia_en,
    'limits' => array (
      'DL'    => NULL,  // max speed limits for this user, in bytes / sec; NULL - no limit
      'UL'    => NULL,
      'total' => NULL,
    ),
  ),
);
