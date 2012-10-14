<?php
#
#  Miscellaneous utilities
#
#  Copyright (C) 2004 Borislav Manolov. This program is in the public domain.
#
#  Author: Borislav Manolov <b.manolov at gmail dot com>
#          http://purl.org/NET/borislav/
#
#  Modified and appended by Grigor Gatchev, 2008-2011.
#
#############################################################################


# ----- File handling ----- #

function my_fwrite($file, $text, $mode='a+') {
  $myFile = @fopen($file, $mode);
  if (! $myFile) return false;
  flock($myFile, LOCK_EX);
  $write_result = @fputs($myFile, $text);
  flock($myFile, LOCK_UN);
  if (! $write_result) return false;
  if (! @fclose($myFile)) return false;
  return true;
}


# ----- Memory handling ----- #


function get_memory_limit () {  // always returns bytes
  preg_match ( '/(\d+)([^\d])?/', ini_get ( 'memory_limit' ), $matches );
  switch ( strtolower ( $matches[2] ) ) {
    case 'k' : $matches[1] *= 1024;
    case 'm' : $matches[1] *= 1024 * 1024;
    case 'g' : $matches[1] *= 1024 * 1024 * 1024;
  }
  return $matches[1];
}


# ----- Wiki-typical strings ----- #

function wikilink ( $target, $text = NULL ) {
  if ( empty ( $text ) && preg_match ( '/([^\(]+)\([^\)]+\)(\#.+)?$/u', $target, $matches ) ) {
    $text = trim ( $matches[1] );
  }
  if ( ! empty ( $text ) ) {
    $target .= "|" . $text;
  }
  return "[[" . $target . "]]";
}


# ----- String diffs ----- #

function added_to_str ( $old_str, $new_str ) {
  $diff = array_diff ( explode ( ' ', $old_str ), explode ( ' ', $new_str ) );
  return implode ( ' ', $diff );
}

# Inspired by Paul Butler's SimpleDiff, and using his algorithm.
function diff_arrays ( $old, $new ) {
  foreach ( $old as $old_index => $old_value ) {
    $new_keys = array_keys ( $new, $old_value );
    foreach ( $new_keys as $new_index ) {
      $matrix[$old_index][$new_index] =
        isset ( $matrix[$old_index - 1][$new_index - 1] ) ?
        $matrix[$old_index - 1][$new_index - 1] + 1 :
        1;
      if ( $matrix[$old_index][$new_index] > $maxlen ) {
        $maxlen = $matrix[$old_index][$new_index];
        $old_max = $old_index + 1 - $maxlen;
        $new_max = $new_index + 1 - $maxlen;
      }
    }
  }
  if ( $maxlen == 0 ) {
    return array ( array ('d' => $old, 'i' => $new ) );
  } else {
    return array_merge (
      diff_arrays ( array_slice ( $old, 0, $old_max ), array_slice ( $new, 0, $new_max ) ),
      array_slice ( $new, $new_max, $maxlen ),
      diff_arrays ( array_slice ( $old, $old_max + $maxlen ), array_slice ( $new, $new_max + $maxlen ) )
    );
  }
}

function diff_strings ( $old, $new ) {
  return diff_arrays ( explode ( ' ', $old ), explode (' ', $new ) );
}
