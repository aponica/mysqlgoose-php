<?php declare(strict_types=1);
//=============================================================================
// Copyright 2019-2022 Opplaud LLC and other contributors. MIT licensed.
//=============================================================================

namespace Aponica\Mysqlgoose;

use mysqli;

//-----------------------------------------------------------------------------
/// Provides methods for storing and retrieving documents.
///
/// Used like MongooseJS's
/// <a href="https://mongoosejs.com/docs/api/model.html">Model</a> class.
///
/// Do **not** instantiate a `Model` directly; call `Mysqlgoose::model()`
/// instead.
///
/// Only a subset of the methods provided by MongooseJS's class are
/// currently supported, and not always in a fully-compatible way.
/// For most cases, however, there's enough to get by.
//-----------------------------------------------------------------------------

class Model {

  private static array $hiModels = [];

  private Mysqlgoose $iGoose;
  private Schema $iSchema;
  private string $zSafeTableName;

  //---------------------------------------------------------------------------
  /// Constructs a model for a specified table name.
  ///
  /// Do **not** use this constructor; call `Mysqlgoose::model()`
  /// instead.
  ///
  /// @param $zTable
  ///   The name of the table.
  ///
  /// @param $iSchema
  ///   The `Schema` for the table/model.
  ///
  /// @param $iGoose
  ///   The `Mysqlgoose` for the database owning the table.
  ///
  /// @see `Mysqlgoose::model()`
  //---------------------------------------------------------------------------

  public function __construct(
    string $zTable, Schema $iSchema, Mysqlgoose $iGoose ) {

    $this->iGoose = $iGoose;
    $this->iGoose->debug( $zTable, '__construct' );
    $this->iSchema = $iSchema;
    $conn = $this->iGoose->getConnection();
    $this->zSafeTableName = $conn->real_escape_string( $zTable );
    self::$hiModels[ $zTable ] = $this;

    } // __construct()


  //---------------------------------------------------------------------------
  /// Adds the `ORDER BY` clauses to a query.
  ///
  /// @param $vContext
  ///   Either the context `Model` for the `hOrders`, or a specific column
  ///   definition.
  ///
  /// @param $hOrders
  ///   A hash (associative array) containing a set of order-by directives.
  ///   This might contain nested directives, in which case this function is
  ///   called recursively with the contents and their new vContext.
  ///
  /// @param $hStmt
  ///   The statement so far.
  ///
  /// @returns hash:
  ///   The revised statement (hStmt).
  ///
  /// @throws MysqlgooseError
  //--------------------------------------------------------------------------

  private static function fhAddOrderByToStmt(
    mixed $vContext, array $hOrders, array $hStmt ) : array {

    foreach ( $hOrders as $zKey => $vValue ) {

      if ( ( $vContext instanceof Model ) &&
        array_key_exists( $zKey, $vContext->iSchema->hhColDefs ) )
          { // new context is a column definition

          if ( array_key_exists( 'orderby', $hStmt ) )
            $hStmt[ 'orderby' ] .= ', ';
          else
            $hStmt[ 'orderby' ] = '';

          $hStmt[ 'orderby' ] .= '`' . $vContext->zSafeTableName . '`.`' .
            $vContext->iSchema->hhColDefs[ $zKey ]
              [ 'zSafeColumnName' ] . '`';

          if ( ! is_int( $vValue ) )
            throw new MysqlgooseError(
              '$orderby expected integer direction, found ' .
                json_encode( $vValue ) );

          if ( 0 > $vValue )
            $hStmt[ 'orderby' ] .= ' DESC';

          } // new context is a column definition

      else if ( array_key_exists( $zKey, self::$hiModels ) )
        { // new context is a model

        $hStmt =
          self::fhAddOrderByToStmt( self::$hiModels[ $zKey ], $vValue, $hStmt );

        $hStmt[ 'azPopulate' ][] = self::$hiModels[ $zKey ]->zSafeTableName;

        } // new context is a model

      else
        throw new MysqlgooseError(
          '$orderby field ' . $zKey . ' not found' );

      } // $zKey=>$vValue

    return $hStmt;

    } // fhAddOrderByToStmt


  //---------------------------------------------------------------------------
  /// Prohibits `$limit`, `$skip` and `$orderby` substatements.
  ///
  /// @param $zOp
  ///   The operator containing the substatement.
  ///
  /// @param $hSubstmt
  ///   The substatement to check.
  ///
  /// @throws cMysqlgoose.Error
  ///   If the substatement contains a `$limit`, `$skip` or `$orderby`.
  //---------------------------------------------------------------------------

  private static function fAssertNoLimitSkipOrderby(
    string $zOp, array $hSubstmt ) : void {

    foreach ( [ 'limit', 'skip', 'orderby' ] as  $zProp )
      if ( array_key_exists( $zProp, $hSubstmt ) )
        throw new MysqlgooseError( $zOp . ' cannot contain $' . $zProp );

    } // fAssertNoLimitSkipOrderby


  //---------------------------------------------------------------------------
  /// Generates the `WHERE`, `ORDER BY` and `LIMIT` clauses for a statement.
  ///
  /// Clauses are generated from the specified conditions. This is invoked
  /// recursively, or by various methods of
  /// {@linkcode Model}.
  ///
  /// @param $vContext
  ///   The context of the current subset of the conditions:
  ///
  ///   1. From outside, the current model's schema is passed.
  ///
  ///   2. During recursive calls, this is often set to a specific column
  ///     definition.
  ///
  ///   3. When an embedded/nested field is specified in the conditions, this
  ///     is set to the corresponding `Model` (in which case, the corresponding
  ///     table is joined as if Mysqlgoose::POPULATE included it).
  ///
  /// @param $hConditions
  ///   Hash (associative array) similar to the object passed to MongoDB for
  ///   specifying conditions that rows must match.
  ///
  /// @param $bSerialize
  ///   If `true`, returns the serialized results;
  ///   Otherwise, returns the components.
  ///
  /// @param $zAnd
  ///   In recursive cases, this will usually be the word `AND` to append the
  ///   condition to others. Otherwise, it is an empty string.
  ///
  /// @returns mixed:
  ///   If `bSerialize` is `true`, an array is returned with these members:
  ///
  ///     [0] string:
  ///       A list of $clauses with `?` placeholders where values
  ///       must be substituted;
  ///
  ///     [1] *[]:
  ///       The values to substitute for the placeholders.
  ///
  ///     [2] string[]:
  ///       The table names to be joined.
  ///
  ///   If `bSerialize` is `false` or not provided (intended to be used when
  ///   the function is called recursively), returns a hash (associative array)
  ///   containing:
  ///
  ///     ['zWhere']
  ///       The `WHERE` conditions string (without the keyword `WHERE`,
  ///       and possibly empty), with `?` placeholders where values must be
  ///       substituted;
  ///
  ///     ['avValues']
  ///       A (possibly empty) array of values to substitute for the
  ///       `WHERE` placeholders;
  ///
  ///     ['orderby']
  ///       The `ORDER BY` conditions (without the keywords `ORDER BY`,
  ///       and possibly empty). This member is named to match the filter
  ///       name (without $) to simplify error detection.
  ///
  ///     ['skip']
  ///       The number of records to skip in the results (possibly 0).
  ///       This member is named to match the filter name (without $) to
  ///       simplify error detection.
  ///
  ///     ['limit']
  ///       The number of records to return in the results (0=unlimited),
  ///       This member is named to match the filter name (without $) to
  ///       simplify error detection.
  ///
  ///     ['azPopulate']
  ///       A list of table names that should be populated to provide
  ///       nested values.
  ///
  /// @throws MysqlgooseError
  //---------------------------------------------------------------------------

  private function fvBuildSqlClauses(
    mixed $vContext, array $hConditions, bool $bSerialize = true,
    string $zAnd = '' ) : mixed {

    $zContextColumn = (
      ( $vContext instanceof Model ) ?
        '[unspecified field]' :
        self::fzQualifiedColumnName( $vContext )
      );

    $hSubstmt = null;

    $hStmt = [ 'zWhere' => '', 'avValues' => [], 'azPopulate' => [] ];

    foreach ( $hConditions as $zProp => $vValue ) { // each condition

      switch ( "$zProp" ) { // process condition

        case '$and':

          if ( ! is_array( $vValue ) )
            throw new MysqlgooseError( '$and must be an array' );

          $count = count($vValue);

          if ( 2 > $count )
            throw new MysqlgooseError( '$and array must have 2+ members' );

          $hStmt[ 'zWhere' ] .= "$zAnd ( ";

          for ( $n = 0 ; $n < $count ; $n++ ) { // each AND condition

            if ( ! ( array_key_exists( $n, $vValue ) && is_array( $vValue[$n] ) ) )
              throw new MysqlgooseError( '$and array member #' . $n .
                ' must be a nested associative array' );

            $hSubstmt = $this->fvBuildSqlClauses( $vContext, $vValue[$n], false );

            self::fAssertNoLimitSkipOrderby( $zProp, $hSubstmt );

            $hStmt[ 'azPopulate' ] =
              array_merge( $hStmt[ 'azPopulate' ], $hSubstmt[ 'azPopulate' ] );

            if ( 0.5 < $n )
              $hStmt[ 'zWhere' ] .= ' AND ';

            $hStmt[ 'zWhere' ] .= '( ' . $hSubstmt['zWhere'] . ' )';

            $hStmt[ 'avValues' ] =
              array_merge( $hStmt['avValues'], $hSubstmt['avValues'] );

            } // each AND condition

          $hStmt[ 'zWhere' ] .= ' )';
          break;

        case '$comment':

          break;

        case '$eq':

          if ( null === $vValue )
            $hStmt['zWhere'] .= "$zAnd $zContextColumn IS NULL";

          else if ( is_bool( $vValue ) )
            $hStmt['zWhere'] .= "$zAnd $zContextColumn IS " .
              ( $vValue ? 'TRUE' : 'FALSE' );

          else {
            $hStmt['zWhere'] .= "$zAnd $zContextColumn = ?";
            $hStmt[ 'avValues' ][] =
              self::fvCastForBinding( $vValue, $vContext );
            }

          break;

        case '$exists':

          $hStmt['zWhere'] .= ( $vValue ) ?
            "$zAnd $zContextColumn IS NOT NULL" :
            "$zAnd $zContextColumn IS NULL";

          break;

        case '$gt':

          $hStmt['zWhere'] .= "$zAnd $zContextColumn > ?";

          $hStmt[ 'avValues' ][] =
            self::fvCastForBinding( $vValue, $vContext );

          break;

        case '$gte':

          $hStmt['zWhere'] .= "$zAnd $zContextColumn >= ?";

          $hStmt[ 'avValues' ][] =
            self::fvCastForBinding( $vValue, $vContext );

          break;

        case '$in':

          if ( ( ! is_array( $vValue ) ) || ( 0.5 > count( $vValue ) ) )
            throw new MysqlgooseError( '$in requires [ value, ... ]' );

          $hStmt['zWhere'] .= "$zAnd $zContextColumn IN ( ? ";

          $hStmt[ 'avValues' ][] =
            self::fvCastForBinding( $vValue[ 0 ], $vContext );

          for ( $n = 1 ; $n < count($vValue) ; $n++ ) {
            $hStmt['zWhere'] .= ", ?";
            $hStmt[ 'avValues' ][] =
              self::fvCastForBinding( $vValue[ $n ], $vContext );
            }

          $hStmt['zWhere'] .= ' )';

          break;

        case '$limit':

          if ( array_key_exists( 'limit', $hStmt ) )
            throw new MysqlgooseError( '$limit can only appear once' );

          $hStmt['limit'] = intval( $vValue );

          break;

        case '$lt':

          $hStmt['zWhere'] .= "$zAnd $zContextColumn < ?";

          $hStmt[ 'avValues' ][] =
            self::fvCastForBinding( $vValue, $vContext );

          break;

        case '$lte':

          $hStmt['zWhere'] .= "$zAnd $zContextColumn <= ?";

          $hStmt[ 'avValues' ][] =
            self::fvCastForBinding( $vValue, $vContext );

          break;

        case '$meta':

          throw new MysqlgooseError( '$meta not supported because $text uses LIKE' );

          // break;

        case '$mod':

          if ( ! ( is_array( $vValue ) && ( 2 === count( $vValue ) ) ) )
            throw new MysqlgooseError( '$mod requires [ divisor, remainder ]' );

          $hStmt['zWhere'] .= "$zAnd $zContextColumn % ? = ?";

          $hStmt[ 'avValues' ][] =
            self::fvCastForBinding( $vValue[ 0 ], $vContext );

          $hStmt[ 'avValues' ][] =
            self::fvCastForBinding( $vValue[ 1 ], $vContext );

          break;

        case '$ne':

          if ( null === $vValue )
            $hStmt['zWhere'] .= "$zAnd $zContextColumn IS NOT NULL";

          else if ( is_bool( $vValue ) )
            $hStmt['zWhere'] .= "$zAnd $zContextColumn IS NOT " .
              ( $vValue ? 'TRUE' : 'FALSE' );

          else {
            $hStmt['zWhere'] .= "$zAnd $zContextColumn != ?";
            $hStmt[ 'avValues' ][] =
              self::fvCastForBinding( $vValue, $vContext );
            }

          break;

        case '$nin':

          if ( ( ! is_array( $vValue ) ) || ( 0.5 > count( $vValue ) ) )
            throw new MysqlgooseError( '$nin requires [ value, ... ]' );

          $hStmt['zWhere'] .= "$zAnd $zContextColumn NOT IN ( ? ";

          $hStmt[ 'avValues' ][] =
            self::fvCastForBinding( $vValue[ 0 ], $vContext );

          for ( $n = 1 ; $n < count($vValue) ; $n++ ) {
            $hStmt['zWhere'] .= ', ?';
            $hStmt[ 'avValues' ][] =
              self::fvCastForBinding( $vValue[ $n ], $vContext );
            } // for

          $hStmt['zWhere'] .= ' )';

          break;

        case '$not':

          if ( is_string( $vValue ) ) { // assume regex; negate

            $hStmt['zWhere'] .= "$zAnd $zContextColumn NOT REGEXP ?";

            $hStmt[ 'avValues' ][] =
              self::fvCastForBinding( $vValue, $vContext );

            } // assume regex; negate

          else if ( ! is_array( $vValue ) )
            throw new MysqlgooseError(
              'use $ne instead of $not to test values' );

          else { // not a regex

            $hSubstmt =
              $this->fvBuildSqlClauses( $vContext, $vValue, false, $zAnd );

            self::fAssertNoLimitSkipOrderby( $zProp, $hSubstmt );

            $hStmt[ 'azPopulate' ] =
              array_merge( $hStmt[ 'azPopulate' ], $hSubstmt[ 'azPopulate' ] );

            $hStmt['zWhere'] .= " $zAnd NOT (" . $hSubstmt['zWhere'] . ' )';

            $hStmt['avValues'] =
              array_merge( $hStmt['avValues'], $hSubstmt['avValues'] );

            } // not a regex

          break;

        case '$or':
          if ( ! is_array( $vValue ) )
            throw new MysqlgooseError( '$or must be an array' );

          $count = count($vValue);

          if ( 2 > $count )
            throw new MysqlgooseError( '$or array must have 2+ members' );

          $hStmt['zWhere'] .= "$zAnd (";

          for ( $n = 0 ; $n < $count ; $n++ ) {

            if ( ! ( array_key_exists( $n, $vValue ) && is_array( $vValue[$n] ) ) )
              throw new MysqlgooseError(
                '$or array member #' . $n . ' must be a nested array' );

            $hSubstmt = $this->fvBuildSqlClauses( $vContext, $vValue[$n], false );

            self::fAssertNoLimitSkipOrderby( $zProp, $hSubstmt );

            $hStmt[ 'azPopulate' ] =
              array_merge( $hStmt[ 'azPopulate' ], $hSubstmt[ 'azPopulate' ] );

            if ( 0.5 < $n )
              $hStmt['zWhere'] .= ' OR';

            $hStmt['zWhere'] .= ' ( ' . $hSubstmt['zWhere'] . ' )';

            $hStmt['avValues'] =
              array_merge( $hStmt['avValues'], $hSubstmt['avValues'] );

            } // n

          $hStmt['zWhere'] .= ' )';

          break;

        case '$orderby':

          if ( ! is_array( $vValue ) )
            throw new MysqlgooseError( '$orderby must be a hash array' );

          $hStmt = self::fhAddOrderByToStmt( $vContext, $vValue, $hStmt );
          break;

        case '$query':

          throw new MysqlgooseError( 'specify a query without $query' );

          // break;

        case '$regex':

          if ( ! is_string( $vValue ) )
            throw new MysqlgooseError(
              'specify $regex value as a string (without delimiters)' );

          $hStmt['zWhere'] .= "$zAnd $zContextColumn REGEXP ?";

          $hStmt[ 'avValues' ][] = self::fvCastForBinding( $vValue, $vContext );

          break;

        case '$skip':

          if ( array_key_exists( 'skip', $hStmt ) )
            throw new MysqlgooseError( '$skip can only appear once' );

          $hStmt['skip'] = intval( $vValue );

          break;

        case '$text':

          if ( ! ( is_array( $vValue ) &&
            array_key_exists( '$search', $vValue ) ) )
              throw new MysqlgooseError( '$text requires $search' );

          if ( array_key_exists( '$caseSensitive', $vValue ) )
            throw new MysqlgooseError(
              'use $language instead of $caseSensitive' );

          if ( array_key_exists( '$diacriticSensitive', $vValue ) )
            throw new MysqlgooseError(
              'use $language instead of $diacriticSensitive' );

          $hStmt['zWhere'] .= "$zAnd $zContextColumn LIKE '" .
            $this->iGoose->fzEscape( $vValue[ '$search' ] ) . "'";

          if ( array_key_exists( '$language', $vValue ) )
            $hStmt['zWhere'] .= ' COLLATE "' . $this->iGoose->fzEscape(
              $vValue[ '$language' ] ) . '"';

          break;

        default:

          if ( '$' == substr( $zProp, 0, 1 ) )
            throw new MysqlgooseError( $zProp . ' is not supported' );

          //  If the new context is a column definition, remember it, and
          //  remember its qualified name to insert into the query string.

          $vNewContext = null;

          if ( ( $vContext instanceof Model ) &&
            array_key_exists( $zProp, $vContext->iSchema->hhColDefs ) )
              { // column context

              $vNewContext =
                [ 'iModel' => $vContext, 'zColumnName' => $zProp ];

              $zContextColumn = self::fzQualifiedColumnName( $vNewContext );

              } // column context

          //  If the new context is a model, remember it, remember to populate
          //  it, and clear out any prior context column name.

          else if ( array_key_exists( $zProp, self::$hiModels ) )
            { // model context

            $vNewContext = self::$hiModels[ $zProp ];

            $hStmt[ 'azPopulate' ][] = $vNewContext->zSafeTableName;

            $zContextColumn = '[unspecified field]';

            } // model context

          else
            throw new MysqlgooseError( 'unknown column: ' . $zProp );


          if ( null === $vValue )
            $hStmt['zWhere'] .= "$zAnd $zContextColumn IS NULL";

          else if ( is_bool( $vValue ) )
            $hStmt['zWhere'] .= "$zAnd $zContextColumn IS " .
              ( $vValue ? 'TRUE' : 'FALSE' );

          else if ( is_array( $vValue ) ) { // sub-statement

            $hSubstmt = $this->fvBuildSqlClauses(
              $vNewContext, $vValue, false, $zAnd );

            $hStmt[ 'azPopulate' ] =
              array_merge( $hStmt[ 'azPopulate' ], $hSubstmt[ 'azPopulate' ] );

            $hStmt['zWhere'] .= $hSubstmt['zWhere'];

            if ( array_key_exists( 'orderby', $hSubstmt ) ) { // orderby

              if ( array_key_exists( 'orderby', $hStmt ) )
                $hStmt['orderby'] .= ', ' . $hSubstmt['orderby'];

              else
                $hStmt['orderby'] = $hSubstmt['orderby'];

              } // orderby

            if ( array_key_exists( 'skip', $hSubstmt ) ) { // skip

              if ( array_key_exists( 'skip', $hStmt ) )
                throw new MysqlgooseError( '$skip can only appear once' );

              $hStmt['skip'] = $hSubstmt['skip'];

              } // skip

            if ( array_key_exists( 'limit', $hSubstmt ) ) { // limit

              if ( array_key_exists( 'limit', $hStmt ) )
                throw new MysqlgooseError( '$limit can only appear once' );

              $hStmt['limit'] = $hSubstmt['limit'];

              } // limit

            $hStmt['avValues'] =
              array_merge( $hStmt['avValues'], $hSubstmt['avValues']);

            } // sub-statement

          else { // scalar, hopefully

            $hStmt['zWhere'] .= "$zAnd $zContextColumn = ?";

            $hStmt[ 'avValues' ][] =
              self::fvCastForBinding( $vValue, $vNewContext );

            } // scalar, hopefully

        } // process condition

      if ( 0.5 < mb_strlen( $hStmt[ 'zWhere' ] ) )
        $zAnd = ' AND';

      } // each condition

    if ( ! $bSerialize )
      return $hStmt;

    $clauses = '';

    if ( 0 < strlen( $hStmt['zWhere'] ) )
      $clauses .= ' WHERE ' . $hStmt['zWhere'];

    if ( array_key_exists( 'orderby', $hStmt ) )
      $clauses .= ' ORDER BY ' . $hStmt['orderby'];

    if ( array_key_exists( 'limit', $hStmt ) ||
      array_key_exists( 'skip', $hStmt ) ) { // limit

        $clauses .= ' LIMIT ';

        if ( array_key_exists( 'skip', $hStmt ) )
          $clauses .= $hStmt['skip'].",";

        if ( ! array_key_exists( 'limit', $hStmt ) )
          throw new MysqlgooseError( 'cannot use $skip without $limit' );

        $clauses .= $hStmt['limit'];

        } // limit

    return [ $clauses, $hStmt['avValues'], $hStmt[ 'azPopulate' ] ];

    } // fvBuildSqlClauses


  //---------------------------------------------------------------------------
  /// Casts a value to the type suitable for a SQL statement.
  ///
  /// @param $vValue
  ///   The value to be cast.
  ///
  /// @param $hContext
  ///   A hash (associative array) defining the context for the column.
  ///
  /// @returns mixed:
  ///   The value, cast to the correct type for a prepared SQL statement.
  ///
  /// @throws MysqlgooseError
  //---------------------------------------------------------------------------

  private static function fvCastForBinding(
    mixed $vValue, array $hContext ) : mixed {

    $hColDef =
      $hContext[ 'iModel' ]->iSchema->hhColDefs[ $hContext[ 'zColumnName' ] ];

    if ( null !== $vValue )
      switch ( $hColDef[ 'zType' ] ) {

        case 'char':
        case 'text':
        case 'varchar':

          if ( ! is_string( $vValue ) )
            $vValue = "$vValue";

          break;

        case 'datetime':
        case 'timestamp':

          if ( ! ( $vValue instanceof \DateTime ) )
            throw new MysqlgooseError(
              $hColDef[ 'zSafeColumnName' ] . ' must be a DateTime' );

          $vValue = $vValue->format( 'Y-m-d H:i:s' ); // local time

          break;

        case 'decimal':

          if ( is_string( $vValue ) && is_numeric( $vValue ) )
            $vValue = floatval( $vValue );

          if ( ! ( is_float( $vValue ) || is_int( $vValue ) ) )
            throw new MysqlgooseError(
                $hColDef[ 'zSafeColumnName' ] . ' must be numeric' );

          $vValue = sprintf( '%' . $hColDef[ 'nPrecision' ] . '.' .
            $hColDef[ 'nScale' ] . 'f', $vValue );

          break;

        default:

          if ( is_bool( $vValue ) )
            $vValue = ( $vValue ? 1 : 0 );

        } // switch

    return $vValue;

    } // fvCastForBinding


  //---------------------------------------------------------------------------
  /// Saves a new document to the database.
  ///
  /// Currently this is the only way to save a new document, and it only
  /// saves one document to a single table.
  ///
  /// @param $hDoc
  ///   Hash (associative array) representing the table row.
  ///
  /// @returns Hash:
  ///   Hash representing the table row as saved.
  ///
  /// @throws MysqlgooseError
  ///   If the created record does not have a valid ID, or any mysqli error.
  //---------------------------------------------------------------------------

  public function create( $hDoc ) : array {

    $this->iGoose->debug( $this->zSafeTableName, 'create', $hDoc );

    [ $zList, $avValues ] = $this->favSqlSetValues( $hDoc, true );

    $hResults = $this->iGoose->fvQuery(
      "INSERT INTO `{$this->zSafeTableName}` SET {$zList}", $avValues );

    $nId = (
      ( array_key_exists( 'insertId', $hResults ) &&
        ( 0 !== $hResults[ 'insertId' ] ) ) ?
          $hResults[ 'insertId' ] :
          ( ( null !== $this->iSchema->zIdField ) ?
            $hDoc[ $this->iSchema->zIdField ] :
            false
            )
      ); // $nId

    if ( ! $nId )
      throw new MysqlgooseError( 'missing insert ID' );

    return $this->findById( $nId );

    } // create


  //---------------------------------------------------------------------------
  /// Finds one or more documents matching specified conditions.
  ///
  /// Returns an array of hashes (associative arrays), each containing
  /// the columns for a matching row (and possibly nested rows from related
  /// tables).
  ///
  /// If `hOptions` includes a member named `Mysqlgoose::POPULATE`, or the
  /// `hFilter` includes an embedded (nested) field, then this behaves
  /// similar to chaining a .populate() call to a MongooseJS query: each
  /// resulting hash will contain nested hash objects for the populated
  /// associations, where the property name (index) for the nested hash is
  /// the name of the corresponding table. Note that this only works if the
  /// outer table has a field that references the primary key of the embedded
  /// table.
  ///
  /// *Note:* The examples that follow assume a table named `customer` with
  /// columns named `bVerified` and `zName`, and a table named `order` with
  /// a column named `bActive` and a column (name unspecified) that provides
  /// a foreign key to the `customer` table.
  ///
  /// Basic example:
  ///
  ///   `customerModel::find( [ 'zName' => 'John Doe' ] );`
  ///
  /// Example w/automatic population:
  ///
  ///   `orderModel::find( [ 'customer' => [ 'bVerified' =>  true ] ] );`
  ///
  /// Example w/explicit population:
  ///
  ///   `orderModel::find( [ 'bActive' => true ], null,
  ///       [ Mysqlgoose.POPULATE => 'customer' ] );`
  ///
  /// @param $vFilter
  ///   Either a row ID number (matching the primary key field), or a hash
  ///   (associative array) similar to the
  ///   <a href="https://docs.mongodb.com/manual/tutorial/query-documents/">
  ///     query document</a>
  ///   supported by MongoDB.
  ///
  /// @param $vProjection
  ///   This is currently ignored but included for consistency with MongoDB/
  ///   MongooseJS and intended to be implemented in the future.
  ///
  /// @param $hOptions
  ///   Options. Currently, only a member named Mysqlgoose::POPULATE is
  ///   recognized.
  ///
  ///   [ Mysqlgoose::POPULATE ]
  ///     The names of all related tables that should be included in the
  ///     result documents.
  ///
  /// @returns array:
  ///   Returns an array of hashes (associative arrays), each containing the
  ///   column name-value pairs for a matching row (and possibly nested tables).
  ///
  /// @throws MysqlgooseError
  ///   From Mysqlgoose::fvQuery().
  ///
  /// @see <a
  ///   href="https://docs.mongodb.com/manual/tutorial/query-documents/">
  ///     MongoDB Query Documents</a>
  ///
  /// @see <a
  ///   href="https://docs.mongodb.com/manual/reference/operator/query/#query-selectors">
  ///     MongoDB Query Filters</a>
  //---------------------------------------------------------------------------

  public function find( mixed $vFilter = [],
    mixed $vProjection = null, array $hOptions = [] ) : array {

    $this->iGoose->debug( $this->zSafeTableName, 'find',
      $vFilter, $vProjection, $hOptions );

    if ( array_key_exists( Mysqlgoose::POPULATE, $hOptions ) &&
      ( ! is_array( $hOptions[ Mysqlgoose::POPULATE ] ) ) )
        throw new MysqlgooseError( 'option POPULATE must be an array' );

    $hSettings = array_merge( [ Mysqlgoose::POPULATE => [] ], $hOptions );


    //  Build the query for this table.

    [ $zClauses, $avValues, $azPopulate ] =
      $this->fvBuildSqlClauses( $this, $vFilter );

    $zSelect = null;

    $zFrom = "FROM `{$this->zSafeTableName}`";

    foreach ( $this->iSchema->hhColDefs as $zCol => $hDefs ) {

      if ( null === $zSelect )
        $zSelect = "SELECT `{$this->zSafeTableName}`.`{$hDefs['zSafeColumnName']}`";

      else
        $zSelect .= ", `{$this->zSafeTableName}`.`{$hDefs['zSafeColumnName']}`";

      } // $zCol

    //  If any embedded (nested) tables were referenced, add them as joins.

    if ( count( $azPopulate ) ) // add referenced tables
      $hSettings[ Mysqlgoose::POPULATE ] =
        array_merge( $azPopulate, $hSettings[ Mysqlgoose::POPULATE ] );


    //  If we're supposed to populate linked tables, so first add the right
    //  column names to the query, and figure out where the joins are.

    $hazLinkedModels = [];

    if ( count( $hSettings[ Mysqlgoose::POPULATE] ) ) { // populate joins

      $azModelNames = array_unique( $hSettings[Mysqlgoose::POPULATE] );

      [ $zJoinClauses, $azAlreadyJoined ] = $this->favSqlJoin( $azModelNames, [] );

      $hazLinkedModels[ $this->zSafeTableName ] = $azAlreadyJoined;

      $zFrom .= $zJoinClauses;

      foreach ( $azModelNames as $zModelName ) { // each model

        $iModel = self::$hiModels[ $zModelName ];

        foreach ( $iModel->iSchema->hhColDefs as $zCol => $hDefs ) { // each column

          $zSTN = $iModel->zSafeTableName;

          $zSCN = $hDefs[ 'zSafeColumnName' ];

          $zSelect .= ", `$zSTN`.`$zSCN` AS '$zSTN>$zSCN'";

          } // each column

        [ $zJoinClauses, $azContains ] =
          $iModel->favSqlJoin( $azModelNames, $azAlreadyJoined );

        $azAlreadyJoined = array_merge( $azAlreadyJoined, $azContains );

        $hazLinkedModels[ $zModelName ] = $azContains;

        $zFrom .= $zJoinClauses;

        } // each model

    } // populate joins


    //  Perform the query, then create separate objects to represent the
    //  populated results.

    $ahResults =
      $this->iGoose->fvQuery( "$zSelect $zFrom $zClauses", $avValues );

    $ahReturnArray = [];

    foreach ( $ahResults as $hRow ) {

      //  Store each column from the result in its table's array

      $hhvObjects = [];
      foreach ( $hRow as $zName => $vValue ) {

        $azParts = explode( '>', $zName );

        [ $zTable, $zCol ] =
          ( ( 1 == count( $azParts ) ) ?
            [ $this->zSafeTableName, $azParts[0] ] :
            $azParts );

        if ( ! array_key_exists( $zTable, $hhvObjects ) )
          $hhvObjects[ $zTable ] = [];

        switch( self::$hiModels[$zTable]->iSchema->hhColDefs[ $zCol ][ 'zType' ] )
          { // type conversion

          case 'datetime':
          case 'timestamp':
            $hhvObjects[ $zTable ][ $zCol ] =
              ( ( null === $vValue ) ? null : new \DateTime( $vValue ) );
            break;

          case 'decimal':
            $hhvObjects[ $zTable ][ $zCol ] = floatval( $vValue );
            break;

          default:
            $hhvObjects[ $zTable ][ $zCol ] = $vValue;

          } // type conversion

        } // $zName => $vValue


      //  Inject each table as a nested array within the table that
      //  links to it.

      foreach ( $hazLinkedModels as $zContainerName => $azContainerContents )
        foreach ( $azContainerContents as $zContained )
          $hhvObjects[ $zContainerName ][ $zContained ] =
            &$hhvObjects[ $zContained ];


      //  Save this substructure in the results.

      $ahReturnArray[] = $hhvObjects[ $this->zSafeTableName ];

      } // $hRow

    return $ahReturnArray;

    } // find


  //---------------------------------------------------------------------------
  /// Finds the document matching the specified ID.
  ///
  /// Returns a single hash (associative array) containing the columns
  /// for the matching row (and possibly nested rows from related tables).
  ///
  /// This is a wrapper for `Model::findOne()` which in turn is essentially
  /// a wrapper for `Model::find()`.
  ///
  /// @param $nId
  ///   The row ID number (matching the primary field).
  ///
  /// @param $vProjection
  ///   This is currently ignored but included for consistency with MongoDB/
  ///   MongooseJS and intended to be implemented in the future.
  ///
  /// @param $hOptions
  ///   Options expected by `Model::find()`.
  ///
  /// @returns array:
  ///   Hash (associative array) containing the column name-value pairs for
  ///   the specified row (and possibly nested tables), or null if there is
  ///   no match.
  ///
  /// @throws MysqlgooseError
  //---------------------------------------------------------------------------

  public function findById( int $nId,
    mixed $vProjection = null, array $hOptions = [] ) : ?array {

    $this->iGoose->debug( $this->zSafeTableName, 'findById',
      $nId, $vProjection, $hOptions );

    if ( null === $this->iSchema->zIdField )
      throw new MysqlgooseError( 'no primary key for ' . $this->zSafeTableName );

    return $this->findOne( [ $this->iSchema->zIdField => $nId ],
      $vProjection, $hOptions );

    } // findById


  //---------------------------------------------------------------------------
  /// Removes the document matching the specified ID.
  ///
  /// This is a wrapper for `Model->findById()` and `Model::remove()`.
  ///
  /// @param $nId
  ///   The row ID number (matching the primary key).
  ///
  /// @param $hOptions
  ///   Options expected by `Model::findById()`.
  ///
  /// @returns array:
  ///   Hash (associative array) containing the column name-value pairs for
  ///   the removed row (and possibly nested tables).
  ///
  /// @throws MysqlgooseError
  ///
  /// @see Model::findById()
  /// @see Model::remove()
  //---------------------------------------------------------------------------

  public function findByIdAndRemove( int $nId, array $hOptions = [] ) : array {

    $this->iGoose->debug( $this->zSafeTableName, 'findByIdAndRemove',
      $nId, $hOptions );

    $hDoc = $this->findById( $nId, null, $hOptions );

    $hResult =
      $this->remove( [ $this->iSchema->zIdField => $nId ], [ 'single' => true ] );

    if ( ( ! array_key_exists( 'nRemoved', $hResult ) ) ||
      ( 1 !== $hResult['nRemoved'] ) )
        throw new MysqlgooseError( $hResult[ 'nRemoved' ] . ' rows deleted' );

    return $hDoc;

    } // findByIdAndRemove

  //---------------------------------------------------------------------------
  /// Updates the row matching the ID to resemble the specified document.
  ///
  /// @param $nId
  ///   The row ID number (matching the primary key).
  ///
  /// @param $hUpdate
  ///   Hash (associative array) containing changes to the document.
  ///   Note that this need not be a complete *replacement*, but merely
  ///   specifies those columns that should be changed.
  ///
  ///   Columns in related tables cannot currently be updated.
  ///
  /// @param $hOptions
  ///   Options expected by `Model::find()`.
  ///
  /// @returns array:
  ///   Hash (associative array) containing the column name-value pairs for
  ///   the updated row (and possibly nested tables).
  ///
  /// @throws MysqlgooseError
  ///
  /// @see Model::find()
  //---------------------------------------------------------------------------

  public function findByIdAndUpdate(
    int $nId, array $hUpdate, array $hOptions = [] ) : array {

    $this->iGoose->debug( $this->zSafeTableName, 'findByIdAndUpdate',
      $nId, $hUpdate, $hOptions );

    if ( null === $this->iSchema->zIdField )
      throw new MysqlgooseError( 'no primary key for ' . $this->zSafeTableName );

    [ $zSetList, $avValues ] = $this->favSqlSetValues( $hUpdate );

    [ $zClauses, $avClauseValues, ] =
      $this->fvBuildSqlClauses( $this, [ $this->iSchema->zIdField => $nId ] );

    $avValues = array_merge( $avValues, $avClauseValues );

    $hResult = $this->iGoose->fvQuery(
      'UPDATE `' . $this->zSafeTableName . "` SET $zSetList $zClauses", $avValues );

    if ( ( ! array_key_exists( 'affectedRows', $hResult ) ) ||
      ( 1 != $hResult['affectedRows'] ) )
        throw new MysqlgooseError( $hResult[ 'affectedRows' ] . ' rows updated' );

    return $this->findById( $nId, $hOptions );

    } // findByIdAndUpdate


  //---------------------------------------------------------------------------
  /// Finds the first document matching specified conditions.
  ///
  /// Resolves with a single hash (associative array) containing the columns
  /// for the first matching row (and possibly nested rows from related
  /// tables).
  ///
  /// This is essentially a wrapper for `Model::find()`.
  ///
  /// @param $vFilter
  ///   Either a row ID number (matching the primary key field), or a hash
  ///   (associative array) similar to that supported by MongooseJS/MongoDB.
  ///
  /// @param $vProjection
  ///   **This is currently ignored,** but included for consistency with
  ///   MongoDB/MongooseJS and intended to be implemented in the future.
  ///
  /// @param $hOptions
  ///   Options expected by `Model::find()`.
  ///
  /// @returns array:
  ///   Hash (associative array) containing the column name-value pairs for
  ///   the first matching row (and possibly nested tables).
  ///
  /// @throws MysqlgooseError
  ///
  /// @see $this::find().
  //--------------------------------------------------------------------------

  public function findOne( mixed $vFilter,
    mixed $vProjection = null, array $hOptions = [] ) : ?array {

    $this->iGoose->debug( $this->zSafeTableName, 'findOne',
      $vFilter, $vProjection, $hOptions );

    $vFilter[ '$limit' ] = 1;

    $ahResults = $this->find( $vFilter, $vProjection, $hOptions );

    return ( count( $ahResults ) ? $ahResults[ 0 ] : null );

    } // findOne


  //---------------------------------------------------------------------------
  /// Returns the qualified column name for a column context.
  ///
  /// This differs from the JS version, which uses fazQualifiedColumn()
  /// instead, because ?? is supported in JS prepared statements buy not
  /// in PHP.
  ///
  /// @param $hContext
  ///   A hash (associative array) containing:
  ///
  ///     ['iModel']
  ///       A model.
  ///
  ///     ['zColumnName']
  ///       The name of a column in the model.
  ///
  /// @returns string:
  ///   The column name in the format "`stn`.`scn`", where "stn" is the safe
  ///   table name and "scn" is the safe column name.
  //--------------------------------------------------------------------------

  private static function fzQualifiedColumnName( array $hContext ) : string {

    return '`' . $hContext[ 'iModel' ]->zSafeTableName . '`.`' .
      $hContext[ 'iModel' ]->iSchema->
        hhColDefs[ $hContext[ 'zColumnName' ] ][ 'zSafeColumnName' ] . '`';

    } // fzQualifiedColumnName


  //---------------------------------------------------------------------------
  /// Removes one or more documents matching specified conditions.
  ///
  /// @param $vFilter
  ///   Either a row ID number (matching the primary key field), or a hash
  ///   (associative array) similar to that supported by MongooseJS/MongoDB.
  ///
  /// @param $hOptions
  ///   Options. Currently, only one is recognized:
  ///
  ///     ['single']
  ///       If `true`, only the first matching document should be removed.
  ///       This is the same as specifying filter `['$limit'=>1]` (and will
  ///       be overridden by `vFilter['$limit']` if both are specified).
  ///
  /// @returns array:
  ///   Returns a Hash (associative array) containing:
  ///
  ///     ['nRemoved']
  ///       The number of documents removed.
  ///
  /// @throws MysqlgooseError
  //--------------------------------------------------------------------------

  public function remove( mixed $vFilter, array $hOptions = [] ) : array {

    $this->iGoose->debug( $this->zSafeTableName, 'remove',
      $vFilter, $hOptions );

    //  Build and execute the query for this table, then return the
    //  number of affected rows as nRemoved.

    if ( array_key_exists( 'single', $hOptions ) )
      $vFilter = array_merge( [ '$limit' => 1 ], $vFilter );

    [ $zClauses, $avValues, ] =
      $this->fvBuildSqlClauses( $this, $vFilter );

    $hResult = $this->iGoose->fvQuery(
      'DELETE FROM `' . $this->zSafeTableName . "` $zClauses", $avValues );

    return [ 'nRemoved' => $hResult['affectedRows'] ];

    } // remove


  //--------------------------------------------------------------------------
  /// Creates clauses to populate a document's linked table data.
  ///
  /// This must only be invoked by `Model::find()`; it is only made public
  /// so it can be unit-tested separately!
  ///
  /// @param $azModelNames
  ///   The names of linked tables that should be populated.
  ///
  /// @param $azAlreadyJoined
  ///   Names of linked tables that have already been joined (and should not
  ///   be joined again).
  ///
  /// @returns array:
  ///   An array with the following members:
  ///
  ///   [0] string:
  ///     The LEFT JOIN clauses to add to the query.
  ///
  ///   [1] string[]:
  ///     The subset of azModelNames that are linked by this model.
  //--------------------------------------------------------------------------

  public function favSqlJoin(
    array $azModelNames, array $azAlreadyJoined ) : array {

    $zJoinClauses = '';
    $azLinkedModels = [];

    foreach ( $azModelNames as $zModelName ) { // each name

      foreach ( $this->iSchema->hhColDefs as $zColumn => $hColDef ) { // each column

        if ( array_key_exists( 'hReferences', $hColDef ) &&
          ( $hColDef[ 'hReferences' ][ 'zTable' ] === $zModelName ) )
            { // found model

            if ( ! in_array( $zModelName, $azAlreadyJoined ) ) { // join

              $zSTN = self::$hiModels[ $zModelName ]->zSafeTableName;

              $zJoinClauses .=
                "\n  LEFT JOIN `$zSTN` ON `" .
                $this->zSafeTableName . '`.`' . $hColDef[ 'zSafeColumnName' ] .
                "` = `$zSTN`.`" .
                  self::$hiModels[ $zModelName ]->iSchema->hhColDefs[
                    $hColDef[ 'hReferences' ][ 'zColumn' ] ][ 'zSafeColumnName' ] .
                  '`';

              $azLinkedModels[] = $zModelName;

              } // join

            } // found model

        } // each column

      } // each name

    return [ $zJoinClauses, $azLinkedModels ];

    } // favSqlJoin


  //--------------------------------------------------------------------------
  /// Creates an assignment string for a `SET` clause, pulling the name=value
  /// pairs from the specified document.
  ///
  /// This must only be invoked by `Model::create()` and
  /// `Model::findByIdAndUpdate()`.
  ///
  /// @param $hDoc
  ///   Hash (associative array) containing property names and values for a
  ///   table row.
  ///
  /// @param $bIncludeIds
  ///   If `true`, any ID fields should be set to the values specified in the
  ///   document. This should only be the case when the record is created.
  ///   Defaults to `false`.
  ///
  /// @returns array:
  ///   An array with the following members:
  ///
  ///   [0] string:
  ///     The `SET` string (without the keyword `SET`), with `?` placeholders
  ///     where values must be substituted;
  ///
  ///   [1] mixed[]:
  ///     The values to substitute for the placeholders.
  //--------------------------------------------------------------------------

  private function favSqlSetValues(
    array $hDoc, bool $bIncludeIds = false ) : array {

    $zList = '';
    $avValues = [];
    $bIdIgnored = false;

    foreach ( $hDoc as $zProp => $vValue ) { // each prop

      if ( ! array_key_exists( $zProp, $this->iSchema->hhColDefs ) ) // not a column
        throw new MysqlgooseError(
          is_array( $vValue ) ?
            'nested table update not supported' :
            'unknown column "' . $zProp . '"'
          ); // Error()

      if ( $bIncludeIds || ( $this->iSchema->zIdField !== $zProp ) )
          { // not the ID or a nested table

          if ( $zList )
            $zList .= ', ';

          $zList .= '`' .
            $this->iGoose->getConnection()->real_escape_string( $zProp ) .
            '` = ? ';

          array_push( $avValues,
            self::fvCastForBinding( $vValue,
              [ 'iModel' => $this, 'zColumnName' => $zProp ] ) );

          } // not the ID or a nested table

      else
        $bIdIgnored = true;

      } // each prop

    if ( 0.5 > mb_strlen( $zList ) )
      throw new MysqlgooseError( 'no update specified' .
        ( $bIdIgnored ? ' (IDs are ignored)' : '' ) );

    return [ $zList, $avValues ];

    } // favSqlSetValues

  } // Model

// EOF
