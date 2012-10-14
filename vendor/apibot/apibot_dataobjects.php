<?php
#
#  Apibot - a MediaWiki bot.
#  Data objects module.
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
#  You should have received a copy of the GNU Affero General Public License
#  along with this program; if not, write to the Free Software Foundation, Inc.,
#  59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
#  http://www.gnu.org/copyleft/gpl.html
#
#  Author: Grigor Gatchev <grigor at gatchev dot info>
# ---------------------------------------------------------------------------- #


# ---------------------------------------------------------------------------- #
# --                           Utility functions                            -- #
# ---------------------------------------------------------------------------- #


function assign_if_nonnull ( &$target, $source, $value = NULL ) {
  if ( $value === NULL ) {
    $value = $source;
  }
  if ( ! ( $source === NULL ) ) {
    $target = $value;
  }
}

function assign_ts_nonnull ( &$target, $source, $value = NULL ) {
  if ( is_null ( $value ) && ! is_null ( $source ) ) {
    $value = substr ( $source, 0, 10 ) . " " . substr ( $source, 11, 8 );
  }
  if ( ! is_null ( $source ) ) {
    $target = $value;
  }
}

function assign_if_nonnull_and_noorigin ( &$target, $source, $value = NULL ) {
  if ( empty ( $target ) ) {
    assign_if_nonnull ( $target, $source, $value );
  }
}

function assign_ts_nonnull_and_noorigin ( &$target, $source, $value = NULL ) {
  if ( empty ( $target ) ) {
    assign_ts_nonnull ( $target, $source, $value );
  }
}

function add2array_if_nonnull ( &$array, $key, $value ) {
  if ( ! is_null ( $value ) ) {
    $array[$key] = $value;
  }
}

function set_if_arraykey_exists ( &$to_var, $from_array, $valuename, $targetvalue = NULL ) {
  if ( array_key_exists ( $valuename, $from_array ) ) {
    if ( $targetvalue == NULL ) { $targetvalue = $from_array[$valuename]; };
    $to_var = $targetvalue;
  }
}

function merge_array_if_exists ( &$target, $source ) {
  if ( ! is_array ( $target ) ) {
    $target = array();
  }
  if ( is_array ( $source ) ) {
    $target = array_merge ( $target, $source );
  }
}

function merge_data_if_requested ( &$dest, $query, $keyword, $continues_element ) {
  if ( is_array ( $continues_element ) && array_key_exists ( $keyword, $continues_element ) ) {
    merge_array_if_exists ( $dest, $query[$keyword] );
  }
}




# ----- New utils: ----- #

function array_sub ( $element, $key, $default = NULL ) {
  return ( is_array ( $element ) && array_key_exists ( $key, $element ) ?
    $element[$key] :
    $default );
}

function array_subsub ( $element, $key1, $key2, $default = NULL ) {
  return ( ( is_array ( $element ) && array_key_exists ( $key1, $element ) ) &&
           ( is_array ( $element[$key1] ) && array_key_exists ( $key2, $element[$key1] ) ) ?
    $element[$key1][$key2] :
    $default );
}

function array_ts ( $element, $key, $default = NULL ) {
  return ( is_array ( $element ) && array_key_exists ( $key, $element ) ?
    substr ( $element[$key], 0, 10 ) . " " . substr ( $element[$key], 11, 8 ) :
    $default );
}


# ---------------------------------------------------------------------------- #
# --                             Abstract classes                           -- #
# ---------------------------------------------------------------------------- #


abstract class Generic_Data_Item {
  public $bot;

  public function read_from_element ( $data_element, $bot ) {
    $this->bot = $bot;
  }

}


abstract class Namespaced_Data_Item extends Generic_Data_Item {
  public $ns;
  public $namespace;

  private function get_namespace () {
    if ( ! is_null ( $this->bot ) ) {
      $this->namespace = $this->bot->wiki_namespace_name ( $this->ns );
    }
  }

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->ns = array_sub ( $data_element, 'ns' );
    $this->get_namespace();
  }

}


abstract class Generic_Page_Item extends Namespaced_Data_Item {
  public $title;
  public $pageid;

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->title  = array_sub ( $data_element, 'title'  );
    $this->pageid = array_sub ( $data_element, 'pageid' );
  }

}


abstract class Generic_Image extends Generic_Data_Item {
  public $name;
  public $mime;
  public $url;
  public $timestamp;
  public $user;
  public $comment;
  public $width;
  public $height;
  public $size;
  public $sha1;
  public $metadata;
  public $descriptionurl;

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->name      = array_sub ( $data_element, 'name' );
    $this->mime      = array_sub ( $data_element, 'mime' );
    $this->url       = array_sub ( $data_element, 'url' );
    $this->timestamp = array_ts  ( $data_element, 'timestamp' );
    $this->user      = array_sub ( $data_element, 'user' );
    $this->comment   = array_sub ( $data_element, 'comment' );
    $this->width     = array_sub ( $data_element, 'width' );
    $this->height    = array_sub ( $data_element, 'height' );
    $this->size      = array_sub ( $data_element, 'size' );
    $this->sha1      = array_sub ( $data_element, 'sha1' );
    $this->metadata  = array_sub ( $data_element, 'metadata' );
    $this->descriptionurl = array_sub ( $data_element, 'descriptionurl' );
  }

}


# ---------------------------------------------------------------------------- #
#                      Page properties iterators data                       -- #
# ---------------------------------------------------------------------------- #


class Page_Revision extends Generic_Data_Item {
  public $pageid;  // supplied by querying revids, and useful in many cases
  public $revid;
  public $is_minor;
  public $timestamp;
  public $user;
  public $comment;
  public $size;
  public $content;
  public $section;
  public $parsetree;
  public $tags;

  function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->pageid    = array_sub ( $data_element, 'pageid' );
    $this->revid     = array_sub ( $data_element, 'revid' );
    $this->is_minor  = array_key_exists ( 'minor', $data_element );
    $this->timestamp = array_ts  ( $data_element, 'timestamp' );
    $this->user      = array_sub ( $data_element, 'user' );
    $this->comment   = array_sub ( $data_element, 'comment' );
    $this->size      = array_sub ( $data_element, 'size' );
    $this->content   = array_sub ( $data_element, '*' );
    $this->section   = array_sub ( $data_element, 'section' );
    $this->parsetree = array_sub ( $data_element, 'parsetree' );
    $this->tags      = array_sub ( $data_element, 'tags' );
  }

}


class Page_Category extends Generic_Page_Item {
  public $sortkey;
  public $timestamp;

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->sortkey   = array_ts  ( $data_element, 'sortkey' );
    $this->timestamp = array_sub ( $data_element, 'timestamp' );
  }

}


class Page_ImageInfo extends Generic_Image {

  public $archivename;
  public $imagerepository;

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->archivename     = array_sub ( $data_element, 'archivename' );
    $this->imagerepository = array_sub ( $data_element, 'imagerepository' );
  }

}


class Page_LangLink extends Generic_Data_Item {

  public $lang;
  public $title;

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->lang  = array_sub ( $data_element, 'lang' );
    $this->title = array_sub ( $data_element, '*' );
  }

}


class Page_Link extends Generic_Page_Item {

}


class Page_Template extends Generic_Page_Item {

}


class Page_Image extends Generic_Page_Item {

}


class Page_Extlink extends Generic_Data_Item {

  public $url;

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->url = array_sub ( $data_element, '*' );
  }

}


class Page_DuplicateFile extends Generic_Data_Item {
  public $name;
  public $user;
  public $timestamp;

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->name      = array_sub ( $data_element, 'name' );
    $this->user      = array_sub ( $data_element, 'user' );
    $this->timestamp = array_ts  ( $data_element, 'timestamp' );
  }

}


class Page_GlobalUsage extends Generic_Data_Item {
  public $title;
  public $url;
  public $wiki;

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->title = array_sub ( $data_element, 'title' );
    $this->url   = array_sub ( $data_element, 'url'   );
    $this->wiki  = array_sub ( $data_element, 'wiki'  );
  }

}


# ---------------------------------------------------------------------------- #
# --                 Basic functions / List iterators data                  -- #
# ---------------------------------------------------------------------------- #


class Image extends Generic_Image {

}


class UserContrib extends Generic_Page_Item {
  public $user;
  public $revid;
  public $timestamp;
  public $comment;

  public $is_anon;
  public $id_new;
  public $is_bot;
  public $is_minor;
  public $is_top;

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->user      = array_sub ( $data_element, 'user'      );
    $this->revid     = array_sub ( $data_element, 'revid'     );
    $this->timestamp = array_ts  ( $data_element, 'timestamp' );
    $this->comment   = array_sub ( $data_element, 'comment'   );

    $this->is_anon  = array_key_exists ( 'anon' , $data_element );
    $this->is_new   = array_key_exists ( 'new'  , $data_element );
    $this->is_bot   = array_key_exists ( 'bot'  , $data_element );
    $this->is_minor = array_key_exists ( 'minor', $data_element );
    $this->is_top   = array_key_exists ( 'top'  , $data_element );
  }
}


class RecentChange extends UserContrib {
  public $type;

  public $rcid;
  public $old_revid;

  public $logid;
  public $logtype;
  public $logaction;
  public $logparams;

  public $is_patrolled;  // filled in only if the user has the patrol right

  public $oldlen;
  public $newlen;

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->type      = array_sub ( $data_element, 'type' );
    $this->rcid      = array_sub ( $data_element, 'rcid' );
    $this->old_revid = array_sub ( $data_element, 'old_revid' );
    $this->logid     = array_sub ( $data_element, 'logid' );
    $this->logtype   = array_sub ( $data_element, 'logtype' );
    $this->logaction = array_sub ( $data_element, 'logaction' );
    switch ( $data_element['logtype'] ) {
      case 'move'  : $this->logparams = array_subsub ( $data_element, 'move', 'new_title' );
      case 'block' : $this->logparams = array_subsub ( $data_element, 'block', 'duration' );
      default      : $this->logparams = array_sub ( $data_element, '0' );
    }
    $this->is_patrolled = array_key_exists ( 'patrolled', $data_element );
    $this->oldlen = array_sub ( $data_element, 'oldlen' );
    $this->newlen = array_sub ( $data_element, 'newlen' );
  }

}


class User extends Generic_Data_Item {
  public $name;
  public $editcount;
  public $registration;
  public $groups;
  public $blockedby;
  public $blockreason;
  public $emailable;
  public $userrightstoken;

  public $is_missing;
  public $is_invalid;

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->name            = array_sub ( $data_element, 'name' );
    $this->editcount       = array_sub ( $data_element, 'editcount' );
    $this->registration    = array_ts  ( $data_element, 'registration' );
    $this->groups          = array_sub ( $data_element, 'groups' );
    $this->blockedby       = array_sub ( $data_element, 'blockedby' );
    $this->blockreason     = array_sub ( $data_element, 'blockreason' );
    $this->emailable       = array_sub ( $data_element, 'emailable' );
    $this->userrightstoken = array_sub ( $data_element, 'userrightstoken' );

    $this->is_missing = array_key_exists ( 'missing', $data_element );
    $this->is_invalid = array_key_exists ( 'invalid', $data_element );
  }

  public function is_in_group ( $group ) {
    if ( is_array ( $this->groups ) ) {
      return ( ! ( array_search ( $group, $this->groups ) === false ) );
    }
    return false;
  }

  public function is_bot () {
    return $this->is_in_group ( "bot" );
  }

  public function is_sysop () {
    return $this->is_in_group ( "sysop" );
  }

  public function is_bureaucrat () {
    return $this->is_in_group ( "bureaucrat" );
  }

}


class Block extends Generic_Data_Item {
  public $id;
  public $user;
  public $by;
  public $timestamp;
  public $expiry;
  public $reason;
  public $rangestart;
  public $rangeend;
  public $is_nocreate;
  public $is_autoblock;

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->id         = array_sub ( $data_element, 'id' );
    $this->user       = array_sub ( $data_element, 'user' );
    $this->by         = array_sub ( $data_element, 'by' );
    $this->timestamp  = array_ts  ( $data_element, 'timestamp' );
    $this->expiry     = array_sub ( $data_element, 'expiry' );
    $this->reason     = array_sub ( $data_element, 'reason' );
    $this->rangestart = array_sub ( $data_element, 'rangestart' );
    $this->rangeend   = array_sub ( $data_element, 'rangeend' );

    $this->is_nocreate  = array_key_exists ( 'nocreate' , $data_element );
    $this->is_autoblock = array_key_exists ( 'autoblock', $data_element );
  }

}


class LogEvent extends Generic_Page_Item {
  public $logid;
  public $type;
  public $action;
  public $user;
  public $timestamp;
  public $comment;
  public $details;

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->logid     = array_sub ( $data_element, 'logid' );
    $this->type      = array_sub ( $data_element, 'type' );
    $this->action    = array_sub ( $data_element, 'action' );
    $this->user      = array_sub ( $data_element, 'user' );
    $this->timestamp = array_ts  ( $data_element, 'timestamp' );
    $this->comment   = array_sub ( $data_element, 'comment' );
    switch ( $data_element['type'] ) {
      case 'delete'     : $this->details = array_sub ( $data_element, 'delete' );    // the field will not exist
        break;
      case 'move'       : $this->details = array_sub ( $data_element, 'move' );      // array ( new_ns, new_title ); new_title has namespace prefix
        break;
      case 'protect'    :
        switch ( $data_element['action'] ) {
          case 'protect' : $this->details[0] = array_sub ( $data_element, '0' ); // the protections and a language-specific expiry
                           $this->details[0] = array_sub ( $data_element, '1' ); // mostly empty
            break;
          case 'unprotect' :  // no details to obtain
            break;
          case 'move_prot' : $this->details = array_sub ( $data_element, '0' ); // the old name of the moved protected page ('title' is the new name)
            break;
        }
        break;
      case 'block'      : $this->details = array_sub ( $data_element, 'block' );     // if range - no data; if account/ip - array ( flags, duration, expiry )
        break;
      case 'rights'     : $this->details = array_sub ( $data_element, 'rights' );    // array ( new, old ); lists of MW rights (privileges)
        break;
      case 'renameuser' : $this->details = array_sub ( $data_element, '0' );  // the new name ('title' is the old name)
        break;
      case 'newusers'   :
        switch ( $data_element['action'] ) {
          case 'newusers' :
            break;
          case 'create2' : $this->details = array_sub ( $data_element, '0' );  // userid? (can be missing)
            break;
          case 'create' : $this->details = array_sub ( $data_element, '0' );  // userid? (can be missing)
            break;
          case 'autocreate' : $this->details = array_sub ( $data_element, '0' );  // userid? (can be missing)
            break;
        }
        break;
      case 'patrol'     : $this->details = array_sub ( $data_element, 'patrol' );    // array ( auto, prev, cur ); auto - 0 or 1, prev/cur - revids
        break;
      case 'upload'     :  // no details to obtain
        break;
      default           : $this->details = array_sub ( $data_element, 'details' );
    }
  }

}


class ProtectedTitle extends Generic_Page_Item {

  public $timestamp;
  public $user;
  public $comment;
  public $expiry;
  public $level;

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->timestamp = array_ts  ( $data_element, 'timestamp' );
    $this->user      = array_sub ( $data_element, 'user' );
    $this->comment   = array_sub ( $data_element, 'comment' );
    $this->expiry    = array_sub ( $data_element, 'expiry' );
    $this->level     = array_sub ( $data_element, 'level' );
  }

}


class Page_WithExtlink extends Generic_Page_Item {
  public $url;

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->url = array_sub ( $data_element, 'url' );
  }

}


class Page_FromWatchlist extends Generic_Page_Item {
  public $revid;
  public $old_revid;
  public $rcid;
  public $user;
  public $comment;
  public $timestamp;
  public $oldlen;
  public $newlen;
  public $is_patrolled;
  public $is_new;
  public $is_botedit;
  public $is_minor;
  public $is_anon;

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->revid     = array_sub ( $data_element, 'revid' );
    $this->old_revid = array_sub ( $data_element, 'old_revid' );
    $this->rcid      = array_sub ( $data_element, 'rcid' );
    $this->user      = array_sub ( $data_element, 'user' );
    $this->comment   = array_sub ( $data_element, 'comment' );
    $this->timestamp = array_ts  ( $data_element, 'timestamp' );
    $this->oldlen    = array_sub ( $data_element, 'oldlen' );
    $this->newlen    = array_sub ( $data_element, 'newlen' );

    $this->is_patrolled = array_key_exists ( 'patrolled', $data_element );
    $this->is_new       = array_key_exists ( 'new'      , $data_element );
    $this->is_botedit   = array_key_exists ( 'bot'      , $data_element );
    $this->is_minor     = array_key_exists ( 'minor'    , $data_element );
    $this->is_anon      = array_key_exists ( 'anon'     , $data_element );
  }

}


class List_Title extends Generic_Data_Item {

  public $title;

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->title = array_sub ( $data_element, '*' );
  }

}


class List_Link extends Generic_Page_Item {

  public $fromid;

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->fromid = array_sub ( $data_element, 'fromid' );
  }

}


class List_SearchResult extends Generic_Page_Item {

  public $snippet;
  public $size;
  public $wordcount;
  public $timestamp;

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->snippet   = array_sub ( $data_element, 'snippet'   );
    $this->size      = array_sub ( $data_element, 'size'      );
    $this->wordcount = array_sub ( $data_element, 'wordcount' );
    $this->timestamp = array_ts  ( $data_element, 'timestamp' );
  }

}


class DeletedRevision extends Page_Revision {
  public $token;  // undelete token - use it while undeleting revisions!

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->token = array_sub ( $data_element, 'token' );
    $this->size  = array_sub ( $data_element, 'len'   );  // 'size' in deletedrevs list is replaced by 'len'
  }

}


# ---------------------------------------------------------------------------- #
# --                              Page support                              -- #
# ---------------------------------------------------------------------------- #


class Page extends Generic_Page_Item {

  public $counter;     // times the page was accessed; some wikis don't supply this info
  public $length;      // in bytes
  public $timestamp;   // of the last revision fetched
  public $lasttouched; // timestamp the page was last touched by a change in its rendering (eg. in a template it includes...)
  public $lastrevid;   // the last page revid (might be different from the last revid requested)
  public $protection;  // array of protection description arrays
  public $url;
  public $editurl;

  public $is_missing;
  public $is_invalid; // if the name requested is invalid
  public $is_new;     // has only 1 revision
  public $is_redirect;

  # These might be empty or partially filled, depending on the page request properties.
  public $revisions;  // history of the page
  public $categories; // categories it is in
  public $imageinfo;  // image versions ('revisions'), if the page is an image
  public $stashimageinfo;
  public $langlinks;  // a.k.a. interwikis
  public $links;      // a.k.a. wikilinks
  public $templates;  // and other included pages
  public $images;     // and other media
  public $extlinks;
  public $duplicatefiles;
  public $globalusage;

  public $imagerepository;  // 'local' etc.
  public $categorysize;     // if this is a category page, the number of members
  public $categorypages;
  public $categoryfiles;
  public $categorysubcats;

  public $text;             // text of the page latest (requested) revision
  public $section;          // section No. that wat fetched (NULL - all text, 'new' - new section)
  public $rvtimestamp;      // timestamp of the latest (requested) revision

/*  not implemented yet:
  public $prependtext;
  public $appendtext;
*/

  public $requested_title;  // the non-normalized title from the request;
  public $fetchtimestamp;   // the wiki timestamp the page was fetched on

  public $deny_bots;        // if true, access for this bot is denied by a {{Bots}} template.


  protected $lastrev;


  private function objects_array ( &$data_element, $array_key, $object_type ) {
    if ( array_key_exists ( $array_key, $data_element ) ) {
      if ( is_array ( $data_element[$array_key] ) ) {
        $objects = array();
        foreach ( $data_element[$array_key] as $key => &$subelement ) {
          $object = new $object_type;
          $object->read_from_element ( $subelement, $this->bot );
          unset ( $data_element[$array_key][$key] );  // decreases memory usage, but will cause data element modification!!!
          $objects[] = $object;
        }
        return $objects;
      }
    }
    return NULL;
  }

  private function latest_revision () {
    if ( ! isset ( $this->lastrev ) ) {
      if ( empty ( $this->revisions ) ) return false;
      $begrev = reset ( $this->revisions );
      $endrev = end   ( $this->revisions );
      if ( $endrev->timestamp > $begrev->timestamp ) { $this->lastrev = &$endrev; } else { $this->lastrev = &$begrev; }
    }
    return $this->lastrev;
  }

  private function deny_bots () {
    return (bool) preg_match (
      '/\{\{(' .
          'nobots|' .
          'bots\|allow=none|' .
          'bots\|deny=all|' .
          'bots\|optout=all|' .
          'bots\|deny=.*?' . preg_quote ( $this->bot->my_username(), '/' ) . '.*?' .
        ')\}\}/iS',
      $this->text, $matches );
  }

  public function read_from_element ( $data_element, $bot ) {
    parent::read_from_element ( $data_element, $bot );

    $this->counter     = array_sub ( $data_element, 'counter'    );
    $this->length      = array_sub ( $data_element, 'length'     );
    $this->lasttouched = array_ts  ( $data_element, 'touched'    );
    $this->lastrevid   = array_sub ( $data_element, 'lastrevid'  );
    $this->protection  = array_sub ( $data_element, 'protection' );  // an array of arrays...
    $this->url         = array_sub ( $data_element, 'fullurl'    );
    $this->editurl     = array_sub ( $data_element, 'editurl'    );

    $this->is_missing  = array_key_exists ( 'missing' , $data_element );
    $this->is_invalid  = array_key_exists ( 'invalid' , $data_element );
    $this->is_new      = array_key_exists ( 'new'     , $data_element );
    $this->is_redirect = array_key_exists ( 'redirect', $data_element );

    $this->revisions      = $this->objects_array ( $data_element, 'revisions'     , 'Page_Revision'      );
    $this->categories     = $this->objects_array ( $data_element, 'categories'    , 'Page_Category'      );
    $this->imageinfo      = $this->objects_array ( $data_element, 'imageinfo'     , 'Page_ImageInfo'     );
    $this->stashimageinfo = $this->objects_array ( $data_element, 'stashimageinfo', 'Page_ImageInfo'     );
    $this->langlinks      = $this->objects_array ( $data_element, 'langlinks'     , 'Page_LangLink'      );
    $this->links          = $this->objects_array ( $data_element, 'links'         , 'Page_Link'          );
    $this->templates      = $this->objects_array ( $data_element, 'templates'     , 'Page_Template'      );
    $this->images         = $this->objects_array ( $data_element, 'images'        , 'Page_Image'         );
    $this->extlinks       = $this->objects_array ( $data_element, 'extlinks'      , 'Page_Extlink '      );
    $this->duplicatefiles = $this->objects_array ( $data_element, 'duplicatefiles', 'Page_DuplicateFile' );
    $this->globalusage    = $this->objects_array ( $data_element, 'globalusage'   , 'Page_GlobalUsage'   );

    $this->imagerepository = array_sub ( $data_element, 'imagerepository' );

    if ( array_key_exists ( 'categoryinfo', $data_element ) ) {
      $this->categorysize    = array_subsub ( $data_element, 'categoryinfo', 'size'    );
      $this->categorypages   = array_subsub ( $data_element, 'categoryinfo', 'pages'   );
      $this->categoryfiles   = array_subsub ( $data_element, 'categoryinfo', 'files'   );
      $this->categorysubcats = array_subsub ( $data_element, 'categoryinfo', 'subcats' );
    }

    $this->fetchtimestamp = array_sub ( $data_element, 'fetchtimestamp' );
    if ( empty ( $this->fetchtimestamp ) ) {
      $this->fetchtimestamp = date ( 'Y-m-d H:i:s', $this->bot->wiki_lastreq_time() );
    }

    $this->reset_text_changes();
    $this->deny_bots = $this->deny_bots();

  }

  public function redirects_to () {
    preg_match ( '/#' . $this->bot->wiki_magicword_namesregex ( 'redirect' ) . '\s*\[\[([^\]]+)\]\]/Ui', $this->text, $matches );
    if ( empty ( $matches[2] ) ) { return false; } else { return $matches[2]; }
  }

  public function is_edited () {
    $lastrev = $this->latest_revision();
    $origtext = ( ( $lastrev === false ) ? NULL : $lastrev->content );
    return ( $this->text !== $origtext );
  }

  public function is_modified () {
    return ( $this->is_edited() || ( $this->timestamp !== $this->rvtimestamp ) );
  }

  public function is_actual () {
    $lastrev = $this->latest_revision();
    if ( ! $lastrev ) return false;
    return ( $lastrev->revid == $this->lastrevid );
  }

  public function reset_text_changes () {
    $lastrev = $this->latest_revision();
    if ( is_object ( $lastrev ) ) {
      $this->text        = $lastrev->content;
      $this->section     = $lastrev->section;
      $this->rvtimestamp = $lastrev->timestamp;
      if ( empty ( $this->timestamp ) ) { $this->timestamp = $lastrev->timestamp; }
    }
  }

  public function replace ( $regex, $with, $limit = -1 ) {
    $this->text = preg_replace ( $regex, $with, $this->text, $limit, $count );
    return $count;
  }

  # $old - the old category (empty - add the new); $new - the new category (empty - del the old), $new_sortkey - the new sortkey ( NULL - reuse the old, "" - none )
  public function replace_category ( $old, $new, $new_sortkey = NULL ) {
    if ( ! empty ( $new_sortkey ) ) $new_sortkey = '|' . $new_sortkey;
    if ( empty ( $old ) ) {
      if ( empty ( $new ) ) {
        return false;
      } else {
        $has_categories = preg_match ( $this->bot->regex_wikilink ( false, "", $this->bot->wiki_namespace_barsepnames ( NAMESPACE_ID_CATEGORY ), NULL, "", NULL ), $this->text );
        $has_interwikis = preg_match ( $this->bot->regex_wikilink ( false, $this->bot->wiki_interwikis_barsepnames(), "", NULL, "", NULL ), $this->text );
        if ( ! $has_interwikis ) {
          $this->text .= "\n[[" . $new . $new_sortkey . "]]";
          return true;
        } elseif ( $has_categories ) {
          $regex = '/' .
            '(' . $this->bot->regexmatch_wikilink ( false, "", $this->bot->wiki_namespace_barsepnames ( NAMESPACE_ID_CATEGORY ), NULL, "", NULL ) . ')' .
            '((\v\h*)*)' .
            '(' . $this->bot->regexmatch_wikilink ( false, $this->bot->wiki_interwikis_barsepnames(), "", NULL, "", NULL ) . ')' .
            '/Uus';
          $with = '$1' . "\n[[" . $new . $new_sortkey . "]]" . '$12$14';
          return $this->replace ( $regex, $with, 1 );
        } else {
          $regex = '/(' . $this->bot->regexmatch_wikilink ( false, $this->bot->wiki_interwikis_barsepnames(), "", NULL, "", NULL ) . ')(.*)$/Uus';
          $with  = '[[' . $new . $new_sortkey . "]]\n\n" . '$1$12';
          return $this->replace ( $regex, $with, 1 );
        }
      }

    } else {
      $regex = '/' . $this->bot->regexmatch_wikilink ( false, "", $this->bot->wiki_namespace_barsepnames ( NAMESPACE_ID_CATEGORY ), $old, "", NULL ) . '\v?/u';
      if ( empty ( $new ) ) {
        $with = "";
      } else {
        if ( is_null ( $new_sortkey ) ) {
          $new_sortkey = '$9';
        }
        $with = "[[" . $new . $new_sortkey . "]]\n";
      }
      return $this->replace ( $regex, $with, 1 );
    }
  }

  public function replace_langlink ( $interwiki, $old_article_name, $new_article_name ) {
    if ( empty ( $old_article_name ) ) {
      if ( empty ( $new_article_name ) ) {
        return false;
      } else {
        $this->text .= "\n[[" . $interwiki . ':' . $new_article_name . "]]";
        return true;
      }
    } else {
      $regex = '/\v?' . $this->bot->regexmatch_wikilink ( false, $interwiki, "", $old_article_name, "", "" ) . '/u';
      if ( empty ( $new_article_name ) ) {
        $with = "";
      } else {
        $with = "\n[[" . $interwiki . ':' . $new_article_name . "]]";
      }
      return $this->replace ( $regex, $with, 1 );
    }
  }

  public function replace_file ( $old_name, $new_name, $new_attrs = NULL, $count = 1 ) {
    if ( is_array ( $new_attrs ) ) $new_attrs = '|' . implode ( '|', $new_attrs );
    $regex = $this->bot->regex_wikilink ( NULL, NULL, $this->bot->wiki_namespace_barsepnames ( NAMESPACE_ID_FILE ), $old_name, "", NULL );
    $with = ( empty ( $new_name ) ? '' : '[[$1$3$4' . $new_name . ( is_null ( $new_attrs ) ? '$9' : $new_attrs ) . ']]' );
    return $this->replace ( $regex, $with, $count );
  }

}

