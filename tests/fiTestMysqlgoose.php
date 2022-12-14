<?php declare(strict_types=1);
//=============================================================================
// Copyright 2019-2022 Opplaud LLC and other contributors. MIT licensed.
//=============================================================================

use Aponica\Mysqlgoose\Mysqlgoose;
use Aponica\Mysqlgoose\Schema;

return function() {

  $iGoose = new Mysqlgoose();

  $iGoose->connect( json_decode( file_get_contents(
    __DIR__ . '/../tests-config/config_mysql.json' ), true ) );

  $hhModelDefs = json_decode( file_get_contents(
    __DIR__ . '/../tests-config/definitions.json' ), true );

  $hiModels = [];

  foreach ( $hhModelDefs as $zName => $hDef )
    if ( '//' !== $zName )
      $hiModels[ $zName ] =
        $iGoose->model( $zName,
          new Schema( $hDef, $iGoose->getConnection() ) );

  return [ $iGoose, $hiModels ];

  };

// EOF
