<?php
#
#  An Apibot extension - ActionObjects. Used together with the Iterators.
#
#  Example for usage:
#
#  $bot = new Apibot ( $bot_login_data, $logname );
#  $bot->enter_wiki();  // mandatory for some iterators to work
#  $Iterator = new Iterator_WhateverTypeYouNeed ( $bot );
#  $ActionObject = new ActionObject_WhateverActionYouNeed();
#  $processed_elements_count = $Iterator->iterate ( $ActionObject );
#
#  !!! Beware! Not every iterator fills out all properties of an object! !!!
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

require_once ( dirname ( __FILE__ ) . "/apibot_dataobjects.php" );


# ---------------------------------------------------------------------------- #
# --                                                                        -- #
# --                    Official ActionObject classes                       -- #
# --                                                                        -- #
# -- The Apibot canonical support of the MediaWiki API.                     -- #
# --                                                                        -- #
# ---------------------------------------------------------------------------- #


# ---------------------------------------------------------------------------- #
# --                    Generic ActionObject classes                        -- #
# ---------------------------------------------------------------------------- #


abstract class ActionObject {

  public $iterator;

  public function preprocess () {  // override on need
    return true;
  }

  public function postprocess () {  // override on need
    return true;
  }

  abstract public function process ( $element );

}


abstract class ActionObject_WithBot extends ActionObject {

  protected $bot;

  function __construct ( $bot ) {
    $this->bot = $bot;
  }

  public function log ( $string, $loglevel = LL_INFO ) {
    return $this->bot->log ( $string, $loglevel );
  }

}


abstract class ActionObject_WithComment extends ActionObject_WithBot {

  public $comment;  // AKA reason, summary etc.

  protected function get_comment ( $element, $object ) {
    return $this->comment;
  }

}


abstract class ActionObject_WriteFile extends ActionObject {

  public $filename;

  abstract protected function element_string ( $element );  // should return NULL to have nothing written into the file

  protected function get_filename ( $element ) {
    return $this->filename;
  }

  public function process ( $element ) {
    $string = $this->element_string ( $element );
    if ( ! is_null ( $string ) ) {
      my_fwrite ( $this->get_filename ( $element ), $string );
      return true;
    } else {
      return false;
    }
  }
}


abstract class ActionObject_Iterate extends ActionObject_WithBot {

  protected $internal_iterator;
  protected $internal_actionobject;

  abstract protected function create_iterator     ( $element );
  abstract protected function create_actionobject ( $bot, $element );

  public function process ( $element ) {
    $this->internal_iterator     = $this->create_iterator     ( $element );
    $this->internal_actionobject = $this->create_actionobject ( $this->bot, $element );
    return $this->internal_iterator->iterate ( $this->internal_actionobject );
  }

}


# ---------------------------------------------------------------------------- #
# --                      Common ActionObject classes                       -- #
# ---------------------------------------------------------------------------- #


class ActionObject_Echo extends ActionObject {  // for testing iterators etc.

  public function process ( $element ) {
    print_r ( $element ); echo "\n";
    return true;
  }

}


class ActionObject_Count extends ActionObject {  // just counts the objects
// This is the most resource-consuming way - use as a last resort only, or for counting very specific objects only!

  public $count = 0;

  protected function countable ( $element ) { return true; }  // override to count specific elements

  public function process ( $element ) {
    if ( $this->countable ( $element ) ) {
      $this->count++;
      return true;
    } else {
      return false;
    }
  }

}


class ActionObject_MakeArray extends ActionObject {  // useful to get an elements array

  public $unique = false;

  public $startno;
  public $count;
  protected $element_counter = 0;

  public $elements = array();

  protected function process_element ( $element ) {
  // override to make array only of element parts, or of processed elements; return NULL to skip adding this specific element
    return $element;
  }

  protected function element_array_key ( $element ) {
    return NULL;
  }

  public function process ( $element ) {
    $processed_element = $this->process_element ( $element );
    if ( ! is_null ( $processed_element ) ) {
      $this->elements_counter++;
      if ( is_numeric ( $this->startno ) && ( $this->startno >= $this->elements_counter ) ) return false;
      if ( is_numeric ( $this->count ) && ( $this->startno + $this->count < $this->elements_counter ) ) return false;
      if ( ! ( $this->unique && in_array ( $processed_element, $this->elements ) ) ) {
        $key = $this->element_array_key ( $processed_element );
        if ( is_null ( $key ) ) {
          $this->elements[] = $processed_element;
        } else {
          $this->elements[$key] = $processed_element;
        }
        return true;
      }
      return false;
    }
  }

}


# ---------------------------------------------------------------------------- #
# --                 Standard Page ActionObject classes                     -- #
# ---------------------------------------------------------------------------- #


# -----  Abstract  ----- #


abstract class ActionObject_Page_Generic extends ActionObject_WithComment {

  # --- Page titles --- #

  public $move_new_title = true;  // on move page events return the new title; false - the old title

  protected function element_pagetitle ( &$element ) {
    if ( is_string ( $element ) ) {
      return $element;
    } elseif ( is_array ( $element ) ) {
      return $element['title'];
    } elseif ( is_object ( $element ) ) {
      if ( $element instanceof Page ) {
        return $element->title;
      } elseif ( $element instanceof RecentChange ) {
        if ( $this->move_new_title && ( $element->type == 'log' ) && ( $element->logtype == 'move' ) && ( $element->logaction == 'move' ) ) {
          return $element->logparams;
        } else {
          return $element->title;
        }
      } elseif ( $element instanceof UserContrib ) {
        return $element->title;
      } elseif ( $element instanceof LogEvent ) {
        if ( $this->move_new_title && ( $element->logtype == 'move' ) && ( $element->logaction == 'move' ) ) {
          return $element->logparams;
        } else {
          return $element->title;
        }
      } elseif ( $element instanceof ProtectedTitle ) {
        return $element->title;
      } elseif ( $element instanceof Page_WithExtlink ) {
        return $element->title;
      } elseif ( $element instanceof Page_FromWatchlist ) {
        return $element->title;
      } elseif ( $element instanceof List_Title ) {
        return $element->title;
      } elseif ( $element instanceof List_Link ) {
        return $element->title;
      } elseif ( $element instanceof List_SearchResult ) {
        return $element->title;
      }
    }
    $this->log ( "Unsupported element type supplied by " . get_class ( $this->iterator ) . " to " . get_class ( $this ) . "!", LL_ERROR );
    return false;
  }

  # --- Full pages --- #

  protected $element_page_properties;

  protected function refetch_page_if_needed ( $page ) {
    if ( ! empty ( $this->element_page_properties ) ) {
      return $this->bot->fetch_page ( $page->title, $this->element_page_properties );
    }
  }

  protected function element_page ( &$element ) {
    if ( is_object ( $element ) ) {
      if ( $element instanceof Page ) {
        return $this->refetch_page_if_needed ( $element );
      }
    }
    $title = $this->element_pagetitle ( $element );
    if ( $title === false ) {
      return false;
    }
    return $this->bot->fetch_page ( $title, $this->element_page_properties );
  }

  # --- Element texts --- #

  public $get_text = true;

  protected function element_text ( &$element ) {
    if ( ! $this->get_text ) { return false; }
    if ( is_string ( $element ) ) {
      return $element;
    } elseif ( is_array ( $element ) ) {
      return $element['text'];
    } elseif ( is_object ( $element ) ) {
      if ( $element instanceof Page ) {
        return $element->text;
      } elseif ( $element instanceof PageRevision ) {
        return $element->content;
      }
    }
    $this->log ( "Unsupported element type supplied by " . get_class ( $this->iterator ) . " to " . get_class ( $this ) . "!", LL_ERROR );
    return false;
  }

}


abstract class ActionObject_Page_Edit_Generic extends ActionObject_Page_Generic {

  public $edit_minor = true;

  protected $element_page_properties = array (
    'info' => array ( 'prop' => 'protection' ),
    'revisions' => array ( 'prop' => 'content|timestamp', 'limit' => 1 ),
  );

  # ----------  Protected  ---------- #

  protected function is_editable ( $element, $page ) { return ( $page !== false ); }

  protected function refetch_page_if_needed ( $page ) {
    if ( is_null ( $page->text ) || empty ( $page->timestamp ) ) {
      return $this->bot->fetch_page ( $page->title, $this->element_page_properties );
    }
    return $page;
  }

  protected function get_edit_minor ( $page ) { return $this->edit_minor; }

  protected function modify_page ( &$page, $element ) { return $page->is_modified(); }  // override in children classes

  # ----------  Public  ---------- #

  public function process ( $element ) {
    $page = $this->element_page ( $element );
    if ( $page === false ) {
      $this->log ( "Page does not exist - skipping..." );
      return false;
    }
    if ( $this->is_editable ( $element, $page ) ) {
      $this->log ( "Editing page '" . $page->title . "'...", LL_INFO );
      if ( $this->modify_page ( $page, $element ) ) {
        return $this->bot->submit_page ( $page,
           $this->get_comment ( $element, $page ),
           $this->get_edit_minor ( $element ) );
      }
    }
    return false;
  }

}


# -----  Classic page actions  ----- #


class ActionObject_Page_Edit extends ActionObject_Page_Edit_Generic {

  public $replaces = array();

  protected $replacements_count;

  # -----  Protected  ----- #

  protected function register_action ( $replace, $count ) { return true; }

  protected function replace ( &$page, &$replace ) {
    if ( is_null ( $replace['name'] ) ) $replace['name'] = "Replacing " . $replace['regex'] . " with " . $replace['with'];
    if ( is_null ( $replace['limit'] ) ) $replace['limit'] = -1;

    if ( empty ( $replace['regex'] ) ) {
      $page->text .= $replace['with'];
      $this->replacements_count++;
    } else {
      $regex = $replace['regex'];
      $this->replacements_count += $page->replace ( $regex, $replace['with'], $replace['limit'] );
    }
    $this->register_action ( $replace, $this->replacements_count );
    if ( $count > 0 ) {
      $this->log ( $replace['name'] . ": " . $count, LL_DEBUG );
    }
  }

  protected function modify_page ( &$page, $element ) {
    $this->replacements_count = 0;

    foreach ( $this->replaces as $replace ) {
      $this->replace ( $page, $replace, $templates[$replace['type']] );
    }

    return parent::modify_page ( $page, $element );
  }

}


class ActionObject_Page_Undo extends ActionObject_Page_Generic {

  public $revert_revid;
  public $to_revid;

  protected function is_undoable ( $element, $title ) { return ( $title !== false ); }  // override to get page-specific undo

  protected function revert_revid ( $element, $title ) { return $this->revert_revid; } // override on need
  protected function to_revid     ( $element, $title ) { return $this->to_revid; } // override on need

  public function process ( $element ) {
    if ( $this->bot->can_i_edit() ) {
      $title = $this->element_pagetitle ( $element );
      if ( $this->is_undoable ( $element, $title ) ) {
        return $this->bot->undo_page ( $title,
          $this->revert_revid ( $element, $title ),
          $this->to_revid ( $element, $title ),
          $this->get_comment ( $element, $title )
        );
      }
    }
    return false;
  }

}


class ActionObject_Page_Move extends ActionObject_Page_Generic {

  public $noredirect = false;  // whether to not leave a redirect
  public $movetalk   = true ;  // whether to move the talk page, too

  public $move_over  = false;  // if a page with the new name already exist, whether to delete it

  protected function is_moveable ( $element, $title ) { return ( $title !== false ); }  // override to get page-specific deletion

  protected function element_newtitle ( $element, $title ) {
    if ( is_string ( $element ) ) {
      return $element;  // best override it - this will also be the old title!
    } elseif ( is_array ( $element ) ) {
      return $element['new_title'];
    } elseif ( is_object ( $element ) ) {
      return $element->new_title;
    }
    $this->log ( "Unsupported element type supplied by " . get_class ( $this->iterator ) . " to " . get_class ( $this ) . "!", LL_ERROR );
    return false;
  }

  protected function noredirect ( $element, $title ) { return $this->noredirect; }
  protected function movetalk   ( $element, $title ) { return $this->movetalk  ; }

  protected function free_new_name ( $new_title, $title, $element ) {
    $this->bot->delete_page ( $new_title, "Will move [[" . $title . "]] at its place" );
  }

  public function process ( $element ) {
    $title = $this->element_pagetitle ( $element );
    if ( $this->is_moveable ( $element, $title ) ) {
      $new_title = $this->element_newtitle ( $element, $title );
      if ( empty ( $new_title ) ) {
        return false;
      } else {
        if ( $this->move_over ) $this->free_new_name ( $new_title, $title, $element );
        return $this->bot->move_page ( $title, $new_title,
          $this->get_comment ( $element, $title ),
          $this->noredirect ( $element, $title ),
          $this->movetalk ( $element, $title ) );
      }
    }
    return false;
  }

}


class ActionObject_Page_Delete extends ActionObject_Page_Generic {

  protected function is_deleteable ( $element, $title ) { return ( $title !== false ); }  // override to get page-specific deletion

  public function process ( $element ) {
    $title = $this->element_pagetitle ( $element );
    if ( $this->is_deleteable ( $element, $title ) ) {
      return $this->bot->delete_page ( $title, $this->get_comment ( $element, $title ) );
    }
    return false;
  }

}


class ActionObject_Page_Undelete extends ActionObject_Page_Generic {  // MW 1.12+

  public $timestamps = array();  // list of the timestamps of revisions to be restored (if empty - all)

  public $drstart;  // used only if $timestamps is empty
  public $drend;    // used only if $timestamps is empty
  public $druser;   // used only if $timestamps is empty

  protected function is_undeleteable ( $element, $title ) { return ( $title !== false ); }  // override to get per-page undeleting

  protected function get_timestamps ( $title ) {
    if ( empty ( $this->timestamps ) ) {
      $timestamps = array();
      $Iter = new Iterator_DeletedRevs ( $this->bot );
      $Iter->titles[] = $title;
      $Iter->start    = $this->drstart;
      $Iter->end      = $this->drend;
      $AO = new ActionObject_MakeArray ( $this->bot );
      $Iter->iterate ( $AO );
      foreach ( $AO->elements as $revision ) {
        if ( is_null ( $this->druser ) ||
             ( is_string ( $this->druser ) && ( $revision->user === $this->druser ) ) ||
             ( is_array ( $this->druser ) && in_array ( $revision->user, $this->druser ) )
           ) {
          $timestamps[] = $revision->timestamp;
        }
      }
      return $timestamps;
    } else {
      return $this->timestamps;
    }
  }

  public function process ( $element ) {
    $title = $this->element_pagetitle ( $element );
    if ( $this->is_undeleteable ( $element, $title ) ) {
      return $this->bot->undelete_page ( $title,
        $this->get_comment ( $element, $title ),
        $this->get_timestamps ( $title ) );
    }
    return false;
  }

}


class ActionObject_Page_Rollback extends ActionObject_Page_Generic {

  public $user;

  protected function is_rollbackable ( $element, $title ) { return ( $title !== false ); }  // override to get page-specific rollback

  protected function get_user ( $element, $title ) { return $this->user; }

  public function process ( $element ) {
    $title = $this->element_pagetitle ( $element );
    if ( $this->is_rollbackable ( $element, $title ) ) {
      if ( $this->bot->can_i_rollback() ) {
        return $this->bot->rollback_page ( $title,
          $this->get_user ( $element, $title ),
          $this->get_comment ( $element, $title ) );
      } else {
        $this->log ( "Cannot rollback here!", LL_ERROR );
        // possibly try RevertRevisions, if $this->bot->can_i_edit()?
      }
    }
    return false;
  }

}


class ActionObject_Page_Protect extends ActionObject_Page_Generic {

  public $edit     = 'sysop';
  public $move     = 'sysop';
  public $rollback = 'sysop';
  public $delete   = 'sysop';
  public $restore  = 'sysop';

  public $expiry   = NULL;
  public $cascade  = false;

  protected function is_protectable ( $element, $title ) { return ( $title !== false ); }  // override to get page-specific protection

  protected function get_protections ( $element, $title ) {
    return array (
      'edit'     => $this->edit,
      'move'     => $this->move,
      'rollback' => $this->rollback,
      'delete'   => $this->delete,
      'restore'  => $this->restore,
    );
  }

  protected function get_expiry  ( $element, $title ) { return $this->expiry ; }
  protected function get_cascase ( $element, $title ) { return $this->cascade; }

  public function process ( $element ) {  // override to set per-element protections, expiry, comment and/or cascade
    $title = $this->element_pagetitle ( $element );
    if ( $this->is_protectable ( $element, $title ) ) {
      return $this->bot->protect_page ( $title,
        $this->get_protections ( $element, $title ),
        $this->get_expiry ( $element, $title ),
        $this->get_comment ( $element, $title ),
        $this->get_cascade ( $element, $title ) );
    }
    return false;
  }

  public function set_unprotect () {  // call after creating to turn the object in common unprotection mode
    $this->edit     = 'all';
    $this->move     = 'autoconfirmed';
    $this->rollback = 'autoconfirmed';
    $this->delete   = 'sysop';
    $this->restore  = 'autoconfirmed';
  }

}


class ActionObject_Page_Watch extends ActionObject_Page_Generic {

  public $watch_on = true;

  protected function is_watchable ( $element, $title ) { return ( $title !== false ); } // override to get page-specific watching

  protected function get_watch_on ( $element, $title ) { return $this->watch_on; }

  public function process ( $element ) {
    $title = $this->element_pagetitle ( $element );
    if ( $this->is_watchable ( $element, $title ) ) {
      return $this->bot->watch_page ( $title, $this->get_watch_on ( $element, $title ) );
    }
    return false;
  }

}


class ActionObject_Page_Import extends ActionObject_Page_Generic {  // MW 1.15+

  public $source;               // interwiki to import the page from
  public $fullhistory = false;  // import all page revisions, not just the current one
  public $namespace;            // import into this namespace instead of in the original page namespace
  public $templates   = false;  // import also all templates included in the page

  protected function is_importable ( $element, $title ) { return ( $title !== false ); } // override to get page-specific import

  protected function get_source      ( $element, $title ) { return $this->source     ; }
  protected function get_fullhistory ( $element, $title ) { return $this->fullhistory; }
  protected function get_namespace   ( $element, $title ) { return $this->namespace  ; }
  protected function get_templates   ( $element, $title ) { return $this->templates  ; }

  public function process ( $element ) {
    $title = $this->element_pagetitle ( $element );
    if ( $this->is_importable ( $element ) ) {
      return $this->bot->import_page ( $title,
        $this->get_source ( $element, $title ),
        $this->get_fullhistory ( $element, $title ),
        $this->get_namespace ( $element, $title ),
        $this->get_templates ( $element, $title ) );
    }
    return false;
  }

}


class ActionObject_Page_PurgeCache extends ActionObject_Page_Generic {

  protected function is_purgeable ( $element, $title ) { return ( $title !== false ); }

  public function process ( $element ) {
    $title = $this->element_pagetitle ( $element );
    if ( $this->is_purgeable ( $element, $title ) ) {
      return $this->bot->purge_page_cache ( $title );
    }
    return false;
  }

}


# ----------  Text and page preprocessing ActionObject classes  ---------- #

class ActionObject_ExpandTemplates extends ActionObject_Page_Generic {

  protected function is_expandable ( $element ) { return true; }

  protected function postprocess_text ( $text, $element ) {
    echo $text . "\n" . str_repeat ( '-', 80 ) . "\n";  // override to get something more useful
  }

  public function process ( $element ) {
    if ( $this->is_expandable ( $element ) ) {
      $text = $this->bot->expand_templates ( $this->element_text ( $element ),
        $this->element_pagetitle ( $element ) );
      if ( $text === false ) {
        return false;
      } else {
        return $this->postprocess_text ( $text, $element );
      }
    }
  }

}


class ActionObject_ParseText extends ActionObject_Page_Generic {

  public $properties;
  public $pst = true;
  public $uselang;

  protected function is_parseable ( $element ) { return true; }

  protected function get_properties ( $element ) { return $this->properties; }
  protected function get_pst ( $element ) { return $this->pst; }
  protected function get_uselang ( $element ) { return $this->uselang; }

  protected function postprocess_data ( $data, $element ) {
    print_r ( $data );  // override to get something more useful
  }

  public function process ( $element ) {
    if ( $this->is_parseable ( $element ) ) {
      $data = $this->bot->parse_text ( $this->element_text ( $element ),
        $this->element_pagetitle ( $element ), $this->get_properties ( $element ),
        $this->get_pst ( $element ), $this->get_uselang ( $element ) );
      if ( $data === false ) {
        return false;
      } else {
        return $this->postprocess_data ( $data, $element );
      }
    }
  }

}


class ActionObject_ParsePage extends ActionObject_Page_Generic {

  public $properties;
  public $uselang;

  protected function is_parseable ( $element ) { return true; }

  protected function get_properties ( $element ) { return $this->properties; }
  protected function get_uselang ( $element ) { return $this->uselang; }

  protected function postprocess_data ( $data ) {
    print_r ( $data );  // override to get something more useful
  }

  public function process ( $element ) {
    if ( $this->is_parseable ( $element ) ) {
      $data = $this->bot->parse_page ( $this->element_pagetitle ( $element ),
        $this->get_properties ( $element ), $this->get_uselang ( $element ) );
      if ( $data === false ) {
        return false;
      } else {
        return $this->postprocess_data ( $data );
      }
    }
  }

}


# ---------- Misc additional page-related ActionObject classes ---------- #


class ActionObject_MakeArray_PageTitles extends ActionObject_Page_Generic {

  public $unique = false;

  public $elements = array();

  public function process ( $element ) {
    $title = $this->element_pagetitle ( $element );
    if ( ! ( $this->unique && in_array ( $title, $this->elements ) ) ) {
      $this->elements[] = $title;
      return true;
    } else {
      return false;
    }
  }

}



# ---------------------------------------------------------------------------- #
# --                 Standard User ActionObject classes                     -- #
# ---------------------------------------------------------------------------- #

# ----------  Abstract  ---------- #

abstract class ActionObject_User_Generic extends ActionObject_WithComment {

  protected function element_username ( $element ) {
    if ( is_string ( $element ) ) {
      return $element;
    } elseif ( is_array ( $element ) ) {
      return $element['user'];
    } elseif ( is_object ( $element ) ) {
      if ( $element instanceof User ) {
        return $element->name;
      } elseif ( $element instanceof UserContrib ) {  // and thus also of RecentChange
        return $element->user;
      } elseif ( $element instanceof Block ) {
        return $element->user;
      } elseif ( $element instanceof LogEvent ) {
        return $element->user;
      } elseif ( $element instanceof Image ) {
        return $element->user;
      } elseif ( $element instanceof ProtectedTitle ) {
        return $element->user;
      } elseif ( $element instanceof Page_FromWatchlist ) {
        return $element->user;
      } elseif ( $element instanceof Page_Revision ) {
        return $element->user;
      } elseif ( $element instanceof Page_ImageInfo ) {
        return $element->user;
      } elseif ( $element instanceof Page_DuplicateFile ) {
        return $element->user;
      }
    }
    $this->log ( "Unsupported element type supplied by " . get_class ( $this->iterator ) . " to " . get_class ( $this ) . "!", LL_ERROR );
    return false;
  }

}


# ----------  Non-abstract  ---------- #

class ActionObject_User_Block extends ActionObject_User_Generic {

  public $expiry    = 'never';  // expiry timestamp, or stuff like '5 months', '2 weeks' etc.
  public $anononly  = false;
  public $nocreate  = false;
  public $autoblock = false;
  public $noemail   = false;

  protected function is_blockable ( $element, $username ) { return ( $username !== false ); }  // override to get per-user blocking

  protected function get_expiry    ( $element, $username ) { return $this->expiry   ; }
  protected function get_anononly  ( $element, $username ) { return $this->anononly ; }
  protected function get_nocreate  ( $element, $username ) { return $this->nocreate ; }
  protected function get_autoblock ( $element, $username ) { return $this->autoblock; }
  protected function get_noemail   ( $element, $username ) { return $this->noemail  ; }

  public function process ( $element ) {
    $username = $this->element_username ( $element );
    if ( $this->is_blockable ( $element, $username ) && $this->bot->can_i_block() ) {
      return $this->bot->block_user ( $username,
        $this->get_expiry    ( $element, $username ),
        $this->get_comment   ( $element, $username ),
        $this->get_anononly  ( $element, $username ),
        $this->get_nocreate  ( $element, $username ),
        $this->get_autoblock ( $element, $username ),
        $this->get_noemail   ( $element, $username ) );
    }
    return false;
  }

}


class ActionObject_User_Unblock extends ActionObject_User_Generic {

  public $block_id;

  protected function is_unblockable ( $element, $username ) { return ( $username !== false ); }  // override to get per-user unblocking

  protected function get_block_id ( $element, $username ) { return $this->block_id; }

  public function process ( $element ) {
    $username = $this->element_username ( $element );
    if ( $this->is_unblockable ( $element, $username ) ) {
      return $this->bot->unblock_user ( $username,
        $this->get_block_id ( $element, $username ),
        $this->get_comment ( $element, $username ) );
    }
    return false;
  }

}


class ActionObject_User_ModifyGroups extends ActionObject_User_Generic {  // MW 1.16+

  public $addto_groups      = array();
  public $removefrom_groups = array();

  protected function is_modifiable ( $element, $username ) { return ( $username !== false ); }  // override to get per-user groups modification

  protected function get_addto_groups      ( $element, $username ) { return $this->addto_groups     ; }
  protected function get_removefrom_groups ( $element, $username ) { return $this->removefrom_groups; }

  public function process ( $element ) {
    $username = $this->element_username ( $element );
    if ( $this->is_modifiable ( $element, $username ) ) {
      return $this->bot->change_userrights ( $username,
        $this->get_addto_groups ( $element, $username ),
        $this->get_removefrom_groups ( $element, $username ),
        $this->get_comment ( $element, $username ) );
    }
    return false;
  }

}


class ActionObject_User_Email extends ActionObject_User_Generic {

  public $subject;
  public $text;
  public $cc_me = false;

  protected function is_emailable ( $element, $username ) { return ( $username !== false ); } // override to get per-user emailing

  protected function get_subject ( $element, $username ) { return $this->subject; }
  protected function get_text    ( $element, $username ) { return $this->text   ; }
  protected function get_cc_me   ( $element, $username ) { return $this->cc_me  ; }

  public function process ( $element ) {
    $username = $this->element_username ( $element );
    if ( $this->is_emailable ( $element, $username ) ) {
      return $this->bot->email_user ( $username,
        $this->get_subject ( $element, $username ),
        $this->get_text    ( $element, $username ),
        $this->get_cc_me   ( $element, $username ) );
    }
    return false;
  }

}


# ---------------------------------------------------------------------------- #
# --             Standard RecentChange ActionObject classes                 -- #
# ---------------------------------------------------------------------------- #

# ----------  Abstract  ---------- #

abstract class ActionObject_RecentChange_Generic extends ActionObject {

  protected function element_rcid ( $element ) {
    if ( is_numeric ( $element ) ) {
      return $element;
    } elseif ( is_array ( $element ) ) {
      return $element['rcid'];
    } elseif ( is_object ( $element ) ) {
      if ( $element instanceof RecentChange ) {
        return $element->rcid;
      } elseif ( $element instanceof Page_FromWatchList ) {
        return $element->rcid;
      }  // could also fetch RCs by page revisions, different log events etc. (needs fetch_*() functions!)
    }
    $this->log ( "Unsupported element type supplied by " . get_class ( $this->iterator ) . " to " . get_class ( $this ) . "!", LL_ERROR );
    return false;
  }

}

# ----------  Non-abstract  ---------- #

class ActionObject_RecentChange_Patrol extends ActionObject {

  protected function is_patrollable ( $element, $rcid ) { return ( $rcid !== false ); }  // override to get per-recentchange patrolling

  public function process ( $element ) {
    if ( $this->bot->can_i_autopatrol() ) {
      $rcid = $this->element_rcid ( $element );
      if ( $this->is_patrollable ( $element, $rcid ) ) {
        return $this->bot->patrol_recentchange ( $rcid );
      }
    }
    return false;
  }

}



# ---------------------------------------------------------------------------- #
# --            Standard FileProcessing ActionObject classes                -- #
# ---------------------------------------------------------------------------- #


class ActionObject_Upload_File extends ActionObject_WithComment {

  public $watch          = false;
  public $ignorewarnings = true;

  protected function element_filename ( $element ) {
    if ( is_array ( $element ) ) {
      if ( ! empty ( $element['filename'] ) ) {
        return $element['filename'];
      } elseif ( ! empty ( $element['file'] ) ) {
        return $element['file'];
      }
    } elseif ( is_string ( $element ) ) {
      return $element;
    } 
    $this->log ( "Unsupported element type supplied by " . get_class ( $this->iterator ) . " to " . get_class ( $this ) . "!", LL_ERROR );
    return false;
  }

  protected function is_uploadable ( $element, $filename ) { return ( $filename !== false ); }

  protected function get_text ( $element, $filename ) { return NULL; }
  protected function get_target_filename ( $element, $filename ) { return NULL; }
  protected function get_watch ( $element, $filename ) { return $this->watch; }
  protected function get_ignorewarnings ( $element, $filename ) { return $this->ignorewarnings; }

  public function process ( $element ) {
    $filename = $this->element_filename ( $element );
    if ( $this->is_uploadable ( $element, $filename ) ) {
      if ( file_exists ( $filename ) ) {
        return $this->upload_file ( $filename,
          $this->get_text ( $element, $filename ),
          $this->get_comment ( $element, $filename ),
          $this->get_target_filename ( $element, $filename ),
          $this->get_watch ( $element, $filename ),
          $this->get_ignorewarnings ( $element, $filename ) );
      }
    }
    return false;
  }

}


class ActionObject_Upload_URL extends ActionObject_WithComment {

  public $watch          = false;
  public $ignorewarnings = true;

  protected function element_url ( $element ) {
    if ( is_array ( $element ) ) {
      if ( ! empty ( $element['URL'] ) ) {
        return $element['URL'];
      }
    } elseif ( is_string ( $element ) ) {
      return $element;
    } 
    $this->log ( "Unsupported element type supplied by " . get_class ( $this->iterator ) . " to " . get_class ( $this ) . "!", LL_ERROR );
    return false;
  }

  protected function is_uploadable ( $element, $URL ) { return ( $URL !== false ); }

  protected function get_text ( $element, $URL ) { return NULL; }
  protected function get_target_filename ( $element, $URL ) { return NULL; }
  protected function get_watch ( $element, $URL ) { return $this->watch; }
  protected function get_ignorewarnings ( $element, $URL ) { return $this->ignorewarnings; }

  public function process ( $element ) {
    $URL = $this->element_filename ( $element );
    if ( $this->is_uploadable ( $element, $URL ) ) {
      return $this->upload_url ( $URL, $this->get_text ( $element, $URL ),
        $this->get_comment ( $element, $URL ),
        $this->get_target_filename ( $element, $URL ),
        $this->get_watch ( $element, $URL ),
        $this->get_ignorewarnings ( $element, $URL ) );
    }
    return false;
  }

}



# ---------------------------------------------------------------------------- #
# --                                                                        -- #
# --                   Unofficial ActionObject classes                      -- #
# --                                                                        -- #
# -- Classes that make easier specific tasks. Immature and/or too specific. -- #
# -- May be undocumented in the Apibot wiki.                                -- #
# -- May be made official, heavily modified or dropped in the future.       -- #
# -- Use and rely on them at your own risk.                                 -- #
# --                                                                        -- #
# ---------------------------------------------------------------------------- #


# ---------------------------------------------------------------------------- #
# --                 Extended Page ActionObject classes                     -- #
# ---------------------------------------------------------------------------- #


# ----- Selective revisions revert system ----- #

abstract class ActionObject_Page_Revert_Generic extends ActionObject_Page_Generic {

  public $undo_revert = true;  // use the 'undo' method to revert revisions where possible

  public $page_deletion_marker;  // will insert this into page text if can't delete pages

  protected $reverted_revs_count;  // must be set by evaluate_revert_revisions()
  protected $revert_revision;      // revert this revision
  protected $to_revision;          // ... and back to (but not including) this revision (if NULL - delete the page)

  # ----- Protected ----- #

  protected function is_revertable ( $element, $page ) { return ( $page !== false ); }

  protected function revert_page ( $page ) {
    $summary = $this->summary_reverted ( $page );

    if ( ! $this->bot->can_i_edit() ) {
      $this->log ( "Would revert page '" . $page->title . "' (" . $summary .
        ") to revid " . $to_revision->revid . ", but cannot edit the wiki!" );
      return false;
    }

    $this->log ( "Reverting page '" . $page->title . "' to revision " . $this->to_revision->revid .
      ", obsoleting up to revision " . $this->revert_revision->revid );
    if ( $this->undo_revert && ( $this->bot->mw_version_number() >= 11303 ) ) {
      return $this->bot->undo_page ( $page, $this->revert_revision->revid, $this->to_revision->revid, $summary );
    } else {
      $page->text        = $this->to_revision->content;
      $page->rvtimestamp = $this->to_revision->timestamp;
      $page->timestamp   = $this->revert_revision->timestamp;

      return $this->bot->submit_page ( $page, $summary, false );
    }
  }

  protected function delete_page ( $page ) {
    $summary = $this->summary_deleted ( $page );
    if ( $this->bot->can_i_delete() ) {
      $this->log ( "Deleting page '" . $page->title . "' (" . $summary . ")" );
      return $this->bot->delete_page ( $page->title, $summary );
    } elseif ( $this->bot->can_i_edit() && ! empty ( $this->page_deletion_marker ) ) {
      $this->log ( "Marking page '" . $page->title . "' for deletion (" . $summary . ")" );
      $page->text        = $this->page_deletion_marker . $this->revert_revision->content;
      $page->rvtimestamp = $this->revert_revision->timestamp;
      return $this->bot->submit_page ( $page, "Marking page for deletion (" . $summary . ")" );
    } else {
      $this->log ( "Would delete page '" . $page->title . "' (" . $summary .
        "), but can neither delete nor mark it for deletion!" );
      return false;
    }

  }

  protected function skip_page ( $page, $reason_template = NULL ) {
    if ( ! is_null ( $reason_template ) ) {
      $this->log ( str_replace ( '$1', $page->title, $reason_template ) );
    }
    return false;
  }

  abstract protected function evaluate_revert_revisions ( $element, $page );

  abstract protected function summary_reverted ( $page );
  abstract protected function summary_deleted  ( $page );

  public function process ( $element ) {
    $page = $this->element_page ( $element );
    if ( $this->is_revertable ( $element, $page ) ) {

      $this->evaluate_revert_revisions ( $element, $page );
      if ( is_null ( $this->to_revision ) ) {
        if ( $this->reverted_revs_count == 0 ) {
          return $this->skip_page ( $page, "Processed 0 revisions for page '$1' - was it deleted meanwhile?" );
        } else {
          return $this->delete_page ( $page );
        }
      } else {
        if ( $this->reverted_revs_count == 0 ) {
          return $this->skip_page ( $page, "Page '$1' does not need to be reverted." );
        } else {
          return $this->revert_page ( $page );
        }
      }
    } else {
      return $this->skip_page ( $page, "Page '$1' was skipped - marked as non-revertable" );
    }
  }

}


abstract class ActionObject_Page_Revert extends ActionObject_Page_Revert_Generic {

  public $report_only = false;

  # -----  Protected  ----- #

  protected function evaluate_revert_revisions ( $element, $page ) {
    $internal_iterator     = $this->create_iterator     ( $element, $page );
    $internal_actionobject = $this->create_actionobject ( $this->bot, $element, $page );
    $internal_iterator->iterate ( $internal_actionobject );
    $this->revert_revision = $internal_actionobject->last_revision;
    if ( $internal_iterator->abort_iteration ) {
      $this->reverted_revs_count = $internal_iterator->elements_counter - 1;
      $this->to_revision         = $internal_actionobject->to_revision;
    } else {
      $this->reverted_revs_count = $internal_iterator->elements_counter;
      $this->to_revision         = NULL;
    }
  }

  protected function register_action ( $page, $action_taken ) { return true; } // override to eg. make lists of what is done.

  protected function create_iterator ( $element, $page ) {
    $internal_iterator = new Iterator_PageRevisions_WithDiffs ( $this->bot );
    $internal_iterator->content = true;
    $internal_iterator->title   = $page->title;
    return $internal_iterator;
  }

  abstract protected function create_actionobject ( $bot, $element, $page );
/*   the internal ActionObject must have:
   public $to_revision;   // set here the revision the page must be reverted to, or NULL if it is to be deleted
   public $last_revision; // set here the last page revision
 */

  protected function revert_page ( $page ) {
    $this->register_action ( $page, "revert" );
    if ( $this->report_only ) {
      $this->log ( "Would revert page '" . $page->title . "' to revid " .
        $this->to_revision->revid . " (" . $this->summary_reverted ( $page ) . ")" );
      return true;
    } else {
      return parent::revert_page ( $page );
    }
  }

  protected function delete_page ( $page ) {
    $summary = $this->summary_deleted ( $page );
    $this->register_action ( $page, "delete" );
    if ( $this->report_only ) {
      if ( $this->bot->can_i_delete() ) {
        $this->log ( "Would delete page '" . $page->title . "' (" . $summary . ")" );
        return true;
      } elseif ( $this->bot->can_i_edit() && ! empty ( $this->page_deletion_marker ) ) {
        $this->log ( "Would mark page '" . $page->title . "' for deletion (" . $summary . ")" );
        return true;
      } else {
        $this->log ( "Page '" . $page->title . "' should be deleted (" . $summary .
          "), but can neither delete nor mark it for deletion!" );
        return false;
      }
    } else {
      return parent::delete_page ( $page );
    }
  }

  protected function skip_page ( $page, $reason_template = NULL ) {
    $this->register_action ( $page, "skip" );
    return parent::skip_page ( $page, $reason_template );
  }

}


abstract class ActionObject_Page_RevertWithStats_Generic extends ActionObject_Page_Revert {

  public $stats = array (
    'processed_pages'    => 0,
    'reverted_pages'     => 0,
    'deleted_pages'      => 0,
    'skipped_pages'      => 0,
    'rejected_revisions' => 0,
    'accepted_revisions' => 0,
  );

  protected function revert_page ( $page ) {
    $this->stats['processed_pages'   ] = $this->stats['processed_pages'   ] + 1;
    $this->stats['reverted_pages'    ] = $this->stats['reverted_pages'    ] + 1;
    $this->stats['accepted_revisions'] = $this->stats['accepted_revisions'] + 1;
    $this->stats['rejected_revisions'] = $this->stats['rejected_revisions'] + $this->reverted_revs_count;
    return parent::revert_page ( $page );
  }

  protected function delete_page ( $page ) {
    $this->stats['processed_pages'   ] = $this->stats['processed_pages'   ] + 1;
    $this->stats['deleted_pages'     ] = $this->stats['deleted_pages'     ] + 1;
    $this->stats['rejected_revisions'] = $this->stats['rejected_revisions'] + $this->reverted_revs_count;
    return parent::delete_page ( $page );
  }

  protected function skip_page ( $page, $reason_template = NULL ) {
    $this->stats['processed_pages'] = $this->stats['processed_pages'] + 1;
    $this->stats['skipped_pages'  ] = $this->stats['skipped_pages'  ] + 1;
    return parent::skip_page ( $page, $reason_template );
  }

}


# This is an actionobject used internally by ActionObject_Page_RevertRevisions.
# Do not use it with your iterators, unless you really know what you are doing.
class InternalActionObject_Page_IsRevisionToBeReverted extends ActionObject {

  public $user_regex;
  public $not_user_regex;

  public $comment_regex;
  public $not_comment_regex;

  public $content_regex;
  public $not_content_regex;

  public $revid_min;
  public $revid_max;

  public $timestamp_min;
  public $timestamp_max;

  public $bytes_min;
  public $bytes_max;

  public $chars_min;
  public $chars_max;

  public $is_minor;  // true, false, NULL

  public $last_revision;
  public $to_revision;

  # ----- Protected ----- #

  protected function match_regex ( $regex, $element ) {
    if ( is_null ( $regex ) ) { return false; }
    if ( is_array ( $regex ) ) {
      foreach ( $regex as $regex_element ) {
        if ( $this->match_regex ( $regex_element, $element ) ) { return true; }
      }
    }
    return preg_match ( $regex, $element );
  }

  protected function match_posneg_regex ( $pos_regex, $neg_regex, $element ) {
    return ( $this->match_regex ( $pos_regex, $element ) &&
      ! $this->match_regex ( $neg_regex, $element ) );
  }

  protected function match_diap ( $min, $max, $element ) {
    if ( ( $min === NULL ) && ( $max === NULL ) ) { return false; }
    if ( ( $min === NULL ) && ( $max !== NULL ) ) { $min = 0; }
    if ( ( $min !== NULL ) && ( $max === NULL ) ) { $max = PHP_INT_MAX; }
    if ( $min < $max ) {
      return ( ( $min <= $element ) && ( $element <= $max ) );
    } else {
      return ( ( $max <= $element ) && ( $element <= $min ) );
    }
  }

  protected function match_bool ( $test, $element ) {
    return ( ( $test !== NULL ) && ( $test === $element ) );
  }

  protected function is_to_be_reverted ( $revision ) {
    return (
      $this->match_posneg_regex ( $this->user_regex   , $this->not_user_regex   , $revision->user ) ||
      $this->match_posneg_regex ( $this->comment_regex, $this->not_comment_regex, $revision->comment ) ||
      $this->match_posneg_regex ( $this->content_regex, $this->not_content_regex, $revision->content ) ||
      $this->match_diap ( $this->revid_min    , $this->revid_max    , $revision->revid     ) ||
      $this->match_diap ( $this->timestamp_min, $this->timestamp_max, $revision->timestamp ) ||
      $this->match_diap ( $this->bytes_min    , $this->bytes_max    , $revision->size      ) ||
      $this->match_diap ( $this->chars_min    , $this->chars_max    , mb_strlen ( $revision->content ) ) ||
      $this->match_bool ( $this->is_minor, $revision->is_minor )
    );
  }

  public function process ( $revision ) {
    if ( is_null ( $this->last_revision ) ) { $this->last_revision = $revision; }

    if ( ! $this->is_to_be_reverted ( $revision ) ) {
      $this->to_revision = $revision;
      $this->iterator->abort_iteration = true;
    }

    return true;
  }

}


class ActionObject_Page_RevertRevisions extends ActionObject_Page_Revert {

  public $user_regex;     // should match the user(s) whose revisions must be reverted
  public $not_user_regex; // should match the user(s) whose revisions must NOT be reverted

  public $comment_regex;
  public $not_comment_regex;

  public $content_regex;
  public $not_content_regex;

  public $revid_min;
  public $revid_max;

  public $timestamp_min;
  public $timestamp_max;

  public $bytes_min;
  public $bytes_max;

  public $chars_min;
  public $chars_max;

  public $is_minor;  // true, false, NULL (no check)

  protected function summary_reverted ( $page ) {
    return "Reverted revisions after " . $this->to_revision->revid .
      ", up to " . $this->revert_revision->revid;
  }

  protected function summary_deleted  ( $page ) {
    "Reverted all revisions - nothing left; deleting page";
  }

  protected function create_actionobject ( $bot, $element, $page ) {
    $AO = new InternalActionObject_Page_IsRevisionToBeReverted ( $bot );

    $AO->user_regex        = $this->user_regex;
    $AO->not_user_regex    = $this->not_user_regex;
    $AO->comment_regex     = $this->comment_regex;
    $AO->not_comment_regex = $this->not_comment_regex;
    $AO->content_regex     = $this->content_regex;
    $AO->not_content_regex = $this->not_content_regex;
    $AO->revid_min         = $this->revid_min;
    $AO->revid_max         = $this->revid_max;
    $AO->timestamp_min     = $this->timestamp_min;
    $AO->timestamp_max     = $this->timestamp_max;
    $AO->bytes_min         = $this->bytes_min;
    $AO->bytes_max         = $this->bytes_max;
    $AO->chars_min         = $this->chars_min;
    $AO->chars_max         = $this->chars_max;
    $AO->is_minor          = $this->is_minor;

    return $AO;
  }

}


# ----- Category-related page editing ----- #


class ActionObject_Page_ReplaceCategory extends ActionObject_Page_Edit_Generic {

  public $old_category;
  public $new_category;
  public $new_sortkey;

  protected function old_category_name ( $element ) { return $this->old_category; }
  protected function new_category_name ( $element ) { return $this->new_category; }
  protected function new_sortkey ( $element ) { return $this->new_sortkey; }

  protected function modify_page ( &$page, $element ) {
    return $page->replace_category ( $this->old_category_name ( $element ),
      $this->new_category_name ( $element ), $this->new_sortkey ( $element ) );
  }

}


class ActionObject_RecategorizeCategoryMembers extends ActionObject_Page_Generic {

  public $new_category_name;

  public $namespace;  // only recategorize members in this namespace
  public $startsortkey;  // only recategorize members between these sortkeys (only sortkey or timestamp-based selection is allowed, but not both!)
  public $endsortkey;
  public $starttimestamp;  // only recategorize members between these timestamps;
  public $endtimestamp;

  protected function new_category_name ( $element, $old_category ) { return $this->new_category_name; }  // override on need
  protected function new_sortkey ( $element, $old_category ) { return $this->new_sortkey; }

  public function process ( $element ) {
    $old_category = $this->element_pagetitle ( $element );
    if ( $this->bot->title_namespace_id ( $old_category ) !== NAMESPACE_ID_CATEGORY ) {
      $this->bot->log ( "[[" . $old_category . "]] appears to not be a category!", LL_ERROR );
      return false;
    }

    $Iter = new Iterator_CategoryMembers ( $this->bot );
    $Iter->title       = $old_category;
    $Iter->namespace   = $this->namespace;
    if ( ! ( empty ( $this->startsortkey ) && empty ( $this->endsortkey ) ) ) {
      $Iter->sort = "sortkey";
      $Iter->startsortkey = $this->startsortkey;
      $Iter->endsortkey   = $this->endsortkey;
    } elseif ( ! ( empty ( $this->starttimestamp ) && empty ( $this->endtimestamp ) ) ) {
      $Iter->sort = "timestamp";
      $Iter->starttimestamp = $this->starttimestamp;
      $Iter->endtimestamp   = $this->endtimestamp;
    }

    $AO = new ActionObject_Page_ReplaceCategory ( $this->bot );
    $AO->comment = $this->comment;
    $AO->old_category_name = $this->bot->title_pagename ( $old_category );
    $AO->new_category_name = $this->new_category_name ( $element, $old_category );

    $Iter->iterate ( $AO );

    return true;
  }

}


class ActionObject_Category_Rename extends ActionObject_Page_Move {

  public $recategorize_comment;  // will be passed to the recategorizing AO

  public function process ( $element ) {
    $title = $this->element_pagetitle ( $element );
    if ( $this->bot->title_namespace_id ( $title ) !== NAMESPACE_ID_CATEGORY ) {
      $this->bot->log ( "[[" . $title . "]] appears to not be a category!", LL_ERROR );
      return false;
    }

    if ( parent::process ( $element ) ) {
      $AO = new ActionObject_RecategorizeCategoryMembers ( $this->bot );
      $AO->comment           = $this->recategorize_comment;
      $AO->new_category_name = $this->bot->title_pagename ( $this->element_newtitle ( $element, $title ) );

      $AO->process ( $element );

      return true;
    } else {
      return false;
    }
  }

}


class ActionObject_Category_JoinTo extends ActionObject_Page_Delete {  // deletes the source category and transfers all members to the target category 

  public $recategorize_comment;  // will be passed to the recategorizing AO

  public function process ( $element ) {
    $title = $this->element_pagetitle ( $element );
    if ( $this->bot->title_namespace_id ( $title ) !== NAMESPACE_ID_CATEGORY ) {
      $this->bot->log ( "[[" . $title . "]] appears to not be a category!", LL_ERROR );
      return false;
    }

    if ( parent::process ( $element ) ) {
      $AO = new ActionObject_RecategorizeCategoryMembers ( $this->bot );
      $AO->comment           = $this->recategorize_comment;
      $AO->new_category_name = $this->bot->title_pagename ( $this->element_newtitle ( $element, $title ) );

      $AO->process ( $element );

      return true;
    } else {
      return false;
    }
  }

}


# ----- Wikilink-related page editing ----- #


class ActionObject_Page_RelinkWikilinks extends ActionObject_Page_Edit {

  public $relink_from_title;
  public $relink_to_title;
  public $preserve_anchors = true;

  protected function relink_from_title ( $element ) { return $this->relink_from_title; }
  protected function relink_to_title   ( $element ) { return $this->relink_to_title; }

  public function process ( $element ) {
    $relink_from_title = $this->relink_from_title();
    $relink_to_title   = $this->relink_to_title();
    $this->replaces = array (
      array (
        'type'  => "text",
        'name'  => "Relinking title [[" . $relink_from_title . "]] to [[" . $relink_to_title . "]]...",
        'regex' => $this->bot->regexmatch_wikilink ( false, "", "", $relink_from_title, NULL, NULL ),
        'with'  => '[[$1$3$5' . $relink_to_title . ( $this->preserve_anchors ? '$7' : "" ) . '$9]]',
      ),
    );
    return parent::process ( $element );
  }
}


class ActionObject_RelinkAllWikilinksToPage extends ActionObject_Page_Generic {

  public $new_title;
  public $preserve_anchors = true;

  public $namespace;    // work in this namespace only (NULL - all, "" - main, etc)
  public $filterredir;  // "all" (default), "redirects" (only in redirects), "non-redirects" (only in non-redirects)

  protected function new_title ( $element, $title ) { return $this->new_title; }  // override on need

  public function process ( $element ) {
    $title = $this->element_pagetitle ( $element );
    $new_title = $this->new_title ( $element, $title );

    $Iter = new Iterator_BackLinks ( $this->bot );
    $Iter->title       = $title;
    $Iter->namespace   = $this->namespace;
    $Iter->filterredir = $this->filterredir;

    $AO = new ActionObject_Page_RelinkWikilinks ( $this->bot );
    $AO->relink_title     = $title;
    $AO->relink_to_title  = $new_title;
    $AO->preserve_anchors = $this->preserve_anchors;

    $Iter->iterate ( $AO );

    return parent::process ( $element );
  }

}


class ActionObject_Page_WikilinkText extends ActionObject_Page_Edit {

  public $link_target;
  public $link_section;
  public $link_text;
  public $links_count;

  protected function link_target  ( $element, $title ) { return $this->link_target; }
  protected function link_section ( $element, $title ) { return $this->link_section; }
  protected function link_text    ( $element, $title ) { return $this->link_text; }
  protected function links_count  ( $element, $title ) { return $this->links_count; }

  public function process ( $element ) {
    if ( $this->wikilinkable ( $element ) ) {
      $title = $this->element_pagetitle ( $element );
      $link_text    = $this->link_text    ( $element, $title );
      $link_section = $this->link_section ( $element, $title );
      $link_target  = $this->link_target  ( $element, $title );
      $links_count  = $this->links_count  ( $element, $title );

      if ( empty ( $link_text ) ) {
        $replace_name = "Wikilinking \"" . $link_text . "\"...";
        $link_text = $link_target;
      } else {
        $replace_name = "Wikilinking \"" . $link_text . "\" to [[" . $link_target . "]]...";
      }

      if ( ! empty ( $link_section ) ) $link_target .= "#" . $link_section;

      $this->replaces = array (
        array (
          'type'  => "text",
          'name'  => $replace_name,
          'regex' => '/' . preg_quote ( $link_text ) . '/u',
          'with'  => wikilink ( $link_target, $link_text ),
          'count' => $links_count,
        ),
      );
      return parent::process ( $element );
    }
  }

}


# ----- Moving / deleting pages and fixing wikilinks that point to them ----- #


class ActionObject_Page_MoveAndRelink extends ActionObject_Page_Move {
# Moves the page and modifies wikilinks to it to point the new title.

  public $relink_comment;  // will be passed to the relink wikilinks AO

  public $noredirect = true;  // "overriding" the default false

  public $namespace;    // work in this namespace only (NULL - all, "" - main, etc)
  public $filterredir;  // "all" (default), "redirects" (only in redirects), "non-redirects" (only in non-redirects)

  public function process ( $element ) {
    if ( parent::process ( $element ) ) {
      $AO = new ActionObject_RelinkAllWikilinksToPage ( $this->bot );
      $AO->comment     = $this->relink_comment;
      $AO->new_title   = $this->element_newtitle ( $element, $title );
      $AO->namespace   = $this->namespace;
      $AO->filterredir = $this->filterredir;

      $AO->process ( $element );

      return true;
    } else {
      return false;
    }
  }

}


class ActionObject_Page_DeleteAndUnlink extends ActionObject_Page_Delete {
# Both deletes the page and unlinks all wikilinks that point to it.

  public $unlink_comment;  // will be passed to the relink wikilinks AO

  public $namespace;    // work in this namespace only (NULL - all, "" - main, etc)
  public $filterredir;  // "all" (default), "redirects" (only unlink in redirects), "non-redirects" (only in non-redirects)

  public function process ( $element ) {
    if ( parent::process ( $element ) ) {
      $title = $this->element_pagetitle ( $element );

      $Iter = new Iterator_BackLinks ( $this->bot );
      $Iter->title       = $title;
      $Iter->namespace   = $this->namespace;
      $Iter->filterredir = $this->filterredir;
      $Iter->redirect    = false;

      $AO = new ActionObject_Page_Edit ( $this->bot );
      $AO->comment = $this->unlink_comment;
      $AO->replaces = array (
        array (
          'type'  => "text",
          'name'  => "Unlinking title [[" . $title . "]]...",
          'regex' => regex_match_wikilink ( $title, NULL, NULL ),
          'with'  => '$6',
        ),
      );

      $Iter->iterate ( $AO );
      return true;
    } else {
      return false;
    }
  }

}


class ActionObject_Redirect_DeleteAndRelink extends ActionObject_Page_Delete {

  public $relink_comment;  // will be passed to the relink wikilinks AO

  public function process ( $element ) {
    $page = $this->element_page ( $element );
    if ( parent::process ( $element ) ) {
      $redirects_to_title = $page->redirects_to();

      $AO = new ActionObject_RelinkAllWikilinksToPage ( $this->bot );
      $AO->comment = $this->relink_comment;
      $AO->new_title = $redirects_to_title;
      $AO->preserve_anchors = false;
      $AO->process ( $element );

      return true;
    } else {
      return false;
    }
  }

}


# ----- Other ----- #


class ActionObject_User_RollbackEdits extends ActionObject_User_Generic {

  public $namespace;
  public $start;
  public $end;
  public $minor;
  public $protected;

  public function process ( $element ) {
    $user = $this->element_username ( $element );

    $Iter = new Iterator_UserContribs ( $this->bot );
    $Iter->user      = $user;
    $Iter->namespace = $this->namespace;
    $Iter->start     = $this->start;
    $Iter->end       = $this->end;
    $Iter->minor     = $this->minor;
    $Iter->protected = $this->protected;

    $AO = new ActionObject_Page_Rollback ( $this->bot );
    $AO->comment = $this->comment;
    $AO->user    = $user;

    $Iter->iterate ( $AO );

    return true;
  }

}


# ---------------------------------------------------------------------------- #
# --               Abstract Database ActionObject classes                   -- #
# ---------------------------------------------------------------------------- #


abstract class ActionObject_WithDatabase extends ActionObject {

  public $db_details;  // array: host, port, user, pass, name, charset - must be set before using with an iterator!
  public $db;          // may be set externally, eg. by an Iterator_Database_*

  abstract protected function db_connect ( $db_details );
  abstract protected function db_disconnect ( $db );
  abstract protected function db_query ( $SQL );

  public function preprocess () {
    if ( empty ( $this->db ) && ! empty ( $this->db_details ) ) {
      $this->db = $this->db_connect ( $this->db_details );
    }
    return parent::preprocess();
  }

  public function postprocess () {
    if ( ! empty ( $this->db ) && ! empty ( $this->db_details ) ) {
      $this->db = $this->db_disconnect ( $this->db );
    }
    return parent::postprocess();
  }

}


abstract class ActionObject_WithDatabase_Mysql extends ActionObject_WithDatabase {

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
    // do nothing - using mysql_pconnect(), disconnect is not needed
  }

  protected function db_query ( $SQL ) {
    mysql_select_db ( $this->db_details['name'], $this->db );
    $result = mysql_query ( $SQL, $this->db );
    if ( ! $result ) {
      throw new Exception ( "SQL query failed (" . mysql_error() . "): " . $SQL );
    }
    return $result;
  }

  // public function process ( $element ) is still abstract

}

