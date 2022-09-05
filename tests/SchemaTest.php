<?php declare(strict_types=1);
//=============================================================================
// Copyright 2019-2022 Opplaud LLC and other contributors. MIT licensed.
//=============================================================================

use Aponica\Mysqlgoose;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase {

  protected static $iGoose;

  private $hhEachResult;

  //---------------------------------------------------------------------------

  public static function setUpBeforeClass(): void {

    $f = require( 'fiTestMysqlgoose.php' );
    [ self::$iGoose, ] = $f();

    } // setUpBeforeClass

  //---------------------------------------------------------------------------

  public function testEachPath() {

    $hhDocDef = [
      'nId' => [ 'zType' => 'int', 'bPrimary' => true ],
      'zName' => [ 'zType' => 'varchar' ]
      ];

    $hhExpect = [];
    foreach ( $hhDocDef as $zKey => $hDef ) {
      $hhExpect[ $zKey ] = array_merge( [], $hDef );
      $hhExpect[ $zKey ][ 'zSafeColumnName' ] =
        self::$iGoose->getConnection()->real_escape_string( $zKey );
      }

    $iSchema = new Aponica\Mysqlgoose\Schema(
      $hhDocDef, self::$iGoose->getConnection() );

    $this->assertSame( 'nId', $iSchema->zIdField );

    $this->hhEachResult = [];
    $that = $this;

    $iSchema->eachPath( function( $z, $h ) use ( $that ) {
      $that->hhEachResult[ $z ] = $h; } );

    foreach ( $this->hhEachResult as $zKey => $hCol )
      $this->assertArrayHasKey( $zKey, $hhExpect );

    foreach ( $hhExpect as $zKey => $hCol ) {

      $this->assertArrayHasKey( $zKey, $this->hhEachResult );

      $hResult = $this->hhEachResult[ $zKey ];

      foreach( $hCol as $zName => $vValue ) {
        $this->assertArrayHasKey( $zName, $hResult );
        $this->assertSame( $vValue, $hResult[ $zName ] );
        }

      } // $zKey=>$hCol

    } // testEachPath

  } // SchemaTest

// EOF


