<?php declare(strict_types=1);
//=============================================================================
// Copyright 2019-2022 Opplaud LLC and other contributors. MIT licensed.
//=============================================================================

namespace Aponica\Mysqlgoose;

//-----------------------------------------------------------------------------
/// A session is used to manage transactions.
///
/// Used like MongoDB's <a
/// href="https://mongodb.github.io/node-mongodb-native/3.3/api/ClientSession.html">
///   ClientSession</a> class.
///
/// Do **not** instantiate a `Session` directly; call
/// `Mysqlgoose::startSession()` instead.
///
/// Only a subset of the methods provided by MongooseJS's class are
/// currently supported, and not always in a fully-compatible way.
/// For most cases, however, there's enough to get by.
//-----------------------------------------------------------------------------

class Session {

  private $iGoose;

  //---------------------------------------------------------------------------
  /// Constructs a session for a specified Mysqlgoose instance.
  ///
  /// Do **not** use this constructor; call `Mysqlgoose::startSession()`
  /// instead.
  ///
  /// @param $iGoose
  ///   The Mysqlgoose object constructing this session.
  ///
  /// @see `Mysqlgoose::startSession()`
  //---------------------------------------------------------------------------

  public function __construct( Mysqlgoose $iGoose ) {
    $this->iGoose = $iGoose;
    }

  //---------------------------------------------------------------------------
  /// Aborts a transaction.
  ///
  /// @throws MysqlgooseError
  ///   If the rollback fails.
  //---------------------------------------------------------------------------

  public function abortTransaction() {
    if ( ! $this->iGoose->fvQuery( 'ROLLBACK' ) )
      throw new MysqlgooseError( 'rollback failed' );
    }

  //---------------------------------------------------------------------------
  /// Commits a transaction.
  ///
  /// @throws MysqlgooseError
  ///   If the rollback fails.
  //---------------------------------------------------------------------------

  public function commitTransaction() {
    if ( ! $this->iGoose->fvQuery( 'COMMIT' ) )
      throw new MysqlgooseError( 'commit failed' );
    }

  //---------------------------------------------------------------------------
  /// Starts a transaction.
  ///
  /// @throws MysqlgooseError
  ///   If `START TRANSACTION` fails.
  //---------------------------------------------------------------------------

  public function startTransaction() {
    if ( ! $this->iGoose->fvQuery( 'START TRANSACTION' ) )
      throw new MysqlgooseError( 'start transaction failed' );
    }

  }; // Session

// EOF
