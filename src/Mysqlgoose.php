<?php declare(strict_types=1);

//=============================================================================
// Copyright 2019-2022 Opplaud LLC and other contributors. MIT licensed.
//=============================================================================

namespace Aponica\Mysqlgoose;

use mysqli;
use Throwable;

use const MYSQLI_REPORT_OFF;

//-----------------------------------------------------------------------------
/// Establishes and manages a connection to a MySQL database.
///
/// Used like MongooseJS's
/// <a href="https://mongoosejs.com/docs/api/mongoose.html">Mongoose</a> class.
///
/// Only a subset of the methods provided by MongooseJS's class are
/// currently supported, and not always in a fully-compatible way.
/// For most cases, however, there's enough to get by.
///
/// The database is accessed using
///   <a href="https://www.php.net/manual/en/book.mysqli.php">mysqli</a>.
//-----------------------------------------------------------------------------

class Mysqlgoose {

  private ?mysqli $iConn = null;
  private array $hOptions = [ 'debug' => false ];
  private array $hiPreparedStatements = [];

  //---------------------------------------------------------------------------
  /// Option to populate nested tables during a query.
  ///
  /// Used as a property name of the options (`hOptions`) argument passed to
  /// various Model methods.
  ///
  /// @see Model
  //---------------------------------------------------------------------------

  const POPULATE = '$Mysqlgoose$POPULATE$';

  //---------------------------------------------------------------------------
  /// Establishes a database connection using the specified configuration.
  ///
  /// The connection is established using
  /// <a href="https://www.php.net/manual/en/book.mysqli.php">mysqli</a>.
  ///
  /// @param $hParams
  ///   A hash (associative array) containing:
  ///
  ///     ['database']
  ///       The database name.
  ///
  ///     ['host']
  ///       The host name.
  ///
  ///     ['password']
  ///       The user's password.
  ///
  ///     ['user']
  ///       The name of a user with access to the necessary tables.
  ///
  /// @param $hOptions
  ///   A hash that optionally contains:
  ///
  ///     ['nReportMode']
  ///       A union of the desired MYSQLI_REPORT_* flags.
  ///       The default is MYSQL_REPORT_OFF. Note that changing this value
  ///       can affect whether MysqlgooseError is thrown where documented.
  ///
  ///
  /// @throws MysqlgooseError
  ///   If the connection cannot be established.
  //---------------------------------------------------------------------------

  public function connect( array $hParams, array $hOptions = [] ) : void {

    try {

      $hSettings = array_merge(
        [ 'nReportMode' => MYSQLI_REPORT_OFF ], $hOptions );

      mysqli_report( $hSettings[ 'nReportMode' ] );

      $this->iConn = new mysqli( $hParams['host'], $hParams['user'],
                                $hParams['password'], $hParams['database'] );

      $this->iConn->set_charset('utf8');

      }
    catch ( Throwable $iThrown ) {
      throw new MysqlgooseError( $iThrown->getMessage() );
      }

    } // connect


  //---------------------------------------------------------------------------
  /// Logs a debugging message if appropriate.
  ///
  /// **Do not called this method directly;** it is used internally when
  /// debugging has been enabled by a call to `Mysqlgoose::set()`.
  ///
  /// @param $avArgs
  ///   An array of arguments (of any type) to be logged. Each is converted
  ///   to a string using json_encode().
  ///
  /// @see `set()`
  //---------------------------------------------------------------------------

  public function debug( ...$avArgs ) : void {

    $iThis = $this;

    $logger =
      is_bool( $this->hOptions['debug'] ) ?
        function( ...$avArgs ) use ( $iThis ) {
          if ( $iThis->hOptions['debug'] ) {
            echo "<div class=MysqlgooseDebug>\n";
            foreach ( $avArgs as $arg )
              echo "<pre>" . json_encode( $arg ) . "</pre>\n";
            echo "</div>\n";
            $nOldReporting = error_reporting(E_ALL & ~E_NOTICE);
            ob_flush();
            flush();
            error_reporting( $nOldReporting );
            }
          } :
        $this->hOptions['debug'];

    $logger( ...$avArgs );

    } // debug


  //---------------------------------------------------------------------------
  /// Closes the database connection.
  ///
  /// @returns bool:
  ///   `true` if the connection can be closed, else `false`; but really, an
  ///   error occurs if the connection has already been closed!
  //---------------------------------------------------------------------------

  public function disconnect() : bool {
    return $this->iConn->close();
    }


  //---------------------------------------------------------------------------
  /// Retrieves the database connection.
  ///
  /// The connection will be as specified by
  /// <a href="https://www.php.net/manual/en/book.mysqli.php">mysqli</a>.
  ///
  /// @returns mysqli:
  ///   The database connection.
  //---------------------------------------------------------------------------

  public function getConnection() : mysqli {
    return $this->iConn;
    }


  //--------------------------------------------------------------------------
  /// Returns a new `Model` suitable for managing a table.
  ///
  /// @param $zName
  ///   The table name.
  ///
  /// @param $iSchema
  ///   The schema for the table.
  ///
  /// @returns Model:
  ///   A Mysqlgoose Model.
  ///
  /// @see Model
  /// @see Schema
  //--------------------------------------------------------------------------

  public function model( string $zName, Schema $iSchema ) : Model {
    return new Model( $zName, $iSchema, $this );
    }


  //--------------------------------------------------------------------------
  ///  Escapes special characters in a string for use in an SQL statement
  ///
  /// This is primarily for use in models.
  ///
  /// @param $z
  ///   The string to escape.
  ///
  /// @returns string:
  ///   The escaped string.
  //--------------------------------------------------------------------------

  public function fzEscape( string $z ) : string {

    return $this->iConn->real_escape_string( $z );

    }

  //--------------------------------------------------------------------------
  /// Performs a MYSQL query and provides the results.
  ///
  /// Do not called this method directly; it is used internally by various
  /// Model and Session methods.
  ///
  /// **Warning:** The query must be made safe BEFORE it is passed.
  ///
  /// @param $zQuery
  ///   The query string to perform, possibly containing `?` characters for
  ///   substitution.
  ///
  ///   **Warning:** This must be made safe prior to passing!
  ///
  /// @param $avValues
  ///   Values to substitute for `?`s in the query string. Each argument
  ///   may be of any type.
  ///
  /// @returns
  ///   For DML, returns a hash (associative array) containing:
  ///
  ///     ['affectedRows']
  ///       The number of affected rows.
  ///
  ///     ['insertId']
  ///       The ID of the inserted row (if applicable).
  ///
  ///   For a transaction command (`COMMIT`/`ROLLBACK`/`START TRANSACTION`),
  ///   returns the result from `mysqli::query()`.
  ///
  ///   Otherwise (for queries), an array of hashes (associative arrays),
  ///   where each hash represents a row of data.
  ///
  /// @throws MysqlgooseError
  //--------------------------------------------------------------------------

  function fvQuery( string $zQuery, array $avValues = [] ) :
    array|bool|\mysqli_result {

    $this->debug( 'Mysqlgoose', 'fvQuery', $zQuery, $avValues );

    $iConn = $this->getConnection();

    //  Transaction statements can't be prepared.  Mysqli has methods for
    //  these, but they seem to work haphazardly.

    if ( in_array( $zQuery, [ 'COMMIT', 'ROLLBACK', 'START TRANSACTION' ] ) )
      { // transactional
      $vResult = $iConn->query( $zQuery );
      return $vResult;
      }

    //  If the statement is not already prepared, prepare it.

    if ( !array_key_exists( $zQuery, $this->hiPreparedStatements ) ) { // prepare
      $iStmt = $iConn->prepare( $zQuery );
      if ( false === $iStmt )
        throw new MysqlgooseError( $iConn->error . ' (#' . $iConn->errno . ')' );
      $this->hiPreparedStatements[ $zQuery ] = $iStmt;
    } // prepare

    $iStmt = $this->hiPreparedStatements[ $zQuery ];


    //  Bind the parameter values and execute the query.

    if ( count( $avValues ) ) { // bind
      $zTypes = '';
      $nValues = count( $avValues );
      for ( $nIndex = 0; $nIndex < $nValues; $nIndex++ ) {
        $zThisType = (
          is_double( $avValues[ $nIndex ] ) ? 'd' :
            ( is_int( $avValues[ $nIndex ] ) ? 'i' :
              ( is_string( $avValues[ $nIndex ] ) ? 's' :
                ( ( null === $avValues[ $nIndex ] ) ? 's' : null
                ) ) ) );
        if ( null === $zThisType )
          throw new MysqlgooseError( "param value [$nIndex] invalid datatype" );
        $zTypes .= $zThisType;
        } // $nIndex
      $iStmt->bind_param( $zTypes, ...$avValues );
      } // bind

    if ( !$iStmt->execute() )
      throw new MysqlgooseError( $iStmt->error . ' (#' . $iStmt->errno . ')' );

    $vResult = $iStmt->get_result();


    //  DML doesn't return a result, so as long as there's no error,
    //  assume it was DML and create our own result.

    if ( ( false === $vResult ) && ( 0 === $iConn->errno ) ) // DML
      $vReturnValue = [
        'affectedRows' => $iStmt->affected_rows,
        'insertId' => $iStmt->insert_id
      ];

    //  For non-DML queries, copy the results to an array, then return it.

    else {
      $vReturnValue = [];
      while ( $hRow = $vResult->fetch_assoc() )
        $vReturnValue[] = $hRow;
    }

    $this->debug( 'Mysqlgoose', 'fiQuery (results)', $vReturnValue );

    $iStmt->reset();

    return $vReturnValue;

    } // fvQuery


  //--------------------------------------------------------------------------
  /// Returns a new Schema suitable for defining a table.
  ///
  /// @param $hhDocDef
  ///   The table definition, typically created by calling:
  ///
  ///     `npx @aponica/mysqlgoose-schema-js`
  ///
  ///   See `Mysqlgoose::Schema` for its structure.
  ///
  /// @returns Schema:
  ///   A Schema.
  //--------------------------------------------------------------------------

  public function Schema( array $hhDocDef ) : Schema {
    return new Schema( $hhDocDef, $this->iConn );
    }


  //--------------------------------------------------------------------------
  /// Sets an option to a value.
  ///
  /// Currently, only the `'debug'` option is supported.
  ///
  /// @param $zKey
  ///   The option key (name).
  ///
  ///   Currently, only `'debug'` is supported.
  ///
  /// @param $vValue
  ///   The option value.
  ///
  ///   When `zKey` is `'debug'`, `vValue` must be one of:
  ///
  ///     `true`
  ///       Each argument will be encoded as JSON (using `json_encode`) and
  ///       output (using `echo`) within a `div` element with class name
  ///       `MySqlgooseDebug`.
  ///
  ///     `false`
  ///       Debug messages will be suppressed.
  ///
  ///     `function(...avArgs){...}`
  ///       Debug messages will be passed to the specified function for
  ///       processing.
  ///
  /// @returns Mysqlgoose:
  ///   This instance, theoretically for chaining.
  ///
  /// @see debug()
  //--------------------------------------------------------------------------

  public function set( string $zKey, mixed $vValue ) : Mysqlgoose {
    $this->hOptions[ $zKey ] = $vValue;
    return $this;
    }


  //--------------------------------------------------------------------------
  /// Starts a session.
  ///
  /// A `Session` is required to perform transactions.
  ///
  /// @returns Session:
  ///   A new Session object.
  //--------------------------------------------------------------------------

  public function startSession() : Session {
    return new Session( $this );
    }

  }; // class Mysqlgoose

// EOF
