<?php
#
#  An Apibot extension - Iterators. Used together with ActionObjects.
#
#  Example for usage:
#
#  $bot = new Apibot ( $bot_login_data, $logname );
#  $bot->enter_wiki();  // mandatory for some iterators to work
#  $Iterator = new Iterator_WhateverTypeYouNeed ( $bot );
#  $ActionObject = new ActionObject_WhateverActionYouNeed();
#  $processed_elements_count = $Iterator->iterate ( $ActionObject );
#
#  -----
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
#  You should have received a copy of the GNU General Public License along
#  with this program; if not, write to the Free Software Foundation, Inc.,
#  59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
#  http://www.gnu.org/copyleft/gpl.html
#
#  Author: Grigor Gatchev <grigor at gatchev dot info>
#
#  --------------------------------------------------------------------------- #

require_once ( dirname ( __FILE__ ) . '/apibot.php' );
require_once ( dirname ( __FILE__ ) . '/apibot_actionobjects.php' );  // not really needed, but convenient


# ----- -----  Iterator classes  ----- ----- #

# ----------  Generic iterator class  ---------- #


abstract class Iterator_Generic {
// A base for all iterator classes, both these that use the bot as elements source and others.
// Since this is an Apibot module, a bot will always be needed eventually.

  public $elements_counter = 0;
  public $elements_limit = PHP_INT_MAX;

  public $abort_iteration = false;

  public $bot;

  # ----------  Constructor  ---------- #

  function __construct ( $bot ) {
    $this->bot = $bot;
  }

  # ----------  Protected  ---------- #

  protected function log ( $string, $loglevel = LL_INFO ) {
    $this->bot->log ( $string, $loglevel );
  }

  protected function appropriate_actionobject_type () {  // override with more specific on need.
    return 'ActionObject';
  }

  protected function iterate_element ( $element, $ActionObject ) {  // override to modify the behaviour
    if ( is_object ( $ActionObject ) ) {
      return $ActionObject->process ( $element );
    } elseif ( is_string ( $ActionObject ) || is_array ( $ActionObject ) ) {
      return call_user_func ( $ActionObject, $element, $this, $this->bot );
    }
  }

  protected function iterate_elements ( $ActionObject ) {  // override to modify the behaviour
    if ( ! $this->open_elements_source() ) { $this->log ( $this->error_info(), LL_ERROR ); }
    $result = $this->query();
    if ( ! $result ) { $this->log ( $this->error_info(), LL_ERROR ); }
    while ( $result ) {
      $elements_array = $this->obtain_elements_array();
      if ( $elements_array !== false ) {
        foreach ( $elements_array as $element ) {
          if ( $this->iterate_element ( $element, $ActionObject ) ) { $this->elements_counter += 1; }
          if ( $this->abort_iteration || ( $this->elements_counter == $this->elements_limit ) ) { break 2; }
        }
        $result = $this->continue_query();
      } else {
        break;
      }

    }
    if ( ! $this->close_elements_source() ) { $this->log ( $this->error_info(), LL_ERROR ); }
    return $this->elements_counter;
  }

  protected function pre_iterate_elements ( $ActionObject ) {  // override if necessary
    return $ActionObject->preprocess ();
  }

  protected function post_iterate_elements ( $ActionObject ) {  // override if necessary
    return $ActionObject->postprocess ();
  }

  protected function  open_elements_source () { return true; }
  protected function close_elements_source () { return true; }

  abstract protected function query();

  abstract protected function continue_query();

  abstract protected function obtain_elements_array();

  abstract protected function error_info();

  # ----------  Public  ---------- #

  public function iterate ( $ActionObject ) {
    if ( is_object ( $ActionObject ) ) {
      $appropriate_actionobject_type = $this->appropriate_actionobject_type();
      if ( $ActionObject instanceof $appropriate_actionobject_type ) {
        if ( $this->pre_iterate_elements ( $ActionObject ) ) {
          $this->elements_counter = 0;
          $ActionObject->iterator = $this;
          $result = $this->iterate_elements ( $ActionObject );
          $this->post_iterate_elements ( $ActionObject );
          return $result;
        }
      } else {
        $this->log ( "Inapropriate action object type -- aborting!", LL_ERROR );
        return false;
      }
    } elseif ( is_string ( $ActionObject ) || is_array ( $ActionObject ) ) {
      return $this->iterate_elements ( $ActionObject );
    } else {
      $this->log ( "I don't know how to use such an action object!", LL_ERROR );
      return false;
    }
  }

}


# ----------  Generic Apibot iterator class  ---------- #

abstract class Iterator_Apibot_Generic extends Iterator_Generic {

  # ----------  Protected  ---------- #

  protected function error_info () {
    return $this->bot->error_string();
  }

  protected function iterate_element ( $element, $ActionObject ) {
    $this->bot->push_bot_state();
    $result = parent::iterate_element ( $element, $ActionObject );
    $this->bot->pop_bot_state();
    return $result;
  }

}

# ----------  Generic API iterator class  ---------- #

abstract class Iterator_GenericAPI extends Iterator_Apibot_Generic {

  protected $query_datatree_element_name;  // to be overridden!
  protected $element_object_class_name;    // to be overridden!

  # ----------  Private  ---------- #

  # ----- Obtaining requested data from the request response ----- #

  private function check_data_elements_for_errors ( $elements ) {
    $counter = 0;
    while ( true ) {
      $counter--;
      if ( array_key_exists ( $counter, $elements ) ) {
        $error = $elements[$counter];
        if ( array_key_exists ( "invalid", $error ) ) {
          $this->bot->error['type'] = 2;
          $this->bot->error['code'] = "invalid_element";
          $this->bot->error['info'] = "Invalid element data supplied!";
          return true;
        }
      } else {
        break;
      }
    }
    return false;
  }

  private function dispatch_data_element ( $data_element ) {
    $object = new $this->element_object_class_name;
    $object->read_from_element ( $data_element, $this->bot );
    return $object;
  }

  # ----------  Protected  ---------- #

  # ----- Tools ----- #

  protected function add_nonempty ( &$params, $param, $key, $minimal_mw_version = 11000 ) {
    if ( $minimal_mw_version <= $this->bot->mw_version_number() ) {
      if ( ! empty ( $param ) ) {
        $params[$key] = $param;
      }
    }
  }

  protected function add_namespace ( &$params, $namespace, $key = 'namespace' ) {
    if ( ! is_null ( $namespace ) ) {
      $ns_code = $this->bot->wiki_namespace_id ( $namespace );
      if ( $ns_code === false ) {
        if ( is_numeric ( $namespace ) ) {
          $params[$key] = $namespace;
        } else {
          $this->log ( "Bad namespace: " . $namespace . "!", LL_ERROR );
        }
      } else {
        $params[$key] = $ns_code;
      }
    }
  }

  protected function add_startenddir ( &$params, $start, $end, $suffix = NULL ) {
    if ( ! is_null ( $start ) ) { $params['start'.$suffix] = $start; }
    if ( ! is_null ( $end   ) ) { $params['end'  .$suffix] = $end  ; }
    if ( ! ( is_null ( $start ) || is_null ( $end ) ) ) {
      if ( $start >= $end ) {
        $params['dir'] = 'older';
      } else {
        $params['dir'] = 'newer';
      }
    }
  }

  protected function add_user ( &$params, $user ) {
    if ( ! empty ( $user ) ) {
      if ( mb_substr ( $user, 0, 1 ) == '!' ) {
        $params['excludeuser'] = mb_substr ( $user, 1 );
      } else {
        $params['user'] = $user;
      }
    }
  }

  protected function add_boolbang ( &$array, $bool, $string, $minimal_mw_version = 11000 ) {
    if ( $minimal_mw_version <= $this->bot->mw_version_number() ) {
      if ( $bool === true ) {
        $array[] = $string;
      } elseif ( $bool === false ) {
        $array[] = '!' . $string;
      }
    }
  }

  protected function add_boolkey ( &$array, $bool, $key, $value = '' ) {
    if ( $bool === true ) {
      $array[$key] = $value;
    }
  }

  protected function add_boolvalue ( &$array, $bool, $value, $minimal_mw_version = 11000 ) {
    if ( $minimal_mw_version <= $this->bot->mw_version_number() ) {
      if ( $bool === true ) {
        $array[] = $value;
      }
    }
  }

  # ----- API querying ----- #

  protected function query_elements_array ( $query_tree ) {  // override further for the general types classes!
    if ( ! is_array ( $query_tree ) ) { return false; }
    if ( ! empty ( $this->bot->error ) ) { return false; }
    if ( array_key_exists ( $this->query_datatree_element_name, $query_tree ) ) {
      return $query_tree[$this->query_datatree_element_name];
    } else {
      return false;
    }
  }

  protected function obtain_elements_array () {
    $data_elements = $this->query_elements_array ( $this->bot->query_tree() );
    if ( $data_elements === false ) {
      return array();
    } else {
      if ( $this->check_data_elements_for_errors ( $data_elements ) ) {
        return false;
      } else {
        $data_objects = array();
        foreach ( $data_elements as $data_element ) {
          $data_objects[] = $this->dispatch_data_element ( $data_element );
        }
        return $data_objects;
      }
    }
  }

  protected function continue_query () {
    return $this->bot->continue_query();
  }

}


# ----------  List members iterator classes  ---------- #


abstract class Iterator_ListMembers extends Iterator_GenericAPI {

  protected $list_code;  // to be 'overridden'

  public $limit = "max";

  public $titles  = array();  // titles of the pages who can be processed (valid only in some iterators)
  public $pageids = array();
  public $revids  = array();

  # ----------  Protected  ---------- #

  protected function query () {  // overrides abstract
    $listparams = $this->gather_list_params();
    $params     = $this->gather_params();
    return $this->bot->query_list ( $this->query_datatree_element_name, $this->list_code, $listparams, $params );
  }

  protected function gather_params () {
    $params = array();
    $this->add_nonempty ( $params, $this->titles , 'titles' , 11200 );
    $this->add_nonempty ( $params, $this->pageids, 'pageids', 11200 );
    $this->add_nonempty ( $params, $this->revids , 'revids' , 11200 );
    return $params;
  }

  protected function gather_list_params () {
    $params = array();
    $this->add_nonempty ( $params, $this->limit, 'limit', 11200 );
    return $params;
  }

}


class Iterator_RecentChanges extends Iterator_ListMembers {

  protected $query_datatree_element_name = 'recentchanges';  // overrides 'abstract'
  protected $element_object_class_name   = 'RecentChange';   // overrides 'abstract'
  protected $list_code = 'rc';

  public $start;         // datetime
  public $end;           // datetime (if before $start, iteration goes from $end to $start)
  public $namespace = NULL;  // NULL - all namespaces, "" or 0 - main namespace, etc.
  public $user;          // username/IP, or NULL for all users
  public $type;          // rc entry types to obtain - 'edit, 'new', 'log'
  public $show_minor;    // NULL, true (minor only), false (non-minor only)
  public $show_anon;     // like $show_minor, for anonymous edits
  public $show_bot;      // like $show_minor, for bot edits
  public $show_redirect; // like $show_minor, for redirect pages
  public $show_patrolled;// will work only if the bot has the patrol right
  public $get_user      = true;
  public $get_comment   = true;
  public $get_timestamp = true;
  public $get_title     = true;
  public $get_ids       = true;
  public $get_sizes     = true;
  public $get_redirect  = true;
  public $get_patrolled = true;
  public $get_loginfo   = true;
  public $get_flags     = true;

  protected function gather_list_params () {
    $mw_version_number = $this->bot->mw_version_number();

    $params = parent::gather_list_params();

    $this->add_startenddir ( $params, $this->start, $this->end );
    $this->add_namespace   ( $params, $this->namespace );
    $this->add_user        ( $params, $this->user );
    $this->add_nonempty    ( $params, $this->type, 'type' );

    $show = array();
    $this->add_boolbang ( $show, $this->show_minor    , 'minor'     );
    $this->add_boolbang ( $show, $this->show_anon     , 'anon'      );
    $this->add_boolbang ( $show, $this->show_bot      , 'bot'       );
    $this->add_boolbang ( $show, $this->show_redirect , 'redirect'  );
    if ( $this->bot->can_i_patrol() ) {
      $this->add_boolbang ( $show, $this->show_patrolled, 'patrolled', 11300 );
    }
    $this->add_nonempty ( $params, $this->bot->barsepstring ( $show ), 'show' );

    $prop = array();
    $this->add_boolvalue ( $prop, $this->get_user     , 'user'      );
    $this->add_boolvalue ( $prop, $this->get_comment  , 'comment'   );
    $this->add_boolvalue ( $prop, $this->get_timestamp, 'timestamp' );
    $this->add_boolvalue ( $prop, $this->get_title    , 'title'     );
    $this->add_boolvalue ( $prop, $this->get_ids      , 'ids'       );
    $this->add_boolvalue ( $prop, $this->get_sizes    , 'sizes'     );
    $this->add_boolvalue ( $prop, $this->get_redirect , 'redirect', 11300 );
    if ( $this->bot->can_i_patrol() ) {
      $this->add_boolvalue ( $prop, $this->get_patrolled, 'patrolled', 11300 );
    }
    $this->add_boolvalue ( $prop, $this->get_loginfo  , 'loginfo' , 11300 );
    $this->add_boolvalue ( $prop, $this->get_flags    , 'flags'     );
    $this->add_nonempty ( $params, $this->bot->barsepstring ( $prop ), 'prop' );

    return $params;
  }

}


class Iterator_UserContribs extends Iterator_ListMembers {

  protected $query_datatree_element_name = 'usercontribs';  // overrides 'abstract'
  protected $element_object_class_name   = 'UserContrib';   // overrides 'abstract'
  protected $list_code = 'uc';

  public $user;            // mandatory, unless $userprefix is specified
  public $userprefix;      // overrides $user
  public $start;           // datetime
  public $end;             // datetime (if before $start, iteration goes from $end to $start)
  public $namespace = NULL;// NULL - all namespaces, "" or 0 - main namespace, etc.
  public $show_minor;      // NULL, true (minor only), false (non-minor only)
  public $show_patrolled;  // will work only if the user has patrol right
  public $get_ids       = true;
  public $get_title     = true;
  public $get_timestamp = true;
  public $get_comment   = true;
  public $get_patrolled = true;
  public $get_flags     = true;

  protected function gather_list_params () {
    if ( empty ( $this->user ) && empty ( $this->userprefix ) ) {
      $this->log ( "UserContribs request: neither user nor userprefix are specified - no data will be obtained!", LL_ERROR );
    }
    if ( ! empty ( $this->userprefix ) ) { $this->user = NULL; }

    $params = parent::gather_list_params();

    $this->add_startenddir ( $params, $this->start, $this->end );
    $this->add_namespace   ( $params, $this->namespace );
    $this->add_nonempty    ( $params, $this->user      , 'user' );
    $this->add_nonempty    ( $params, $this->userprefix, 'userprefix' );

    $show = array();
    $this->add_boolbang ( $show, $this->show_minor    , 'minor'     );
    if ( $this->bot->can_i_patrol() ) {
      $this->add_boolbang ( $show, $this->show_patrolled, 'patrolled', 11400 );
    }
    $this->add_nonempty ( $params, $this->bot->barsepstring ( $show ), 'show' );

    $prop = array();
    $this->add_boolvalue ( $prop, $this->get_ids      , 'ids'       );
    $this->add_boolvalue ( $prop, $this->get_title    , 'title'     );
    $this->add_boolvalue ( $prop, $this->get_timestamp, 'timestamp' );
    $this->add_boolvalue ( $prop, $this->get_comment  , 'comment'   );
    if ( $this->bot->can_i_patrol() ) {
      $this->add_boolvalue ( $prop, $this->get_patrolled, 'patrolled', 11400 );
    }
    $this->add_boolvalue ( $prop, $this->get_flags    , 'flags'     );
    $this->add_nonempty ( $params, $this->bot->barsepstring ( $prop ), 'prop' );

    return $params;
  }

}


class Iterator_AllUsers extends Iterator_ListMembers {

  protected $query_datatree_element_name = 'allusers';  // overrides 'abstract'
  protected $element_object_class_name   = 'User';   // overrides 'abstract'
  protected $list_code = 'au';

  public $from;    // user to start with
  public $prefix;  // username prefix
  public $group;   // could be any defined group; 'bot', 'sysop' and 'bureaucrat' are common
  public $get_editcount    = true;
  public $get_groups       = true;
  public $get_registration = true;

  protected function gather_list_params () {
    $params = parent::gather_list_params();

    $this->add_nonempty ( $params, $this->from  , 'from'   );
    $this->add_nonempty ( $params, $this->prefix, 'prefix' );
    $this->add_nonempty ( $params, $this->group , 'group'  );

    $prop = array();
    $this->add_boolvalue ( $prop, $this->get_editcount   , 'editcount'    );
    $this->add_boolvalue ( $prop, $this->get_groups      , 'groups'       );
    $this->add_boolvalue ( $prop, $this->get_registration, 'registration' );
    $this->add_nonempty ( $params, $this->bot->barsepstring ( $prop ), 'prop' );

    return $params;
  }

}


class Iterator_Blocks extends Iterator_ListMembers {

  protected $query_datatree_element_name = 'blocks';  // overrides 'abstract'
  protected $element_object_class_name   = 'Block';   // overrides 'abstract'
  protected $list_code = 'bk';

  public $start;        // datetime
  public $end;          // datetime (if before $start, iteration goes from $end to $start)
  public $ids;          // will iterate these block IDs
  public $users;        // will iterate blocks of these users
  public $ip;           // will iterate blocks affecting this IP address / CIDR range (ranges cannot be larger than /16)
  public $get_id        = true;
  public $get_user      = true;
  public $get_by        = true;
  public $get_timestamp = true;
  public $get_expiry    = true;
  public $get_reason    = true;
  public $get_range     = true;
  public $get_flags     = true;

  protected function gather_list_params () {
    $params = parent::gather_list_params();

    $this->add_startenddir ( $params, $this->start, $this->end );
    $this->add_nonempty ( $params, $this->ids  , 'ids'   );
    $this->add_nonempty ( $params, $this->users, 'users' );
    $this->add_nonempty ( $params, $this->ip   , 'ip'    );

    $prop = array();
    $this->add_boolvalue ( $prop, $this->get_id       , 'id'        );
    $this->add_boolvalue ( $prop, $this->get_user     , 'user'      );
    $this->add_boolvalue ( $prop, $this->get_by       , 'by'        );
    $this->add_boolvalue ( $prop, $this->get_timestamp, 'timestamp' );
    $this->add_boolvalue ( $prop, $this->get_expiry   , 'expiry'    );
    $this->add_boolvalue ( $prop, $this->get_reason   , 'reason'    );
    $this->add_boolvalue ( $prop, $this->get_range    , 'range'     );
    $this->add_boolvalue ( $prop, $this->get_flags    , 'flags'     );
    $this->add_nonempty ( $params, $this->bot->barsepstring ( $prop ), 'prop' );

    return $params;
  }

}


class Iterator_LogEvents extends Iterator_ListMembers {

  protected $query_datatree_element_name = 'logevents';  // overrides 'abstract'
  protected $element_object_class_name   = 'LogEvent';   // overrides 'abstract'
  protected $list_code = 'le';

  public $start;        // datetime
  public $end;          // datetime (if before $start, iteration goes from $end to $start)
  public $user;         // will iterate log events caused by this user
  public $title;        // will iterate log events concerning this page title
  public $type;         // 'block', 'protect', 'rights', 'delete', 'upload', 'move', 'import', 'patrol', 'merge', 'newusers'
  public $get_ids       = true;
  public $get_title     = true;
  public $get_type      = true;
  public $get_user      = true;
  public $get_timestamp = true;
  public $get_comment   = true;
  public $get_details   = true;

  protected function gather_list_params () {
    $params = parent::gather_list_params();

    $this->add_startenddir ( $params, $this->start, $this->end );
    $this->add_nonempty ( $params, $this->user , 'user'  );
    $this->add_nonempty ( $params, $this->title, 'title' );
    $this->add_nonempty ( $params, $this->type , 'type'  );

    $prop = array();
    $this->add_boolvalue ( $prop, $this->get_ids      , 'ids'       );
    $this->add_boolvalue ( $prop, $this->get_title    , 'title'     );
    $this->add_boolvalue ( $prop, $this->get_type     , 'type'      );
    $this->add_boolvalue ( $prop, $this->get_user     , 'user'      );
    $this->add_boolvalue ( $prop, $this->get_timestamp, 'timestamp' );
    $this->add_boolvalue ( $prop, $this->get_comment  , 'comment'   );
    $this->add_boolvalue ( $prop, $this->get_details  , 'details'   );
    $this->add_nonempty ( $params, $this->bot->barsepstring ( $prop ), 'prop' );

    return $params;
  }

}


class Iterator_DeletedRevs extends Iterator_ListMembers {
// You can iterate the deleted revs EITHER for certain titles, OR for certain user, OR for certain namespace!

  protected $query_datatree_element_name = 'deletedrevs';     // overrides 'abstract'
  protected $element_object_class_name   = 'DeletedRevision'; // overrides 'abstract'
  protected $list_code = 'dr';

  public $start;      // datetime
  public $end;        // datetime (if before $start, iteration goes from $end to $start)
  public $user;              // only iterate revisions deleted by this user ('!user' - not by this user)
  public $namespace = NULL;  // NULL - all namespaces, "" or 0 - main namespace, etc.
                             // set only one of $titles, $user and $namespace!
  public $from;              // will start from this title
  public $unique = false;    // list only one revision for each page?
  public $get_revid   = true;
  public $get_user    = true;
  public $get_comment = true;
  public $get_minor   = true;
  public $get_len     = true;
  public $get_content = true;
  public $get_token   = true;

  protected function gather_list_params () {
    if ( ! $this->bot->can_i_see_deletedhistory() ) {
      $this->log ( "Deleted revisions requested without having the right 'deletedhistory' - no data will be obtained!", LL_ERROR );
    }

    if ( $this->bot->mw_version_number() < 11300 ) $this->limit = NULL;  // MW 1.12 deletedrevs module doesn't understand limit=max
    $params = parent::gather_list_params();

    $this->add_startenddir ( $params, $this->start, $this->end );
    if ( empty ( $this->titles ) ) {
      if ( ! empty ( $this->user ) ) {
        $this->add_user      ( $params, $this->user, 'user'  );
      } else {
        $this->add_namespace ( $params, $this->namespace );
      }
    }
    $this->add_nonempty    ( $params, $this->from  , 'from'  );
    $this->add_boolkey     ( $params, $this->unique, 'unique' );

    $prop = array();
    $this->add_boolvalue ( $prop, $this->get_revid  , 'revid'   );
    $this->add_boolvalue ( $prop, $this->get_user   , 'user'    );
    $this->add_boolvalue ( $prop, $this->get_comment, 'comment' );
    $this->add_boolvalue ( $prop, $this->get_minor  , 'minor'   );
    $this->add_boolvalue ( $prop, $this->get_len    , 'len'     );
    if ( $this->bot->can_i_undelete() ) {
      $this->add_boolvalue ( $prop, $this->get_content, 'content' );
    }
    $this->add_boolvalue ( $prop, $this->get_token  , 'token'   );
    $this->add_nonempty ( $params, $this->bot->barsepstring ( $prop ), 'prop' );

    return $params;
  }

  protected function query_elements_array ( $data_tree ) {
    $page_elements = parent::query_elements_array ( $data_tree );
    $deleted_revisions_elements = array();
    foreach ( $page_elements as $page_element ) {
      foreach ( $page_element['revisions'] as $revision_element ) {
        $revision_element['title'] = $page_element['title'];
        $revision_element['token'] = $page_element['token'];
        $deleted_revisions_elements[] = $revision_element;
      }
    }
    return $deleted_revisions_elements;
  }

}


class Iterator_Users extends Iterator_ListMembers {

  protected $query_datatree_element_name = 'users';  // overrides 'abstract'
  protected $element_object_class_name   = 'User';   // overrides 'abstract'
  protected $list_code = 'us';

  public $users = array();  // list of users names to get info about - mandatory!
  public $get_token_rights = true;
  public $get_blockinfo    = true;
  public $get_groups       = true;
  public $get_editcount    = true;
  public $get_registration = true;
  public $get_emailable    = true;

  protected function gather_list_params () {
    $params = parent::gather_list_params();

    $this->add_nonempty    ( $params, $this->bot->barsepstring ( $this->users ), 'users' );
    if ( $this->get_token_rights ) { $params['token'] = 'userrights'; }

    $prop = array();
    $this->add_boolvalue ( $prop, $this->get_blockinfo   , 'blockinfo'    );
    $this->add_boolvalue ( $prop, $this->get_groups      , 'groups'       );
    $this->add_boolvalue ( $prop, $this->get_editcount   , 'editcount'    );
    $this->add_boolvalue ( $prop, $this->get_registration, 'registration' );
    $this->add_boolvalue ( $prop, $this->get_emailable   , 'emailable'    );
    $this->add_nonempty ( $params, $this->bot->barsepstring ( $prop ), 'prop' );

    return $params;
  }

}


class Iterator_ProtectedTitles extends Iterator_ListMembers {

  protected $query_datatree_element_name = 'protectedtitles';  // overrides 'abstract'
  protected $element_object_class_name   = 'ProtectedTitle';   // overrides 'abstract'
  protected $list_code = 'pt';

  public $start;          // timestamp to start listing page protections from
  public $end;            // timestamp to end listing to (if before $start, they will be listed backwards)
  public $namespace;      // NULL - all namespaces, "" or 0 - main namespace, etc.
  public $level;          // list only pages with this protection level, eg. 'sysop'
  public $get_timestamp;  // when the title was protected
  public $get_expiry;     // timestamp when the protection expires
  public $get_user;       // who protected it
  public $get_comment;    // with what comment
  public $get_level;      // level needed to create the page

  protected function gather_list_params () {
    $params = parent::gather_list_params();

    $this->add_startenddir ( $params, $this->start, $this->end );
    $this->add_namespace   ( $params, $this->namespace );
    $this->add_nonempty    ( $params, $this->level, 'level' );

    $prop = array();
    $this->add_boolvalue ( $prop, $this->get_timestamp, 'timestamp' );
    $this->add_boolvalue ( $prop, $this->get_user     , 'user'      );
    $this->add_boolvalue ( $prop, $this->get_comment  , 'comment'   );
    $this->add_boolvalue ( $prop, $this->get_expiry   , 'expiry'    );
    $this->add_boolvalue ( $prop, $this->get_level    , 'level'     );
    $this->add_nonempty ( $params, $this->bot->barsepstring ( $prop ), 'prop' );

    return $params;
  }

}


# ----------  Generator members iterator classes  ---------- #


abstract class Iterator_GeneratorMembers extends Iterator_ListMembers {

  protected $element_object_class_name   = 'Page';   // overrides 'abstract'
  protected $query_datatree_element_name = 'pages';  // overrides 'abstract'
  // When in list mode, the name is 'allpages', 'alllinks' etc. for the different iterators.
  // When in generator mode, the name is always 'pages'!

  protected $list_name;  // to be 'overriden'

  public $list_mode = false;   // if true, will work as an ordinary list iterator

  public $properties = array();  // set here subarrays with properties lists, eg. $this->properties['info'] = array ( 'props' => 'title|ids' )

  # ----------  Protected  ---------- #

  protected function query () {
    if ( $this->list_mode ) {
      return parent::query();
    } else {
      $this->element_object_class_name   = 'Page';  // generator mode always yields pages
      $this->query_datatree_element_name = 'pages';
      $listparams = $this->gather_list_params();
      $params     = $this->gather_params();
      return $this->bot->query_generator ( $this->list_name, $this->list_code, $listparams, $this->properties, $params );
    }
  }

}


class Iterator_AllPages extends Iterator_GeneratorMembers {

  protected $query_datatree_element_name = 'allpages';  // list mode
  protected $element_object_class_name   = 'Page';      // list mode
  protected $list_code = 'ap';
  protected $list_name = 'allpages';

  public $from;            // start (alphabetically) from this title
  public $prefix;          // pagetitle prefix (without any namespace preface)!
  public $namespace;       // NULL, "" or 0 - main namespace, etc. (only one namespace is enumerated at a time!)
  public $filterredir;     // 'all' (default), 'redirects', 'non-redirects'
  public $filterlanglinks; // 'all' (default), 'withlanglinks', 'withoutlanglinks'
  public $minsize;         // minimal page size to list, in bytes
  public $maxsize;         // maximal page size to list, in bytes
  public $prtype;          // 'edit', 'move' or other types of actions pages have been protected against
  public $prlevel;         // 'autoconfirmed', 'sysop' or other levels of protection (incompatible with prtype!)
  public $direction;       // 'ascending' (default), 'descending'

  protected function gather_list_params () {
    if ( empty ( $this->prtype ) && ! empty ( $this->prlevel ) ) {
      $this->log ( "AllPages: \$prlevel may not be specified without \$prtype - ignoring it!", LL_WARNING );
      $this->prlevel = NULL;
    }
    $from_ns = $this->bot->title_namespace ( $this->from );
    if ( ! empty ( $from_ns ) ) {
      if ( $this->bot->title_is_in_namespace ( $this->from, $this->namespace ) ) {
        $this->from = $this->bot->title_pagename ( $this->from );
      } else {
        $this->log ( "From-page [[" . $this->from . "]] is not in namespace " . $this->namespace . "; ignoring the difference - you know best what you want", LL_WARNING );
      }
    }

    $params = parent::gather_list_params();

    $this->add_nonempty  ( $params, $this->from           , 'from'            );
    $this->add_nonempty  ( $params, $this->prefix         , 'prefix'          );
    $this->add_namespace ( $params, $this->namespace );
    $this->add_nonempty  ( $params, $this->filterredir    , 'filterredir'     );
    $this->add_nonempty  ( $params, $this->filterlanglinks, 'filterlanglinks' );
    $this->add_nonempty  ( $params, $this->minsize        , 'minsize'         );
    $this->add_nonempty  ( $params, $this->maxsize        , 'maxsize'         );
    $this->add_nonempty  ( $params, $this->prtype         , 'prtype'          );
    $this->add_nonempty  ( $params, $this->prlevel        , 'prlevel'         );
    $this->add_nonempty  ( $params, $this->direction      , 'dir'             );

    return $params;
  }

}


class Iterator_AllLinks extends Iterator_GeneratorMembers {

  protected $query_datatree_element_name = 'alllinks';  // list mode
  protected $element_object_class_name   = 'List_Link'; // list mode
  protected $list_code = 'al';
  protected $list_name = 'alllinks';

  public $from;            // start (alphabetically) from this title
  public $prefix;          // pagetitle prefix (without any namespace preface)!
  public $namespace;       // NULL, "" or 0 - main namespace, etc (only one namespace is enumerated at a time!)
  public $unique = false;  // if true, multiple links to the same title will be listed only once, and listing IDs will not be obtained.
  public $get_ids   = true;
  public $get_title = true;

  protected function gather_list_params () {
    if ( $this->unique && $this->get_ids ) {
      $this->log ( "AllLinks: both unique and get_ids specified; is not allowed - will not get_ids!", LL_WARNING );
      $this->get_ids = false;
    }

    $params = parent::gather_list_params();

    $this->add_nonempty  ( $params, $this->from     , 'from'   );
    $this->add_nonempty  ( $params, $this->prefix   , 'prefix' );
    $this->add_namespace ( $params, $this->namespace );
    $this->add_boolkey   ( $params, $this->unique   , 'unique' );

    $prop = array();
    $this->add_boolvalue ( $prop, $this->get_ids  , 'ids'   );
    $this->add_boolvalue ( $prop, $this->get_title, 'title' );
    $this->add_nonempty ( $params, $this->bot->barsepstring ( $prop ), 'prop' );

    return $params;
  }

}


class Iterator_AllCategories extends Iterator_GeneratorMembers {
# Not the same as allpages with namespace 14! It lists categories with text, even if empty.
# This lists categories with members, even if without text.

  protected $query_datatree_element_name = 'allcategories'; // list mode
  protected $element_object_class_name   = 'List_Title';    // list mode
  protected $list_code = 'ac';
  protected $list_name = 'allcategories';

  public $from;            // start (alphabetically) from this title
  public $prefix;          // title prefix (without any namespace preface)!
  public $direction;

  protected function gather_list_params () {
    $params = parent::gather_list_params();

    $this->add_nonempty  ( $params, $this->from     , 'from'   );
    $this->add_nonempty  ( $params, $this->prefix   , 'prefix' );
    $this->add_nonempty  ( $params, $this->direction, 'dir'    );

    return $params;
  }

}


class Iterator_AllImages extends Iterator_GeneratorMembers {

  protected $query_datatree_element_name = 'allimages';  // list mode
  protected $element_object_class_name   = 'Image';      // list mode
  protected $list_code = 'ai';
  protected $list_name = 'allimages';

  public $from;            // start (alphabetically) from this title
  public $prefix;          // pagetitle prefix (without any namespace preface)!
  public $minsize;         // minimal page size to list, in bytes
  public $maxsize;         // maximal page size to list, in bytes
  public $direction;       // 'ascending' (default), 'descending'
  public $sha1;
  public $sha1base36;
  public $get_timestamp  = true;
  public $get_user       = true;
  public $get_comment    = true;
  public $get_url        = true;
  public $get_size       = true;
  public $get_dimensions = true;
  public $get_mime       = true;
  public $get_sha1       = true;
  public $get_metadata   = true;

  protected function gather_list_params () {
    $params = parent::gather_list_params();

    $this->add_nonempty  ( $params, $this->from      , 'from'       );
    $this->add_nonempty  ( $params, $this->prefix    , 'prefix'     );
    $this->add_nonempty  ( $params, $this->minsize   , 'minsize'    );
    $this->add_nonempty  ( $params, $this->maxsize   , 'maxsize'    );
    $this->add_nonempty  ( $params, $this->direction , 'dir'        );
    $this->add_nonempty  ( $params, $this->sha1      , 'sha1'       );
    $this->add_nonempty  ( $params, $this->sha1base36, 'sha1base36' );

    $prop = array();
    $this->add_boolvalue ( $prop, $this->get_timestamp   , 'timestamp'   );
    $this->add_boolvalue ( $prop, $this->get_user        , 'user'        );
    $this->add_boolvalue ( $prop, $this->get_comment     , 'comment'     );
    $this->add_boolvalue ( $prop, $this->get_url         , 'url'         );
    $this->add_boolvalue ( $prop, $this->get_size        , 'size'        );
    $this->add_boolvalue ( $prop, $this->get_dimensions  , 'dimenstions' );
    $this->add_boolvalue ( $prop, $this->get_mime        , 'mime'        );
    $this->add_boolvalue ( $prop, $this->get_sha1        , 'sha1'        );
    $this->add_boolvalue ( $prop, $this->get_metadata    , 'metadata'    );
    $this->add_nonempty ( $params, $this->bot->barsepstring ( $prop ), 'prop' );

    return $params;
  }

}


class Iterator_BackLinks extends Iterator_GeneratorMembers {

  protected $query_datatree_element_name = 'backlinks';  // list mode
  protected $element_object_class_name   = 'Page';       // list mode
  protected $list_code = 'bl';
  protected $list_name = 'backlinks';

  public $title;       // backlinks to this page title
  public $namespace;   // NULL - all namespaces, "" or 0 - main namespace, etc.
  public $filterredir; // 'all' (default), 'redirects', 'non-redirects'
  public $redirect = false;  // list also pages linking to this page through a redirect?

  protected function gather_list_params () {
    if ( empty ( $this->title ) ) {
      $this->log ( "BackLinks: no page title specified - no data will be obtained!", LL_WARNING );
    }

    $params = parent::gather_list_params();

    $this->add_nonempty  ( $params, $this->title      , 'title'       );
    $this->add_namespace ( $params, $this->namespace );
    $this->add_nonempty  ( $params, $this->filterredir, 'filterredir' );
    $this->add_boolvalue ( $params, $this->redirect   , 'redirect'    );

    return $params;
  }

}


class Iterator_CategoryMembers extends Iterator_GeneratorMembers {

  protected $query_datatree_element_name = 'categorymembers';  // list mode
  protected $element_object_class_name   = 'Page';             // list mode
  protected $list_code = 'cm';
  protected $list_name = 'categorymembers';

  public $title;            // category to iterate ('Category:' prefix can be omitted)
  public $namespace;        // NULL - all namespaces, "" or 0 - main namespace, etc.
  public $sort;             // 'sortkey' (default) or 'timestamp'
  public $start;            // start timestamp (used with $sort => 'timestamp')
  public $end;              // end timestamp (used with $sort => 'timestamp')
  public $startsortkey;     // start sortkey (used with $sort => 'sortkey')
  public $endsortkey;       // end sortkey (used with $sort => 'sortkey')
  public $get_ids       = true;
  public $get_title     = true;
  public $get_sortkey   = true;
  public $get_timestamp = true;

  protected function gather_list_params () {
    if ( empty ( $this->title ) ) {
      $this->log ( "CategoryMembers: category name not specified - no data will be obtained!", LL_WARNING );
    }
    if ( ! $this->bot->title_is_in_namespace ( $this->title, NAMESPACE_ID_CATEGORY ) ) {
      if ( mb_strpos ( $this->title, ':' ) === false ) {
        $this->title = $this->bot->wiki_namespace_name ( NAMESPACE_ID_CATEGORY ) . ':' . $this->title;
      } else {
        $this->log ( "CategoryMembers: page '" . $this->title . "' appears to not be a category - no data will be obtained!", LL_WARNING );
      }
    }
    if ( ( ( $this->sort != 'sortkey' ) || ( $this->sortkey !== '' ) ) &&
         ( ! empty ( $this->startsortkey ) || ! empty ( $this->endsortkey ) ) ) {
      $this->log ( "CategoryMembers: not sorted by sortkey - startsortkey and endsortkey will be ignored!", LL_WARNING );
      $this->startsortkey = NULL;
      $this->endsortkey   = NULL;
    }

    $params = parent::gather_list_params();

    $this->add_nonempty    ( $params, $this->title       , 'title'       );
    $this->add_namespace   ( $params, $this->namespace );
    $this->add_startenddir ( $params, $this->start, $this->end );
    $this->add_nonempty    ( $params, $this->startsortkey, 'startsortkey' );
    $this->add_nonempty    ( $params, $this->endsortkey  , 'endsortkey'   );
    $this->add_nonempty    ( $params, $this->sort        , 'sort'         );

    $prop = array();
    $this->add_boolvalue ( $prop, $this->get_ids      , 'ids'       );
    $this->add_boolvalue ( $prop, $this->get_title    , 'title'     );
    $this->add_boolvalue ( $prop, $this->get_sortkey  , 'sortkey'   );
    $this->add_boolvalue ( $prop, $this->get_timestamp, 'timestamp' );
    $this->add_nonempty ( $params, $this->bot->barsepstring ( $prop ), 'prop' );

    return $params;
  }

}


class Iterator_EmbeddedIn extends Iterator_GeneratorMembers {

  protected $query_datatree_element_name = 'embeddedin'; // list mode
  protected $element_object_class_name   = 'Page';       // list mode
  protected $list_code = 'ei';
  protected $list_name = 'embeddedin';

  public $title;        // iterate pages that include (eg. as a template) this title (include namespace preface!)
  public $namespace;    // NULL - ;ist pages from all namespaces, "" or 0 - main namespace, etc.
  public $filterredir;  // 'all' (default), 'redirects', 'non-redirects'

  protected function gather_list_params () {
    $params = parent::gather_list_params();

    $this->add_nonempty  ( $params, $this->title      , 'title'       );
    $this->add_namespace ( $params, $this->namespace );
    $this->add_nonempty  ( $params, $this->filterredir, 'filterredir' );

    return $params;
  }

}


class Iterator_ExtUrlUsage extends Iterator_GeneratorMembers {

  protected $query_datatree_element_name = 'exturlusage';      // list mode
  protected $element_object_class_name   = 'Page_WithExtlink'; // list mode
  protected $list_code = 'eu';
  protected $list_name = 'exturlusage';

  public $query;     // '*' is wildcard (never use it alone!); if completely empty, protocol is ignored
  public $protocol;  // if $query is not empty: 'http' (default), 'https', 'ftp', 'irc', 'gopher', 'telnet', 'nntp', 'worldwind', 'mailto', 'news'
  public $namespace; // NULL - all namespaces, "" or 0 - main namespace, etc.
  public $get_ids   = true;
  public $get_title = true;
  public $get_url   = true;   // no sense in setting it - MW API doesn't return the URLs, at least up to 1.16w4! :-(

  protected function gather_list_params () {
    $params = parent::gather_list_params();

    $this->add_nonempty  ( $params, $this->query   , 'query'    );
    $this->add_nonempty  ( $params, $this->protocol, 'protocol' );
    $this->add_namespace ( $params, $this->namespace );

    $prop = array();
    $this->add_boolvalue ( $prop, $this->get_ids  , 'ids'   );
    $this->add_boolvalue ( $prop, $this->get_title, 'title' );
    $this->add_boolvalue ( $prop, $this->get_url  , 'url'   );
    $this->add_nonempty ( $params, $this->bot->barsepstring ( $prop ), 'prop' );

    return $params;
  }

}


class Iterator_ImageUsage extends Iterator_GeneratorMembers {

  protected $query_datatree_element_name = 'imageusage'; // list mode
  protected $element_object_class_name   = 'Page';       // list mode
  protected $list_code = 'iu';
  protected $list_name = 'imageusage';

  public $title;       // image title ('Image' or 'File' prefix can be omitted)
  public $namespace;   // NULL - all namespaces, "" or 0 - main namespace, etc.
  public $filterredir; // 'all' (default), 'redirects', 'non-redirects'
  public $redirect = false;

  protected function gather_list_params () {
    if ( empty ( $this->title ) ) {
      $this->log ( "ImageUsage: no image title specified - no data will be obtained!", LL_WARNING );
    }

    if ( ! $this->bot->title_is_in_namespace ( $this->title, NAMESPACE_ID_FILE ) ) {
      if ( mb_strpos ( $this->title, ':' ) === false ) {
        $this->title = $this->bot->wiki_namespace_name ( NAMESPACE_ID_FILE ) . ':' . $this->title;
      } else {
        $this->log ( "ImageUsage: page '" . $this->title . "' appears to not be an image (or file) - no data will be obtained!", LL_WARNING );
      }
    }

    $params = parent::gather_list_params();

    $this->add_nonempty  ( $params, $this->title      , 'title'       );
    $this->add_namespace ( $params, $this->namespace );
    $this->add_nonempty  ( $params, $this->filterredir, 'filterredir' );
    $this->add_boolvalue ( $params, $this->redirect   , 'redirect'    );

    return $params;
  }

}


class Iterator_Search extends Iterator_GeneratorMembers {

  protected $query_datatree_element_name = 'search';             // list mode
  protected $element_object_class_name   = 'List_SearchResult';  // list mode
  protected $list_code = 'sr';
  protected $list_name = 'search';

  public $search;    // what to search for
  public $what;      // what to search in: 'title' (default), 'text')
  public $namespace; // NULL, "" or 0 - main namespace, etc.
  public $redirects = false;

  protected function gather_list_params () {
    if ( empty ( $this->search ) ) {
      $this->log ( "Search: no search string specified - all pages will be listed!", LL_WARNING );
    }

    $params = parent::gather_list_params();

    $this->add_nonempty  ( $params, $this->search   , 'search'    );
    $this->add_nonempty  ( $params, $this->what     , 'what'      );
    $this->add_namespace ( $params, $this->namespace );
    $this->add_boolvalue ( $params, $this->redirects, 'redirects' );

    return $params;
  }

}


class Iterator_Watchlist extends Iterator_GeneratorMembers {

  protected $query_datatree_element_name = 'watchlist';          // list mode
  protected $element_object_class_name   = 'Page_FromWatchlist'; // list mode
  protected $list_name = 'watchlist';
  protected $list_code = 'wl';

  public $start;       // timestamp to start listing watched pages from
  public $end;         // timestamp to end listing to (if before $start, they will be listed backwards)
  public $namespace;   // NULL - all namespaces, "" or 0 - main namespace, etc.
  public $user;        // only list changes made by this user ('!user' - not by this user)
  public $allrev;      // true - list all revisions, false - list only the last revision
  public $show_minor;  // NULL (all), true (minor only), false (non-minor only)
  public $show_anon;
  public $show_bot;
  public $get_user      = true;
  public $get_comment   = true;
  public $get_timestamp = true;
  public $get_title     = true;
  public $get_ids       = true;
  public $get_sizes     = true;
  public $get_patrol    = true;
  public $get_flags     = true;

  protected function gather_list_params () {
    if ( ! $this->list_mode && $this->allrev ) {
      $this->log ( "WatchList: \$allrev is not allowed in generator mode - ignoring it!", LL_WARNING );
      $this->allrev = NULL;
    }

    $params = parent::gather_list_params();

    $this->add_startenddir ( $params, $this->start, $this->end );
    $this->add_namespace   ( $params, $this->namespace );
    $this->add_user        ( $params, $this->user );
    $this->add_boolvalue   ( $params, $this->allrev, 'allrev' );

    $show = array();
    $this->add_boolbang ( $show, $this->show_minor    , 'minor'     );
    $this->add_boolbang ( $show, $this->show_anon     , 'anon'      );
    $this->add_boolbang ( $show, $this->show_bot      , 'bot'       );
    $this->add_nonempty ( $params, $this->bot->barsepstring ( $show ), 'show' );

    $prop = array();
    $this->add_boolvalue ( $prop, $this->get_user     , 'user'      );
    $this->add_boolvalue ( $prop, $this->get_comment  , 'comment'   );
    $this->add_boolvalue ( $prop, $this->get_timestamp, 'timestamp' );
    $this->add_boolvalue ( $prop, $this->get_title    , 'title'     );
    $this->add_boolvalue ( $prop, $this->get_ids      , 'ids'       );
    $this->add_boolvalue ( $prop, $this->get_sizes    , 'sizes'     );
    if ( $this->bot->can_i_patrol() ) {
      $this->add_boolvalue ( $prop, $this->get_patrol , 'patrol'    );
    }
    $this->add_boolvalue ( $prop, $this->get_flags    , 'flags'     );
    $this->add_nonempty ( $params, $this->bot->barsepstring ( $prop ), 'prop' );

    return $params;
  }

}


class Iterator_Random extends Iterator_GeneratorMembers {

  protected $query_datatree_element_name = 'random';  // list mode
  protected $element_object_class_name   = 'Page';    // list mode
  protected $list_code = 'rn';
  protected $list_name = 'random';

  public $namespace;         // NULL - all namespaces, "" or 0 - main namespace, etc.
  public $redirect = false;  // non-redirects only; true will list only redirects

  protected function gather_list_params () {
    $params = parent::gather_list_params();

    $this->add_namespace ( $params, $this->namespace );
    $this->add_boolvalue ( $params, $this->redirect, 'redirect' );

    return $params;
  }

}


# ----------  Page elements iterator classes  ---------- #


abstract class Iterator_PageElements extends Iterator_GenericAPI {

  protected $query_datatree_element_name = 'pages';  // overrides 'abstract' variable

  protected $page_datatree_element_name;  // to be overridden

  public $pageid; // pageid of the page whose elements are to be iterated
  public $title;  // title of the page whose elements are to be iterated
                  // (mutually exclusive with pageid - specify one of the two; if both are specified, pageid takes precedence)

  public $properties;  // page properties to request (specific iterators offer more convenience for their specific props)

  public $limit = 'max';

  public $page;   // the page whose properties are iterated (as a favor for the actionobject :-)

  # ----------  Constructor / Destructor  ---------- #

  function __construct ( $bot ) {
    parent::__construct ( $bot );
    $this->properties = $this->page_default_props();
  }

  # ----------  Protected  ---------- #

  # ----- Basic ----- #

  protected function query_elements_array ( $query_tree ) {
    $pages_tree = parent::query_elements_array ( $query_tree );
    if ( $pages_tree !== false ) {
      $page_tree = reset ( $pages_tree );
      $this->page = new Page;
      $this->page->read_from_element ( $page_tree, $this->bot );
      if ( array_key_exists ( $this->page_datatree_element_name, $page_tree ) ) {
        return $page_tree[$this->page_datatree_element_name];
      }
    }
    return false;
  }

  protected function page_default_props () {
    $props = array();
    $props['info'] = array();
    $props['info']['prop'] = "protection";
    return $props;
  }

  protected function query () {
    $properties = $this->element_props();
    if ( $properties === false ) { return false; }
    $this->properties[$this->page_datatree_element_name] = $properties;
    if ( ! ( $this->pageid === NULL ) ) {
      return $this->bot->query_pageids ( $this->pageid, $this->properties );
    } elseif ( ! ( $this->title === NULL ) ) {
      return $this->bot->query_titles ( $this->title, $this->properties );
    } else {
      $this->log ( "Error: Trying to guery page properties without specifying the page!", LL_ERROR );
      return false;
    }
  }

  protected function element_props () {
    if ( array_key_exists ( $this->page_datatree_element_name, $this->properties ) ) {
      $props = $this->properties[$this->page_datatree_element_name];
    } else {
      $props = array();
    }
    $this->add_nonempty ( $props, $this->limit, 'limit', 11200 );
    return $props;
  }

}


class Iterator_PageRevisions extends Iterator_PageElements {

  protected $element_object_class_name  = 'Page_Revision';  // overrides 'abstract' variable
  protected $page_datatree_element_name = 'revisions';      // overrides 'abstract' variable

  public $start;                   // timestamp to start listing page revisions from
  public $end;                     // timestamp to end listing to (if before $start, they will be listed backwards)
  public $startid;                 // revision ID to start listing page revisions from
  public $endid;                   // revision ID to end listing to (if before $start, they will be listed backwards)
  public $user;                    // list only revisions made by this user ('!user' - NOT by this user)
  public $expandtemplates = false; // expand included templates in the revision content (if content is requested)
  public $generatexml = false;     // generate XML parse tree for revision content (since version ????)
  public $section;                 // return only this section (if content is requested; MW 1.13 and up)
  public $diffto;                  // return a diff to the revision with this ID; 'prev', 'next' and 'cur' are allowed
  public $difftotext;              // return a diff to this text (overrides $diffto)

  public $get_rollbacktoken = false;   // get rollback tokens for each revision
  public $get_size          = true;
  public $get_content       = true;
  public $get_tags          = true;

  # ----------  Protected  ---------- #

  protected function element_props () {
    if ( $this->bot->mw_version_number() < 11700 ) { $this->get_tags = NULL; }

    $props = parent::element_props();
    $this->add_startenddir ( $props, $this->start, $this->end );
    $this->add_startenddir ( $props, $this->startid, $this->endid, "id" );
    $this->add_user ( $props, $this->user );
    $this->add_nonempty ( $props, $this->section, 'section', 11300 );
    $this->add_boolkey  ( $props, $this->expandtemplates, 'expandtemplates' );
    $this->add_boolkey  ( $props, $this->generatexml, 'generatexml' );
    $this->add_nonempty ( $props, $this->diffto, 'diffto' );
    $this->add_nonempty ( $props, $this->difftotext, 'difftotext' );

    if ( ( $this->bot->mw_version_number() < 11200 ) &&
         ( empty ( $this->start ) && empty ( $this->end ) &&
           empty ( $this->startid ) && empty ( $this->endid ) ) ) {
      $this->add_startenddir ( $props, PHP_INT_MAX, 0, "id" );
    }

    if ( $this->get_rollbacktoken ) { $props['token'] = 'rollback'; }

    $props['prop'] = "ids|flags|timestamp|user|comment";
    if ( $this->get_size    ) { $props['prop'] .= "|size";    }
    if ( $this->get_content ) { $props['prop'] .= "|content"; }
    if ( $this->get_tags    ) { $props['prop'] .= "|tags";    }
    return $props;
  }

}


class Iterator_PageRevisions_WithDiffs extends Iterator_PageRevisions {

  protected $previous_revision;

  protected function iterate_element ( $element, $ActionObject ) {
    if ( is_null ( $this->previous_revision ) ) {
      $result = true;
    } else {
      $this->previous_revision->inserted = added_to_str ( $this->previous_revision->content, $element->content );
      $this->previous_revision->removed  = added_to_str ( $element->content, $this->previous_revision->content );
      $result = parent::iterate_element ( $this->previous_revision, $ActionObject );
    }
    $this->previous_revision = $element;
    return $result;
  }

  protected function iterate_elements ( $ActionObject ) {
    $this->previous_revision = NULL;
    $this->elements_counter--;
    parent::iterate_elements ( $ActionObject );
    if ( ! $this->abort_iteration ) {
      if ( $this->iterate_element ( NULL, $ActionObject ) ) {
        $this->elements_counter++;
      }
    }
    return $this->elements_counter;
  }

}


class Iterator_PageCategories extends Iterator_PageElements {

  protected $element_object_class_name  = 'Page_Category'; // overrides 'abstract' variable
  protected $page_datatree_element_name = 'categories';    // overrides 'abstract' variable

  public $show_hidden   = NULL;     // false - do not show hidden categories; true - show only them; NULL - show both kinds
  public $only_these    = array();  // show only the categories listed here - useful to check if the page is in a given category
  public $get_sortkey   = true;
  public $get_timestamp = true;

  # ----------  Protected  ---------- #

  protected function element_props () {
    if ( $generator ) {  // not implemented - could not find info how to use this as a generator
      $this->log ( "PageCategories: \$sortkey and $timestamp are not allowed in generator mode - ignoring them!", LL_WARNING );
      $this->sortkey   = NULL;
      $this->timestamp = NULL;
    }

    $prop = array();
    $this->add_boolvalue ( $prop, $this->get_sortkey  , 'sortkey'   );
    $this->add_boolvalue ( $prop, $this->get_timestamp, 'timestamp' );

    $show = array();
    $this->add_boolbang ( $show, $this->show_hidden, 'hidden' );

    $props = parent::element_props();
    $this->add_nonempty ( $props, $prop, 'prop' );
    $this->add_nonempty ( $props, $show, 'show' );
    $this->add_nonempty ( $props, $this->bot->barsepstring ( $this->only_these ), 'categories' );
    return $props;
  }

}


class Iterator_PageImageInfo extends Iterator_PageElements {

  protected $element_object_class_name  = 'Page_ImageInfo'; // overrides 'abstract' variable
  protected $page_datatree_element_name = 'imageinfo';    // overrides 'abstract' variable

  public $start;      // timestamp to start listing page image info from
  public $end;        // timestamp to end listing to (if before $start, they will be listed backwards)
  public $urlwidth;   // the URL returned (if get_url is set) will point to image resized to this width
  public $urlheight;  // as with $urlwidth, but for the image height
  public $get_timestamp   = true;
  public $get_user        = true;
  public $get_comment     = false;
  public $get_url         = false;
  public $get_size        = false;
  public $get_sha1        = false;
  public $get_mime        = false;
  public $get_metadata    = false;
  public $get_archivename = false;

  # ----------  Protected  ---------- #

  protected function element_props () {
    if ( ! empty ( $this->title ) &&
         ( $this->bot->title_namespace_id ( $this->title ) === "" ) ) {
      $this->title = $this->bot->wiki_namespace_name ( NAMESPACE_ID_FILE ) . ":" . $this->title;
    }

    $show = array();
    $this->add_boolvalue ( $show, $this->get_timestamp  , 'timestamp'   );
    $this->add_boolvalue ( $show, $this->get_user       , 'user'        );
    $this->add_boolvalue ( $show, $this->get_comment    , 'comment'     );
    $this->add_boolvalue ( $show, $this->get_url        , 'url'         );
    $this->add_boolvalue ( $show, $this->get_size       , 'size'        );
    $this->add_boolvalue ( $show, $this->get_sha1       , 'sha1'        );
    $this->add_boolvalue ( $show, $this->get_mime       , 'mime'        );
    $this->add_boolvalue ( $show, $this->get_metadata   , 'metadata'    );
    $this->add_boolvalue ( $show, $this->get_archivename, 'archivename' );

    $props = parent::element_props();
    $this->add_nonempty ( $props, $this->start    , 'start'     );
    $this->add_nonempty ( $props, $this->end      , 'end'       );
    $this->add_nonempty ( $props, $this->urlwidth , 'urlwidth'  );
    $this->add_nonempty ( $props, $this->urlheight, 'urlheight' );
    $this->add_nonempty ( $props, $show, 'prop' );
    return $props;
  }

}


class Iterator_PageStashImageInfo extends Iterator_PageElements {

  protected $element_object_class_name  = 'Page_ImageInfo'; // overrides 'abstract' variable
  protected $page_datatree_element_name = 'stashimageinfo';    // overrides 'abstract' variable

  public $sessionkey; // Session key for a temporarily stashed previous upload (mandatory!)
  public $urlwidth;   // the URL returned (if get_url is set) will point to image resized to this width
  public $urlheight;  // as with $urlwidth, but for the image height
  public $get_timestamp   = true;
  public $get_url         = false;
  public $get_size        = false;
  public $get_sha1        = false;
  public $get_mime        = false;
  public $get_metadata    = false;

  # ----------  Protected  ---------- #

  protected function element_props () {
    if ( ! empty ( $this->title ) &&
         ( $this->bot->title_namespace_id ( $this->title ) === "" ) ) {
      $this->title = $this->bot->wiki_namespace_name ( NAMESPACE_ID_FILE ) . ":" . $this->title;
    }

    if ( empty ( $this->sessionkey ) ) {
      $this->log ( "No session key supplied -- cannot query stashed image info!", LL_ERROR );
      return false;
    }

    $show = array();
    $this->add_boolvalue ( $show, $this->get_timestamp, 'timestamp' );
    $this->add_boolvalue ( $show, $this->get_url      , 'url'       );
    $this->add_boolvalue ( $show, $this->get_size     , 'size'      );
    $this->add_boolvalue ( $show, $this->get_sha1     , 'sha1'      );
    $this->add_boolvalue ( $show, $this->get_mime     , 'mime'      );
    $this->add_boolvalue ( $show, $this->get_metadata , 'metadata'  );

    $props = parent::element_props();
    $this->add_nonempty ( $props, $this->sessionkey, 'sessionkey' );
    $this->add_nonempty ( $props, $this->urlwidth  , 'urlwidth'   );
    $this->add_nonempty ( $props, $this->urlheight , 'urlheight'  );
    $this->add_nonempty ( $props, $show, 'prop' );
    return $props;
  }

}


class Iterator_PageLangLinks extends Iterator_PageElements {

  protected $element_object_class_name  = 'Page_LangLink'; // overrides 'abstract' variable
  protected $page_datatree_element_name = 'langlinks';     // overrides 'abstract' variable

}


class Iterator_PageLinks extends Iterator_PageElements {

  protected $element_object_class_name  = 'List_Title'; // overrides 'abstract' variable
  protected $page_datatree_element_name = 'links';      // overrides 'abstract' variable

  public $namespace;  // NULL - list link to pages in all namespaces; "" or 0 - in main namespace, etc.

  # ----------  Protected  ---------- #

  protected function element_props () {
    $props = parent::element_props();
    $this->add_namespace ( $props, $this->namespace );
    return $props;
  }

}


class Iterator_PageTemplates extends Iterator_PageElements {

  protected $element_object_class_name  = 'Page_Template'; // overrides 'abstract' variable
  protected $page_datatree_element_name = 'templates';     // overrides 'abstract' variable

  public $namespace;  // NULL - list link to included pages in all namespaces; "" or 0 - in main namespace, 8 - classic templates only, etc.

  # ----------  Protected  ---------- #

  protected function element_props () {
    $ns_code = $this->bot->wiki_namespace_id ( $this->namespace );
    if ( $ns_code !== false ) { $this->namespace = $ns_code; }

    $props = parent::element_props();
    $this->add_namespace ( $props, $this->namespace );
    return $props;
  }

}


class Iterator_PageImages extends Iterator_PageElements {

  protected $element_object_class_name  = 'Page_Image'; // overrides 'abstract' variable
  protected $page_datatree_element_name = 'images';     // overrides 'abstract' variable

}


class Iterator_PageExtlinks extends Iterator_PageElements {

  protected $element_object_class_name  = 'Page_Extlink'; // overrides 'abstract' variable
  protected $page_datatree_element_name = 'extlinks';     // overrides 'abstract' variable

}


// page_categoryinfo has no elements that can be iterated


class Iterator_PageDuplicateFiles extends Iterator_PageElements {

  protected $element_object_class_name  = 'Page_DuplicateFile'; // overrides 'abstract' variable
  protected $page_datatree_element_name = 'duplicatefiles'    ; // overrides 'abstract' variable

}


class Iterator_PageGlobalUsage extends Iterator_PageElements {

  protected $element_object_class_name  = 'Page_GlobalUsage'; // overrides 'abstract' variable
  protected $page_datatree_element_name = 'globalusage';      // overrides 'abstract' variable

  public $filterlocal = false;

  # ----------  Protected  ---------- #

  protected function element_props () {
    $props = parent::element_props();
    $this->add_boolkey ( $props, $this->filterlocal, 'filterlocal' );
    return $props;
  }

}


# ----------  Apibot siteinfo and userinfo iterator classes  ---------- #


abstract class Iterator_Apibot_MetaInfo extends Iterator_Apibot_Generic {

  # ---------- Protected ---------- #

  protected function query         () { return true ; }
  protected function continue_query() { return false; }

  protected function obtain_elements_array () { return $this->info_elements(); }

  abstract protected function info_elements();

}


class Iterator_Wiki_Namespaces extends Iterator_Apibot_MetaInfo {
  protected function info_elements () { return $this->bot->wiki_namespaces(); }
}

class Iterator_Wiki_NamespaceAliases extends Iterator_Apibot_MetaInfo {
  protected function info_elements () { return $this->bot->wiki_namespaces_aliases(); }
}

class Iterator_Wiki_Interwikis extends Iterator_Apibot_MetaInfo {
  protected function info_elements () { return $this->bot->wiki_interwikis(); }
}

class Iterator_Wiki_SpecialPageAliases extends Iterator_Apibot_MetaInfo {
  protected function info_elements () { return $this->bot->wiki_specialpagealiases(); }
}

class Iterator_Wiki_MagicWords extends Iterator_Apibot_MetaInfo {
  protected function info_elements () { return $this->bot->wiki_magicwords(); }
}

class Iterator_Wiki_Extensions extends Iterator_Apibot_MetaInfo {
  protected function info_elements () { return $this->bot->wiki_extensions(); }
}

class Iterator_Wiki_FileExtensions extends Iterator_Apibot_MetaInfo {
  protected function info_elements () { return $this->bot->wiki_fileextensions(); }
}

class Iterator_Wiki_UserGroups extends Iterator_Apibot_MetaInfo {
  protected function info_elements () { return $this->bot->wiki_usergroups(); }
}

class Iterator_User_Groups extends Iterator_Apibot_MetaInfo {
  protected function info_elements () { return $this->bot->my_groups(); }
}

class Iterator_User_ChangeableGroups extends Iterator_Apibot_MetaInfo {

  public $action = 'add';

  protected function info_elements () {
    $groups = $this->bot->my_changeablegroups();
    return $groups[$this->action];
  }
}

class Iterator_User_Rights extends Iterator_Apibot_MetaInfo {
  protected function info_elements () { return $this->bot->my_rights(); }
}

class Iterator_User_Options extends Iterator_Apibot_MetaInfo {
  protected function info_elements () {
    $options = $this->bot->my_options();
    $elements = array();
    foreach ( $options as $name => $value ) {
      $elements[] = array ( 'name' => $name, 'value' => $value );
    }
    return $elements;
  }
}

class Iterator_User_RateLimits extends Iterator_Apibot_MetaInfo {
  protected function info_elements () { return $this->bot->my_ratelimits(); }
}

class Iterator_Wiki_Messages extends Iterator_Apibot_MetaInfo {

  protected function info_elements () {
    if ( ! $this->bot->are_wiki_messages_fetched() ) {
      $this->bot->fetch_wiki_messages();
    }
    return $this->bot->wiki_messages();
  }
}


# ----------  Custom API iterator classes  ---------- #


class Iterator_CategoryMembers_Recursive extends Iterator_CategoryMembers {

  public $max_nesting_depth  = PHP_INT_MAX;    // max depth to recurse through subcategories (0 - do not recurse)

  public $process_categories = false;  // whether to process the category pages
  public $process_pages      = true;   // whether to process the non-category pages

  public $ignore_processed_categories = true;  // whether to ignore already processed categories, or to re-process them

  public $processed_categories = array();  // categories that are already processed (to avoid re-processing categories)
  public $category_stack = array();

  # ---------- Protected ---------- #

  protected function on_max_nesting_depth ( $title ) {
    $this->bot->log ( "Max nesting depth (" . $this->max_nesting_depth . ") reached - refusing to process category '" . $title . "'!", LL_INFO );
    return true;
  }

  protected function on_circular_categorization ( $title ) {
    $this->bot->log ( "Category '" . $title . "' is nested inside its own tree (circular categorization?) - will not process it.", LL_WARNING );
    return true;
  }

  protected function on_already_processed () {
    $this->bot->log ( "Category '" . $this->title . "' is already processed (met " .
      $this->processed_categories[$this->title] . " times) - skipping it.", LL_INFO );
  }

  protected function create_iterator ( $element ) {
    $classname = get_class ( $this );
    $Iterator = new $classname ( $this->bot );  // allows using child classes

    $Iterator->title = $element->title;

    $Iterator->processed_categories = &$this->processed_categories;
    $Iterator->category_stack       = &$this->category_stack;
    $Iterator->max_nesting_depth    =  $this->max_nesting_depth;
    $Iterator->process_categories   =  $this->process_categories;
    $Iterator->process_pages        =  $this->process_pages;

    $Iterator->namespace     = $this->namespace;
    $Iterator->start         = $this->start;
    $Iterator->end           = $this->end;
    $Iterator->startsortkey  = $this->startsortkey;
    $Iterator->endsortkey    = $this->endsortkey;
    $Iterator->sort          = $this->sort;
    $Iterator->get_ids       = $this->get_ids;
    $Iterator->get_title     = $this->get_title;
    $Iterator->get_sortkey   = $this->get_sortkey;
    $Iterator->get_timestamp = $this->get_timestamp;

    return $Iterator;
  }

  protected function iterate_subcategories ( $element, $ActionObject ) {
    $Iterator = $this->create_iterator ( $element );

    $this->bot->push_bot_state();
    $this->elements_counter += $Iterator->iterate ( $ActionObject );
    $this->bot->pop_bot_state();
  }

  protected function iterate_element ( $element, $ActionObject ) {
    if ( $element->ns == NAMESPACE_ID_CATEGORY ) {
      $this->iterate_subcategories ( $element, $ActionObject );

      if ( $this->process_categories ) {
        if ( parent::iterate_element ( $element ) ) {
          $this->elements_counter++;
        }
      }

    } else {

      if ( $this->process_pages ) {
        if ( parent::iterate_element ( $element ) ) {
          $this->elements_counter++;
        }
      }

    }
  }

  public function iterate ( $ActionObject ) {
    $result = false;
    if ( $this->processed_categories[$this->title] && $this->ignore_processed_categories ) {
      $this->on_already_processed();
    } else {
      if ( count ( $this->category_stack ) >= $this->max_nesting_depth ) {
        $this->on_max_nesting_depth ( $this->title );
      } elseif ( in_array ( $this->title, $this->category_stack ) ) {
        $this->on_circular_categorization ( $this->title );
      } else {
        array_push ( $this->category_stack, $this->title );

        $this->bot->log ( "Entering category '" . $this->title . "' (nesting depth " . count ( $this->category_stack ) . ")", LL_INFO );
        $result = parent::iterate ( $ActionObject );
        $this->bot->log ( "Leaving category '" . $this->title . "'", LL_INFO );

        array_pop ( $this->category_stack );

      }
    }
    $this->processed_categories[$this->title] = $this->processed_categories[$this->title] + 1;

    return $result;
  }

}


class Iterator_PageAndSubpages extends Iterator_AllPages {

  public $title;

  public $iterate_main_space = true;  // whether to process the pages in the given namespace
  public $iterate_talk_space = true;  // whether to process the matching talk pages

  # ----------  Protected  ---------- #

  protected function iterate_elements ( $ActionObject ) {
    $page = $this->bot->fetch_page ( $this->title, $this->properties );
    $counter = $this->iterate_element ( $page, $ActionObject );
    $title_parts = $this->bot->title_parts ( $this->title );
    $this->prefix = $title_parts['title'] . '/';
    $this->namespace = $title_parts['namespace'];
    return parent::iterate_elements ( $ActionObject ) + $counter;
  }

  public function iterate ( $ActionObject ) {
    $temp_title = $this->title;
    $pages_titles = $this->bot->maintalk_pages_titles ( $this->title );
    $counter = 0;
    if ( $this->iterate_main_space ) {
      $this->title = $pages_titles['main'];
      $counter += $this->iterate_elements ( $ActionObject );
    }
    if ( $this->iterate_talk_space ) {
      $this->title = $pages_titles['talk'];
      $counter += $this->iterate_elements ( $ActionObject );
    }
    $this->title = $temp_title;
    return $counter;
  }

}


class Iterator_AllPagesAllNamespaces extends Iterator_Wiki_Namespaces {
// Like Iterator_AllPages, but is not limited to iterating only one namespace.

  public $from;            // start (alphabetically) from this title
  public $prefix;          // pagetitle prefix (without any namespace preface)!
  public $filterredir;     // 'all' (default), 'redirects', 'non-redirects'
  public $filterlanglinks; // 'all' (default), 'withlanglinks', 'withoutlanglinks'
  public $minsize;         // minimal page size to list, in bytes
  public $maxsize;         // maximal page size to list, in bytes
  public $prtype;          // 'edit', 'move' or other types of actions pages have been protected against
  public $prlevel;         // 'autoconfirmed', 'sysop' or other levels of protection (incompatible with prtype!)
  public $direction;       // 'ascending' (default), 'descending'

  public $namespaces = array();  // traverse only these namespaces (if not set - all namespaces)

  protected $namespaces_ids = array();

  protected function iterate_element ( $element, $ActionObject ) {
    if ( empty ( $this->namespaces_ids ) && ! empty ( $this->namespaces ) ) {
      foreach ( $this->namespaces as $namespace ) {
        $this->namespaces_ids[] = $bot->wiki_namespace_id ( $namespace );
      }
    }

    if ( empty ( $this->namespaces_ids ) || in_array ( $element['id'], $this->namespaces_ids ) ) {
      $classname = get_class ( $this );
      $IterAP = new $classname ( $this->bot );  // usable by child classes, too
      $IterAP->from            = $this->from;
      $IterAP->prefix          = $this->prefix;
      $IterAP->filterredir     = $this->filterredir;
      $IterAP->filterlanglinks = $this->filterlanglinks;
      $IterAP->minsize         = $this->minsize;
      $IterAP->maxsize         = $this->maxsize;
      $IterAP->prtype          = $this->prtype;
      $IterAP->prlevel         = $this->prlevel;
      $IterAP->direction       = $this->direction;

      $IterAP->namespace       = $element['id'];

      $IterAP->iterate ( $ActionObject );
    }
  }

}


# ----------  Directory and file iterator classes  ---------- #


class Iterator_Directory extends Iterator_Generic {

  public $path;

  public $filename_regex = '/.*/';

  public $minsize = 0;
  public $maxsize = PHP_INT_MAX;

  public $ctimebeg = 0;
  public $ctimeend = PHP_INT_MAX;
  public $mtimebeg = 0;
  public $mtimeend = PHP_INT_MAX;
  public $atimebeg = 0;
  public $atimeend = PHP_INT_MAX;

  public $dirname_regex = '/^$/';   // regex for the subdirectory names to be iterated

  public $subdirs_regex = '/.*/';   // regex for the subcategory names to be recursed into
  public $max_nesting_depth = 0;    // max recursion depth (0 - no recursion)

  public $depth = 0;                // subdirectory level

  protected $dp;
  protected $files = array();
  protected $subdirs = array();

  # -----  Protected  ----- #

  private function between_values ( $min, $max, $test ) {
    if ( $min <= $max ) {
      return ( ( $text >= $min ) && ( $test <= $max ) );
    } else {
      return ( ( $text < $min ) || ( $text > $max ) );
    }
  }

  private function regex_matches ( $regex, $string ) {
    if ( ! is_array ( $regex ) ) { $regex = array ( $regex ); }
    foreach ( $regex as $regex_element ) {
      if ( preg_match ( $regex_element, $string, $matches ) ) { return true; }
    }
    return false;
  }

  protected function file_is_ok ( $file ) {
    return ( $this->between_values ( $this->minsize , $this->maxsize , $file['size' ] ) &&
             $this->between_values ( $this->atimebeg, $this->atimeend, $file['atime'] ) &&
             $this->between_values ( $this->mtimebeg, $this->mtimeend, $file['mtime'] ) &&
             $this->between_values ( $this->ctimebeg, $this->ctimeend, $file['ctime'] ) &&
             $this->regex_matches  ( $this->filename_regex, $file['name'] )
           );
  }

  protected function open_elements_source () {
    if ( ! is_dir ( $this->path ) ) {
      $this->error_text = "Path not found: " . $this->path;
      return false;
    }
    if ( substr ( $this->path, -1 ) != '/' ) { $this->path .= '/'; }
  }

  protected function query () {
    $dir = scandir ( $this->path );
    foreach ( $dir as $filename ) {
      $file = stat ( $this->path . $filename );
      $file['path'] = $this->path;
      $file['name'] = $filename;
      if ( is_dir ( $this->path . $filename ) ) {
        if ( $this->regex_matches ( $this->dirname_regex, $filename ) ) {
          $this->files[] = $file;
        }
        if ( $this->depth < $this->max_nesting_depth ) {
          if ( $this->regex_matches ( $this->subdirs_regex, $filename ) &&
               ( $filename != '.' ) && ( $filename != '..' ) ) {
            $this->subdirs[] = $filename;
          }
        }
      } else {
        if ( $this->file_is_ok ( $file ) ) { $this->files[] = $file; }
      }
    }
    return ( count ( $this->files ) > 0 );
  }

  protected function continue_query () {
    return false;
  }

  protected function obtain_elements_array () {
    return $this->files;
  }

  protected function error_info () {
    return $this->error_text;
  }

  protected function iterate_elements ( $ActionObject ) {
    if ( ! is_int ( $this->ctimebeg ) ) { $this->ctimebeg = strtotime ( $this->ctimebeg ); }
    if ( ! is_int ( $this->ctimeend ) ) { $this->ctimeend = strtotime ( $this->ctimeend ); }
    if ( ! is_int ( $this->mtimebeg ) ) { $this->mtimebeg = strtotime ( $this->mtimebeg ); }
    if ( ! is_int ( $this->mtimeend ) ) { $this->mtimeend = strtotime ( $this->mtimeend ); }
    if ( ! is_int ( $this->atimebeg ) ) { $this->atimebeg = strtotime ( $this->atimebeg ); }
    if ( ! is_int ( $this->atimeend ) ) { $this->atimeend = strtotime ( $this->atimeend ); }
    parent::iterate_elements ( $ActionObject );
    foreach ( $this->subdirs as $subdir ) {
      $classname = get_class ( $this );
      $Iter = new $classname ( $this->bot );
      $Iter->path              = $this->path . $subdir;
      $Iter->filename_regex    = $this->filename_regex;
      $Iter->minsize           = $this->minsize;
      $Iter->maxsize           = $this->maxsize;
      $Iter->ctimebeg          = $this->ctimebeg;
      $Iter->ctimeend          = $this->ctimeend;
      $Iter->mtimebeg          = $this->mtimebeg;
      $Iter->mtimeend          = $this->mtimeend;
      $Iter->atimebeg          = $this->atimebeg;
      $Iter->atimeend          = $this->atimeend;
      $Iter->dirname_regex     = $this->dirname_regex;
      $Iter->subdirs_regex     = $this->subdirs_regex;
      $Iter->max_nesting_depth = $this->max_nesting_depth;
      $Iter->depth             = $this->depth++;
      $Iter->iterate ( $ActionObject );
    }
  }

}


abstract class Iterator_GenericFile extends Iterator_Generic {

  public $filename;           // this file will be read for elements to be iterated
  public $batch_size = 1000;  // read up to this number of elements per batch

  protected $fp;                  // the file handle
  protected $elements = array();  // the file elements being read and processed

  protected $error_text;

  # ----------  Protected  ---------- #

  protected function read_elements () {
    while ( count ( $this->elements ) < $this->batch_size ) {
      $element = $this->read_element ();
      if ( $element === false ) { break; }
      $this->elements[] = $element;
    }
    return ( count ( $this->elements ) > 0 );
  }

  protected function query () {
    return $this->read_elements();
  }

  protected function continue_query () {
    return $this->read_elements();
  }

  protected function obtain_elements_array () {
    return $this->elements;
  }

  protected function error_info () {
    return $this->error_text;
  }

  protected function open_elements_source () {
    $this->fp = @fopen ( $filename, 'r' );
    return ( $this->fp !== false );
  }

  protected function close_elements_source () {
    if ( @fclose ( $this->fp ) ) {
      return true;
    } else {
      return false;
    }
  }

  abstract protected function read_element ();

}


class Iterator_TextFile extends Iterator_GenericFile {

  # ----------  Protected  ---------- #

  protected function read_element () {
    while ( true ) {
      if ( feof ( $this->fp ) ) { return false; }
      return @fgets ( $this->fp );
    }
  }

}


class Iterator_ConfigFile extends Iterator_TextFile {

  public $line_comment_marks = array ( '#', '//' );

  protected $line_comment_marks_regex;

  protected function query() {
    $this->line_comment_marks_regex = '/^\s*(' . $this->bot->barsepstring ( $this->line_comment_marks, true ) . ')/Uus';
    return parent::query();
  }

  protected function read_element () {
    $line = parent::read_element();
    if ( $line === false ) { return false; }
    if ( preg_match ( $this->line_comment_marks_regex, $line, $matches ) ) $line = '';
    return $line;
  }

}


# ----------  Chronology iterator classes  ---------- #


abstract class Iterator_Monthdays_Generic extends Iterator_Apibot_Generic {

  protected $monthsdata = array (
     1 => array ( 'name' => "януари"   , 'days' => 31 ),
     2 => array ( 'name' => "февруари" , 'days' => 29 ),
     3 => array ( 'name' => "март"     , 'days' => 31 ),
     4 => array ( 'name' => "април"    , 'days' => 30 ),
     5 => array ( 'name' => "май"      , 'days' => 31 ),
     6 => array ( 'name' => "юни"      , 'days' => 30 ),
     7 => array ( 'name' => "юли"      , 'days' => 31 ),
     8 => array ( 'name' => "август"   , 'days' => 31 ),
     9 => array ( 'name' => "септември", 'days' => 30 ),
    10 => array ( 'name' => "октомври" , 'days' => 31 ),
    11 => array ( 'name' => "ноември"  , 'days' => 30 ),
    12 => array ( 'name' => "декември" , 'days' => 31 ),
  );

  public $year;  // if not specified explicitly, the current year will be used.

  protected $leap_year;

  protected function query          () { return true ; }
  protected function continue_query () { return false; }

  protected function obtain_elements_array () {  // just calculates whether the year is leap - extend with returning some data as array.
    if ( is_null ( $this->year ) ) { $this->year = date ( 'Y' ); }
    if ( ( $this->year % 4 ) == 0 ) { $this->leap_year = true;  }
    if ( ( ( $this->year % 100 ) == 0 ) && ! ( $this->year % 400 ) == 0 ) { $this->leap_year = false; }
    return false;
  }

}

class Iterator_Monthdays_Year extends Iterator_Monthdays_Generic {

  protected function obtain_elements_array () {
    parent::obtain_elements_array();

    $monthdays = array();
    $yeardayno = 1;
    foreach ( $this->monthsdata as $monthno => $monthdata ) {
      for ( $day = 1; $day <= $monthdata['days']; $day++ ) {
        $monthday = array ( 'monthno' => $monthno, 'monthname' => $monthdata['name'], 'monthday' => $day, 'yearday' => $yeardayno );
        if ( $this->leap_year || ! ( ( $monthno == 2 ) && ( $day == 29 ) ) ) {
          $monthdays[] = $monthday;
          $yeardayno++;
        }
      }
    }
    return $monthdays;
  }

}


class Iterator_Monthdays_Month extends Iterator_Monthdays_Generic {

  public $month;  // the month you would like to iterate its days, as name or number (1-12).

  protected function obtain_elements_array () {
    if ( empty ( $this->month ) ) {
      $this->log ( "ERROR: Please specify a month to the monthdays iterator!", LL_PANIC );
      die();
    }
    if ( ! is_int ( $this->month ) ) {
      foreach ( $this->monthsdata as $monthno => $monthdata ) {
        if ( $this->month == $monthdata['name'] ) { $this->month = $monthno; break; }
      }
      $this->log ( "ERROR: Bad month name: '" . $this->month . "'!", LL_PANIC );
      die();
    }
    if ( ( $this->month < 1 ) || ( $this->month > 12 ) ) {
      $this->log ( "ERROR: Bad month No.: " . $this->month . "!", LL_PANIC );
      die();
    }

    $monthdays = array();
    for ( $day = 1; $day <= $this->monthsdata[$this->month]; $day++ ) {
      if ( $this->leap_year || ! ( ( $this->month == 2 ) && ( $day == 29 ) ) ) {
        $monthdays[$day] = $day;
      }
    }
    return $monthdays;
  }

}


# ----------  Database iterator classes  ---------- #

abstract class Iterator_Database_Generic extends Iterator_Generic {

  public $db_details;  // array: host, port, user, pass, name, charset - must be set before using with an iterator!
  public $db;          // may be set externally

  public $batch_size = 1000;  // read up to so much elements per batch

  public $offset = 0;

  public $db_query_sql;  // put here the SQL request, sans 'limit' and 'offset';

  protected $elements_array = array();

  protected $error_text;

  # ----- Protected ----- #

  protected function query_elements_array ( $count = NULL, $offset = NULL ) {
    $this->elements_array = $this->db_query ( $count, $offset );
    if ( is_array ( $this->elements_array ) ) $this->offset += count ( $this->elements_array );
    return ( ! empty ( $this->elements_array ) );
  }

  protected function query () {
    return $this->query_elements_array ( $this->batch_size, $this->offset );
  }

  protected function continue_query () {
    return $this->query_elements_array ( $this->batch_size, $this->offset );
  }

  protected function obtain_elements_array () {
    return $this->elements_array();
  }

  protected function error_info () {
    return $this->error_text;
  }

  # ----- Overriding ----- #

  protected function open_elements_source () {
    $this->db = $this->db_connect ( $this->db_details );
    return ( ! empty ( $this->db ) );
  }

  protected function close_elements_source () {
    return $this->db_disconnect ( $this->db );
  }

  # ----- Absract ----- #

  abstract protected function db_connect ( $db_details );
  abstract protected function db_disconnect ( $db );

  abstract protected function db_query ( $count = NULL, $offset = NULL );

  # ----- Public ----- #

  public function iterate ( $ActionObject ) {
    if ( $ActionObject instanceof ActionObject_WithDatabase ) {
      if ( empty ( $ActionObject->db_details ) ) {
        $ActionObject->db = $this->db;
      }
    }
    parent::iterate ( $ActionObject );
  }

}


class Iterator_Database_MySQL extends Iterator_Database_Generic {

  protected function db_connect ( $db_details ) {
    $hostname = $db_details['host'];
    if ( ! is_null ( $this->port ) ) {
      $hostname .= ":" . $db_details['port'];
    }
    $db = mysql_pconnect ( $hostname, $db_details['user'], $db_details['pass'] );
    if ( is_null ( $db ) ) {
      throw new Exception ( "Could not connect to host/socket `" . $hostname . "` as `" . $db_details['user'] . "`!" );
    }
    if ( ! mysql_select_db ( $db_details['name'], $db ) ) {
      throw new Exception ( "Could not select database `" . $db_details['name'] . "`!" );
    }
    if ( ! empty ( $db_details['charset'] ) ) {
      mysql_query ( "SET CHARACTER SET " . $db_details['charset'], $db );
      mysql_query ( "SET NAMES "         . $db_details['charset'], $db );
    }
    return $db;
  }

  protected function db_disconnect ( $db ) {
    // do nothing - disconnect is not needed
  }

  protected function db_query ( $count = NULL, $offset = NULL ) {
    $SQL = $this->db_query_sql ( $count, $offset );
    mysql_select_db ( $this->db_details['name'], $this->db );
    $result = mysql_query ( $SQL, $this->db );
    if ( ! $result ) {
      return false;
    } else {
      while ( $row = mysql_fetch_assoc ( $result ) ) {
        $this->elements_array[] = $row;
      }
      mysql_free_result ( $result );
    }
  }

  protected function db_query_sql ( $count = NULL, $offset = NULL ) {  // should return the SQL statement that extracts the elements
    if ( substr ( $this->db_query_sql, -1 ) == ';' ) {
      $this->db_query_sql = rtrim ( $this->db_query_sql, ';' );
    }
    if ( ! is_null ( $count  ) ) { $count  = ' LIMIT '  . $count ; }
    if ( ! is_null ( $offset ) ) { $offset = ' OFFSET ' . $offset; }
    return $this->db_query_sql . $count . $offset . ';';
  }

}

