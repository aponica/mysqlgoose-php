<?php declare(strict_types=1);
//=============================================================================
// Copyright 2019-2022 Opplaud LLC and other contributors. MIT licensed.
//=============================================================================

use Aponica\Mysqlgoose;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase {

  protected static $iGoose;
  protected static $hiModels = [];

  //---------------------------------------------------------------------------

  public function faCreateTransaction( $bValid ) {

    $nId = null;
    $zCustName = 'PHP Session ' . ( $bValid ? 'COMMIT' : 'ABORT' );

    $iSession = new Mysqlgoose\Session( self::$iGoose );

    $iSession->startTransaction();

    try {
      $hCust = self::$hiModels[ 'customer' ]->create( [ 'zName' => $zCustName ] );

      $nId = $hCust[ 'nId' ]; // also use as product ID
      $zProdName = "PHP Session Customer $nId";

      self::$hiModels[ 'product' ]->create( [
        'nId' => $nId, 'zName' => $zProdName,
        'nPrice' => rand( 0, 999999 ) / 100
        ] );

      $hOrder = self::$hiModels[ 'order' ]-> create(
        [ 'nCustomerId' => $nId ] );

      $hOrderProduct = [ 'nOrderId' => $hOrder[ 'nId' ] ];
      if ( $bValid )
        $hOrderProduct[ 'nProductId' ] = $nId;

      self::$hiModels[ 'order_product' ]-> create( $hOrderProduct );

      $iSession->commitTransaction();
      }
    catch( Throwable $t ) {
      $iSession->abortTransaction();
      }

    $ahResults = self::$hiModels[ 'order_product' ]->find(
      [ 'nProductId' => $nId, 'customer' => [ 'nId' => $nId ] ],
      null,
      [ Mysqlgoose\Mysqlgoose::POPULATE =>
        [ 'product', 'order' ] ]
      ); // find()

    return [ 'nId' => $nId, 'zCustomerName' => $zCustName,
      'zProductName' => $zProdName, 'ahOrderProduct' => $ahResults ];

    } // faCreateTransaction

  //---------------------------------------------------------------------------

  public static function setUpBeforeClass(): void {

    $f = require( 'fiTestMysqlgoose.php' );
    [ self::$iGoose, self::$hiModels ] = $f();

    } // setUpBeforeClass

  //---------------------------------------------------------------------------

  public function testCommit() {

    $hvResults = $this->faCreateTransaction( true );

    $this->assertSame( 1, count( $hvResults[ 'ahOrderProduct' ] ) );

    $hResult = $hvResults[ 'ahOrderProduct' ][ 0 ];

    $this->assertArrayHasKey( 'product', $hResult );

    $this->assertSame( $hvResults[ 'nId' ], $hResult[ 'product' ][ 'nId' ] );

    $this->assertSame( $hvResults[ 'zProductName' ],
      $hResult[ 'product' ][ 'zName' ] );

    $this->assertArrayHasKey( 'order', $hResult );

    $this->assertArrayHasKey( 'customer', $hResult[ 'order' ] );

    $this->assertSame( $hvResults[ 'nId' ],
      $hResult[ 'order' ] [ 'customer' ][ 'nId' ] );

    $this->assertSame( $hvResults[ 'zCustomerName' ],
      $hResult[ 'order' ] [ 'customer' ][ 'zName' ] );

    } // testCommit

  //---------------------------------------------------------------------------

  public function testRollback() {

    $hvResults = $this->faCreateTransaction( false );

    $this->assertCount( 0, $hvResults[ 'ahOrderProduct' ] );

    $ahProducts = self::$hiModels[ 'product' ]->
      find( [ 'zName' => $hvResults[ 'zProductName' ] ] );

    $this->assertCount( 0, $ahProducts );

    $ahCusts = self::$hiModels[ 'customer' ]->
      find( [ 'zName' => $hvResults[ 'zCustomerName' ] ] );

    $this->assertCount( 0, $ahCusts );

    $ahOrders = self::$hiModels[ 'order' ]->
      find( [ 'nCustomerId' => $hvResults[ 'nId' ] ] );

    $this->assertCount( 0, $ahOrders );

    $ahOrderProducts = self::$hiModels[ 'order_product' ]->
      find( [ 'nProductId' => $hvResults[ 'nId' ] ] );

    $this->assertCount( 0, $ahOrderProducts );

    } // testRollback

} // SchemaTest

// EOF


