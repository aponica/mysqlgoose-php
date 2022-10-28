<?php declare(strict_types=1);
//=============================================================================
// Copyright 2019-2022 Opplaud LLC and other contributors. MIT licensed.
//=============================================================================

require_once 'src/Model.php';
require_once 'src/Session.php';

use Aponica\Mysqlgoose\Mysqlgoose;
use PHPUnit\Framework\TestCase;

final class MysqlgooseTest extends TestCase {

  protected static $iGoose;
  protected static $hiModels;

  //---------------------------------------------------------------------------

  public static function setUpBeforeClass(): void {

    $f = require( 'fiTestMysqlgoose.php' );
    [ self::$iGoose, self::$hiModels ] = $f();

    } // setupBeforeClass

  //---------------------------------------------------------------------------

  public function testInvalidConnection() {

    $i = new Mysqlgoose();

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );

    $this->expectExceptionMessageMatches( '/Access denied for user/u' );

    $i->connect( [ 'host' => '',
      'user' => '', 'password' => '', 'database' => '' ] );

    } // testInvalidConnection

  //---------------------------------------------------------------------------

  public function testModels() : void {


    $this->assertNotEquals( null, self::$iGoose );

    $this->assertInstanceOf(
      Aponica\Mysqlgoose\Model::class, self::$hiModels[ 'customer' ] );

    $this->assertInstanceOf(
      Aponica\Mysqlgoose\Model::class, self::$hiModels[ 'order' ] );

    $this->assertInstanceOf(
      Aponica\Mysqlgoose\Model::class, self::$hiModels[ 'order_product' ] );

    $this->assertInstanceOf(
      Aponica\Mysqlgoose\Model::class, self::$hiModels[ 'product' ] );

    $this->assertInstanceOf(
      Aponica\Mysqlgoose\Model::class, self::$hiModels[ 'review' ] );

    } // testModels

  //---------------------------------------------------------------------------

  public function testQueryError() : void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );

    $this->expectExceptionMessage( 'Query was empty (#1065)' );

    self::$iGoose->fvQuery( '' );

    } // testQueryError

  //---------------------------------------------------------------------------

  public function testSetAndDebug() : void {

    $this->assertSame(  self::$iGoose , self::$iGoose->set( 'debug', true ) );

    self::$iGoose->debug( 'coverage test' );

    $avArgs = [ 'four', 5, 'six' ];

    $this->assertSame(
      self::$iGoose,
      self::$iGoose->set( 'debug', function ( ...$avParams ) {
        throw new Exception( json_encode( $avParams ) );
        } )
      ); // assertSame();

    $this->expectException( Exception::class );

    $this->expectErrorMessage( json_encode( $avArgs ) );

    self::$iGoose->debug( ...$avArgs );

    } // testSetAndDebug

  //---------------------------------------------------------------------------

  public function testStartSession() : void {

    $this->assertInstanceOf( Aponica\Mysqlgoose\Session::class,
      self::$iGoose->startSession() );

    } // testStartSession

  //---------------------------------------------------------------------------

  public function testValidConnection() {

    $f = require( 'fiTestMysqlgoose.php' );
    [ $iGoose, $hiModels ] = $f();

    $this->assertInstanceOf( Aponica\Mysqlgoose\Mysqlgoose::class, $iGoose );

    $this->assertInstanceOf( \mysqli::class, $iGoose->getConnection() );

    $this->assertArrayHasKey( 'customer', $hiModels );

    $this->assertInstanceOf( Aponica\Mysqlgoose\Model::class,
      $hiModels[ 'customer' ] );

    $iSchema = $iGoose->Schema(
      [ 'nId' => [ 'zType' => 'int', 'bPrimary' => true ] ],
      $iGoose->getConnection() );

    $this->assertSame( 'nId', $iSchema->zIdField );

    $this->assertSame( true, $iGoose->disconnect() );

    $this->expectError();
    $this->expectErrorMessageMatches(
      '#((mysqli::close\(\): Couldn\'t fetch mysqli)|' .
        '(mysqli object is already closed))#ui' );

    $this->assertSame( false, $iGoose->disconnect() );

    } // testValidConnection

  } // MysqlgooseTest

// EOF
