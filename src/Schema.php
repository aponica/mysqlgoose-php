<?php declare(strict_types=1);
//=============================================================================
// Copyright 2019-2022 Opplaud LLC and other contributors. MIT licensed.
//=============================================================================

namespace Aponica\Mysqlgoose;

//-----------------------------------------------------------------------------
/// Provides methods for defining document structure.
///
/// A `Schema` is passed to `Mysqlgoose::model()` to create a model.
///
/// Used like MongooseJS's
///   <a href="https://mongoosejs.com/docs/api/schema.html">Schema</a> class.
///
/// Only a subset of the methods provided by MongooseJS's class are
/// currently supported, and not always in a fully-compatible way.
/// For most cases, however, there's enough to get by.
//-----------------------------------------------------------------------------

use mysqli;

class Schema {

  /// The column definitions. This should not be accessed directly, but is
  /// public so it can be accessed by `Model`.

  public $hhColDefs = [];


  /// The primary key field, if any. This should not be accessed directly,
  /// but is public so it can be accessed by `Model`.

  public $zIdField = null;

  //---------------------------------------------------------------------------
  /// Constructs a schema for a document.
  ///
  /// Here's how to create models from `@aponica/mysqlgoose-schema-js` output:
  ///
  ///   ```
  ///   $defs = json_decode( file_get_contents( 'defs.json' ), true );
  ///   foreach ( $defs as $table => $def )
  ///     if ( '//' !== $table ) // skip comment member
  ///       $models[ $table ] = $goose::model( $table,
  ///         new Schema( $def, $goose::getConnection() ) );
  ///   ```
  ///
  /// @param $hhDocDef
  ///   The document definition, which is a hash (associative array) of hashes
  ///   generated by <a href="https://aponica.com/docs/mysqlgoose-schema-js">
  ///     aponica/mysqlgoose-schema-js</a> from the database schema.
  ///
  ///   The outer hash is indexed by column names, and each inner hash
  ///   includes members such as:
  ///
  ///     ['vDefault']
  ///       The optional default value for the column.
  ///
  ///     ['nPrecision']
  ///       The precision of a numeric column.
  ///
  ///     ['bPrimary']
  ///       True if the column is the primary key for the table.
  ///
  ///     ['hReferences']
  ///       For a foreign key, specifies the foreign table and column names
  ///       referenced by the column:
  ///
  ///         ['zTable']
  ///           The reference table name.
  ///
  ///         ['zColumn']
  ///           The referenced column name.
  ///
  ///     ['nScale']
  ///       The scale of a numeric column.
  ///
  ///     ['zType']
  ///       The datatype of the column.
  ///
  /// @param $iConn
  ///   The MySQL connection, used to escape the column names.
  //---------------------------------------------------------------------------

  public function __construct( array $hhDocDef, mysqli $iConn ) {

    foreach ( $hhDocDef as $zColumnName => $hColDef ) { // store column info

      $this->hhColDefs[ $zColumnName ] = $hColDef;

      $this->hhColDefs[ $zColumnName ][ 'zSafeColumnName' ] =
        $iConn->real_escape_string( $zColumnName );

      if ( array_key_exists( 'bPrimary', $hColDef ) && $hColDef[ 'bPrimary' ] )
        $this->zIdField = $zColumnName;

      } // store column info

    } // __construct()


  //---------------------------------------------------------------------------
  /// Iterates the schemas paths similar to `foreach()`.
  ///
  /// @param $fCallback
  ///   Callback function. For each iteration, this is passed two arguments:
  ///
  ///     $zPathName
  ///       The path (column) name.
  ///
  ///     $hSchemaType
  ///       The schema type (definition), which will include members that
  ///       were added to the definition when the schema was instantiated.
  ///
  /// @returns Schema:
  ///   The schema, for chaining.
  //---------------------------------------------------------------------------

  function eachPath( callable $fCallback ) : Schema {

    foreach ( $this->hhColDefs as $zPath => $hSchemaType )
      $fCallback( $zPath, $hSchemaType );

    return $this;

    } // eachPath

  } // class Schema

// EOF
