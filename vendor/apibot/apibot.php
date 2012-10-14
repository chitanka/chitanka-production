<?php
#
#  A MediaWiki bot - used for automated editing of pages on sites
#  powered by MediaWiki.
#
#  Based on the idea of Bgbot, by Borislav Manolov.
#
#  This program is free software; you can redistribute it and/or
#  modify it under the terms of the GNU Affero General Public License
#  as published by the Free Software Foundation; either version 3
#  of the License, or (at your option) any later version.
#
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.
#
#  You should have received a copy of the GNU Afero General Public License
#  along with this program; if not, write to the Free Software Foundation, Inc.,
#  59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
#  http://www.gnu.org/copyleft/agpl.html
#
#  Author: Grigor Gatchev <grigor at gatchev dot info>
# ---------------------------------------------------------------------------- #

require_once ( dirname ( __FILE__ ) . '/browser.php' );
require_once ( dirname ( __FILE__ ) . '/logins.php' );
require_once ( dirname ( __FILE__ ) . '/apibot_dataobjects.php' );


# ---------------------------------------------------------------------------- #
#                               Bot constants                                  #
# ---------------------------------------------------------------------------- #

define ( 'APIBOT_VERSION', '0.32' );
define ( 'APIBOT_BROWSER_AGENT', 'Mozilla 5.0 (Apibot ' . APIBOT_VERSION . ')' );

# ----- Bot loglevels ----- #

define ( 'LL_PANIC'  , 0 );  // the bot is expected to die after logging this level.
define ( 'LL_ERROR'  , 1 );
define ( 'LL_WARNING', 2 );
define ( 'LL_INFO'   , 3 );
define ( 'LL_DEBUG'  , 4 );

# ----- Namespace IDs ----- #

define ( 'NAMESPACE_ID_MEDIA', -2 );
define ( 'NAMESPACE_ID_SPECIAL', -1 );

define ( 'NAMESPACE_ID_MAIN', 0 );
define ( 'NAMESPACE_ID_MAINTALK', 1 );
define ( 'NAMESPACE_ID_USER', 2 );
define ( 'NAMESPACE_ID_USERTALK', 3 );
define ( 'NAMESPACE_ID_WIKI', 4 );  // the wiki name - Wikipedia, or whatever
define ( 'NAMESPACE_ID_WIKITALK', 5 );
define ( 'NAMESPACE_ID_FILE', 6 );
define ( 'NAMESPACE_ID_FILETALK', 7 );
define ( 'NAMESPACE_ID_MEDIAWIKI', 8 );
define ( 'NAMESPACE_ID_MEDIAWIKITALK', 9 );
define ( 'NAMESPACE_ID_TEMPLATE', 10 );
define ( 'NAMESPACE_ID_TEMPLATETALK', 11 );
define ( 'NAMESPACE_ID_HELP', 12 );
define ( 'NAMESPACE_ID_HELPTALK', 13 );
define ( 'NAMESPACE_ID_CATEGORY', 14 );
define ( 'NAMESPACE_ID_CATEGORYTALK', 15 );


# Some of the errors are standard MW API ones, duplicated here for usage by the web backends etc.
$APIBOT_ERRORS = array (

  'genericerror' => array ( 'level' => NULL, 'type' => NULL, 'code' => NULL, 'info' => NULL ),  // set code and info!

  'xfererror'    => array ( 'level' => LL_ERROR, 'type' => 1, 'code' => "xfererror",
    'info' => "Data transfer error" ),
  'browsererror' => array ( 'level' => LL_ERROR, 'type' => 1, 'code' => "browsererror",
    'info' => "Browser error" ),  // add the browser error No. in the code!
  'xfernoreply'  => array ( 'level' => LL_ERROR, 'type' => 1, 'code' => "xfernoreply",
    'info' => "No reply received" ),

  'notloggedin'  => array ( 'level' => LL_ERROR, 'type' => 2, 'code' => "notloggedin",
    'info' => "You must be logged in" ),
  'editconflict' => array ( 'level' => LL_ERROR, 'type' => 2, 'code' => "editconflict",
    'info' => "Edit conflict detected" ),
  'pagedeleted'  => array ( 'level' => LL_ERROR, 'type' => 2, 'code' => "pagedeleted",
    'info' => "The page has been deleted since you fetched its timestamp" ),
  'permissiondenied' => array ( 'level' => LL_ERROR, 'type' => 2, 'code' => "permissiondenied",
    'info' => "Permission denied" ),
  'unknownreason' => array ( 'level' => LL_ERROR, 'type' => 2, 'code' => "reasonunknown",
    'info' => "Unknown reason" ),
  'revertcaptcha' => array ( 'level' => LL_ERROR, 'type' => 2, 'code' => "revertcaptcha",
    'info' => "You must solve a captcha to revert this page" ),
  'revertknown'   => array ( 'level' => LL_ERROR, 'type' => 2, 'code' => "revertknown",
    'info' => NULL ),  // add the error from the ['result'] tree element!
  'revertunknown' => array ( 'level' => LL_ERROR, 'type' => 2, 'code' => "revertunknown",
    'info' => "Unknown reason" ),
  'editknown'     => array ( 'level' => LL_ERROR, 'type' => 2, 'code' => "editknown",
    'info' => NULL ),  // add the error from the ['result'] tree element!
  'notmodified'   => array ( 'level' => LL_ERROR, 'type' => 2, 'code' => "notmodified",
    'info' => "Not modified" ),
  'pageprotected' => array ( 'level' => LL_ERROR, 'type' => 2, 'code' => "pageprotected",
    'info' => "Protected against bots" ),
  'pagemissing'   => array ( 'level' => LL_ERROR, 'type' => 2, 'code' => "pagemissing",
    'info' => "Missing page title" ),
  'pageinvalid'   => array ( 'level' => LL_ERROR, 'type' => 2, 'code' => "pageinvalid",
    'info' => "Invalid page title" ),
  'noparaminfo'   => array ( 'level' => LL_ERROR, 'type' => 2, 'code' => "noparaminfo",
    'info' => "Could not obtain paraminfo" ),
  'nopimodules'   => array ( 'level' => LL_ERROR, 'type' => 2, 'code' => "nopimodules",
    'info' => "Could not obtain modules paraminfo" ),
  'nopiqmodules'  => array ( 'level' => LL_ERROR, 'type' => 2, 'code' => "nopiqmodules",
    'info' => "Could not obtain querymodules paraminfo" ),
  'cantdelete'    => array ( 'level' => LL_ERROR, 'type' => 2, 'code' => "cantdelete",
    'info' => "Cannot delete pages on this wiki" ),
  'notblocked'   => array ( 'level' => LL_ERROR, 'type' => 2, 'code' => "notblocked",
    'info' => "User not blocked" ),
  'insharedrepo' => array ( 'level' => LL_ERROR, 'type' => 2, 'code' => "insharedrepo",
    'info' => "This file is in shared repository" ),


  'olderversion' => array ( 'level' => LL_ERROR, 'type' => 3, 'code' => "olderversion",
    'info' => "The MediaWiki version is too old to support this request" ),
  'notoken'      => array ( 'level' => LL_ERROR, 'type' => 3, 'code' => "notoken",
    'info' => "Could not obtain the token requested" ),
  'api_error'    => array ( 'level' => LL_ERROR, 'type' => 3, 'code' => "api_error",
    'info' => "An internal MediaWiki API error occurred" ),
  'web_error'    => array ( 'level' => LL_ERROR, 'type' => 3, 'code' => "web_error",
    'info' => "An incorrect or incomplete HTML page was received" ),
  'noblockidoruser' => array ( 'level' => LL_ERROR, 'type' => 3, 'code' => "noblockidoruser",
    'info' => "Specified neither user nor block ID" ),
);


# ---------------------------------------------------------------------------- #
#                               The main bot class                             #
# ---------------------------------------------------------------------------- #

class Apibot {

  public    $test_mode = false;   // if true, info will be printed to screen instead of exchange
  public    $dump_mode = false;   // if true, the exchange will be dumped also to screen

  protected $login;               // login data, passed to Apibot

  protected $bot_params;          // bot global parameters

  protected $wiki = array();      // wiki info
  protected $user = array();      // user info

  protected $browser;             // the browser object
  public    $browser_agent = APIBOT_BROWSER_AGENT;
  public    $browser_compression = true;

  public    $error = array();     // 'level', 'type', 'code' and 'info' are supported
  // level: panic - 0, error - 1, warning - 2 (info and debug do not go here)
  // type: 0 or NULL - no error, 1 - link (browser), 2 - MediaWiki / HTML, 3 - program logic... 255 - unknown

  protected $params = array();    // parameters to be passed on request
  protected $fileparams = array();// file upload parameters to be passed on request
  protected $states = array();    // states of modules etc. to be preserved between calls and recursions

  protected $data_tree = array(); // data returned by the request

  protected $bot_stack = array(); // preserves the full bot states during inserted tasks

  protected $logname;             // filename to write the log in
  protected $logpreface;          // a string to preface every log string
  protected $logpreface_stack = array(); // the log prefaces stack
  public    $loglevel = LL_INFO;  // levels: 0 (panic), 1 (error), 2 (warning), 3 (info), 4 (debug)
  public    $echo_log;            // echo the log on the screen, too
  public    $html_log;            // format the log in HTML

  public    $max_postdata_size = PHP_INT_MAX;  // if postdata turns out longer, this will be logged.
  public    $log_levelprefs = array ( LL_PANIC => '!', LL_ERROR => '#', LL_WARNING => '=', LL_INFO => '+', LL_DEBUG => '-' );

  protected $APIBOT_PAGE_PROPERTIES = array (
    'info'           => 'in',
    'revisions'      => 'rv',
    'categories'     => 'cl',
    'imageinfo'      => 'ii',
    'stashimageinfo' => 'sii',
    'langlinks'      => 'll',
    'links'          => 'pl',
    'templates'      => 'tl',
    'images'         => 'im',
    'extlinks'       => 'el',
    'categoryinfo'   => 'ci',
    'duplicatefiles' => 'df',
    'globalusage'    => 'gu',
  );

  # ---------- Constructor and destructor ---------- #

  function __construct ( $login, $params = NULL ) {

    $this->set_bot_params ( $params, $login );
    $this->set_bot_variables();

    $this->log ( "Started, Apibot v" . APIBOT_VERSION, LL_INFO );

    $this->login ( $login );
  }

  function __destruct () {
    $this->log ( "Ended, Apibot v" . APIBOT_VERSION . "\n", LL_INFO );
  }

  private function set_bot_params ( $params, $login ) {
    if ( is_null ( $params ) ) { $params = array(); }
    if ( is_null ( $params['logname'] ) ) { $params['logname'] = basename ( $_SERVER['SCRIPT_FILENAME'], '.php' ) . '.log'; }
    if ( is_null ( $params['loglevel'] ) ) { $params['loglevel'] = LL_INFO; }
    if ( is_null ( $params['echo_log'] ) ) { $params['echo_log'] = true; }
    if ( is_null ( $params['html_log'] ) ) { $params['html_log'] = false; }

    if ( is_null ( $params['workfiles_path'] ) ) { $params['workfiles_path'] = "."; }

    if ( is_null ( $params['fetch_info'] ) ) { $params['fetch_info'] = "on_newrevision"; }
    if ( is_null ( $params['fetch_wikiinfo'] ) ) { $params['fetch_wikiinfo'] = $params['fetch_info']; }
    if ( is_null ( $params['fetch_userinfo'] ) ) { $params['fetch_userinfo'] = $params['fetch_info']; }
    if ( $params['fetch_userinfo'] == "on_newversion" || $params['fetch_userinfo'] == "on_newrevision" ) {
      $params['fetch_userinfo'] = "on_expiry";
    }

    if ( is_null ( $params['fetched_info_expiry'] ) ) { $params['fetched_info_expiry'] = 60 * 60 * 24 * 7; }
    if ( is_null ( $params['fetched_wikiinfo_expiry'] ) ) { $params['fetched_wikiinfo_expiry'] = $params['fetched_info_expiry']; }
    if ( is_null ( $params['fetched_userinfo_expiry'] ) ) { $params['fetched_userinfo_expiry'] = $params['fetched_info_expiry']; }

    if ( ! is_array ( $params['limits'] ) ) { $params['limits'] = array(); }
    if ( ! is_array ( $login['limits'] ) ) { $login['limits'] = array(); }
    if ( ! is_array ( $login['wiki']['limits'] ) ) { $login['wiki']['limits'] = array(); }
    $params['limits'] = array_merge ( $login['wiki']['limits'], $params['limits'] );
    $params['limits'] = array_merge ( $login['limits']        , $params['limits'] );

    $this->test_mode = $params['test_mode'];
    $this->dump_mode = $params['dump_mode'];

    if ( ! is_null ( $params['memory_limit'] ) ) ini_set ( 'memory_limit', $params['memory_limit'] );
    if ( ! is_null ( $params['max_text_length'] ) ) {
      preg_match ( '/^(\d+)(K|M|G)?$/ui', $params['max_text_length'], $matches );
      $bytes = $matches[1] * 4;  // * 2 for the MediaWiki utf8, and * 2 for the regex search/replace process
      ini_set ( 'pcre.backtrack_limit', $bytes . $matches[2] );
    }

    $this->bot_params = $params;
  }

  private function set_bot_variables ( $bot_params = NULL ) {
    if ( is_null ( $bot_params ) ) $bot_params = $this->bot_params;

    $this->logname    = $bot_params['logname'];
    $this->logpreface = $bot_params['logpreface'];
    $this->loglevel   = $bot_params['loglevel'];
    $this->echo_log   = $bot_params['echo_log'];
    $this->html_log   = $bot_params['html_log'];

    $browser_params = array();
    if ( ! empty ( $bot_params['cookies_file'] ) ) {
      $browser_params['cookies_file'] = $bot_params['workfiles_path'] . "/" . $bot_params['cookies_file'];
    }
    $this->browser = new Browser ( $browser_params );

  }

  # ---------- Tools ---------- #

  # ----- Logfile ----- #

  public function log ( $msg, $msglevel = LL_INFO ) {
    $msg = $this->logpreface . $msg;
    if ( $msglevel <= $this->loglevel ) {
      if ( ! empty ( $msg ) ) {
        $msg = $this->log_levelprefs[$msglevel] . ' ['. date('Y-m-d H:i:s') .'] '. $msg;
        if ( $this->echo_log ) {
          # print errors in red
          echo ( ( $msglevel < LL_WARNING ) ? "\033[31m$msg\033[0m" : $msg ) . "\n";
          flush();
        }
      }
      if ( $this->html_log ) $msg = "<p>" . $msg . "</p>";
      if ( $this->logname !== "" ) my_fwrite ( $this->logname, $msg . "\n" );
    }
  }

  public function push_logpreface ( $new_preface ) {
    array_push ( $this->logpreface_stack, $this->log_preface );
    $this->logpreface = $new_preface;
  }

  public function pop_logpreface () {
    $this->logpreface = array_pop ( $this->logpreface_stack );
  }

  protected function log_status ( $ok_string, $error_template,
    $ok_loglevel = LL_INFO, $error_loglevel = NULL ) {

    if ( array_key_exists ( 'level',  $this->error ) ) {
      if ( is_null ( $error_loglevel ) ) {
        $error_loglevel = $this->error['level'];
      }
      switch ( $this->error['level'] ) {
        case 0 : $level = "Panic"; break;
        case 1 : $level = "Error"; break;
        case 2 : $level = "Warning"; break;
      }
      switch ( $this->error['type'] ) {
        case 1 : $type = "data link"; break;
        case 2 : $type = "MediaWiki"; break;
        case 3 : $type = "logic"; break;
        default : $type = "unknown";
      }
      $error_string = str_replace ( '$level', $level, $error_template );
      $error_string = str_replace ( '$type', $type, $error_string );
      $error_string = str_replace ( '$code', $this->error['code'], $error_string );
      $error_string = str_replace ( '$info', $this->error['info'], $error_string );
      $this->log ( $error_string, $error_loglevel );
      return false;
    } else {
      $this->log ( $ok_string, $ok_loglevel );
      return true;
    }
  }

  protected function log_warnings_if_present ( &$action_tree ) {
    if ( ! empty ( $action_tree['warnings'] ) ) {
      foreach ( $action_tree['warnings'] as $warning => $text ) {
        $this->log ( "Warning: " . $warning . ": " . $text, LL_WARNING );
      }
      return true;
    }
    return false;
  }

  # ----- Saving and restoring bot state ----- #

  public function push_bot_state () {
    $state = array();
    $state['params'    ] = $this->params;
    $state['fileparams'] = $this->fileparams;
    $state['states'    ] = $this->states;
    $state['error'     ] = $this->error;
    $state['data_tree' ] = $this->data_tree;
    $this->bot_stack[] = $state;
  }

  public function pop_bot_state () {
    $state = array_pop ( $this->bot_stack );
    $this->params     = $state['params'    ];
    $this->fileparams = $state['fileparams'];
    $this->states     = $state['states'    ];
    $this->error      = $state['error'     ];
    $this->data_tree  = $state['data_tree' ];
  }

  # ----- Errors ----- #

  protected function set_std_error ( $id, $info = NULL, $code = NULL, $type = NULL, $level = NULL ) {
    $this->error = $GLOBALS['APIBOT_ERRORS'][$id];
    if ( ! is_null ( $level ) ) { $this->error['level'] = $level; }
    if ( ! is_null ( $type ) ) { $this->error['type'] = $type; }
    if ( ! is_null ( $code ) ) { $this->error['code'] = $code; }
    if ( ! is_null ( $info ) ) {
      if ( preg_match ( '/[\ \:]/', mb_substr ( $info, 0, 1 ) ) ) {
        $this->error['info'] .= $info;
      } else {
        $this->error['info'] = $info;
      }
    }
    return false;
  }

  public function error_string () {
    return $this->error['code'] . ": " . $this->error['info'];
  }

  # -----  MediaWiki version handling  ----- #

  public function mw_version_number () {
    $array = explode ( " ", $this->wiki['general']['generator'] );
    $array = explode ( ".", $array[1] );
    return ( $array[0] * 10000 ) + ( $array[1] * 100 ) + ( empty ( $array[2] ) ? 0 : $array[2] );
  }

  public function mw_version_ok ( $version_code ) {
    if ( $this->mw_version_number() >= $version_code ) {
      return true;
    } else {
      return $this->set_std_error ( 'olderversion' );
    }
  }

  protected function mw_version_and_token_ok ( $version_code ) {
    if ( $this->mw_version_ok ( $version_code ) && $this->api_get_token_if_needed() ) {
      if ( empty ( $this->params['token'] ) ) {
        $this->append_param ( 'token', $this->wiki['token'] );
      }
      return true;
    }
    return false;
  }

  # -----  Convenience functions  ----- #

  public function barsepstring ( $arg, $preg_quote = false, $regex_wikicase = false ) {
    if ( is_array ( $arg ) ) {
      foreach ( $arg as &$value ) {
        $value = ( $preg_quote ? preg_quote ( $value ) : $value );
        $value = ( $regex_wikicase ? $this->regex_wikicase ( $value ) : $value );
      }
      return implode ( "|", $arg );
    } else {
      return $arg;
    }
  }

  public function keyequals_barsepstring ( $arg, $match_sign = '=' ) {
    if ( is_array ( $arg ) ) {
      foreach ( $arg as $key => &$value ) {
        $value = $key . $match_sign . $value;
      }
    }
    return $this->barsepstring ( $arg );
  }

  # ---------- Adding request parameters ---------- #

  # ----- Append to the params array ----- #

  protected function append_param ( $paramname, $params = NULL ) {
    $string = $this->barsepstring ( $params );
    if ( empty ( $this->params[$paramname] ) ) {
      $this->params[$paramname] = $string;
    } else {
      $this->params[$paramname] .= "|" . $string;
    }
  }

  protected function append_param_if_nonnull ( $paramname, $params ) {
    if ( ! is_null ( $params ) ) {
      $this->append_param ( $paramname, $params );
    }
  }

  protected function append_param_if_true ( $paramname, $param ) {
    if ( $param ) { $this->append_param ( $paramname, '' ); }
  }

  protected function append_params_array ( $parameters, $code = NULL ) {
    foreach ( $parameters as $parameter => $value ) {
      $value = $this->barsepstring ( $value );
      $this->append_param ( $code . $parameter, $value );
    }
  }

  # ----- Append to the fileparams array ----- #

  protected function append_fileparam ( $paramname, $param = NULL ) {
    $this->fileparams[$paramname] = $param;
  }

  protected function append_fileparam_if_nonnull ( $paramname, $param ) {
    if ( ! is_null ( $param ) ) {
      $this->append_fileparam ( $paramname, $param );
    }
  }

  # ---------- Generic browser requests ---------- #

  protected function test_dump ( $text ) {
    echo $text;
    echo str_repeat ( 80, '-' ) . "\n";
  }

  public function bytecounters () {
    return $this->browser->bytecounters;
  }

  public function reset_bytecounters () {
    return $this->browser->reset_bytecounters();
  }

  public function xfer ( $url, $vars = NULL, $files = NULL, $http_auth = NULL,
    $use_compression = true, $browser_agent = NULL, $limits = NULL,
    $retries = 5, $interval = 1, $checkreply_func = NULL ) {

    if ( is_null ( $browser_agent ) ) { $browser_agent = $this->browser_agent; }

    $this->browser->use_compression = $use_compression;
    $this->browser->agent           = $browser_agent;
    $this->browser->limits          = $limits;

    if ( empty ( $http_auth ) ) {
      $do_auth = false;
    } else {
      $do_auth = true;
      $this->browser->user = $http_auth['user'];
      $this->browser->pass = $http_auth['pass'];
    }

    $counter = 0;
    while ( $counter < $retries ) {
      $this->error = array();

      $result = $this->browser->submit ( $url, $vars, $files, $do_auth );

      if ( ! $result ) {
        $this->set_std_error ( 'browsererror', ": " . $this->browser->error );
      } elseif ( strlen ( $this->browser->content ) == 0 ) {
        $this->set_std_error ( 'xfernoreply' );
      } elseif ( ! empty ( $checkreply_func ) && ! call_user_func ( $checkreply_func, $this->browser ) ) {
        // the error should be set in checkreply_func();
        // it should return false on data transfer error, true otherwise (but may still set an error)
      } else {
        return $this->browser->content;
      }

      $this->log_status ( "", "Data transfer failed (\$info) - retry " . ( $counter + 1 ) . "...",
        LL_INFO, LL_WARNING );

      $counter++;
      sleep ( $interval * $counter * $counter );
    }
    if ( is_null ( $this->error['level'] ) ) {
      return $this->set_std_error ('xfererror' );
    }
    return false;
  }

  private function wiki_xfer ( $url, $vars = NULL, $files = NULL, $checkreply_func = NULL ) {
    return ( $this->xfer ( $url, $vars, $files, $this->login['wiki']['http-auth'],
      $this->browser_compression, $this->browser_agent, $this->bot_params['limits'],
      $this->login['wiki']['retries']['link_error'],
      $this->login['wiki']['interval']['link_error'],
      $checkreply_func ) !== false );
  }

  # ----- Generic API request ----- #

  private function checkreply_api () {
    $this->data_tree = unserialize ( $this->browser->content );
    if ( $this->data_tree === false ) {
      if ( preg_match ( '/Unexpected non-MediaWiki exception encountered\, of type \&quot\;(.*)\&quot\;\<br \/\>(.*)\<br \/\>/Uus', $this->browser->content, $matches ) ) {
        $err_info = ": " . trim ( $matches[1] ) . " (" . trim ( $matches[2] ) . ")";
        if ( preg_match ( '/^unknown_action:/U', trim ( $matches[2] ), $matches ) ) {
          $err_info.= ' (is $wgEnableWriteAPI enabled on this wiki?)';
        }
      } elseif ( preg_match ( "/\<b\>\s*Fatal error\s*\<\/b\>\s*\:(.*)$/Usi", $this->browser->content, $matches ) ) {
        $err_info = "Fatal error: " . trim ( $matches[1] );  // could be processed further
      } elseif ( preg_match ( '/\<title\>([^\<]+)\<\/title\>/ui', $this->browser->content, $matches ) ) {
        $err_info = "Technical problem: " . $matches[1];
      }
      return $this->set_std_error ( 'api_error', $err_info );
    } elseif ( ! is_array ( $this->data_tree ) ) {  // should not occur, but just in case...
      return $this->set_std_error ( 'api_error' );
    } elseif ( array_key_exists ( 'error', $this->data_tree ) ) {
      $this->set_std_error ( 'genericerror', $this->data_tree['error']['info'],
        $this->data_tree['error']['code'], 3, LL_ERROR );
    }
    return true;
  }

  protected function api ( $action ) {
    $this->params['format'] = "php";
    $this->params['action'] = $action;

    if ( $this->dump_mode ) {
      echo "Params: "; print_r ( $this->params );
      echo "Files: "; print_r ( $this->fileparams );
    }

    $result = $this->wiki_xfer ( $this->login['wiki']['api_url'], $this->params,
      $this->fileparams, array ( $this, "checkreply_api" ) );
    // checkreply_api() also unserializes the request result

    if ( $this->dump_mode ) print_r ( $this->data_tree );

    $this->params = array();
    $this->fileparams = array();

    return $result;
  }

  # ----- Generic web request ----- #

  private function checkreply_web () {
    if ( strripos ( $this->browser->content, '</html>' ) === false ) {
      return $this->set_std_error ( 'web_error' );
    } else {
      return true;
    }
  }

  protected function web ( $title, $action, $vars = NULL, $files = NULL, $extra_params = NULL ) {
    if ( ! is_array ( $extra_params ) ) { $extra_params = array(); }
    if ( ! empty ( $title ) ) { $extra_params['title'] = $title; }
    if ( ! empty ( $action ) ) { $extra_params['action'] = $action; }

    foreach ( $extra_params as $name => $value ) {
      if ( empty ( $params ) ) { $params = '?'; } else { $params .= '&'; }
      $params .= urlencode ( $name ) . "=" . urlencode ( $value );
    }

    $url = str_replace ( "api.php", "index.php", $this->login['wiki']['api_url'] ) .
      $params;

    if ( $this->dump_mode ) {
      echo "URL: " . $url;
      echo "Params: "; print_r ( $this->vars );
      echo "Files: "; print_r ( $this->files );
    }

    $result = $this->wiki_xfer ( $url, $vars, $files, array ( $this, "checkreply_web" ) );

    if ( $this->dump_mode ) echo $this->browser->contents;
  }

  # ---------- API requests - first level ---------- #

  protected function api_action_login           () { return $this->api ( "login"      ); }
  protected function api_action_logout          () { return $this->api ( "logout"     ); }
  protected function api_action_query           () { return $this->api ( "query"      ); }
  protected function api_action_edit            () { return $this->api ( "edit"       ); }
  protected function api_action_move            () { return $this->api ( "move"       ); }
  protected function api_action_rollback        () { return $this->api ( "rollback"   ); }
  protected function api_action_delete          () { return $this->api ( "delete"     ); }
  protected function api_action_undelete        () { return $this->api ( "undelete"   ); }
  protected function api_action_protect         () { return $this->api ( "protect"    ); }
  protected function api_action_block           () { return $this->api ( "block"      ); }
  protected function api_action_unblock         () { return $this->api ( "unblock"    ); }
  protected function api_action_watch           () { return $this->api ( "watch"      ); }
  protected function api_action_emailuser       () { return $this->api ( "emailuser"  ); }
  protected function api_action_patrol          () { return $this->api ( "patrol"     ); }
  protected function api_action_import          () { return $this->api ( "import"     ); }
  protected function api_action_userrights      () { return $this->api ( "userrights" ); }
  protected function api_action_expandtemplates () { return $this->api ( "expandtemplates" ); }
  protected function api_action_parse           () { return $this->api ( "parse"      ); }
  protected function api_action_upload          () { return $this->api ( "upload"     ); }
  protected function api_action_purge           () { return $this->api ( "purge"      ); }
  protected function api_action_paraminfo       () { return $this->api ( "paraminfo"  ); }

  # ---------- API requests - second level (where needed) ---------- #

  # ----- Queries ----- #

  # --- Appending page properties --- #

  protected function append_prop ( $properties, $prop, $code ) {
    if ( isset ( $properties[$prop] ) ) {
      $this->append_param ( 'prop', $prop );
      $this->append_params_array ( $properties[$prop], $code );
    }
  }

  protected function append_properties ( $properties ) {
    foreach ( $this->APIBOT_PAGE_PROPERTIES as $name => $prefix ) {
      $this->append_prop ( $properties, $name, $prefix );
    }
  }

  # --- General --- #

  public function query_tree () {
    return $this->data_tree['query'];
  }

  public function query_limits ( $what_for = NULL ) {
    if ( empty ( $this->data_tree['limits'] ) ) return false;
    if ( is_null ( $what_for ) ) {
      return $this->data_tree['limits'];
    } else {
      return ( empty ( $this->data_tree['limits'][$what_for] ) ? false : $this->data_tree['limits'][$what_for] );
    }
  }

  public function query_normalized ( $what = NULL ) {
    if ( empty ( $this->data_tree['query']['normalized'] ) ) return false;
    if ( is_null ( $what ) ) {
      return $this->data_tree['query']['normalized'];
    } else {
      foreach ( $this->data_tree['query']['normalized'] as $normalize ) {
        if ( $normalize['from'] == $what ) return $normalize['to'];
      }
      return false;
    }
  }

  public function query_is_exhausted () {
    return empty ( $this->states['query']['continues'] );
  }

  protected function api_query () {

    if ( is_array ( $this->states['query']['params'] ) ) {
      $this->append_params_array ( $this->states['query']['params'] );
    }
    if ( ! empty ( $this->states['query']['listparams'] ) ) {
      $this->append_params_array ( $this->states['query']['listparams'], $this->states['query']['listparams_code'] );
    }
    if ( is_array ( $this->states['query']['properties'] ) ) {
      $this->append_properties ( $this->states['query']['properties'] );
    }

    if ( is_array ( $this->states['query']['continues'] ) ) {
      foreach ( $this->states['query']['continues'] as $property => $continue ) {
        if ( is_array ( $continue ) ) {
          foreach ( $continue as $propname => $token ) {
            switch ( $propname ) {
              case 'rvstartid' :
                unset ( $this->params['rvstart'] ); break;
              case 'alcontinue' :
                unset ( $this->params['alfrom'] ); break;
              case 'aicontinue' :
                unset ( $this->params['aifrom'] ); break;
              default :
                unset ( $this->params[$propname] );
            }
            if ( ! empty ( $token ) ) {
              $this->params[$propname] = $token;
            }
          }
        }
      }
    }

    $result = $this->api_action_query();

    $this->states['query']['continues'] =
      ( isset ( $this->data_tree['query-continue'] ) ? $this->data_tree['query-continue'] : NULL );

    // properties are continued to exhaustion on single-page, but not on multiple-page or list or generator requests!
    $is_single_page = empty ( $this->states['query']['listparams'] ) &&
         ( strpos ( '|', $this->states['query']['params']['titles' ] ) === false ) &&
         ( strpos ( '|', $this->states['query']['params']['pageids'] ) === false ) &&
         ( strpos ( '|', $this->states['query']['params']['revids' ] ) === false );

    if ( ! $is_single_page && is_array ( $this->states['query']['continues'] ) ) {
      foreach ( $this->APIBOT_PAGE_PROPERTIES as $name => $prefix ) {
        unset ( $this->states['query']['continues'][$name] );
      }
    }

    if ( $is_single_page && is_array ( $this->states['query']['properties'] ) ) {
      foreach ( $this->states['query']['properties'] as $property => &$value ) {
        if ( ! is_array ( $this->states['query']['continues'] ) ||
             ! array_key_exists ( $property, $this->states['query']['continues'] ) ) {
          unset ( $this->states['query']['properties'][$property] );
        }
      }
    }

    return $result;
  }

  protected function api_start_query ( $params ) {
    $this->states['query']['params'] = $params;
    $this->states['query']['continues'] = NULL;
    return $this->api_query();
  }

  # --- Page queries --- #

  protected function api_query_pages ( $queryparam, $queryvalue, $properties ) {
    $this->states['query']['properties'] = $properties;
    unset ( $this->states['query']['listparams'] );
    unset ( $this->states['query']['listparams_code'] );
    return $this->api_start_query ( array ( $queryparam => $queryvalue ) );
  }

  protected function api_query_titles ( $titles, $properties = NULL ) {
    return $this->api_query_pages ( 'titles', $titles, $properties );
  }

  protected function api_query_pageids ( $pageids, $properties = NULL ) {
    return $this->api_query_pages ( 'pageids', $pageids, $properties );
  }

  protected function api_query_revids ( $revids, $properties = NULL ) {
    return $this->api_query_pages ( 'revids', $revids, $properties );
  }

  # --- List and generator queries --- #

  protected function api_query_list ( $list, $code, $listparams = NULL, $params = NULL ) {
    $this->states['query']['listparams'] = $listparams;
    $this->states['query']['listparams_code'] = $code;
    $params['list'] = $list;
    return $this->api_start_query ( $params );
  }

  protected function api_query_generator ( $generator, $code, $listparams = NULL, $properties = NULL, $params = NULL ) {
    $this->states['query']['properties'] = $properties;
    $this->states['query']['listparams'] = $listparams;
    $this->states['query']['listparams_code'] = 'g' . $code;
    $params['generator'] = $generator;
    return $this->api_start_query ( $params );
  }

  # --- Meta queries --- #

  protected function api_query_meta ( $meta ) {
    return $this->api_start_query ( array ( 'meta' => $meta ) );
  }

  protected function api_query_siteinfo ( $siprop = NULL ) {
    $this->append_param ( 'siprop', $siprop );
    return $this->api_query_meta ( "siteinfo" );
  }

  protected function api_query_userinfo ( $uiprop = NULL ) {
    $this->append_param ( 'uiprop', $uiprop );
    return $this->api_query_meta ( "userinfo" );
  }

  protected function api_query_messages ( $ammessages = NULL, $amfilter = NULL, $amlang = NULL ) {
    $this->append_param_if_nonnull ( 'ammessages', $ammessages );
    $this->append_param_if_nonnull ( 'amfilter', $amfilter );
    $this->append_param_if_nonnull ( 'amlang', $amlang );
    return $this->api_query_meta ( "allmessages" );
  }

  # ---------- API requests - third level ---------- #

  # ----- Obtaining tokens ----- #

  protected function api_get_token () {
    $properties = array ( 'info' => array ( 'token' => 'edit' ) );
    if ( $this->api_query_titles ( $this->wiki['general']['mainpage'], $properties ) ) {
      $pagedesc = current ( $this->data_tree['query']['pages'] );
      $this->wiki['token'] = $pagedesc['edittoken'];
      return true;
    }
    return $this->set_std_error ( 'notoken', " (edit)" );
  }

  protected function api_get_token_if_needed () {
    if ( empty ( $this->wiki['token'] ) ) {
      return $this->api_get_token();
    } else {
      return true;
    }
  }

  protected function api_get_rollbacktoken ( $title ) {
    $this->append_param ( 'rvtoken', "rollback" );
    $this->append_param ( 'prop', "revisions" );
    if ( $this->api_query_titles ( $title ) ) {
      $pagedesc = reset ( $this->data_tree['query']['pages'] );
      $lastrev = reset ( $pagedesc['revisions'] );
      if ( ! empty ( $lastrev['rollbacktoken'] ) ) {
        return $lastrev['rollbacktoken'];
      }
    }
    return $this->set_std_error ( 'notoken', " (rollback)" );
  }

  protected function api_get_userrights_token ( $user ) {
    $parameters = array ( 'ususers' => $user, 'ustoken' => "userrights" );
    if ( $this->api_query_list ( "users", 'us', $parameters ) ) {
      $userdesc = reset ( $this->data_tree['query']['users'] );
      if ( ! empty ( $userdesc['userrightstoken'] ) ) {
        return $userdesc['userrightstoken'];
      }
    }
    return $this->set_std_error ( 'notoken', " (userrights)" );
  }

  # ----- Meta info ----- #

  protected function api_siteinfo_general () {
    if ( $this->api_query_siteinfo ( array ( 'general' ) ) ) {
      $this->wiki['general'] = $this->data_tree['query']['general'];
      return true;
    } else {
      return false;
    }
  }

  protected function api_siteinfo () {  // fetch paraminfo first, or you'll end with barebones siteinfo!
    $wiki_version = $this->mw_version_number();
    if ( array_key_exists ( 'paraminfo', $this->wiki ) &&
         array_key_exists ( 'querymodules', $this->wiki['paraminfo'] ) &&
         array_key_exists ( 'siteinfo', $this->wiki['paraminfo']['querymodules'] ) ) {
      $properties = $this->wiki['paraminfo']['querymodules']['siteinfo']['parameters']['prop']['type'];
    } else {
      $properties = array ( "namespaces", "statistics", "interwikimap", "dbrepllag" );
    }
    if ( $this->api_query_siteinfo ( $properties ) ) {
      $this->wiki = array_merge ( $this->wiki, $this->query_tree() );
      return true;
    } else {
      return false;
    }
  }

  // Relying on obtained wiki siteinfo!
  protected function api_userinfo () {
    $wiki_version = $this->mw_version_number();
    if ( array_key_exists ( 'paraminfo', $this->wiki ) &&
         array_key_exists ( 'querymodules', $this->wiki['paraminfo'] ) &&
         array_key_exists ( 'userinfo', $this->wiki['paraminfo']['querymodules'] ) ) {
      $properties = $this->wiki['paraminfo']['querymodules']['userinfo']['parameters']['prop']['type'];
    } else {
      $properties = array ( "blockinfo", "hasmsg", "groups", "rights" );
    }
    if ( $this->api_query_userinfo ( $properties ) ) {
      if ( $wiki_version < 11200 ) {
        $this->user = $this->data_tree['userinfo'];
      } else {
        $this->user = $this->data_tree['query']['userinfo'];
      }
      return true;
    } else {
      return false;
    }
  }

  protected function api_messages ( $ammessages = NULL, $amfilter = NULL, $amlang = NULL ) {
    if ( $this->api_query_messages ( $ammessages, $amfilter, $amlang ) ) {
      $this->wiki['messages'] = $this->data_tree['query']['allmessages'];
      return true;
    }
    return false;
  }

  protected function web_obtain_wikitime () {
    $title = md5 ( rand() ); // make up a long random title
    if ( $this->web ( $title, "edit" ) ) {
      if ( preg_match ( '/\<input\s[^\>]*name="wpStarttime"[^\>]*\>/U', $this->browser->content, $matches ) ) {
        if ( preg_match ( '/value="(\d+)"/U', $matches[0], $matches ) ) {
          $timestr = preg_replace ( '/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', "$1-$2-$3 $4:$5:$6", $matches[1] );
          return strtotime ( $timestr );
        }
      }
    }
    return NULL;
  }

  # ----- Paraminfo ----- #

  private function rekey_paraminfo_module_array ( &$module, $arrayname ) {
    $array = array();
    if ( is_array ( $module ) && array_key_exists ( $arrayname, $module ) && is_array ( $module[$arrayname] ) ) {
      foreach ( $module[$arrayname] as $element ) {
        $array[$element['name']] = $element;
      }
      $module[$arrayname] = $array;
    }
  }

  private function rekey_paraminfo_module_arrays ( &$module ) {
    $this->rekey_paraminfo_module_array ( $module, 'parameters' );
  }

  private function adopt_paraminfo_module ( &$to_array, $from_array, $data_tree_key ) {
    $module = $from_array[$data_tree_key];
    $this->rekey_paraminfo_module_arrays ( $module );
    $to_array[$module['name']] = $module;
  }

  protected function api_paraminfo_paraminfo () {
    if ( $this->mw_version_number() >= 11200 ) {
      $this->append_param ( 'modules', "paraminfo" );
      if ( $this->api_action_paraminfo() ) {
        $this->adopt_paraminfo_module ( $this->wiki['paraminfo']['modules'],
          $this->data_tree['paraminfo']['modules'], 0 );
        return true;
      }
    }
    return $this->set_std_error ( 'noparaminfo' );
  }

  protected function api_paraminfo_mainmodule () {
    if ( ! empty ( $this->wiki['paraminfo']['modules']['paraminfo']['parameters']['mainmodule'] ) ) {
      $this->append_param ( 'mainmodule', "" );
      if ( $this->api_action_paraminfo() ) {
        $this->wiki['paraminfo']['mainmodule'] =
          $this->data_tree['paraminfo']['mainmodule'];
        $this->rekey_paraminfo_module_arrays ( $this->wiki['paraminfo']['mainmodule'] );
        return true;
      }
    }
    return false;
  }

  protected function api_paraminfo_modules () {
    if ( empty ( $this->wiki['paraminfo']['mainmodule'] ) ) {
      $modules = "query|login|logout|edit|move|delete|undelete|rollback|" .
        "protect|block|unblock|watch|emailuser|patrol|import|" .
        "expandtemplates|parse|upload|purge|userrights";
    } else {
      $modules = $this->barsepstring (
        $this->wiki['paraminfo']['mainmodule']['parameters']['action']['type'] );
      $modules = str_replace ( '|paraminfo', '', $modules );  // obtained in advance
    }

    if ( strpos ( $this->wiki['general']['generator'], '1.16' ) !== false ) {
      $modules = str_replace ( '|userrights', '', $modules );  // workaround a MW 1.16 bug
    } elseif ( strpos ( $this->wiki['general']['generator'], '1.17' ) !== false ) {
      $modules = str_replace ( '|upload', '', $modules );  // workaround a MW 1.17 bug
    }

    while ( true ) {
      $this->append_param ( 'modules', $modules );
      if ( ! empty ( $this->wiki['paraminfo']['modules']['paraminfo']['parameters']['pagesetmodule'] ) ) {
        $this->append_param ( 'pagesetmodule', "" );
      }

      // mainmodule is planned to be obtained in advance
      $result = $this->api_action_paraminfo();
      if ( ! empty ( $this->error['code'] ) ) {
        return false;
      } else {
        break;  // all is OK
      }

    }

    if ( $result ) {
      foreach ( $this->data_tree['paraminfo']['modules'] as $key => $module ) {
        $this->adopt_paraminfo_module ( $this->wiki['paraminfo']['modules'],
          $this->data_tree['paraminfo']['modules'], $key );
      }
      $this->wiki['paraminfo']['pagesetmodule'] = $this->data_tree['paraminfo']['pagesetmodule'];
      $this->rekey_paraminfo_module_arrays ( $this->wiki['paraminfo']['pagesetmodule'] );
    } else {
      return $this->set_std_error ( 'nopimodules' );
    }
  }

  protected function api_paraminfo_querymodules () {
    if ( empty ( $this->wiki['paraminfo']['modules']['query'] ) ) {
      $querymodules = "info|revisions|links|langlinks|images|imageinfo|stashimageinfo" .
        "|templates|categories|extlinks|categoryinfo|duplicatefiles|globalusage" .
        "|allimages|allpages|alllinks|allcategories|allusers|backlinks|blocks" .
        "|categorymembers|deletedrevs|embeddedin|imageusage|logevents" .
        "|recentchanges|search|tags|usercontribs|watchlist|watchlistraw" .
        "|exturlusage|users|random|protectedtitles|globalblocks" .
        "|siteinfo|userinfo|allmessages|globaluserinfo";
    } else {
      $querymodules = $this->barsepstring (
        array_merge (
          $this->wiki['paraminfo']['modules']['query']['parameters']['prop']['type'],
          $this->wiki['paraminfo']['modules']['query']['parameters']['list']['type'],
          $this->wiki['paraminfo']['modules']['query']['parameters']['meta']['type']
        )
      );
    }
    $this->append_param ( 'querymodules', $querymodules );
    if ( $this->api_action_paraminfo() ) {
      foreach ( $this->data_tree['paraminfo']['querymodules'] as $key => $module ) {
        $this->adopt_paraminfo_module ( $this->wiki['paraminfo']['querymodules'],
          $this->data_tree['paraminfo']['querymodules'], $key );
      }
    } else {
      return $this->set_std_error ( 'nopiqmodules' );
    }
  }

  protected function api_paraminfo () {
    if ( $this->api_paraminfo_paraminfo() ) {
      $this->api_paraminfo_mainmodule();
      $this->api_paraminfo_modules();
      $this->api_paraminfo_querymodules();
    }
  }

  # ----- Login ----- #

  protected function api_login ( $user = NULL, $pass = NULL, $domain = NULL, $token = NULL ) {

    $attempts_count = 0;
    while ( $attempts_count < 5 ) {
      $attempts_count += 1;

      $this->params = array ( 'lgname' => $user, 'lgpassword' => $pass );
      $this->append_param_if_nonnull ( 'lgdomain', $domain );
      $this->append_param_if_nonnull ( 'lgtoken' , $token  );
      $result = $this->api_action_login ();

      if ( $result ) {
        switch ( $this->data_tree['login']['result'] ) {
          case "Success"   : return $result;
          case "NeedToken" :
            $token = $this->data_tree['login']['token'];
            $attempts_count -= 1;
            break;
          case "Throttled" :
            $throttled_wait = ( $this->data_tree['login']['wait'] / 10 ) + 10;  // to be on the safe side
            $this->log ( "Throttled - waiting for " . $throttled_wait . " secs...", LL_INFO );
            sleep ( $throttled_wait );
            break;
          default :
            return $this->set_std_error ( 'genericerror', $this->data_tree['login']['details'],
              $this->data_tree['login']['result'] );
        }
      }
    }
    return $result;
  }

  # ----- Logout ----- #

  protected function api_logout () {
    return $this->api_action_logout();
  }

  # ----- Edit ----- #

  protected function web_edit ( $timestamp, $fetchtimestamp,
    $title, $text, $section, $summary, $isminor = NULL, $bot = true,
    $watch = 'preferences', $recreate = false ) {

    $this->api_get_token_if_needed();

    $edittime  = preg_replace ( '/[\-\s\:TZ]/', '', $timestamp );
    $starttime = preg_replace ( '/[\-\s\:TZ]/', '', $fetchtimestamp );

    if ( $watch === true ) { $watch = 'watch'; }
    elseif ( $watch === false ) { $watch = 'unwatch'; }

    $vars = array(
      'wpEditToken'   => $this->wiki['token'],
      'wpTextbox1'    => $text,
      'wpSummary'     => $summary,
      'wpStarttime'   => $starttime,
      'wpEdittime'    => $edittime,
      'wpSection'     => $section,
      'wpWatchthis'   => $watch,
      'wpIgnoreBlankSummary' => 1,  // accept edit even if summary is blank or not changed
#      'wpAutoSummary' => md5 ( $summary ),  // does not make any difference
#      'wpScrolltop'   => 0,  // does not make any difference
    );
    $vars['wpMinoredit'] = ( $isminor  ? 1 : 0 );
    $vars['wpRecreate' ] = ( $recreate ? 1 : 0 );

    if ( $this->web ( $title, "submit", $vars ) ) {
      if ( ! preg_match ('/content *= *"noindex,nofollow"/Uus', $this->browser->content ) ) {
        return true;
      } else {
        if ( strpos ( $this->browser->content, 'pt-login' ) !== false ) {
          $this->set_std_error ( 'notloggedin' );
        } elseif ( preg_match ( '/\<textarea [^\>]*id=[\'\"]wpTextbox2[\'\"]/Uus', $this->browser->content, $matches ) ) {
          $this->set_std_error ( 'editconflict' );
        } elseif ( preg_match ( '/\<input [^\>]*id=[\'\"]wpRecreate[\'\"]/Uus', $this->browser->content, $matches ) ) {
          $this->set_std_error ( 'pagedeleted' );
        } else {
          $this->set_std_error ( 'unknownreason' );
        }
        return false;
      }
    } else {
      return false;
    }
  }

  protected function api_edit ( $basetimestamp, $starttimestamp,
    $title, $text, $section, $summary, $isminor = NULL, $bot = true,
    $watch = 'preferences', $createonly = false, $nocreate = false ) {

    if ( $this->mw_version_and_token_ok ( 11303 ) ) {
      $this->append_param_if_nonnull ( "basetimestamp", $basetimestamp );
      $this->append_param_if_nonnull ( "starttimestamp", $starttimestamp );
      $this->append_param ( "title", $title );
      $this->append_param ( "text", $text );
      $this->append_param ( "summary", $summary );
      $this->append_param_if_nonnull ( "section", $section );
      if ( $isminor === true ) {
        $this->append_param ( "minor" );
      } elseif ( $isminor === false ) {
        $this->append_param ( "notminor" );
      }
      if ( $bot ) {
        $this->append_param ( "bot" );
      }
//      $this->append_param ( "md5", md5 ( $page->text ) );  // couldn't get it working - 2009-10-05
      if ( ! empty ( $watch ) ) { $this->append_param ( $watch ); }
      $this->append_param ( "recreate" );  // useful - a bot should know well.
      if ( $createonly ) { $this->append_param ( "createonly" ); }
      if ( $nocreate   ) { $this->append_param ( "nocreate"   ); }

      return $this->api_action_edit();
    } else {
      return false;
    }
  }

  protected function api_undo ( $basetimestamp, $title, $revert_revid, $to_revid = NULL, $summary = NULL, $bot = true ) {
    if ( $this->mw_version_and_token_ok ( 11303 ) ) {
      $this->append_param_if_nonnull ( "basetimestamp", $basetimestamp );
      $this->append_param ( "title", $title );
      $this->append_param ( "summary", $summary );
      $this->append_param ( "undo", $revert_revid );
      $this->append_param_if_nonnull ( "undoafter", $to_revid );
      $this->append_param_if_true ( "bot", $bot );
      $this->append_param ( "recreate" );  // useful - a bot should know well.

      return $this->api_action_edit();
    } else {
      return false;
    }
  }

  # ----- Move ----- #

  protected function api_move ( $from, $to, $reason = NULL, $noredirect = false, $movetalk = true ) {
    if ( $this->mw_version_and_token_ok ( 11200 ) ) {
      $this->append_param ( "from", $from );
      $this->append_param ( "to", $to );
      $this->append_param ( "reason", $reason );
      $this->append_param_if_true ( "noredirect", $noredirect );
      $this->append_param_if_true ( "movetalk", $movetalk );

      return $this->api_action_move();
    } else {
      return false;
    }
  }

  # ----- Delete ----- #

  protected function web_delete ( $title, $reason = NULL ) {
    $vars = array (
      'wpReason'    => $reason,
      'wpConfirmB'  => 1,
      'wpEditToken' => $this->wiki['token'],
    );
    if ( $this->web ( $title, "delete", $vars ) ) {
      if ( preg_match ( '/\<div\s+[^\>]*class="permissions-errors"/Usi', $this->browser->content ) ) {
        $this->set_std_error ( 'permissiondenied' );
      } else {
        return true;
      }
    }
    return false;
  }

  protected function api_delete ( $title, $reason = NULL ) {
    if ( $this->mw_version_and_token_ok ( 11200 ) ) {
      $this->append_param ( "title" , $title );
      $this->append_param ( "reason", $reason );

      return $this->api_action_delete();
    } else {
      return false;
    }
  }

  # ----- Undelete ----- #

  protected function api_undelete ( $title, $reason = NULL, $timestamps = NULL ) {
    if ( $this->mw_version_and_token_ok ( 11200 ) ) {
      $this->append_param ( "title", $title );
      $this->append_param ( "reason", $reason );
      $this->append_param ( "timestamps", $this->barsepstring ( $timestamps ) );

      return $this->api_action_undelete();
    } else {
      return false;
    }
  }

  # ----- Rollback ----- #

  protected function api_rollback ( $title, $user, $summary = NULL, $bot = true, $token = NULL ) {
    if ( $this->mw_version_number() >= 11200 ) {
      if ( is_null ( $token ) ) {
        $token = $this->api_get_rollbacktoken ( $title );
      }
      if ( $token === false ) {
        return $this->set_std_error ( 'notoken', " (rollback)" );
      } else {
        $this->append_param ( "token", $token );
        $this->append_param ( "title", $title );
        $this->append_param ( "user", $user );
        $this->append_param ( "summary", $summary );
        $this->append_param_if_true ( "markbot", $bot );

        return $this->api_action_rollback();
      }
    } else {
      return false;
    }
  }

  # ----- Protect ----- #

  protected function api_protect ( $title, $protections, $expiry = NULL, $reason = NULL, $cascade = false ) {
    if ( $this->mw_version_and_token_ok ( 11200 ) ) {
      $this->append_param ( "title", $title );
      $this->append_param ( "protections", $this->keyequals_barsepstring ( $protections ) );
      $this->append_param ( "expiry", $expiry );
      $this->append_param ( "reason", $reason );
      $this->append_param_if_true ( "cascade", $cascade );

      return $this->api_action_protect();
    } else {
      return false;
    }
  }

  # ----- Block ----- #

  protected function web_block ( $user, $expiry = "never", $reason = NULL, $anononly = false, $nocreate = false, $autoblock = false, $noemail = false ) {
    $this->api_get_token_if_needed();

    $vars = array(
      'wpEditToken'       => $this->wiki['token'],
      'wpBlockAddress'    => $user,
      'wpBlockExpiry'     => "other",
      'wpBlockOther'      => $expiry,
      'wpBlockReasonList' => "other",
      'wpBlockReason'     => $reason,
      'wpAnonOnly'        => ( $anononly  ? "1" : "" ),
      'wpCreateAccount'   => ( $nocreate  ? "1" : "" ),
      'wpEnableAutoblock' => ( $autoblock ? "1" : "" ),
      'wpEmailBan'        => ( $noemail   ? "1" : "" ),
    );

    if ( $this->web ( "Special:Blockip", "submit", $vars ) ) {
      if ( preg_match ( '/\<a +href=[^\>]+\:Contributions\/[^\>]+\:Contributions/U', $this->browser->content ) ) {  // todo! check with failing blocks!
        return true;
      } else {
        if ( strpos ( $this->browser->content, 'pt-login' ) !== false ) {  // todo! check with failing blocks!
          $this->set_std_error ( 'notloggedin' );
        } else {
          $this->set_std_error ( 'unknownreason' );
        }
        return false;
      }
    } else {
      return false;
    }
  }

  protected function api_block ( $user, $expiry = "never", $reason = NULL, $anononly = false, $nocreate = false, $autoblock = false, $noemail = false ) {
    if ( $this->mw_version_and_token_ok ( 11200 ) ) {
      $this->append_param ( "user", $user );
      $this->append_param ( "expiry", $expiry );
      $this->append_param ( "reason", $reason );
      $this->append_param_if_true ( "anononly" , $anononly  );
      $this->append_param_if_true ( "nocreate" , $nocreate  );
      $this->append_param_if_true ( "autoblock", $autoblock );
      $this->append_param_if_true ( "noemail"  , $noemail   );

      return $this->api_action_block();
    } else {
      return false;
    }
  }

  # ----- Unblock ----- #

  protected function web_unblock ( $user, $block_id, $reason = NULL ) {
    $this->api_get_token_if_needed();

    $vars = array(
      'wpEditToken'      => $this->wiki['token'],
      'wpUnblockAddress' => $user,
//      'id'               => $block_id,  // not used in the web unblock
      'wpUnblockReason'  => $reason,
    );

    if ( $this->web ( "Special:Ipblocklist", "submit", $vars ) ) {
      if ( preg_match ( '/\<div +id *= *"contentSub"\>.*\<a href=[^\>]+\:' . $user .
        '[^\>]*title="[^\>]*\:' . $user . '"[^\>]*\>' . $user . '\<\/a\>/U',
        $this->browser->content ) ) {

        return true;
      } else {
        if ( strpos ( $this->browser->content, 'pt-login' ) !== false ) {
          $this->set_std_error ( 'notloggedin' );
        } else {
          $this->set_std_error ( 'unknownreason' );
        }
        return false;
      }
    } else {
      return false;
    }
  }

  protected function api_unblock ( $user, $block_id, $reason = NULL ) {
    if ( $this->mw_version_and_token_ok ( 11200 ) ) {
      if ( empty ( $user ) ) {
        if ( empty ( $block_id ) ) {
          return $this->set_std_error ( 'noblockidoruser' );
        } else {
          $this->append_param ( "id", $block_id );
        }
      } else {
        $this->append_param ( "user", $user );
      }
      $this->append_param ( "reason", $reason );

      return $this->api_action_unblock();
    } else {
      return false;
    }
  }

  # ----- Watch ----- #

  protected function api_watch ( $title, $watch = true ) {
    if ( $this->mw_version_number() >= 11400 ) {
      $this->append_param ( "title", $title );
      $this->append_param_if_true ( "unwatch", ( ! $watch ) );
      return $this->api_action_watch();
    } else {
      return false;
    }
  }

  # ----- Emailuser ----- #

  protected function api_emailuser ( $target, $subject, $text, $ccme = false ) {
    if ( $this->mw_version_and_token_ok ( 11400 ) ) {
      $this->append_param ( "target" , $target );  // the user you are sending email to
      $this->append_param ( "subject", $subject );
      $this->append_param ( "text"   , $text );
      $this->append_param_if_true ( "ccme", $ccme );
      return $this->api_action_emailuser();
    } else {
      return false;
    }
  }

  # ----- Patrol ----- #

  protected function web_patrol ( $rcid ) {
    if ( $this->web ( NULL, "markpatrolled", array ( 'rcid' => $rcid ) ) ) {
      if ( preg_match ( '/\<div\s+[^\>]*class="permissions-errors"/Usi', $this->browser->content ) ) {
        $this->set_std_error ( 'permissiondenied' );
      } else {
        return true;
      }
    }
    return false;
  }

  protected function api_patrol ( $rcid ) {
    $this->append_param ( "rcid" , $rcid );
    return $this->api_action_patrol();
  }

  # ----- Import ----- #

  protected function api_import_interwiki ( $title, $iwcode, $summary, $fullhistory = true, $into_namespace = NULL, $templates = false ) {
    if ( $this->mw_version_and_token_ok ( 11500 ) ) {
      $this->append_param ( 'interwikititle' , $title );
      $this->append_param ( 'interwikisource' , $iwcode );
      $this->append_param ( 'summary' , $summary );
      $this->append_param_if_nonnull ( $into_namespace );
      $this->append_param_if_true ( 'fullhistory' , $fullhistory );
      $this->append_param_if_true ( 'templates'   , $templates   );
      return $this->api_action_import();
    } else {
      return false;
    }
  }

  protected function api_import_xml ( $xml_upload, $summary ) {
    if ( $this->mw_version_and_token_ok ( 11500 ) ) {
      $this->append_param ( 'xml' , $xml_upload );
      $this->append_param ( 'summary' , $summary );
      return $this->api_action_import();
    } else {
      return false;
    }
  }

  # ----- Userrights ----- #

  protected function web_userrights ( $user, $add_groups, $remove_groups, $reason ) {  // todo! test it!
    $this->api_get_token_if_needed();

    $vars = array(
      'wpEditToken'   => $this->wiki['token'],
      'user'          => $user,
      'available'     => $add_groups,
      'removable'     => $remove_groups,
      'user-reason'   => $reason,
    );

    if ( $this->web ( "Special:Userrights", "submit", $vars ) ) {
      if ( ! preg_match ('/content *= *"noindex,nofollow"/', $this->browser->content ) ) {
        return true;
      } else {
        if ( strpos ( $this->browser->content, 'pt-login' ) !== false ) {
          $this->set_std_error ( 'notloggedin' );
        } else {
          $this->set_std_error ( 'unknownreason' );
        }
        return false;
      }
    } else {
      return false;
    }
  }

  protected function api_userrights ( $user, $add_groups, $remove_groups, $reason ) {
    if ( $this->mw_version_and_token_ok ( 11600 ) ) {
      $token = $this->api_get_userrightstoken ( $user );
      if ( ! ( $token === false ) ) {
        $this->append_param ( 'token' , $token );
        $this->append_param ( 'user'  , $user );
        $this->append_param ( 'add'   , $this->barsepstring ( $add_groups    ) );
        $this->append_param ( 'remove', $this->barsepstring ( $remove_groups ) );
        $this->append_param ( 'reason', $reason );
        return $this->api_action_userrights();
      } else {
        return $this->set_std_error ( 'notoken', " (userrights)" );
      }
    } else {
      return false;
    }
  }

  # ----- Expandtemplates ----- #

  protected function api_expandtemplates ( $text, $title = NULL ) {
    if ( $this->mw_version_and_token_ok ( 11200 ) ) {
      $this->append_param ( 'text', $text );
      $this->append_param_if_nonnull ( 'title', $title );
      return $this->api_action_expandtemplates();
    } else {
      return false;
    }
  }

  # ----- Parse ----- #

  protected function api_parse_text ( $text, $title = NULL, $properties = NULL, $pst = true, $uselang = NULL ) {
    if ( $this->mw_version_and_token_ok ( 11200 ) ) {
      $this->append_param ( 'text', $text );
      $this->append_param_if_nonnull ( 'title', $title );
      $this->append_param_if_nonnull ( 'prop' , $properties );
      $this->append_param_if_true ( 'pst', $pst );
      $this->append_param_if_nonnull ( 'uselang', $uselang );
      return $this->api_action_parse();
    } else {
      return false;
    }
  }

  protected function api_parse_page ( $title, $properties = NULL, $uselang = NULL ) {
    if ( $this->mw_version_and_token_ok ( 11200 ) ) {
      $this->append_param ( 'page', $title );
      $this->append_param_if_nonnull ( 'prop' , $properties );
      $this->append_param_if_nonnull ( 'uselang', $uselang );
      return $this->api_action_parse();
    } else {
      return false;
    }
  }

  # ----- Upload ----- #

  protected function web_upload_file ( $file, $text, $comment, $target_filename, $watch = false, $ignorewarnings = false ) {
    $this->api_get_token_if_needed();
    $vars = array(
      'wpUploadFile'        => "@" . $file,
      'wpSourceType'        => "file",
      'wpDestFile'          => $target_filename,
      'wpUploadDescription' => $text,
      'wpLicense'           => "",  // the API does not (yet) provide a documented param for this
      'wpComment'           => $comment,  // is this processed at all?
      'wpWatchthis'         => ( $watch ? '1' : '0' ),
      'wpIgnoreWarning'     => ( $ignorewarnings ? '1' : '0' ),
      'wpEditToken'         => $this->wiki['token'],
      'wpUpload'            => "Upload",
    );

    if ( $this->web ( "Special:Upload", "submit", $vars ) ) {
      if ( preg_match ( '/\<ul/s+class="warning"[^\>]*\>/Us', $this->browser_content, $matches ) ) {
        $this->log ( "Some warnings resulted during the web-mode upload of '" . $file . "'!", LL_WARNING );
      }
      return true;
    } else {
      return false;
    }
  }

  protected function api_upload_file ( $file, $text, $comment, $target_filename, $watch = false, $ignorewarnings = true ) {
    if ( $this->mw_version_and_token_ok ( 11600 ) ) {
      $this->append_param ( 'filename', $target_filename );
      $this->append_param ( 'comment', $comment );
      $this->append_param ( 'text', $text );
      $this->append_param_if_true ( 'watch', "" );
      $this->append_param_if_true ( 'ignorewarnings', "" );
      $this->append_fileparam ( 'file', $file );
      return $this->api_action_upload();
    } else {
      return false;
    }
  }

  protected function api_upload_url ( $URL, $text, $comment, $target_filename, $watch = false, $ignorewarnings = true ) {
    if ( $this->mw_version_and_token_ok ( 11600 ) ) {
      $this->append_param ( 'filename', $target_filename );
      $this->append_param ( 'comment', $comment );
      $this->append_param ( 'text', $text );
      $this->append_param_if_true ( 'watch', "" );
      $this->append_param_if_true ( 'ignorewarnings', "" );
      $this->append_param ( 'url', $URL );
      return $this->api_action_upload();
    } else {
      return false;
    }
  }

  protected function api_upload_sessionkey ( $filename, $sessionkey, $httpstatus = NULL, $ignorewarnings = true ) {
    if ( $this->mw_version_and_token_ok ( 11600 ) ) {
      $this->append_param ( 'filename', $filename );
      $this->append_param ( 'sessionkey', $sessionkey );
      $this->append_param_if_true ( 'ignorewarnings', $ignorewarnings );
      $this->append_param_if_nonnull ( 'httpstatus', $httpstatus );
      return $this->api_action_upload();
    } else {
      return false;
    }
  }

  # ----- Purge ----- #

  protected function api_purge ( $titles ) {
    if ( $this->mw_version_and_token_ok ( 11400 ) ) {
      $this->append_param ( 'titles', $this->barsepstring ( $titles ) );
      return $this->api_action_purge();
    } else {
      return false;
    }
  }

  # ---------- Obtaining initial info ---------- #

  # ----- Overlaying the API calls ----- #

  protected function fetch_wikiinfo_general () {
    if ( $this->api_siteinfo_general () ) {
      $this->wiki['general']['time'] =
        substr ( $this->wiki['general']['time'], 0, 10 ) . " " .
        substr ( $this->wiki['general']['time'], 11, 8 );
      $this->wiki['general']['timediff'] = strtotime ( $this->wiki['general']['time'] ) - $this->browser->lastreq_time();
      $this->log ( "Connected to: " . $this->wiki_name() . " (" . $this->wiki_generator() . ")", LL_DEBUG );
      return true;
    } else {
      $this->log ( "Could not obtain general wiki info for " . $this->login['wiki']['name'] . "!", LL_WARNING );
      return false;
    }
  }

  protected function fetch_paraminfo () {
    $this->api_paraminfo();
    return $this->log_status ( "Obtained wiki paraminfo",
      "Could not obtain wiki paraminfo: \$info", LL_DEBUG, LL_ERROR );
  }

  protected function fetch_wikiinfo () {
    $wiki_general = $this->wiki['general'];
    if ( $this->api_siteinfo () ) {
      if ( ! empty ( $wiki_general ) ) $this->wiki['general'] = $wiki_general;
      $wikiprops = implode ( ", ", array_keys ( $this->wiki ) );
      $this->log ( "Wiki info obtained: " . $wikiprops, LL_DEBUG );
      return true;
    } else {
      $this->log ( "Could not obtain wiki info for " . $this->login['wiki']['name'] . "!", LL_WARNING );
      return false;
    }

  }

  private function obtain_wikiinfo_error () {
    $this->log ( "Could not obtain the wiki info; working may create a mess! Should not continue...", LL_PANIC );
    die();
  }

  protected function fetch_wikiinfo_or_die () {
    if ( $this->fetch_wikiinfo_general() && $this->fetch_paraminfo() && $this->fetch_wikiinfo() ) {
      return true;
    } else {
      return $this->obtain_wikiinfo_error();
    }
  }

  protected function fetch_userinfo () {
    if ( $this->api_userinfo () ) {
      $this->log ( "Entered as: " . $this->user['name'], LL_DEBUG );
      $userprops = "";
      foreach ( $this->user as $infoname => $contents ) {
        if ( ! empty ( $userprops ) ) { $userprops .= ', '; }
        $userprops .= $infoname;
      }
      $this->log ( "User info obtained: " . $userprops, LL_DEBUG );
      return true;

    } else {
      $this->log ( "Could not obtain user info for me on " . $this->login['wiki']['name'] . "!", LL_WARNING );
    }
    return false;
  }

  private function obtain_userinfo_error () {
    $this->log ( "Could not obtain the user info; working may create a mess! Should not continue...", LL_PANIC );
    die();
  }

  protected function fetch_userinfo_or_die () {
    if ( $this->fetch_userinfo() ) {
      return true;
    } else {
      return $this->obtain_userinfo_error();
    }
  }

  protected function fetch_messages ( $lang = NULL ) {
    $this->api_messages ( NULL, NULL, $lang );
    if ( ! is_null ( $lang ) ) {
      $translated_to_addon = " (translated to " . $lang . ")";
    }
    return $this->log_status ( "Obtained wiki message strings" . $translated_to_addon,
      "Could not obtain wiki message strings" . $translated_to_addon . ": \$info" );
  }

  # ----- Handling infofiles ----- #

  protected function infofile_name ( $filename, $extension ) {
    return $this->bot_params['workfiles_path'] . "/" . $filename . "." . $extension;
  }

  protected function infofile_expired ( $filename, $extension, $expiry_period ) {
    $filetime = filemtime ( $this->infofile_name ( $filename, $extension ) );
    return ( time() - $filetime > $expiry_period );
  }

  protected function read_infofile ( $filename, $extension ) {
    $file = $this->infofile_name ( $filename, $extension );
    if ( file_exists ( $file ) ) {
      $result = unserialize ( file_get_contents ( $file ) );
      if ( $result ) {
        $this->log ( "Read infofile " . $file, LL_DEBUG );
      }
      return $result;
    } else {
      $this->log ( "Infofile " . $file . " does not exist!", LL_DEBUG );
      return false;
    }
  }

  protected function write_infofile ( $filename, $extension, $value ) {
    if ( ! file_exists ( $this->bot_params['workfiles_path'] ) ) {
      if ( ! mkdir ( $this->bot_params['workfiles_path'], '0755', true ) ) {
        $this->log ( "Cannot create workfiles path " . $this->bot_params['workfiles_path'] . " - will not store infofiles!", LL_WARNING );
        return false;
      }
    }
    $file = $this->infofile_name ( $filename, $extension );
    if ( file_put_contents ( $file, serialize ( $value ) ) ) {
      $this->log ( "Wrote infofile " . $file, LL_DEBUG );
      return true;
    } else {
      $this->log ( "Cannot create infofile " . $file . " - will not store this info!", LL_WARNING );
      return false;
    }
  }

  protected function fetch_wikiinfo_and_write ( $filename, $extension = "wikiinfo" ) {
    if ( $this->fetch_wikiinfo_or_die() ) {
      return $this->write_infofile ( $filename, $extension, $this->wiki );
    } else {
      return true;
    }
  }

  protected function fetch_userinfo_and_write ( $filename, $extension = "userinfo" ) {
    if ( $this->fetch_userinfo_or_die() ) {
      return $this->write_infofile ( $filename, $extension, $this->user );
    } else {
      return true;
    }
  }

  # ----- Obtaining all initial info ----- #

  protected function obtain_wikiinfo () {
    $filename  = $this->login['wiki']['name'];
    $extension = "wikiinfo";

    if ( ( $this->bot_params['fetch_wikiinfo'] === true ) || ( $this->bot_params['fetch_wikiinfo'] == "always" ) ) {
      return $this->fetch_wikiinfo_or_die();

    } elseif ( $this->bot_params['fetch_wikiinfo'] == "this_time" ) {
      return $this->fetch_wikiinfo_and_write ( $filename, $extension );

    } elseif ( $this->bot_params['fetch_wikiinfo'] == "on_newversion" ) {
      if ( $this->fetch_wikiinfo_general() ) {
        $wikiinfo = $this->read_infofile ( $filename, $extension );
        if ( ( $this->wiki_generator() !== $wikiinfo['general']['generator'] ) ||
             $this->infofile_expired ( $filename, $extension, $this->bot_params['fetched_wikiinfo_expiry'] ) ) {
          $this->log ( "Wiki software version is different, or stored wiki data expired or not found - re-fetching wiki info...", LL_INFO );
          if ( $this->fetch_paraminfo() && $this->fetch_wikiinfo() ) {
            $this->bot_params['fetch_userinfo'] = "this_time";
            return $this->write_infofile ( $filename, $extension, $this->wiki );
          } else {
            return $this->obtain_wikiinfo_error();
          }
        } else {
          $wiki_general = $this->wiki['general'];
          $this->wiki = $wikiinfo;
          $this->wiki['general'] = $wiki_general;
          return true;
        }
      } else {
        return $this->obtain_wikiinfo_error();
      }

    } elseif ( $this->bot_params['fetch_wikiinfo'] == "on_newrevision" ) {
      if ( $this->fetch_wikiinfo_general() ) {
        $wikiinfo = $this->read_infofile ( $filename, $extension );
        if ( ( $this->wiki_revision() !== $wikiinfo['general']['rev'] ) ||
             $this->infofile_expired ( $filename, $extension, $this->bot_params['fetched_wikiinfo_expiry'] ) ) {
          $this->log ( "Wiki software revision is different, or stored wiki data expired or not found - re-fetching wiki info...", LL_INFO );
          if ( $this->fetch_paraminfo() && $this->fetch_wikiinfo() ) {
            $this->bot_params['fetch_userinfo'] = "this_time";
            return $this->write_infofile ( $filename, $extension, $this->wiki );
          } else {
            return $this->obtain_wikiinfo_error();
          }
        } else {
          $wiki_general = $this->wiki['general'];
          $this->wiki = $wikiinfo;
          $this->wiki['general'] = $wiki_general;
          return true;
        }
      } else {
        return $this->obtain_wikiinfo_error();
      }

    } elseif ( $this->bot_params['fetch_wikiinfo'] == "on_expiry" ) {
      if ( $this->infofile_expired ( $filename, $extension, $this->bot_params['fetched_wikiinfo_expiry'] ) ) {
        return $this->fetch_wikiinfo_and_write ( $filename, $extension );
      }

    } elseif ( $this->bot_params['fetch_wikiinfo'] == "if_missing" ) {
      $this->wiki = $this->read_infofile ( $filename, $extension );
      if ( $this->wiki === false ) {
        return $this->fetch_wikiinfo_and_write ( $filename, $extension );
      } else {
        return $this->fetch_wikiinfo_general();
      }

    } elseif ( $this->bot_params['fetch_wikiinfo'] == "never" ) {
      $this->wiki = $this->read_infofile ( $filename, $extension );
      if ( $this->wiki === false ) {
        if ( ! $this->fetch_wikiinfo_general() ) {
          return $this->obtain_wikiinfo_error();
        }
      }
      return true;

    } elseif ( $this->bot_params['fetch_wikiinfo'] == "not_needed" ) {  // DANGEROUS!
      return true;

    } else {
      $this->log ( "Illegal setting for fetching wiki info - exitting!", LL_PANIC );
      die();
    }

    return true;
  }

  protected function obtain_userinfo () {
    $filename  = $this->login['user'] . "@" . $this->login['wiki']['name'];
    $extension = "userinfo";

    if ( ( $this->bot_params['fetch_userinfo'] === true ) || ( $this->bot_params['fetch_userinfo'] == "always" ) ) {
      return $this->fetch_userinfo_or_die();

    } elseif ( ( $this->bot_params['fetch_userinfo'] === true ) || ( $this->bot_params['fetch_userinfo'] == "this_time" ) ) {
      return $this->fetch_userinfo_and_write ( $filename, $extension );

    } elseif ( $this->bot_params['fetch_userinfo'] == "on_expiry" ) {
      if ( $this->infofile_expired ( $filename, $extension, $this->bot_params['fetched_userinfo_expiry'] ) ) {
        return $this->fetch_userinfo_and_write ( $filename, $extension );
      } else {
        $this->user = $this->read_infofile ( $filename, $extension );
      }

    } elseif ( $this->bot_params['fetch_userinfo'] == "if_missing" ) {
      $this->user = $this->read_infofile ( $filename, $extension );
      if ( $this->user === false ) {
        return $this->fetch_userinfo_and_write ( $filename, $extension );
      }

    } elseif ( $this->bot_params['fetch_userinfo'] == "never" ) {
      $this->user = $this->read_infofile ( $filename, $extension );
      if ( $this->user === false ) {
        return $this->obtain_userinfo_error();
      } else {
        return true;
      }

    } elseif ( $this->bot_params['fetch_userinfo'] == "not_needed" ) {
      return true;  // DANGEROUS!

    } else {
      $this->log ( "Illegal setting for fetching user info - exitting!", LL_PANIC );
      die();
    }

    return true;
  }

  protected function obtain_initial_info () {
    $this->obtain_wikiinfo();
    $this->obtain_userinfo();
  }

  # ----------  User level - Basic  ---------- #

  # ----- Login / Logout ----- #

  public function am_i_logged () {
    return ( ! $this->am_i_anonymous() );
  }

  private function check_login ( &$login ) {
    $result = true;

    if ( empty ( $login['wiki']['api_url'] ) ) {
      $this->log ( "Did not get an API script URL to connect to! Cannot do anything - exitting.", LL_PANIC );
      die();
    }
    if ( empty ( $login['user'] ) ) {
      $this->log ( "No login username! Will try to work anonymously...", LL_WARNING );
      $result = false;
    }
    if ( empty ( $login['password'] ) ) {
      $this->log ( "No user password! Will try to work anonymously...", LL_WARNING );
      $result = false;
    }
    if ( empty ( $login['wiki']['retries']['link_error'] ) ) {
      $login['wiki']['retries']['link_error'] = 5;
      $this->log ( "Max link errors count not specified - assuming 5...", LL_WARNING );
    }
    if ( empty ( $login['wiki']['interval']['link_error'] ) ) {
      $login['wiki']['interval']['link_error'] = 5;
      $this->log ( "Link error interval not specified - assuming 5...", LL_WARNING );
    }

    return $result;
  }

  public function login ( $login ) {
    $this->login = $login;

    if ( $this->login['attempt_cookies'] && $this->browser->has_cookies_for ( parse_url ( $login['wiki']['api_url'], PHP_URL_HOST ) ) ) {
      $this->obtain_initial_info();
      if ( $this->am_i_logged() ) return true;
    }

    if ( $this->check_login ( $this->login ) ) {
      $counter = (int) $this->login['wiki']['retries']['bad_login'];
      while ( ! $this->api_login ( $login['user'], $login['password'], $login['domain'] ) && ( $counter > 0 ) ) {
        $counter--;
        $this->log ( "Could not login - retrying (" . $this->error_string() . ")...", LL_WARNING );
      }

      if ( $this->am_i_logged() ) {
        $this->log ( "Logged in " . $this->login['wiki']['name'] . " as " . $this->login['user'] );
      } else {
        $this->log ( "Attention! The site says I am anonymous (unsiccessful login?)", LL_ERROR );
        if ( ! $this->login['anonymous_ok'] ) die();
      }

    }

    $this->obtain_initial_info();

    return true;
  }

  public function logout () {
    if ( $this->api_logout() ) {
      $this->wiki = array();
      $this->user = array();
      $this->log ( $this->login['user'] . " logged out of " . $this->login['wiki']['name'] );
    }
  }

  # ----- Queries ----- #

  public function continue_query () {
    if ( $this->query_is_exhausted() ) {
      return false;
    } else {
      return $this->api_query();
    }
  }

  public function query_titles ( $titles, $properties = NULL ) {
    return $this->api_query_titles ( $titles, $properties );
  }

  public function query_pageids ( $pageids, $properties = NULL ) {
    return $this->api_query_pageids ( $pageids, $properties );
  }

  public function query_revids ( $revids, $properties = NULL ) {
    return $this->api_query_revids ( $revids, $properties );
  }

  public function query_list ( $list, $code, $listparams = NULL, $params = NULL ) {
    return $this->api_query_list ( $list, $code, $listparams, $params );
  }

  public function query_generator ( $generator, $code, $listparams = NULL, $properties = NULL, $params = NULL ) {
    return $this->api_query_generator ( $generator, $code, $listparams, $properties, $params );
  }

  # ----- Editing pages ----- #

  private function fetch_page_objects ( $query_result, $limits = NULL ) {
    if ( $query_result === false ) { return false; }  // std_error is already set

    if ( is_array ( $this->data_tree['query'] ) &&
         array_key_exists ( 'pages', $this->data_tree['query'] ) ) {

      $page_key     = key     ( $this->data_tree['query']['pages'] );
      $page_element = current ( $this->data_tree['query']['pages'] );

      if ( array_key_exists ( 'missing', $page_element ) ) {
        if ( $page_element['imagerepository'] == "shared" ) {
          $this->set_std_error ( 'insharedrepo' );
        } else {
          $this->set_std_error ( 'pagemissing' );
        }
        return false;
      } elseif ( array_key_exists ( 'invalid', $page_element ) ) {
        $this->set_std_error ( 'pageinvalid' );
        return false;
      } elseif ( ( $page_key == -1 ) ) {
        $this->set_std_error ( 'unknownreason' );
        return false;
      } else {
        if ( is_array ( $this->states['query']['properties'] ) ) {
          foreach ( $this->states['query']['properties'] as $property => $values ) {
            if ( isset ( $values['limit'] ) && ! isset ( $limits[$property] ) ) {
              $limits[$property] = $values['limit'];
            }
          }
        }

        while ( $query_result ) {

          if ( is_array ( $limits ) ) {
            foreach ( $limits as $property => &$limit ) {
              if ( $limit != "max" ) {
                $limit -= count ( $page_element[$property] );
                if ( $limit <= 0 ) {
                  unset ( $limits[$property] );
                  unset ( $this->states['query']['properties'][$property] );
                } else {
                  if ( $limit < $this->states['query']['properties'][$property]['limit'] ) {
                    $this->states['query']['properties'][$property]['limit'] = $limit;
                  }
                }
              }
            }
            if ( empty ( $limits ) ) break;
          }

          $query_result = $this->continue_query();
          if ( $query_result ) {
            $continue_element = current ( $this->data_tree['query']['pages'] );
            foreach ( $continue_element as $key => $sub ) {
              if ( is_array ( $sub ) ) {
                $page_element[$key] = array_merge ( $page_element[$key], $sub );
              }
            }
          }
        }
        unset ( $continue_element );

        $page = new Page;
        $page->requested_title = $title;
        $page->read_from_element ( $page_element, $this );
        return $page;
      }
    } else {
      return false;
    }
  }

  public function fetch_title ( $title, $properties = NULL, $limits = NULL ) {
    return $this->fetch_page_objects ( $this->api_query_titles ( $title, $properties ), $limits );
  }

  public function fetch_pageid ( $pageid, $properties = NULL, $limits = NULL ) {
    return $this->fetch_page_objects ( $this->api_query_pageids ( $pageid, $properties ), $limits );
  }

  public function title_exists ( $title ) {
    return (bool) $this->fetch_title ( $title );
  }

  public function pageid_exists ( $pageid ) {
    return (bool) $this->fetch_pageid ( $pageid );
  }

  public function fetch_page ( $title, $properties = NULL, $revision_id = NULL, $section = NULL ) {

    if ( $properties === NULL ) {
      $properties = array();

      if ( empty ( $properties['info'] ) ) {
        $properties['info'] = array ('prop' => 'protection' );
      }

      if ( $revision_id !== 0 ) {
        if ( empty ( $properties['revisions'] ) ) {
          $properties['revisions'] = array (
            'prop'  => 'content|timestamp',
            'limit' => '1',
          );
        }
        if ( ! ( $revision_id === NULL ) ) {
          $properties['revisions']['startid'] = $revision_id;
        }
        if ( ! ( $section === NULL ) ) {
          $properties['revisions']['section'] = $section;
        }
      }
    }

    $page = $this->fetch_title ( $title, $properties, array ( 'revisions' => 1 ) );

    $this->log_status ( "Fetched page [[" . $title . "]].",
      "Could not fetch page [[" . $title . "]]: \$info." );
    return $page;
  }

  public function submit_page ( $page, $summary, $isminor = true, $watch = NULL, $createonly = NULL ) {
    if ( ! $page->is_modified() ) {
      $this->set_std_error ( 'notmodified' );
    } elseif ( $page->deny_bots ) {
      $this->set_std_error ( 'pageprotected' );
    } else {

      $this->api_get_token_if_needed();

      if ( $page->wikiid != $this->wiki['id'] ) {
        $page->timestamp = NULL;
        $page->fetchtimestamp = NULL;
      }

      $markbot = $this->login['mark_bot'];

      if ( $this->test_mode ) {
        $this->test_dump ( "Would submit page [[" . $page->title . "]]\n" .
          "Text: " . $page->text . "\n" .
          "Summary: " . $summary . "\n" );
        return true;
      }

      if ( ( ! $this->login['wiki']['submit_by_web'] ) &&
           ( $this->mw_version_number() >= 11303 ) &&
           $this->is_api_write_enabled() ) {
        if ( $this->api_edit ( $page->timestamp, $page->fetchtimestamp,
          $page->title, $page->text, $page->section, $summary, $isminor,
          $markbot, $watch, ( $createonly === true ), ( $createonly === false ) ) ) {
          $edit_tree = $this->data_tree['edit'];
          if ( is_array ( $edit_tree ) ) {
            if ( array_key_exists ( 'result', $edit_tree ) ) {
              switch ( $edit_tree['result'] ) {
                case "Success" :
                  break;
                default :
                  $this->set_std_error ( 'editknown', $edit_tree['result'] );
              }
            } elseif ( array_key_exists ( 'captcha', $edit_tree ) ) {
              $this->set_std_error ( 'revertcaptcha' );
            } else {
              if ( ! $this->error['type'] ) {
                $this->set_std_error ( 'revertunknown' );
              }
            }
          }
        }
      } else {
        $this->web_edit ( $page->timestamp, $page->fetchtimestamp,
          $page->title, $page->text, $page->section, $summary, $isminor,
          $markbot, $watch );
      }

      sleep ( $this->login['wiki']['interval']['submit'] );

    }
    return $this->log_status ( "Page [[" . $page->title . "]] was submitted.",
      "Page [[" . $page->title . "]] was NOT submitted: \$info." );
  }

  public function undo_page ( $page, $revert_revid, $to_revid = NULL, $summary = NULL ) {
    $markbot = $this->login['mark_bot'];

    if ( $this->test_mode ) {
      $this->test_dump ( "Would undo page [[" . $page->title . "]] from revid " . $revert_revid .
        ( is_null ( $to_revid ) ? "" : " to revid " ) . $to_revid .
        "\nSummary: " . $summary . "\n" );
      return true;
    }

    if ( $this->api_undo ( $page->timestamp, $page->title, $revert_revid, $to_revid, $summary, $markbot ) ) {
      $edit_tree = $this->data_tree['edit'];
      if ( is_array ( $edit_tree ) ) {
        if ( array_key_exists ( 'result', $edit_tree ) ) {
          switch ( $edit_tree['result'] ) {
            case "Success" :
              $logstring = "Reverted page [[" . $page->title . "]]" .
                ( is_null ( $to_revid ) ? " one revision back" : " to revid " . $to_revid );
              break;
            default :
              $this->set_std_error ( 'revertknown', $edit_tree['result'] );
          }
        } elseif ( array_key_exists ( 'captcha', $edit_tree ) ) {
          $this->set_std_error ( 'revertcaptcha' );
        } else {
          if ( ! $this->error['type'] ) {
            $this->set_std_error ( 'revertunknown' );
          }
        }
      }
    }

    sleep ( $this->login['wiki']['interval']['submit'] );

    return $this->log_status ( $logstring . ".",
      "Page [[" . $page->title . "]] not undone: \$info." );
  }

  # ----- Moving, deleting and restoring pages ----- #

  public function move_page ( $from, $to, $reason = NULL, $noredirect = NULL, $movetalk = NULL ) {

    if ( $this->test_mode ) {
      $this->test_dump ( "Would move page [[" . $from . "]] as [[" . $to . "]]\n" .
        "Summary: " . $reason . "\n" );
      return true;
    }

    if ( $noredirect === NULL ) { $noredirect = $this->login['move_noredirect']; }
    if ( $noredirect === NULL ) { $noredirect = false; }
    if ( $movetalk   === NULL ) { $movetalk   = $this->login['move_withtalk']; }
    if ( $movetalk   === NULL ) { $movetalk   = true; }

    sleep ( $this->login['wiki']['interval']['submit'] );

    $this->api_move ( $from, $to, $reason, $noredirect, $movetalk );
    return $this->log_status ( "Page [[" . $from . "]] moved as [[" . $to . "]].",
      "Page [[" . $from . "]] NOT moved as [[" . $to . "]]: \$info." );
  }

  public function delete_page ( $title, $reason = NULL ) {

    if ( $this->test_mode ) {
      $this->test_dump ( "Would delete page [[" . $title . "]]\n" .
        "Summary: " . $reason . "\n" );
      return true;
    }

    if ( $this->can_i_delete() ) {
      if ( $this->mw_version_and_token_ok ( 11200 ) ) {
        $this->api_delete ( $title, $reason );
      } else {
        $this->web_delete ( $title, $reason );
      }
    } else {
      $this->set_std_error ( 'cantdelete' );
    }

    sleep ( $this->login['wiki']['interval']['submit'] );

    return $this->log_status ( "Page [[" . $title . "]] was deleted.",
      "Page [[" . $title . "]] was NOT deleted: \$info." );
  }

  public function undelete_page ( $title, $reason = NULL, $timestamps = NULL ) {

    if ( $this->test_mode ) {
      $this->test_dump ( "Would undelete page [[" . $title . "]]\n" .
        "Summary: " . $reason . "\n" .
        "Revision timestamps: " . print_r ( $timestamps, true ) );
      return true;
    }

    if ( empty ( $timestamps ) ) {
      $this->log ( "Page " . $title . " has no revisions to be undeleted - skipping" );
      return false;
    }

    $this->api_undelete ( $title, $reason, $timestamps );

    sleep ( $this->login['wiki']['interval']['submit'] );

    return $this->log_status ( "Page [[" . $title . "]]: " . count ( $timestamps ) . " revision(s) were undeleted.",
      "Page [[" . $title . "]]: " . count ( $timestamps ) . " revision(s) were NOT undeleted: \$info." );
  }

  # ----- Rolling back changes ----- #

  public function rollback_page ( $title, $user, $summary = NULL, $rollback_token = NULL ) {

    if ( $this->test_mode ) {
      $this->test_dump ( "Would rollback page [[" . $title . "]] revisions by user " . $user . "\n" .
        "Summary: " . $summary . "\n" .
        "Revision timestamps: " . print_r ( $timestamps, true ) );
      return true;
    }

    $markbot = $this->user['settings']['mark_bot'];
    $this->api_rollback ( $title, $user, $summary, $markbot, $rollback_token );

    sleep ( $this->login['wiki']['interval']['submit'] );

    return $this->log_status ( "Page [[" . $title . "]] edits by user '" . $user . "' were rolled back.",
      "Page [[" . $title . "]] edits by user '" . $user . "' were NOT rolled back: \$info." );
  }

  # ----- Protecting and unprotecting pages ----- #

  public function protect_page ( $title, $protections = NULL, $expiry = NULL, $reason = NULL, $cascade = false ) {
    if ( is_null ( $protections ) ) {
      $protections = "edit=sysop|move=sysop|rollback=sysop|delete=sysop|restore=sysop";
    }

    if ( $this->test_mode ) {
      $this->test_dump ( "Would protect page [[" . $title . "]]" . ( $cascade ? " (cascade)" : "" ) . "\n" .
        "Protections: " . print_r ( $protections, true ) .
        "Expiry: " . $expiry . "\n" .
        "Summary: " . $reason . "\n" .
        "Revision timestamps: " . print_r ( $timestamps, true ) );
      return true;
    }

    $this->api_protect ( $title, $protections, $expiry, $reason, $cascade );

    sleep ( $this->login['wiki']['interval']['submit'] );

    return $this->log_status ( "Page [[" . $title . "]] was protected.",
      "Page [[" . $title . "]] was NOT protected: \$info." );
  }

  public function unprotect_page ( $title, $protections = NULL, $reason = NULL, $cascade = false ) {
    if ( is_null ( $protections ) ) {
      $protections = "edit=all|move=autoconfirmed|rollback=autoconfirmed|delete=sysop|restore=autoconfirmed";
    }

    if ( $this->test_mode ) {
      $this->test_dump ( "Would unprotect page [[" . $title . "]]" . ( $cascade ? " (cascade)" : "" ) . "\n" .
        "Protections: " . print_r ( $protections, true ) .
        "Expiry: " . $expiry . "\n" .
        "Summary: " . $reason . "\n" .
        "Revision timestamps: " . print_r ( $timestamps, true ) );
      return true;
    }

    $this->api_protect ( $title, $protections, $expiry, $reason, $cascade );

    sleep ( $this->login['wiki']['interval']['submit'] );

    return $this->log_status ( "Page [[" . $title . "]] was de-protected.",
      "Page [[" . $title . "]] was NOT de-protected: \$info." );
  }

  # ----- Blocking and unblocking users ----- #

  public function block_user ( $username, $expiry = 'never', $reason = NULL, $anononly = false, $nocreate = false, $autoblock = false, $noemail = false ) {

    if ( $this->test_mode ) {
      $this->test_dump ( "Would block user" . $username . "\n" .
        "Expiry: " . $expiry . "\n" .
        "Summary: " . $reason . "\n" );
      return true;
    }

    if ( $this->can_i_block() ) {
      if ( $this->mw_version_and_token_ok ( 11200 ) ) {
        $this->api_block ( $username, $expiry, $reason, $anononly, $nocreate, $autoblock, $noemail );
      } else {
        $this->web_block ( $username, $expiry, $reason, $anononly, $nocreate, $autoblock, $noemail );
      }
      sleep ( $this->login['wiki']['interval']['submit'] );
    } else {
      $this->set_std_error ( 'permissiondenied' );
    }

    return $this->log_status ( "User '" . $username . "' was blocked.",
      "User '" . $username . "' was NOT blocked: \$info." );
  }

  private function get_user_last_block_id ( $username ) {
    $params = array();
    if ( mb_strpos ( $username, '/' ) === false ) {
      $params['users'] = $username;
    } else {
      $params['ip'] = $username;
    }
    $params['start'] = date ( 'Y-m-d H:i:s', $this->wiki_time() );
    $params['end'  ] = date ( 'Y-m-d H:i:s', $this->wiki_time ( 0 ) );
    $params['dir'  ] = "older";
    $params['limit'] = 1;
    $params['prop' ] = "id";

    $this->api_query_list ( 'blocks', 'bk', $params );
    $blockdesc = reset ( $this->data_tree['query']['blocks'] );
    if ( empty ( $blockdesc ) ) {
      $this->set_std_error ( 'notblocked' );
      return false;
    } else {
      return $blockdesc['id'];
    }
  }

  public function unblock_user ( $username, $block_id = NULL, $reason = NULL ) {

    if ( $this->test_mode ) {
      $this->test_dump ( "Would unblock user" . $username . "\n" .
        "Summary: " . $reason . "\n" );
      return true;
    }

    if ( $this->can_i_block() ) {
      if ( $this->mw_version_and_token_ok ( 11200 ) ) {
        if ( is_null ( $block_id ) ) {
          $block_id = $this->get_user_last_block_id ( $username );
        }
        if ( $block_id ) {
          $this->api_unblock ( $username, $block_id, $reason );
        }
      } else {
        $this->web_unblock ( $username, $block_id, $reason );
      }
      sleep ( $this->login['wiki']['interval']['submit'] );
    } else {
      $this->set_std_error ( 'permissiondenied' );
    }

    return $this->log_status ( "User '" . $username . "' was unblocked.",
      "User '" . $username . "' was NOT unblocked: \$info." );
  }

  # ----- Watching and unwatching pages ----- #

  public function watch_page ( $title ) {

    if ( $this->test_mode ) {
      $this->test_dump ( "Would watch page [[" . $title . "]]\n" );
      return true;
    }

    $this->api_watch ( $title, true );

    sleep ( $this->login['wiki']['interval']['submit'] );

    return $this->log_status ( "Page [[" . $title . "]] was marked as watched.",
      "Page [[" . $title . "]] was NOT marked as watched: \$info." );
  }

  public function unwatch_page ( $title ) {

    if ( $this->test_mode ) {
      $this->test_dump ( "Would unwatch page [[" . $title . "]]\n" );
      return true;
    }

    $this->api_watch ( $title, false );

    sleep ( $this->login['wiki']['interval']['submit'] );

    return $this->log_status ( "Page [[" . $title . "]] was marked as not watched.",
      "Page [[" . $title . "]] was NOT marked as not watched: \$info." );
  }

  # ----- E-mailing an user ----- #

  public function email_user ( $user, $subject, $text, $ccme = false ) {

    if ( $this->test_mode ) {
      $this->test_dump ( "Would email user " . $user . "\n" .
        "Subject: " . $subject . "\n" .
        "Text: " . $text . "\n" );
      return true;
    }

    if ( $this->can_i_sendemail() ) {
      $this->api_emailuser ( $user, $subject, $text, $ccme );
      sleep ( $this->login['wiki']['interval']['submit'] );
    } else {
      $this->set_std_error ( 'permissiondenied' );
    }

    return $this->log_status ( "Email was sent to user '" . $user . "'.",
      "Email was NOT sent to user '" . $user . "': \$info." );
  }

  # ----- Patrolling a recent change ----- #

  public function patrol_recentchange ( $rcid ) {

    if ( $this->test_mode ) {
      $this->test_dump ( "Would mark recentchange " . $rcid . "as patrolled\n" );
      return true;
    }

    if ( $this->can_i_patrol() ) {
      if ( $this->mw_version_and_token_ok ( 11400 ) ) {
        return $this->api_patrol ( $rcid );
      } else {
        return $this->web_patrol ( $rcid );
      }
      sleep ( $this->login['wiki']['interval']['submit'] );
    } else {
      $this->set_std_error ( 'permissiondenied' );
    }

    return $this->log_status ( "Recentchange ID " . $rcid . " was marked as patrolled.",
      "Recentchange ID " . $rcid . " was NOT marked as patrolled: \$info." );
  }

  # ----- Importing pages ----- #

  public function import_pages_interwiki ( $title, $iwcode, $summary = NULL, $fullhistory = true, $into_namespace = NULL, $templates = false ) {

    if ( $this->test_mode ) {
      $this->test_dump ( "Would import from interwiki " . $iwcode . " page [[" . $title . "]]\n" .
        "Summary: " . $summary . "\n" );
      return true;
    }

    $this->api_import_interwiki ( $title, $iwcode, $summary, $fullhistory, $into_namespace, $templates );
    sleep ( $this->login['wiki']['interval']['submit'] );

    return $this->log_status ( "Page import was successful.",
      "Page import was NOT successful: \$info." );
  }

  public function import_pages_xml ( $xml_upload, $summary = NULL ) {

    if ( $this->test_mode ) {
      $this->test_dump ( "Would import from XML page [[" . $title . "]]\n" .
        "Summary: " . $summary . "\n" );
      return true;
    }

    return $this->api_import_xml ( $xml_upload, $summary );
    sleep ( $this->login['wiki']['interval']['submit'] );

    return $this->log_status ( "Page XML import was successful.",
      "Page XML import was NOT successful: \$info." );
  }

  # ----- Changing user rights ----- #

  public function change_userrights ( $user, $add_groups = NULL, $remove_groups = NULL, $reason = NULL ) {

    if ( $this->test_mode ) {
      $this->test_dump ( "Would change the rights of " . $user . "\n" .
        "Add groups: " . $this->barsepstring ( $add_groups ) . "\n" .
        "Remove groups: " . $this->barsepstring ( $remove_groups ) . "\n" .
        "Reason: " . $reason . "\n" );
      return true;
    }

    if ( $this->can_i_userrights() ) {
      if ( $this->mw_version_and_token_ok ( 11600 ) ) {
        $this->api_userrights ( $user, $add_groups, $remove_groups, $reason );
      } else {
        $this->web_userrights ( $user, $add_groups, $remove_groups, $reason );
      }
      sleep ( $this->login['wiki']['interval']['submit'] );
    } else {
      $this->set_std_error ( 'permissiondenied' );
    }

    return $this->log_status ( "User '" . $user . "' rights were changed.",
      "User '" . $user . "' rights were NOT changed: \$info." );
  }

  # ----- Preprocessing wiki texts ----- #

  public function expand_templates ( $text, $title = NULL ) {
    if ( $this->api_expandtemplates ( $text, $title ) ) {
      $result = reset ( $this->data_tree['expandtemplates'] );
    } else {
      $result = false;
    }
    $this->log_status ( "Text templates were expanded.",
      "Text templates were NOT expanded: \$info." );
    return $result;
  }

  public function parse_text ( $text, $title = NULL, $properties = NULL, $pst = true, $uselang = NULL ) {
    if ( $this->api_parse_text ( $text, $title, $properties, $pst, $uselang ) ) {
      $result = $this->data_tree['parse'];
    } else {
      $result = false;
    }
    $this->log_status ( "Text was parsed.",
      "Text was NOT parsed: \$info." );
    return $result;
  }

  public function parse_page ( $title, $properties = NULL, $uselang = NULL ) {
    if ( $this->api_parse_page ( $title, $properties, $uselang ) ) {
      $result = $this->data_tree['parse'];
    } else {
      $result = false;
    }
    $this->log_status ( "Page [[" . $title . "]] text was parsed.",
      "Page [[" . $title . "]] text was NOT parsed: \$info." );
    return $result;
  }

  # ----- Uploading files ----- #

  public function upload_file ( $file, $text, $comment, $target_filename = NULL, $watch = false, $ignorewarnings = true ) {
    if ( empty ( $target_filename ) ) { $target_filename = basename ( $file ); }

    if ( $this->test_mode ) {
      $this->test_dump ( "Would upload file " . $file . " as page [[" . $target_filename . "]]\n" .
        "Text: " . $text . "\n" .
        "Comment: " . $comment . "\n" );
      return true;
    }

    if ( $this->mw_version_ok ( 11600 ) ) {
      $result = $this->api_upload_file ( $file, $text, $comment, $target_filename, $watch, $ignorewarnings );
    } else {
      $result = $this->web_upload_file ( $file, $text, $comment, $target_filename, $watch, $ignorewarnings );
    }

    sleep ( $this->login['wiki']['interval']['submit'] );

    $this->log_warnings_if_present ( $this->data_tree['upload'] );
    return $this->log_status ( "Uploaded file '" . $filename . "' as '" . $target_filename . "'.",
      "Upload of file '" . $filename . "' as '" . $target_filename . "' failed: \$info." );
  }

  public function upload_url ( $URL, $text, $comment, $target_filename = NULL, $watch = false, $ignorewarnings = true ) {
    if ( empty ( $target_filename ) ) { $target_filename = basename ( $filename ); }

    if ( $this->test_mode ) {
      $this->test_dump ( "Would upload URL " . $URL . " as page [[" . $target_filename . "]]\n" .
        "Text: " . $text . "\n" .
        "Comment: " . $comment . "\n" );
      return true;
    }

    $this->api_upload_url ( $URL, $text, $comment, $target_filename, $watch, $ignorewarnings );

    sleep ( $this->login['wiki']['interval']['submit'] );

    $this->log_warnings_if_present ( $this->data_tree['upload'] );
    return $this->log_status ( "Uploaded by URL file '" . $filename . "' as '" . $target_filename . "'.",
      "Upload by URL of file '" . $filename . "' as '" . $target_filename . "' failed: \$info." );
  }

  public function is_url_uploaded ( $target_filename, $sessionkey, $ignorewarnings = true ) {
    if ( $this->api_upload_sessionkey ( $target_filename, $sessionkey, true, $ignorewarnings ) ) {
      $this->log ( "Upload (by URL) of '" . $target_filename . "' checked: OK." );
      return true;
    } else {
      $this->log ( "Upload (by URL) of '" . $target_filename . "' checked: Failed." );
      return false;
    }
  }

  public function get_upload_session_key () {
    return $this->data_tree['upload']['sessionkey'];
  }

  # ----- Purging pages caches ----- #

  public function purge_page_cache ( $title ) {

    if ( $this->test_mode ) {
      $this->test_dump ( "Would purge the web cache of page [[" . $title . "]]\n" );
      return true;
    }

    $this->api_purge ( $title );

    sleep ( $this->login['wiki']['interval']['submit'] );

    return $this->log_status ( "Page [[" . $title . "]] cache was purged.",
      "Page [[" . $title . "]] cache was NOT purged: \$info." );
  }

  # ----------  Wiki apimodule info paraminfo  ---------- #

  public function is_apimodule_info_obtained () {
    return ( ! empty ( $this->wiki['paraminfo'] ) );
  }

  # ----- Modules ----- #

  public function apimodules_names ( $parentmodulename = NULL ) {
    switch ( $parentmodulename ) {
      case "query" :
        return array_keys ( $this->wiki['paraminfo']['querymodules'] );
      case NULL :
        return array_keys ( $this->wiki['paraminfo']['modules'] );
      case "/" :
        $modules = array_keys ( $this->wiki['paraminfo'] );
        unset ( $modules[array_search ( 'modules', $modules )] );
        unset ( $modules[array_search ( 'querymodules', $modules )] );
        return $modules;
      default :
        return false;
    }
  }

  public function apimodule_exists ( $modulename, $parentmodulename = NULL ) {
    return in_array ( $modulename, $this->apimodules_names ( $parentmodulename ) );
  }

  public function apimodule ( $modulename, $parentmodulename = NULL ) {
    switch ( $parentmodulename ) {
      case "query" :
        return $this->wiki['paraminfo']['querymodules'][$modulename];
      case NULL :
        return $this->wiki['paraminfo']['modules'][$modulename];
      case "/" :
        return $this->wiki['paraminfo'][$modulename];
      default :
        return false;
    }
  }

  # ----- Module elements ----- #

  private function apimodule_element ( $element_name, $modulename, $parentmodulename = NULL ) {
    $module = $this->apimodule ( $modulename, $parentmodulename );
    return ( is_array ( $module ) ? $module[$element_name] : false );
  }

  private function apimodule_subarraykeys ( $subarray_name, $modulename, $parentmodulename = NULL ) {
    $element = $this->apimodule_element ( $subarray_name, $modulename, $parentmodulename );
    return ( is_array ( $element ) ? array_keys ( $element ) : false );
  }

  private function apimodule_subarrayelement ( $subarray_name, $element_name, $modulename, $parentmodulename = NULL ) {
    $subarray = $this->apimodule_element ( $subarray_name, $modulename, $parentmodulename );
    return ( is_array ( $subarray ) ? $subarray[$element_name] : false );
  }

  # --- Parameters --- #

  public function apimodule_params_names ( $modulename, $parentmodulename = NULL ) {
    return $this->apimodule_subarraykeys ( 'parameters', $modulename, $parentmodulename );
  }

  public function apimodule_param_exists ( $paramname, $modulename, $parentmodulename = NULL ) {
    $paramnames = $this->apimodule_params_names ( $modulename, $parentmodulename );
    return ( is_array ( $paramnames ) ? in_array ( $paramname, $paramnames ) : false );
  }

  public function apimodule_param ( $paramname, $modulename, $parentmodulename = NULL ) {
    return $this->apimodule_subarrayelement ( 'parameters', $paramname,
      $modulename, $parentmodulename );
  }

  private function apimodule_paramelement ( $elementname, $paramname, $modulename, $parentmodulename = NULL ) {
    $param = $this->apimodule_param ( $paramname, $modulename, $parentmodulename );
    if ( is_array ( $param ) ) {
      return ( array_key_exists ( $elementname, $param ) ? $param[$elementname] : false );
    } else {
      return false;
    }
  }

  private function apimodule_paramelement_is_present ( $elementname, $paramname, $modulename, $parentmodulename = NULL ) {
    $element = $this->apimodule_paramelement ( $elementname, $paramname, $modulename, $parentmodulename );
    return ( $element !== false );
  }

  public function apimodule_param_desc ( $paramname, $modulename, $parentmodulename = NULL ) {
    return $this->apimodule_paramelement ( 'description', $paramname, $modulename, $parentmodulename );
  }

  public function apimodule_param_type ( $paramname, $modulename, $parentmodulename = NULL ) {
    return $this->apimodule_paramelement ( 'type', $paramname, $modulename, $parentmodulename );
  }

  public function apimodule_param_default ( $paramname, $modulename, $parentmodulename = NULL ) {
    return $this->apimodule_paramelement ( 'default', $paramname, $modulename, $parentmodulename );
  }

  public function apimodule_param_min ( $paramname, $modulename, $parentmodulename = NULL ) {
    return $this->apimodule_paramelement ( 'min', $paramname, $modulename, $parentmodulename );
  }

  public function apimodule_param_max ( $paramname, $modulename, $parentmodulename = NULL ) {
    return $this->apimodule_paramelement ( 'max', $paramname, $modulename, $parentmodulename );
  }

  public function apimodule_param_highmax ( $paramname, $modulename, $parentmodulename = NULL ) {
    return $this->apimodule_paramelement ( 'highmax', $paramname, $modulename, $parentmodulename );
  }

  public function apimodule_param_limit ( $paramname, $modulename, $parentmodulename = NULL ) {
    return $this->apimodule_paramelement ( 'limit', $paramname, $modulename, $parentmodulename );
  }

  public function apimodule_param_multi ( $paramname, $modulename, $parentmodulename = NULL ) {
    $this->apimodule_paramelement_is_present ( 'multi', $paramname, $modulename, $parentmodulename );
  }

  public function apimodule_param_allowsduplicates ( $paramname, $modulename, $parentmodulename = NULL ) {
    $this->apimodule_paramelement_is_present ( 'allowsduplicates', $paramname, $modulename, $parentmodulename );
  }

  # --- Other --- #

  public function apimodule_errors ( $modulename, $parentmodulename = NULL ) {
    $module = $this->apimodule ( $modulename, $parentmodulename );
    return $module['errors'];
  }

  private function apimodule_element_is_present ( $elementname, $modulename, $parentmodulename = NULL ) {
    $module = $this->apimodule ( $modulename, $parentmodulename );
    return array_key_exists ( $elementname, $module );
  }

  public function apimodule_classname ( $modulename, $parentmodulename = NULL ) {
    return $this->apimodule_element ( 'classname', $modulename, $parentmodulename );
  }

  public function apimodule_desc ( $modulename, $parentmodulename = NULL ) {
    return $this->apimodule_element ( 'description', $modulename, $parentmodulename );
  }

  public function apimodule_version ( $modulename, $parentmodulename = NULL ) {
    return $this->apimodule_element ( 'version', $modulename, $parentmodulename );
  }

  public function apimodule_prefix ( $modulename, $parentmodulename = NULL ) {
    return $this->apimodule_element ( 'prefix', $modulename, $parentmodulename );
  }

  public function apimodule_requires_readrights ( $modulename, $parentmodulename = NULL ) {
    return $this->apimodule_element_is_present ( 'readrights', $modulename, $parentmodulename );
  }

  public function apimodule_requires_writerights ( $modulename, $parentmodulename = NULL ) {
    return $this->apimodule_element_is_present ( 'writerights', $modulename, $parentmodulename );
  }

  public function apimodule_requires_mustbeposted ( $modulename, $parentmodulename = NULL ) {
    return $this->apimodule_element_is_present ( 'mustbeposted', $modulename, $parentmodulename );
  }

  public function apimodule_is_generator ( $modulename, $parentmodulename = NULL ) {
    return $this->apimodule_element_is_present ( 'generator', $modulename, $parentmodulename );
  }

  # ----------  Wiki and user characteristics  ---------- #

  # ----- Internal use functions ----- #

  protected function subarray ( $array, $key ) {
    return ( is_array ( $array[$key] ) ? $array[$key] : false );
  }
  protected function wiki_subarray ( $key ) { return $this->subarray ( $this->wiki, $key ); }
  protected function user_subarray ( $key ) { return $this->subarray ( $this->user, $key ); }

  protected function wiki_element_is_subarray ( $key ) { return is_array ( $this->wiki[$key] ); }
  protected function user_element_is_subarray ( $key ) { return is_array ( $this->user[$key] ); }

  protected function wiki_subarray_count ( $key ) {
    return ( is_array ( $this->wiki[$key] ) ? count ( $this->wiki[$key] ) : false );
  }

  protected function wiki_subarray_elements_elements ( $key, $subkey ) {
    $element_subarray = $this->wiki_subarray ( $key );
    if ( ! $element_subarray ) {
      return $element_subarray;
    } else {
      $elements = array();
      foreach ( $element_subarray as $piecekey => $piece ) {
        $elements[$piecekey] = $piece[$subkey];
      }
      return $elements;
    }
  }

  protected function wiki_subarray_element_by_value ( $key, $subkey, $value ) {
    $element_subarray = $this->wiki_subarray ( $key );
    if ( ! $element_subarray ) {
      return $element_subarray;
    } else {
      foreach ( $element_subarray as $piecekey => $piece ) {
        if ( $piece[$subkey] == $value ) {
          return $piece;
        }
      }
      return false;
    }
  }

  protected function wiki_subarray_elements_by_values ( $key, $subkey, $value ) {
    $element_subarray = $this->wiki_subarray ( $key );
    if ( ! $element_subarray ) {
      return $element_subarray;
    } else {
      $pieces = array();
      foreach ( $element_subarray as $piecekey => $piece ) {
        if ( $piece[$subkey] == $value ) {
          $pieces[$piecekey] = $piece;
        }
      }
      return $pieces;
    }
  }

  # ----- Wiki misc ----- #

  public function is_wiki_info_obtained () { return ( ! empty ( $this->wiki ) ); }

  public function wiki_name              () { return $this->wiki['general']['sitename'            ]; }
  public function wiki_mainpage_title    () { return $this->wiki['general']['mainpage'            ]; }
  public function wiki_mainpage_url      () { return $this->wiki['general']['base'                ]; }
  public function wiki_id                () { return $this->wiki['general']['wikiid'              ]; }

  public function wiki_generator         () { return $this->wiki['general']['generator'           ]; }  // the wiki software and version
  public function wiki_revision          () { return $this->wiki['general']['rev'                 ]; }  // the software revision, I guess
  public function wiki_phpversion        () { return $this->wiki['general']['phpversion'          ]; }
  public function wiki_phpsapi           () { return $this->wiki['general']['phpsapi'             ]; }
  public function wiki_dbtype            () { return $this->wiki['general']['dbtype'              ]; }
  public function wiki_dbversion         () { return $this->wiki['general']['dbversion'           ]; }

  public function wiki_rights            () { return $this->wiki['general']['rights'              ]; }  // the wiki license (see also wiki_rightsinfo() )
  public function wiki_case              () { return $this->wiki['general']['case'                ]; }  // the page names case treatment (eg. first-letter)
  public function wiki_language          () { return $this->wiki['general']['lang'                ]; }  // the natural language used on this wiki
  public function wiki_fallback_encoding () { return $this->wiki['general']['fallback8bitEncoding']; }
  public function wiki_timezone          () { return $this->wiki['general']['timezone'            ]; }
  public function wiki_timeoffset        () { return $this->wiki['general']['timeoffset'          ]; }
  public function wiki_time_at_login     () { return $this->wiki['general']['time'                ]; }  // server time

  public function wiki_server            () { return $this->wiki['general']['server'              ]; }
  public function wiki_article_path      () { return $this->wiki['general']['articlepath'         ]; }
  public function wiki_other_article_path() { return $this->wiki['general']['variantarticlepath'  ]; }  // typically the 'short' article path
  public function wiki_script_path       () { return $this->wiki['general']['scriptpath'          ]; }
  public function wiki_script            () { return $this->wiki['general']['script'              ]; }

  public function is_api_write_enabled () {
    if ( ! is_array ( $this->wiki['general'] ) || ( $this->mw_version_number() <= 11200 ) ) { return NULL; }
    return array_key_exists ( 'writeapi', $this->wiki['general'] );
  }  // can_i_writeapi() is much more user-specific - prefer it where available

  public function wiki_time ( $time = NULL ) {
    if ( is_null ( $time ) ) { $time = time(); }
    elseif ( ! is_numeric ( $time ) ) { $time = strtotime ( $time ); }
    return $time + $this->wiki['general']['timediff'];
  }

  public function wiki_lastreq_time () {
    return $this->wiki_time ( $this->browser->lastreq_time() );
  }

  # ----- Wiki namespaces ----- #

  # --- Lists of namespaces --- #

  protected function index_wiki_namespaces_allnames () {
    if ( ! empty ( $this->wiki['namespaces_indexed'] ) ) { return true; }
    if ( empty ( $this->wiki['namespaces'] ) ) { return NULL; }
    $this->wiki['namespaces_indexed'] = array ();
    foreach ( $this->wiki['namespaces'] as $id => &$namespace ) {
      if ( array_key_exists ( '*', $namespace ) ) {
        $this->wiki['namespaces_indexed'][$namespace['*']] = &$namespace;
      }
      if ( array_key_exists ( 'canonical', $namespace ) ) {
        $this->wiki['namespaces_indexed'][$namespace['canonical']] = &$namespace;
      }
      $aliases = $this->wiki_namespace_aliases ( $id );
      if ( is_array ( $aliases ) ) {
        foreach ( $aliases as $alias ) {
          $this->wiki['namespaces_indexed'][$alias] = &$namespace;
        }
      }
    }
    return true;
  }

  public function are_wiki_namespaces_obtained () {
    return $this->wiki_element_is_subarray ( 'namespaces' );
  }

  public function wiki_namespaces () {
    return $this->wiki_subarray ( 'namespaces' );
  }

  public function wiki_namespaces_count () {
    return $this->wiki_subarray_count ( 'namespaces' );
  }

  public function wiki_namespaces_ids () {
    return $this->wiki_subarray_elements_elements ( 'namespaces', 'id' );
  }

  public function wiki_namespaces_names () {
    return $this->wiki_subarray_elements_elements ( 'namespaces', '*' );
  }

  public function wiki_namespaces_canonical_names () {
    return $this->wiki_subarray_elements_elements ( 'namespace', 'canonical' );
  }

  public function wiki_namespaces_aliases () {
    return $this->wiki_subarray ( 'namespacealiases' );  // each alias is an array ( 'id' => id, '*' => alias )
  }

  public function wiki_namespaces_aliases_count () {
    return $this->wiki_subarray_count ( 'namespacealiases' );
  }

  public function wiki_namespaces_allnames () {
    if ( $this->index_wiki_namespaces_allnames() ) {
      return array_keys ( $this->wiki['namespaces_indexed'] );
    }
  }

  # --- Namespace data by id or name --- #

  public function wiki_namespace ( $id_or_name ) {
    if ( empty ( $this->wiki['namespaces'] ) ) { return NULL; }
    if ( array_key_exists ( $id_or_name, $this->wiki['namespaces'] ) ) {
      return $this->wiki['namespaces'][$id_or_name];
    } elseif ( $this->index_wiki_namespaces_allnames() &&
               array_key_exists ( $id_or_name, $this->wiki['namespaces_indexed'] ) ) {
      return $this->wiki['namespaces_indexed'][$id_or_name];
    } else {
      return false;
    }
  }

  public function wiki_namespace_is_present ( $id_or_name ) {
    $namespace = $this->wiki_namespace ( $id_or_name );
    if ( $namespace ) { return true; } else { return $namespace; }
  }

  protected function wiki_namespace_element ( $id_or_name, $element ) {
    $namespace = $this->wiki_namespace ( $id_or_name );
    if ( $namespace ) { return $namespace[$element]; } else { return $namespace; }
  }
  public function wiki_namespace_name           ( $id_or_name ) { return $this->wiki_namespace_element ( $id_or_name, '*'         ); }
  public function wiki_namespace_canonical_name ( $id_or_name ) { return $this->wiki_namespace_element ( $id_or_name, 'canonical' ); }
  public function wiki_namespace_case           ( $id_or_name ) { return $this->wiki_namespace_element ( $id_or_name, 'case'      ); }

  public function wiki_namespace_allows_subpages ( $id_or_name ) {
    $namespace = $this->wiki_namespace ( $id_or_name );
    return array_key_exists ( 'subpages', $namespace );
  }

  public function wiki_namespace_aliases ( $id_or_name ) {
    $namespace = $this->wiki_namespace ( $id_or_name );
    $aliases = array();
    if ( is_array ( $this->wiki['namespacealiases'] ) ) {
      foreach ( $this->wiki['namespacealiases'] as $alias ) {
        if ( $alias['id'] == $namespace['id'] ) {
          $aliases[] = $alias['*'];
        }
      }
    }
    return $aliases;
  }

  public function wiki_namespace_allnames ( $id_or_name ) {
    $names = array ( $this->wiki_namespace_name ( $id_or_name ) );
    if ( ! ( $names ) ) { return $names; }
    $canonical = $this->wiki_namespace_canonical_name ( $id_or_name );
    if ( ! empty ( $canonical ) ) { $names[] = $canonical; }
    $aliases = $this->wiki_namespace_aliases ( $id_or_name );
    if ( ! empty ( $aliases ) ) { $names = array_merge ( $names, $aliases ); }
    return $names;
  }

  public function wiki_namespace_id ( $name ) {
    $index_result = $this->index_wiki_namespaces_allnames();
    if ( $index_result && array_key_exists ( $name, $this->wiki['namespaces_indexed'] ) ) {
      return $this->wiki['namespaces_indexed'][$name]['id'];
    } else {
      return false;
    }
  }

  # --- Misc namespace support --- #

  public function wiki_namespace_barsepnames ( $id_or_name, $preg_quote = false, $regex_wikicase = false ) {
    if ( is_null ( $id_or_name ) ) {
      return $this->barsepstring ( $this->wiki_namespaces_allnames ( $id_or_name ), $preg_quote, $regex_wikicase );
    } else {
      return $this->barsepstring ( $this->wiki_namespace_allnames ( $id_or_name ), $preg_quote, $regex_wikicase );
    }
  }

  public function wiki_namespace_namesregex ( $id_or_name ) {
    $barsepstring = $this->wiki_namespace_barsepnames ( $id_or_name, true, true );
    if ( ! $barsepstring ) { return $barsepstring; }
    return '(' . $barsepstring . ')';
  }

  public function title_is_in_namespace ( $title, $namespace ) {
    $title_ns_id = $this->wiki_namespace_id ( $this->title_namespace ( $title ) );
    if ( is_int ( $namespace ) ) {
      $namespace_id = $namespace;
    } else {
      $namespace_id = $this->wiki_namespace_id ( $namespace );
      if ( is_null ( $namespace_id ) ) return NULL;
    }
    return ( $title_ns_id === $namespace_id );
  }

  # ----- Wiki interwikis ----- #

  protected function index_interwikis_urls () {
    if ( ! empty ( $this->wiki['interwikimap_indexed'] ) ) { return true; }
    if ( empty ( $this->wiki['interwikimap'] ) ) { return NULL; }
    $this->wiki['interwikimap_indexed'] = array();
    foreach ( $this->wiki['interwikimap'] as &$interwiki ) {
      $this->wiki['interwikimap_indexed'][$interwiki['url']] = $interwiki;
    }
    return true;
  }

  public function are_wiki_interwikis_obtained () {
    return $this->wiki_element_is_subarray ( 'interwikimap' );
  }

  public function wiki_interwikis () {
    return $this->wiki_subarray ( 'interwikimap' );
  }

  public function wiki_interwikis_count () {
    return $this->wiki_subarray_count ( 'interwikimap' );
  }

  public function wiki_interwikis_prefixes () {
    return $this->wiki_subarray_elements_elements ( 'interwikimap', 'prefix' );
  }

  public function wiki_interwiki ( $prefix ) {
    return $this->wiki_subarray_element_by_value ( 'interwikimap', 'prefix', $prefix );
  }

  public function wiki_interwiki_is_present ( $prefix ) {
    $interwiki = $this->wiki_interwiki ( $prefix );
    if ( $interwiki ) { return true; } else { return $interwiki; }
  }

  public function wiki_interwiki_url ( $prefix ) {
    $interwiki = $this->wiki_interwiki ( $prefix );
    if ( $interwiki ) { return $interwiki['url']; } else { return $interwiki; }
  }

  public function wiki_interwiki_language ( $prefix ) {
    $interwiki = $this->wiki_interwiki ( $prefix );
    if ( $interwiki ) { return $interwiki['language']; } else { return $interwiki; }
  }

  public function wiki_interwiki_is_local ( $prefix ) {
    $interwiki = $this->wiki_interwiki ( $prefix );
    if ( $interwiki ) { return array_key_exists ( 'local', $interwiki ); } else { return $interwiki; }
  }

  public function wiki_interwiki_by_url ( $url ) {
    $index_result = $this->index_wiki_interwikis_urls();
    if ( $index_result ) {
      $interwiki = $this->wiki['interwikimap_indexed'][$url];
      if ( empty ( $interwiki ) ) { return false; } else { return $interwiki; }
    } else {
      return $index_result;
    }
  }

  public function wiki_interwikis_by_language ( $language ) {  // language may NOT be unique!
    return $this->wiki_subarray_elements_by_values ( 'interwikimap', 'language', $language );
  }

  public function wiki_interwikis_barsepnames () {
    return $this->barsepstring ( $this->wiki_interwikis_prefixes() );
  }

  public function wiki_interwikis_prefixesregex () {
    return '(' . $this->wiki_interwikis_barsepnames() . ')';
  }

  # ----- Wiki special page aliases ----- #

  protected function index_specialpagealiases_allnames () {
    if ( ! empty ( $this->wiki['specialpagealiases_indexed'] ) ) { return true; }
    if ( empty ( $this->wiki['specialpagealiases'] ) ) { return NULL; }
    $this->wiki['specialpagealiases_indexed'] = array();
    foreach ( $this->wiki['specialpagealiases'] as &$specialpagealias ) {
      $this->wiki['specialpagealiases_indexed'][$specialpagealias['realname']] = $specialpagealias;
      foreach ( $specialpagealias['aliases'] as $alias ) {
        $this->wiki['specialpagealiases_indexed'][$alias] = $specialpagealias;
      }
    }
    return true;
  }

  public function are_wiki_specialpagealiases_obtained () {
    return $this->wiki_element_is_subarray ( 'specialpagealiases' );
  }

  public function wiki_specialpagealiases () {
    return $this->wiki_subarray ( 'specialpagealiases' );
  }

  public function wiki_specialpagealiases_count () {
    return $this->wiki_subarray_count ( 'specialpagealiases' );
  }

  public function wiki_specialpagealias ( $name_or_alias ) {
    $index_result = $this->index_specialpagealiases_allnames();
    if ( $index_result ) {
      $specialpagealias = $this->wiki['specialpagealiases_indexed'][$name_or_alias];
      if ( empty ( $specialpagealias ) ) { return false; } else { return $specialpagealias; }
    } else {
      return $index_result;
    }
  }

  public function wiki_specialpagealias_by_name ( $realname ) {
    return $this->wiki_subarray_element_by_value ( 'specialpagealiases', 'realname', $realname );
  }

  public function wiki_specialpagealias_by_alias ( $alias ) {
    if ( is_array ( $this->wiki['specialpagealiases'] ) ) {
      foreach ( $this->wiki['specialpagealiases'] as $specialpagealias ) {
        if ( in_array ( $alias, $specialpagealias['aliases'] ) ) {
          return $specialpagealias;
        }
      }
      return false;
    }
    return NULL;
  }

  public function wiki_specialpagealias_name_by_alias ( $alias ) {
    $specialpagealias = $this->wiki_specialpagealiases_by_alias ( $alias );
    return $specialpagealias['realname'];
  }

  public function wiki_specialpagealias_aliases ( $name_or_alias ) {
    $specialpagealias = $this->wiki_specialpagealias ( $name_or_alias );
    return $specialpagealias['aliases'];
  }

  public function wiki_specialpagealias_allnames ( $name_or_alias ) {
    $specialpagealias = $this->wiki_specialpagealias ( $name_or_aliases );
    $aliases = $specialpagealias['aliases'];
    $aliases[] = $specialpagealias['realname'];
    return $aliases;
  }

  public function wiki_specialpagealias_barsepnames ( $name_or_alias, $preg_quote = false, $regex_wikicase = false ) {
    return $this->barsepstring ( $this->wiki_specialpagealias_allnames ( $name_or_alias ), $preg_quote, $regex_wikicase );
  }

  public function wiki_specialpagealias_namesregex ( $name_or_alias ) {
    $barsepnames = $this->wiki_specialpagealias_barsepnames ( $name_or_alias, true, true );
    if ( ! $barsepnames ) { return $barsepnames; }
    return '(' . $barsepnames . ')';
  }

  public function wiki_specialpagealias_is_present ( $name_or_alias ) {
    $specialpagealias = $this->wiki_specialpagealias ( $name_or_alias );
    if ( $specialpagealias ) { return true; } else { return $specialpagealias; }
  }

  # ----- Wiki magic words ----- #

  protected function index_megicwords_allnames () {
    if ( ! empty ( $this->wiki['magicwords_indexed'] ) ) { return true; }
    if ( empty ( $this->wiki['magicwords'] ) ) { return NULL; }
    $this->wiki['magicwords_indexed'] = array();
    foreach ( $this->wiki['magicwords'] as &$magicword ) {
      $this->wiki['magicwords_indexed'][$magicword['name']] = $magicword;
      foreach ( $magicword['aliases'] as $alias ) {
        $this->wiki['magicwords_indexed'][$alias] = $magicword;
      }
    }
    return true;
  }

  public function are_wiki_magicwords_obtained () {
    return $this->wiki_element_is_subarray ( 'magicwords' );
  }

  public function wiki_magicwords () {
    return $this->wiki_subarray ( 'magicwords' );
  }

  public function wiki_magicwords_count () {
    return $this->wiki_subarray_count ( 'magicwords' );
  }

  public function wiki_magicword ( $name_or_alias ) {
    if ( empty ( $this->wiki['magicwords_indexed'] ) ) { return NULL; }
    $magicword = $this->wiki['magicwords_indexed'][$name_or_alias];
    if ( empty ( $magicword ) ) { return false; } else { return $magicword; }
  }

  public function wiki_magicword_by_name ( $name ) {
    return $this->wiki_subarray_element_by_value ( 'magicwords', 'name', $name );
  }

  public function wiki_magicword_by_alias ( $alias ) {
    $magicwords = $this->wiki_magicwords();
    if ( is_array ( $magicwords ) ) {
      foreach ( $magicwords as $magicword ) {
        if ( in_array ( $alias, $magicword['aliases'] ) ) {
          return $magicword;
        }
      }
    }
    return false;
  }

  public function wiki_magicword_name_by_alias ( $alias ) {
    $magicword = $this->wiki_magicword_by_alias ( $alias );
    return $magicword['name'];
  }

  public function wiki_magicword_aliases ( $name_or_alias ) {
    $magicword = $this->wiki_magicword ( $name_or_alias );
    if ( $magicword ) {
      return $magicword['aliases'];
    } else {
      return $magicword;
    }
  }

  public function wiki_magicword_allnames ( $name_or_alias ) {
    $magicword = $this->wiki_magicword ( $name_or_alias );
    $aliases = $magicword['aliases'];
    $aliases[] = $name_or_alias;
    return $aliases;
  }

  public function wiki_magicword_barsepnames ( $name_or_alias, $preg_quote = false, $regex_wikicase = false ) {
    return $this->barsepstring ( $this->wiki_magicword_allnames ( $name_or_alias ), $preg_quote, $regex_wikicase );
  }

  public function wiki_magicword_namesregex ( $name_or_alias ) {
    $barsepstring = $this->wiki_magicword_barsepnames ( $name_or_alias, true, true );
    if ( ! $barsepstring ) { return $barsepstring; }
    return '(' . $barsepstring . ')';
  }

  public function wiki_magicword_is_present ( $name_or_alias ) {
    $magicword = $this->wiki_magicword ( $name_or_alias );
    if ( $magicword ) { return true; } else { return $magicword; }
  }

  public function wiki_magicword_is_case_sensitive ( $name_or_alias ) {
    $magicword = $this->wiki_magicword ( $name_or_alias );
    if ( is_array ( $magicword ) ) {
      return in_array ( 'case-sensitive', $magicword );
    } else {
      return $magicword;
    }
  }

  # ----- Wiki extensions ----- #

  public function are_wiki_extensions_obtained () {
    return $this->wiki_element_is_subarray ( 'extensions' );
  }

  public function wiki_extensions () {
    return $this->wiki_subarray ( 'extensions' );
  }

  public function wiki_extensions_count () {
    return $this->wiki_subarray_count ( 'extensions' );
  }

  public function wiki_extensions_names () {
    return $this->wiki_subarray_elements_elements ( 'extensions', 'name' );
  }

  public function wiki_extension ( $name ) {
    return $this->wiki_subarray_element_by_value ( 'extensions', 'name', $name );
  }

  protected function wiki_extension_element ( $name, $element ) {
    $extension = $this->wiki_extension ( $name );
    if ( $extension ) { return $extension[$element]; } else { return $extension; }
  }
  public function wiki_extension_type            ( $name ) { return $this->wiki_extension_element ( $name, 'type'           ); }
  public function wiki_extension_author          ( $name ) { return $this->wiki_extension_element ( $name, 'author'         ); }
  public function wiki_extension_description     ( $name ) { return $this->wiki_extension_element ( $name, 'description'    ); }
  public function wiki_extension_description_msg ( $name ) { return $this->wiki_extension_element ( $name, 'descriptionmsg' ); }
  public function wiki_extension_url             ( $name ) { return $this->wiki_extension_element ( $name, 'url'            ); }
  public function wiki_extension_version         ( $name ) { return $this->wiki_extension_element ( $name, 'version'        ); }

  public function wiki_extension_is_present ( $name ) {
    $extension = $this->wiki_extension ( $name );
    if ( $extension ) { return true; } else { return $extension; }
  }

  public function wiki_extensions_by_type ( $type ) {
    return $this->wiki_subarray_elements_by_values ( 'extensions', 'type', $type );
  }

  public function wiki_extensions_by_author ( $author ) {
    return $this->wiki_subarray_elements_by_values ( 'extensions', 'author', $author );
  }

  # ----- Wiki file extensions ----- #

  public function are_wiki_fileextensions_obtained () {
    return $this->wiki_element_is_subarray ( 'fileextensions' );
  }

  public function wiki_fileextensions () {
    return $this->wiki_subarray ( 'fileextensions' );
  }

  public function wiki_fileextensions_count () {
    return count ( $this->wiki_fileextensions() );
  }

  public function wiki_fileextension_is_ok ( $ext ) {
    return $this->wiki_subarray_element_by_value ( 'fileextensions', 'ext', $ext );
  }

  # ----- Wiki rights info ----- #
  # See also wiki_rights()

  public function wiki_rightsinfo_is_obtained () {
    return $this->wiki_element_is_subarray ( 'rightsinfo' );
  }

  public function wiki_rightsinfo () {
    return $this->wiki_subarray ( 'rightsinfo' );
  }

  public function wiki_rightsinfo_url () {
    $rightsinfo = $this->wiki_rightsinfo();
    return $rightsinfo['url'];
  }

  public function wiki_rightsinfo_text () {
    $rightsinfo = $this->wiki_rightsinfo();
    return $rightsinfo['text'];
  }

  # ----- Wiki user groups ----- #

  public function are_wiki_usergroups_obtained () {
    return $this->wiki_element_is_subarray ( 'usergroups' );
  }

  public function wiki_usergroups () {
    return $this->wiki_subarray ( 'usergroups' );
  }

  public function wiki_usergroups_count () {
    return $this->wiki_subarray_count ( 'usergroups' );
  }

  public function wiki_usergroups_names () {
    return $this->wiki_subarray_elements_elements ( 'usergroups', 'name' );
  }

  public function wiki_usergroup ( $name ) {
    return $this->wiki_subarray_element_by_value ( 'usergroups', 'name', $name );
  }

  public function wiki_usergroup_is_present ( $name ) {
    return ( ! ( $this->wiki_usergroup ( $name ) === false ) );
  }

  public function wiki_usergroup_rights ( $name ) {
    $usergroup = $this->wiki_usergroup ( $name );
    if ( $usergroup ) {
      return $usergroup['rights'];
    } else {
      return $usergroup;
    }
  }

  public function wiki_usergroup_has_right ( $name, $right ) {
    $usergroup = $this->wiki_usergroup ( $name );
    if ( $usergroup ) {
      return array_key_exists ( $right, $usergroup['rights'] );
    } else {
      return $usergroup;
    }
  }

  public function wiki_usergroups_with_right ( $right ) {  // returns an array, possibly empty
    $usergroups = $this->wiki_usergroups();
    $right_groups = array();
    foreach ( $usergroups as $usergroup ) {
      if ( $this->wiki_usergroup_has_right ( $usergroup['name'], $right ) ) {
        $right_groups[] = $usergroup;
      }
    }
    return $right_groups;
  }

  # ----- Wiki statistics ----- #

  public function are_wiki_statistics_obtained () {
    return $this->wiki_element_is_subarray ( 'statistics' );
  }

  public function wiki_stats_pages       () { return $this->wiki['statistics']['pages'      ]; }
  public function wiki_stats_articles    () { return $this->wiki['statistics']['articles'   ]; }
  public function wiki_stats_edits       () { return $this->wiki['statistics']['edits'      ]; }
  public function wiki_stats_images      () { return $this->wiki['statistics']['images'     ]; }
  public function wiki_stats_users       () { return $this->wiki['statistics']['users'      ]; }
  public function wiki_stats_activeusers () { return $this->wiki['statistics']['activeusers']; }
  public function wiki_stats_admins      () { return $this->wiki['statistics']['admins'     ]; }
  public function wiki_stats_jobs        () { return $this->wiki['statistics']['jobs'       ]; }

  # ----- Wiki messages ----- #

  public function are_wiki_messages_fetched () {
    return ( ! empty ( $this->wiki['messages'] ) );
  }

  public function fetch_wiki_messages ( $messages = NULL, $filter = NULL, $language = NULL ) {
    if ( is_array ( $messages ) ) {
      $messages = $this->barsepstring ( $messages );
    }
    return $this->fetch_messages ( $messages, $filter, $language );
  }

  public function fetch_wiki_messages_if_needed ( $messages = NULL, $filter = NULL, $language = NULL ) {
    if ( ! $this->are_wiki_messages_fetched() ) {
      return $this->fetch_wiki_messages ( $messages, $filter, $language );
    } else {
      return true;
    }
  }

  public function wiki_messages () { return ( empty ( $this->wiki['messages'] ) ? false : $this->wiki['messages'] ); }

  public function wiki_message_by_name ( $name ) {
    if ( empty ( $this->wiki['messages'] ) ) { return NULL; }
    foreach ( $this->wiki['messages'] as $message ) {
      if ( $message['name'] == $name ) {
        return $message;
      }
    }
    return false;
  }

  public function wiki_message_text_by_name ( $name ) {
    $message = $this->wiki_message_by_name ( $name );
    if ( $message ) {
      return $message['text'];
    } else {
      return $message;
    }
  }

  public function wiki_message_by_text ( $text ) {
    if ( empty ( $this->wiki['messages'] ) ) { return NULL; }
    foreach ( $this->wiki['messages'] as $message ) {
      if ( $message['*'] == $text ) {
        return $message;
      }
    }
    return false;
  }

  public function wiki_message_name_by_text ( $text ) {
    $message = $this->wiki_message_by_text ( $text );
    if ( $message ) {
      return $message['name'];
    } else {
      return $message;
    }
  }

  public function wiki_message ( $name_or_text ) {
    $message = $this->wiki_message_by_name ( $name_or_text );
    if ( ! $message ) {
      $message = $this->Wiki_message_by_text ( $name_or_text );
    }
    return $message;
  }

  # ----- User general info ----- #

  public function is_user_info_obtained () { return ( ! empty ( $this->user ) ); }

  public function my_userid            () { return $this->user['id'              ]; }
  public function my_username          () { return $this->user['name'            ]; }
  public function my_editcount         () { return $this->user['editcount'       ]; }
  public function my_email             () { return $this->user['email'           ]; }

  public function my_groups            () { return $this->user['groups'          ]; }
  public function my_changeable_groups () { return $this->user['changeablegroups']; }
  public function my_permissions       () { return $this->user['rights'          ]; }
  public function my_options           () { return $this->user['options'         ]; }
  public function my_ratelimits        () { return $this->user['ratelimits'      ]; }

  public function am_i_anonymous () { return array_key_exists ( 'anon', $this->user ); }

  # ----- User groups ----- #

  public function are_my_groups_obtained () {
    return $this->user_element_is_subarray ( 'groups' );
  }

  protected function am_i_member_of ( $group ) {
    if ( $this->are_user_groups_obtained() ) {
      return in_array ( $group, $this->user['groups'] );
    } else {
      return NULL;
    }
  }
  public function am_i_bot_member   () { return $this->am_i_member_of ( 'bot'   ); }
  public function am_i_sysop_member () { return $this->am_i_member_of ( 'sysop' ); }

  # ----- User changeable groups ----- #

  public function are_my_changeable_groups_obtained () {
    return $this->user_element_is_subarray ( 'changeablegroups' );
  }

  public function my_add_groups () {
    return $this->user['changeablegroups']['add'];
  }

  public function my_remove_groups () {
    return $this->user['changeablegroups']['remove'];
  }

  public function my_addself_groups () {
    return $this->user['changeablegroups']['add-self'];
  }

  public function my_removeself_groups () {
    return $this->user['changeablegroups']['remove-self'];
  }

  public function can_i_add_group ( $group ) {
    return in_array ( $group, $this->user['changeablegroups']['add'] );
  }

  public function can_i_remove_group ( $group ) {
    return in_array ( $group, $this->user['changeablegroups']['remove'] );
  }

  public function can_i_addself_group ( $group ) {
    return in_array ( $group, $this->user['changeablegroups']['add-self'] );
  }

  public function can_i_removeself_group ( $group ) {
    return in_array ( $group, $this->user['changeablegroups']['remove-self'] );
  }

  # ----- User rights ----- #

  public function are_user_permissions_obtained () {
    return $this->user_element_is_subarray ( 'rights' );
  }

  protected function have_i_permission ( $right, $min_wiki_version, $max_wiki_version = NULL ) {
    if ( $this->are_user_permissions_obtained() &&
         ( $min_wiki_version <= $this->mw_version_number() ) &&
         ( is_null ( $max_wiki_version ) || ( $this->mw_version_number() <= $max_wiki_version ) )
       ) {
      return in_array ( $right, $this->user['rights'] );
    } else {
      return NULL;
    }
  }

  public function am_i_bot                  () { return $this->have_i_permission ( 'bot'                 , 10500 ); }
  public function am_i_autoconfirmed        () { return $this->have_i_permission ( 'autoconfirmed'       , 10600 ); }
  public function am_i_emailconfirmed       () { return $this->have_i_permission ( 'emailconfirmed'      , 10700, 11300 ); }

  public function am_i_ipblock_exempt       () { return $this->have_i_permission ( 'ipblock-exempt'      , 10900 ); }
  public function am_i_proxyunbannable      () { return $this->have_i_permission ( 'proxyunbannable'     , 10700 ); }

  public function have_i_highlimits         () { return $this->have_i_permission ( 'apihighlimits'       , 11200 ); }
  public function have_i_noratelimit        () { return $this->have_i_permission ( 'noratelimit'         , 11300 ); }

  public function can_i_read                () { return $this->have_i_permission ( 'read'                , 10500 ); }
  public function can_i_edit                () { return $this->have_i_permission ( 'edit'                , 10500 ); }
  public function can_i_editprotected       () { return $this->have_i_permission ( 'editprotected'       , 11300 ); }
  public function can_i_minoredit           () { return $this->have_i_permission ( 'minoredit'           , 10600 ); }
  public function can_i_skipcaptcha         () { return $this->have_i_permission ( 'skipcaptcha'         , 11700 ); }  // could not find it described??
  public function can_i_createpage          () { return $this->have_i_permission ( 'createpage'          , 10600 ); }
  public function can_i_createtalk          () { return $this->have_i_permission ( 'createtalk'          , 10600 ); }
  public function can_i_nominornewtalk      () { return $this->have_i_permission ( 'nominornewtalk'      , 10900 ); }
  public function can_i_writeapi            () { return $this->have_i_permission ( 'writeapi'            , 11300 ); }  // user-specific, unlike api_write_enabled()

  public function can_i_rollback            () { return $this->have_i_permission ( 'rollback'            , 10500 ); }
  public function can_i_markbotedits        () { return $this->have_i_permission ( 'markbotedits'        , 11200 ); }

  public function can_i_import              () { return $this->have_i_permission ( 'import'              , 10500 ); }
  public function can_i_importupload        () { return $this->have_i_permission ( 'importupload'        , 10500 ); }

  public function can_i_move                () { return $this->have_i_permission ( 'move'                , 10500 ); }
  public function can_i_movefile            () { return $this->have_i_permission ( 'movefile'            , 11400 ); }
  public function can_i_move_subpages       () { return $this->have_i_permission ( 'move-subpages'       , 11300 ); }
  public function can_i_move_rootuserpages  () { return $this->have_i_permission ( 'move-rootuserpages'  , 11400 ); }
  public function can_i_suppressredirect    () { return $this->have_i_permission ( 'suppressredirect'    , 11200 ); }

  public function can_i_upload              () { return $this->have_i_permission ( 'upload'              , 10500 ); }
  public function can_i_reupload            () { return $this->have_i_permission ( 'reupload'            , 10600 ); }
  public function can_i_reupload_own        () { return $this->have_i_permission ( 'reupload-own'        , 11100 ); }
  public function can_i_reupload_shared     () { return $this->have_i_permission ( 'reupload-shared'     , 10600 ); }
  public function can_i_uploadbyurl         () { return $this->have_i_permission ( 'upload_by_url'       , 10800 ); }

  public function can_i_see_deletedhistory  () { return $this->have_i_permission ( 'deletedhistory'      , 10600 ); }
  public function can_i_delete              () { return $this->have_i_permission ( 'delete'              , 10500 ); }
  public function can_i_bigdelete           () { return $this->have_i_permission ( 'bigdelete'           , 11200 ); }
  public function can_i_purge               () { return $this->have_i_permission ( 'purge'               , 11000 ); }
  public function can_i_undelete            () {
    $result = $this->have_i_permission ( 'undelete', 11200 );
    if ( is_null ( $result ) && ( $this->mw_version_number() < 11200 ) ) {
      $result = $this->have_i_permission ( 'delete', 10500 );
    }
    return $result;
  }
  public function can_i_browsearchive       () { return $this->have_i_permission ( 'browsearchive'       , 11300 ); }
  public function can_i_mergehistory        () { return $this->have_i_permission ( 'mergehistory'        , 11200 ); }
  public function can_i_suppressrevision    () { return $this->have_i_permission ( 'suppressrevision'    , 10600 ); }
  public function can_i_deleterevision      () { return $this->have_i_permission ( 'deleterevision'      , 10600 ); }

  public function can_i_protect             () { return $this->have_i_permission ( 'protect'             , 10500 ); }

  public function can_i_patrol              () { return $this->have_i_permission ( 'patrol'              , 10500 ); }
  public function can_i_autopatrol          () { return $this->have_i_permission ( 'autopatrol'          , 10900 ); }
  public function can_i_hideuser            () { return $this->have_i_permission ( 'hideuser'            , 11000 ); }

  public function can_i_block               () { return $this->have_i_permission ( 'block'               , 10500 ); }
  public function can_i_blockemail          () { return $this->have_i_permission ( 'blockemail'          , 11100 ); }

  public function can_i_createaccount       () { return $this->have_i_permission ( 'createaccount'       , 10500 ); }
  public function can_i_userrights          () { return $this->have_i_permission ( 'userrights'          , 10500 ); }
  public function can_i_userrights_interwiki() { return $this->have_i_permission ( 'userrights-interwiki', 11200 ); }
  public function can_i_editinterface       () { return $this->have_i_permission ( 'editinterface'       , 10500 ); }
  public function can_i_editusercssjs       () { return $this->have_i_permission ( 'editusercssjs'       , 11200 ); }
  public function can_i_sendemail           () { return $this->have_i_permission ( 'sendemail'           , 11700 ); } // could not find it described??

  public function can_i_remove_trackbacks   () { return $this->have_i_permission ( 'trackback'           , 10700 ); }
  public function can_i_see_unwatchedpages  () { return $this->have_i_permission ( 'unwatchedpages'      , 10600 ); }

  # ----- User options ----- #

  public function are_my_options_obtained () {
    return $this->user_element_is_subarray ( 'options' );
  }

  public function my_option ( $name ) {
    return $this->user['options'][$name];
  }

  # ----- User rate limits ----- #

  public function are_my_ratelimits_obtained () {
    return $this->user_element_is_subarray ( 'ratelimits' );
  }

  public function my_ratelimit ( $action ) {
    return $this->user['ratelimits'][$action];
  }

  # ----------  General support  ---------- #

  # ----- Namespaces support ----- #

  public function is_main_namespace_type ( $id_or_name ) {
    $namespace = $this->wiki_namespace ( $id_or_name );
    return ( ( $namespace['id'] >= 0 ) && ( ( $namespace['id'] % 2 ) == 0 ) );
  }

  public function is_talk_namespace_type ( $id_or_name ) {
    $namespace = $this->wiki_namespace ( $id_or_name );
    return ( ( $namespace['id'] >= 0 ) && ( ( $namespace['id'] % 2 ) == 1 ) );
  }

  public function is_special_namespace_type ( $id_or_name ) {
    $namespace = $this->wiki_namespace ( $id_or_name );
    return ( $namespace['id'] < 0 );
  }

  # ----- Page titles support ----- #

  public function title_parts ( $title, $partname = NULL ) {
    $parts = array();
    $pieces = explode ( ':', $title );

    $parts['wiki'] = "";
    $parts['namespace'] = "";
    $parts['title'] = end ( $pieces );
    if ( count ( $pieces ) > 1 ) {
      $parts['namespace'] = prev ( $pieces );
      if ( ! is_int ( $this->wiki_namespace_id ( $val ) ) ) {
        $parts['wiki'] = $parts['namespace'];
        $parts['namespace'] = "";
      }
    }
    if ( count ( $pieces ) > 2 ) {
      $parts['wiki'] = prev ( $pieces );
    }

    if ( empty ( $partname ) ) {
      return $parts;
    } else {
      return $parts[$partname];
    }
  }

  public function parts_title ( $parts ) {
    if ( ! empty ( $parts['wiki'     ] ) ) { $title .= $parts['wiki'     ] . ':'; }
    if ( ! empty ( $parts['namespace'] ) ) { $title .= $parts['namespace'] . ':'; }
    $title .= $parts['title'];
    return $title;
  }

  public function title_interwiki ( $title ) { return $this->title_parts ( $title, 'wiki'      ); }
  public function title_namespace ( $title ) { return $this->title_parts ( $title, 'namespace' ); }
  public function title_pagename  ( $title ) { return $this->title_parts ( $title, 'title'     ); }

  public function title_namespace_id ( $title ) {
    return $this->wiki_namespace_id ( $this->title_namespace ( $title ) );
  }

  public function is_main_page ( $title ) {
    $parts = $this->title_parts ( $title );
    return $this->is_main_namespace_type ( $parts['namespace'] );
  }

  public function is_talk_page ( $title ) {
    $parts = $this->title_parts ( $title );
    return $this->is_talk_namespace_type ( $parts['namespace'] );
  }

  public function is_special_page ( $title ) {
    $parts = $this->title_parts ( $title );
    return $this->is_special_namespace_type ( $parts['namespace'] );
  }

  public function talk_page_title ( $main_page_title ) {
    $parts = $this->title_parts ( $main_page_title );
    $ns_id = $this->wiki_namespace_id ( $parts['namespace'] );
    if ( ( $ns_id % 2 ) || ( $ns_id < 0 ) ) {
      return false;
    } else {
      $parts['namespace'] = $this->wiki_namespace_name ( $ns_id + 1 );
      return $this->parts_title ( $parts );
    }
  }

  public function main_page_title ( $talk_page_title ) {
    $parts = $this->title_parts ( $talk_page_title );
    $ns_id = $this->wiki_namespace_id ( $parts['namespace'] );
    if ( ! ( $ns_id % 2 ) || ( $ns_id < 0 ) ) {
      return false;
    } else {
      $parts['namespace'] = $this->wiki_namespace_name ( $ns_id - 1 );
      return $this->parts_title ( $parts );
    }
  }

  public function maintalk_pages_titles ( $title ) {
    if ( $this->is_main_page ( $title ) ) {
      return array ( 'main' => $title, 'talk' => $this->talk_page_title ( $title ) );
    } elseif ( $this->is_talk_page ( $title ) ) {
      return array ( 'main' => $this->main_page_title ( $title ), 'talk' => $title );
    } elseif ( $this->is_special_page ( $title ) ) {
      return array ( 'special' => $title );
    } else {
      return false;
    }
  }

  public function wikititle_to_url ( $title ) {
    return urlencode ( $this->wiki_server() . str_replace ( '$1', $title, $this->wiki_article_path() ) );
  }

  public function url_to_wikititle ( $url ) {
    $url = urldecode ( $url );
    $wiki_base = $this->wiki_server() . str_replace ( '$1', '', $this->wiki_article_path() );
    if ( stripos ( $url, $wiki_base ) === 0 ) {
      return stristr ( $url, $wiki_base );
    } else {
      return false;
    }
  }

  # ----- Common regexes ----- #

  public function regex_wikicase ( $string ) {
    switch ( $this->wiki_case() ) {
      case 'first-letter' :
        return '(?i)' . mb_substr ( $string, 0, 1, 'utf-8' ) . '(?-i)' . mb_substr ( $string, 1, 10000, 'utf-8' );
      default :
        return $string;
    }
  }

  // matches: 1 - leaging colon, 2 - wiki + colon, 3 - wiki, 4 - namespace + colon, 5 - namespace, 6 - title, 7 - sharp + anchor, 8 - anchor, 9 - bar + text, 10 - text
  public function regexmatch_wikilink ( $leading_colon = NULL, $wiki = NULL, $namespace = NULL, $title = NULL, $anchor = NULL, $text = NULL ) {
    $regex = '\[\[\h*';

    if ( is_null ( $leading_colon ) ) {
      $regex .= '(\:)?\h*';
    } elseif ( $leading_colon === true ) {
      $regex .= '(\:)\h*';
    } else {
      $regex .= '()';
    }

    if ( is_null ( $wiki ) ) {
      $regex .= '(([^\:\#\|\]]+)\:)?\h*';
    } elseif ( $wiki === "" ) {
      $regex .= "(())";
    } elseif ( $wiki === "*" ) {
      $regex .= '(' . $this->wiki_interwikis_prefixesregex() . '\h*\:)\h*';
    } else {
      $regex .= '((' . str_replace ( '\|', '|', preg_quote ( $wiki ) ) . ')\h*\:)\h*';
    }

    if ( is_null ( $namespace ) ) {
      $regex .= '(([^\:\#\|\]]+)\:)?\h*';
    } elseif ( $namespace === "" ) {
      $regex .= "(())";
    } elseif ( $namespace === "*" ) {
      $regex .= '(' . $this->wiki_namespace_namesregex ( NULL ) . '\h*\:\h*';
    } else {
      $regex .= '((' . str_replace ( '\|', '|', preg_quote ( $namespace ) ) . ')\h*\:)\h*';
    }

    if ( is_null ( $title ) ) {
      $regex .= '([^\#\|\]]+)';
    } elseif ( $title === "" ) {
      $regex .= "()";
    } else {
      $regex .= '(' . str_replace ( '\|', '|', preg_quote ( $title ) ) . ')';
    }

    if ( is_null ( $anchor ) ) {
      $regex .= '(\#([^\|\]]*))?';
    } elseif ( $anchor === "" ) {
      $regex .= '(())';
    } else {
      $regex .= '(\#(' . preg_quote ( $anchor ) . '))';
    }

    if ( is_null ( $text ) ) {
      $regex .= '(\|(\[\[[^\]]*\]\]|[^\]]*))?';
    } elseif ( $text === "" ) {
      $regex .= '(())';
    } else {
      $regex .= '(\|(' . preg_quote ( $text ) . '))';
    }

    $regex .= '\]\]';

    return $regex;
  }

  public function regex_wikilink ( $leading_colon = NULL, $wiki = NULL, $namespace = NULL, $title = NULL, $anchor = NULL, $text = NULL ) {
    return '/' . $this->regexmatch_wikilink ( $leading_colon, $wiki, $namespace, $title, $anchor, $text ) . '/u';
  }

}

