<?php declare(strict_types=1);
//=============================================================================
// Copyright 2019-2022 Opplaud LLC and other contributors. MIT licensed.
//=============================================================================

use Aponica\Mysqlgoose;
use PHPUnit\Framework\TestCase;

set_include_path( get_include_path() . PATH_SEPARATOR . 'tests-config' );

//---------------------------------------------------------------------------

final class ModelTest extends TestCase {

  private static $iGoose;
  private static $hiModels;

  private static $nNextProductId;

  //---------------------------------------------------------------------------

  public static function setUpBeforeClass(): void {

    $f = require( 'fiTestMysqlgoose.php' );
    [ self::$iGoose, self::$hiModels ] = $f();

    self::$nNextProductId = ( new DateTime() )->getTimestamp();

    } // setupBeforeClass

  //---------------------------------------------------------------------------

  public static function tearDownAfterClass(): void {

    self::$hiModels[ 'review' ]->remove(
      [ 'zText' => [ '$not' => '^test-verified ' ] ] );

    self::$hiModels[ 'review' ]->remove(
      [ 'zText' => [ '$exists' => false ] ] );

    self::$hiModels[ 'order_product' ]->remove( [ 'nId' => [ '$gt' => 1 ] ] );
    self::$hiModels[ 'order' ]->remove( [ 'nId' => [ '$gt' => 1 ] ] );
    self::$hiModels[ 'customer' ]->remove( [ 'nId' => [ '$gt' => 1 ] ] );
    self::$hiModels[ 'product' ]->remove( [ 'nId' => [ '$gt' => 1 ] ] );

    } // tearDownAfterClass

  //---------------------------------------------------------------------------

  private function fCompareOrderProducts( $hActual, $hExpected ) {

    $this->assertArrayHasKey( 'nProductId', $hActual );
    $this->assertSame( $hExpected[ 'nProductId' ], $hActual[ 'nProductId' ] );

    $this->assertArrayHasKey( 'product', $hActual );
    $this->assertArrayHasKey( 'nId', $hActual[ 'product' ] );
    $this->assertSame( $hExpected[ 'nProductId' ], $hActual[ 'product' ][ 'nId' ] );

    $this->assertArrayHasKey( 'nOrderId', $hActual );
    $this->assertSame( $hExpected[ 'nOrderId' ], $hActual[ 'nOrderId' ] );

    $this->assertArrayHasKey( 'order', $hActual );
    $this->assertArrayHasKey( 'nId', $hActual[ 'order' ] );
    $this->assertSame( $hExpected[ 'nOrderId' ], $hActual[ 'order' ][ 'nId' ] );

    $this->assertArrayHasKey( 'nCustomerId', $hActual[ 'order' ] );
    $this->assertSame( $hExpected[ 'order' ][ 'nCustomerId' ],
      $hActual[ 'order' ][ 'nCustomerId' ] );

    $this->assertArrayHasKey( 'customer', $hActual[ 'order' ] );

    $this->assertArrayHasKey( 'nId', $hActual[ 'order' ][ 'customer' ] );
    $this->assertSame( $hExpected[ 'order' ][ 'nCustomerId' ],
      $hActual[ 'order' ][ 'customer' ][ 'nId' ] );

    $this->assertArrayHasKey( 'nId', $hActual[ 'order' ][ 'customer' ] );
    $this->assertSame(
      ( ( 1 === $hExpected[ 'order' ][ 'nCustomerId' ] ) ?
        'First Customer' : 'PopulateOrderPHP' ),
      $hActual[ 'order' ][ 'customer' ][ 'zName' ]
      ); // assertSame()

    } // fCompareOrderProducts


  //---------------------------------------------------------------------------

  private function fExpectRejectionForCustomers( $zMessage, $hConditions ) {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );
    $this->expectExceptionMessage( $zMessage );

    self::$hiModels[ 'customer' ]->find( $hConditions );

    } // fExpectRejectionForCustomers

  //---------------------------------------------------------------------------

  public function testBooleanAlsoOr(): void {

    $ahQueries = [
      [ 'hConditions' => [ 'bVerified' => true ], 'nExpect' => 2 ],
      [ 'hConditions' => [ 'bVerified' => false ], 'nExpect' => 2 ],
      [ 'hConditions' => [ 'bVerified' => null ], 'nExpect' => 2 ],
      [ 'hConditions' => [ 'bVerified' => [ '$eq' => true ] ], 'nExpect' => 2 ],
      [ 'hConditions' => [ 'bVerified' => [ '$eq' => false ] ], 'nExpect' => 2 ],
      [ 'hConditions' => [ 'bVerified' => [ '$eq' => null ] ], 'nExpect' => 2 ],
      [ 'hConditions' => [ 'bVerified' => [ '$exists' => true ] ], 'nExpect' => 4 ],
      [ 'hConditions' => [ 'bVerified' => [ '$exists' => false ] ], 'nExpect' => 2 ],
      [ 'hConditions' => [ 'bVerified' => [ '$ne' => true ] ], 'nExpect' => 4 ],
      [ 'hConditions' => [ 'bVerified' => [ '$ne' => false ] ], 'nExpect' => 4 ],
      [ 'hConditions' => [ 'bVerified' => [ '$ne' => null ] ], 'nExpect' => 4 ],
      [ 'hConditions' => [ '$or' => [ [ 'bVerified' => true ], [ 'bVerified' => false ] ] ], 'nExpect' => 4 ]
      ]; // ahQueries

    foreach ( $ahQueries as $hQuery ) {

      $ahReviews = self::$hiModels[ 'review' ]->find( array_merge(
        [ 'zText' => [ '$regex' => '^test-verified ' ] ],
        $hQuery[ 'hConditions' ]
        ) );

      $this->assertCount( $hQuery[ 'nExpect' ], $ahReviews );

      } // $hQuery

    } // testBooleanAlsoOr


  //---------------------------------------------------------------------------

  public function testCastBooleanDecimalString(): void {

    $nId = self::$nNextProductId++;

    $hDoc = [
      'nId' => $nId,
      'zName' => $nId, // numeric to string
      'nPrice' => '9876.54', // string to decimal
      'bDiscontinued' => true // boolean to numeric
      ]; // hProduct;

    $hResult = self::$hiModels[ 'product' ]->create( $hDoc );

    $this->assertArrayHasKey( 'nId', $hResult );
    $this->assertArrayHasKey( 'zName', $hResult );
    $this->assertArrayHasKey( 'nPrice', $hResult );
    $this->assertArrayHasKey( 'bDiscontinued', $hResult );
    $this->assertSame( $hDoc[ 'nId' ], $hResult[ 'nId' ] );
    $this->assertSame( "$nId", $hResult[ 'zName' ] );
    $this->assertSame( 9876.54, $hResult[ 'nPrice' ] );
    $this->assertSame( 1, $hResult[ 'bDiscontinued' ] );

    $hDoc[ 'bDiscontinued' ] = false;
    $hDoc[ 'nId' ] = self::$nNextProductId++;

    $hResult2 = self::$hiModels[ 'product' ]->create( $hDoc );

    $this->assertSame( 0, $hResult2[ 'bDiscontinued' ] );

    } // testCastBooleanDecimalString


  //---------------------------------------------------------------------------
  //  Tests a lot of the Model code:
  //    create() calls findById() which calls findOne() which calls find().
  //    findByIdAndUpdate() calls findById().
  //    findByIdAndRemove() calls findById() and remove().
  //---------------------------------------------------------------------------

  public function testCustomerLifeCycle(): void {

    $hDoc = [ 'zName' => 'Testy Testalot' ];
    $hResult = self::$hiModels[ 'customer' ]->create( $hDoc );

    $this->assertArrayHasKey( 'nId', $hResult );
    $this->assertArrayHasKey( 'zName', $hResult );
    $this->assertSame( $hDoc[ 'zName' ], $hResult[ 'zName' ] );

    $hModified = self::$hiModels[ 'customer' ]->findByIdAndUpdate( $hResult[ 'nId' ],
      [ 'zName' => 'Testy Test-Some-More' ] );

    $this->assertSame( $hModified[ 'nId' ], $hResult[ 'nId' ] );
    $this->assertSame( $hModified[ 'zName' ], 'Testy Test-Some-More' );

    $hRemoved = self::$hiModels[ 'customer' ]->findByIdAndRemove( $hModified[ 'nId' ] );

    $this->assertSame( $hRemoved[ 'nId' ], $hModified[ 'nId' ] );
    $this->assertSame( $hRemoved[ 'zName' ], $hModified[ 'zName' ] );

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );
    $this->expectExceptionMessage( '0 rows deleted' );

    self::$hiModels[ 'customer' ]->findByIdAndRemove( $hRemoved[ 'nId' ] );

    } // testCustomerLifeCycle

  //---------------------------------------------------------------------------

  public function testDateTimeHandling(): void {

    $hOrder = self::$hiModels[ 'order' ]->findById( 1 );

    $hOrder[ 'dtPaid' ] = new DateTime();

    $hOrder2 = self::$hiModels[ 'order' ]->findByIdAndUpdate( 1, $hOrder );

    $this->assertEquals(
      $hOrder[ 'dtPaid' ]->getTimestamp(),
      $hOrder2[ 'dtPaid' ]->getTimestamp()
      );

    $hOrder2[ 'dtPaid' ] = null;

    $hOrder3 = self::$hiModels[ 'order' ]->findByIdAndUpdate( 1, $hOrder2 );

    $this->assertEquals( null, $hOrder3[ 'dtPaid' ] );

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );
    $this->expectExceptionMessage( 'dtPaid must be a DateTime' );

    self::$hiModels[ 'order' ]->findByIdAndUpdate( 1, [ 'dtPaid' => 'not a date' ] );

    } // testDateTimeHandling

  //---------------------------------------------------------------------------

  public function testDecimalHandling(): void {

    $hProduct = self::$hiModels[ 'product' ]->findById( 1 );

    $hProduct[ 'nPrice' ] = rand( 1, 100000 ) / 100;

    $hProduct2 = self::$hiModels[ 'product' ]->findByIdAndUpdate( 1, $hProduct );

    $this->assertEquals(
      sprintf( '%.2f', $hProduct[ 'nPrice' ] ),
      sprintf( '%.2f', $hProduct2[ 'nPrice' ] )
      );

    $hProduct2[ 'nPrice' ] = null;

    $hProduct3 = self::$hiModels[ 'product' ]->findByIdAndUpdate( 1, $hProduct2 );

    $this->assertEquals( null, $hProduct3[ 'nPrice' ] );

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );
    $this->expectExceptionMessage( 'nPrice must be numeric' );

    self::$hiModels[ 'product' ]->findByIdAndUpdate( 1, [ 'nPrice' => 'xyz' ] );

    } // testDecimalHandling

  //---------------------------------------------------------------------------

  public function testFieldOperator(): void {

    //  Greater than.

    $ahResults = self::$hiModels[ 'review' ]->find(
        [ 'product' => [ 'nId' => 1,
          'zName' => [ '$gt' => [ '$FIELD' => [ 'review' => 'zUser' ] ] ] ] ] );

    foreach ( $ahResults as $hResult )
      $this->assertGreaterThan(
        $hResult[ 'zUser' ], $hResult[ 'product' ][ 'zName' ] );


    //  Greater than or equal.

    $ahResults = self::$hiModels[ 'review' ]->find(
        [ 'product' => [ 'nId' => 1,
          'zName' => [ '$gte' => [ '$FIELD' => [ 'review' => 'zUser' ] ] ] ] ] );

    foreach ( $ahResults as $hResult )
      $this->assertGreaterThanOrEqual(
        $hResult[ 'zUser' ], $hResult[ 'product' ][ 'zName' ] );


    //  Less than.

    $ahResults = self::$hiModels[ 'review' ]->find(
        [ 'product' => [ 'nId' => 1,
          'zName' => [ '$lt' => [ '$FIELD' => [ 'review' => 'zUser' ] ] ] ] ] );

    foreach ( $ahResults as $hResult )
      $this->assertLessThan(
        $hResult[ 'zUser' ], $hResult[ 'product' ][ 'zName' ] );


    //  Less than or equal.

    $ahResults = self::$hiModels[ 'review' ]->find(
        [ 'product' => [ 'nId' => 1,
          'zName' => [ '$lte' => [ '$FIELD' => [ 'review' => 'zUser' ] ] ] ] ] );

    foreach ( $ahResults as $hResult )
      $this->assertLessThanOrEqual(
        $hResult[ 'zUser' ], $hResult[ 'product' ][ 'zName' ] );


    //  Add a new product and review with product.zName = review.zText
    //  as well as significantly-related values for nId and nPrice.

    self::$hiModels[ 'product' ]->create(
      [ 'nId' => 123, 'zName' => 'zName=zText', 'nPrice' => 249 ] );

    self::$hiModels[ 'review' ]->create(
      [ 'nProductId' => 123, 'zText' => 'zName=zText' ] );


    //  Equal ( default and $eq).

    $ahResults = self::$hiModels[ 'review' ]->find(
        [ 'zText' => [ '$FIELD' => [ 'product' => 'zName' ] ] ] );

    foreach ( $ahResults as $hResult )
      $this->assertEquals(
        $hResult[ 'zText' ], $hResult[ 'product' ][ 'zName' ] );

    $ahResults = self::$hiModels[ 'review' ]->find(
        [ 'zText' => [ '$eq' => [ '$FIELD' => [ 'product' => 'zName' ] ] ] ] );

    foreach ( $ahResults as $hResult )
      $this->assertEquals(
        $hResult[ 'zText' ], $hResult[ 'product' ][ 'zName' ] );


    //  Not equal.

    $ahResults = self::$hiModels[ 'review' ]->find( [
      'zUser' => [ '$ne' => [ '$FIELD' => [ 'product' => 'zName' ] ] ] ] );

    foreach ( $ahResults as $hResult )
      $this->assertNotEquals(
        $hResult[ 'zUser' ], $hResult[ 'product' ][ 'zName' ] );


    //  $mod.

    $ahResults = self::$hiModels[ 'product' ]->find( [
      'nPrice' => [ '$mod' => [ [ '$FIELD' => [ 'product' => 'nId' ] ], 3 ] ] ] );

    foreach ( $ahResults as $hResult )
      $this->assertEquals(
        3, $hResult[ 'nPrice' ] % $hResult[ 'nId' ] );

    $ahResults = self::$hiModels[ 'product' ]->find( [
      'nPrice' => [ '$mod' => [ 126, [ '$FIELD' => [ 'product' => 'nId' ] ] ] ] ] );

    foreach ( $ahResults as $hResult )
      $this->assertEquals(
        $hResult[ 'nId' ], $hResult[ 'nPrice' ] % 126 );


    //  Add a new product and review where product.zName is a regular
    //  expression that matches on the review text.

    self::$hiModels[ 'product' ]->create(
      [ 'nId' => 456, 'zName' => '^[X4Y5Z6]+$', 'nPrice' => 249 ] );

    self::$hiModels[ 'review' ]->create(
      [ 'nProductId' => 456, 'zText' => 'XYZ456' ] );


    //  Regular expression.

    $ahResults = self::$hiModels[ 'review' ]->find( [
      'zText' => [ '$regex' => [ '$FIELD' => [ 'product' => 'zName' ] ] ] ] );

    foreach ( $ahResults as $hResult )
      $this->assertMatchesRegularExpression(
        '/' . $hResult[ 'product' ][ 'zName' ] . '/u',
        $hResult[ 'zText' ] );


    //  Negated regular expression.

    $ahResults = self::$hiModels[ 'review' ]->find( [
      'zText' => [ '$not' => [ '$FIELD' => [ 'product' => 'zName' ] ] ] ] );

    foreach ( $ahResults as $hResult )
      $this->assertDoesNotMatchRegularExpression(
        '/' . $hResult[ 'product' ][ 'zName' ] . '/u',
        $hResult[ 'zText' ] );

    } // testFieldOperator

  //---------------------------------------------------------------------------

  public function testFindAnyOfMany(): void {

    self::$hiModels[ 'product' ]->create(
      [ 'nId' => 2222, 'zName' => 'FindAnyOfMany' ] );

    $hReview = [ 'nProductId' => 2222, 'zUser' => 'Multiplo' ];

    self::$hiModels[ 'review' ]->create( $hReview );

    $hResult = self::$hiModels[ 'review' ]->create( $hReview );

    $this->assertEquals( $hReview[ 'nProductId' ], $hResult[ 'nProductId' ] );
    $this->assertEquals( $hReview[ 'zUser' ], $hResult[ 'zUser' ] );
    $this->assertEquals( null, $hResult[ 'zText' ] );
    $this->assertEquals( null, $hResult[ 'bVerified' ] );

    } // testFindAnyOfMany


  //---------------------------------------------------------------------------

  public function testFindWithoutConditions(): void {

    $ahResults = self::$hiModels[ 'customer' ]->find();

    $this->assertGreaterThanOrEqual(  1 ,  count( $ahResults ) );
    $this->assertContains(  1,
      array_map( function( $h ) { return $h[ 'nId' ]; }, $ahResults ) );

    } // testFindWithoutConditions


  //---------------------------------------------------------------------------

  public function testJoinAlreadyJoined(): void {

    $az1 = [ 'order', 'product' ];
    $az2 = [ 'order' ];

    $aResult = self::$hiModels[ 'order_product' ]->favSqlJoin( $az1, $az2 );

    $this->assertCount( 2, $aResult  );

    $this->assertSame(
      'LEFT JOIN `product` ON `order_product`.`nProductId` = `product`.`nId`' ,
      trim( $aResult[ 0 ] ) );

    $this->assertCount( 1, $aResult[ 1 ]  );

    $this->assertSame( 'product', $aResult[ 1 ][ 0 ] );

    } // testJoinAlreadyJoined


  //---------------------------------------------------------------------------

  public function testMultiNestedQueries(): void {

    $ahResult = self::$hiModels[ 'order_product' ]->find( [
      'order' => [ 'nId' => 1 ],
      'product' => [ 'nId' => 1 ],
      'nId' => 1
      ] ); // find()

    $this->assertCount( 1, $ahResult  );

    $this->assertSame( 1, $ahResult[ 0 ][ 'nId' ] );
    $this->assertSame( 1, $ahResult[ 0 ][ 'order' ][ 'nId' ] );
    $this->assertSame( 1, $ahResult[ 0 ][ 'product' ][ 'nId' ] );

    } // testMultiNestedQueries


  //---------------------------------------------------------------------------

  public function testNot(): void {

    $ahCustomers = self::$hiModels[ 'customer' ]->find(
      [ '$not' => [ 'zName' => 'First Customer' ] ] );

    $this->assertSame( true , is_array( $ahCustomers ) );

    foreach ( $ahCustomers as $hCustomer )
      $this->assertNotEquals( 'First Customer', $hCustomer[ 'zName' ] );


    $ahProducts = self::$hiModels[ 'product' ]->find(
      [ 'zName' => [ '$not' => '^Primary Product$' ] ] );

    $this->assertSame(  true ,  is_array( $ahProducts )  );

    foreach ( $ahProducts as $hProduct )
      $this->assertNotEquals( 'Primary Product', $hProduct[ 'zName' ] );

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );
    $this->expectExceptionMessage( 'use $ne instead of $not to test values' );

    self::$hiModels[ 'customer' ]->find( [ 'nId' => [ '$not' => 1 ] ] );

    } // testNot


  //---------------------------------------------------------------------------

  public function testNumericComparisonsAndRegex(): void {

    $zPrefix = 'NumCompare-' . (new DateTime())->getTimestamp() . '-';

    for ( $n = 0 ; $n < 9 ; $n++ )
      self::$hiModels[ 'product' ]->create(
        [ 'nId' => self::$nNextProductId++,
          'zName' => $zPrefix . $n, 'nPrice' => (1+$n) ] );

    $azOps = [ '$eq', '$gt', '$gte', '$lt', '$lte', '$ne', '$in', '$nin', '$mod' ];

    $aahResults = [];

    for ( $n = 0 ; $n < 9 ; $n++ )
      $aahResults[] = self::$hiModels[ 'product' ]->find( [
        '$and' => [
          [ 'zName' => [ '$regex' => '^' . $zPrefix . '[0-9]$' ] ],
          [ 'nPrice' => [ $azOps[ $n ] => (
              ( '$mod' === $azOps[ $n ] ) ? [ 3, 0 ] : (
                in_array( $azOps[ $n ], [ '$in', '$nin' ] ) ? [ 4, 5, 6 ] : 5 )
              ) ] ]
          ],
        'nId' => [ '$exists' => true ] // always true
        ] );

    $anExpect = [ 1, 4, 5, 4, 5, 8, 3, 6, 3 ];

    for ( $n = 0 ; $n < 9 ; $n++ )
      $this->assertCount(  $anExpect[ $n ], $aahResults[ $n ]  );

    } // testNumericComparisonsAndRegex()


  //---------------------------------------------------------------------------

  public function testOrderByLimitSkipTextComment(): void {

    $dtPaid = new DateTime();

    $zPrefix = 'OLSTC-PHP-' . (new DateTime())->getTimestamp() . '-';

    $ahCustomers = [];
    for ( $n = 0 ; $n < 10 ; $n++ )
      $ahCustomers[] = self::$hiModels[ 'customer' ]->create( [ 'zName' => "$zPrefix$n" ] );

    for ( $nCust = 0 ; $nCust < 10 ; $nCust++ )
      self::$hiModels[ 'order' ]->create(
        [ 'nCustomerId' => $ahCustomers[ $nCust ][ 'nId' ] ] );

    $ahCustomers = self::$hiModels[ 'customer' ]->find( [
      'zName' => [
        '$text' => [ '$search' => $zPrefix . '%', '$language' => 'utf8_unicode_ci' ],
        '$limit' => 4,
        '$skip' => 3,
        '$comment' => 'nested comment'
        ],
      '$orderby' => [ 'zName' => -1 ],
      '$comment' => 'outer comment'
      ] );

    $this->assertCount( 4, $ahCustomers );

    for ( $n = 1 ; $n < 4 ; $n++ )
      $this->assertSame( $zPrefix . (6-$n), $ahCustomers[ $n ][ 'zName' ] );

    $ahOrders = self::$hiModels[ 'order' ]->find( [
      'customer' => [ 'zName' => [ '$in' =>
        array_map( function ( $hCust ) { return $hCust[ 'zName' ]; }, $ahCustomers )
        ] ],
      '$orderby' => [ 'customer' => [ 'zName' => 1 ] ]
      ] ); // find()

    $this->assertCount( 4, $ahOrders );

    $zPrev = $ahOrders[ 0 ][ 'customer' ][ 'zName' ];

    for ( $n = 1 ; $n < 4 ; $n++ ) {
      $this->assertSame( true, ( $zPrev < $ahOrders[ $n ][ 'customer' ][ 'zName' ] ) );
      $zPrev = $ahOrders[ $n ][ 'customer' ][ 'zName' ];
      }

    } // testDecimalHandling

  //---------------------------------------------------------------------------

  public function testOrderByTwice1(): void {

    $ahOrders = self::$hiModels[ 'order' ]->find( [
      'nId' => 1,
      '$orderby' => [ 'nId' => 1 ],
      'customer' => [ '$orderby' => [ 'zName' => 1 ] ]
      ] );

    $this->assertCount( 1 , $ahOrders  );

    } // testOrderByTwice1


  //---------------------------------------------------------------------------

  public function testOrderByTwice2(): void {

    $ahOrders = self::$hiModels[ 'order' ]->find( [
      'customer' => [ '$orderby' => [ 'zName' => 1 ] ],
      'nId' => 1,
      '$orderby' => [ 'nId' => 1 ]
      ] );

    $this->assertCount( 1 , $ahOrders );

    } // testOrderByTwice2

  //---------------------------------------------------------------------------

  public function testPopulateOrder(): void {

    $nProductId = self::$nNextProductId++;

    $hCustomer = self::$hiModels[ 'customer' ]->create( [ 'zName' => 'PopulateOrderPHP' ] );

    $hProduct = self::$hiModels[ 'product' ]->create(
      [ 'nId' => $nProductId, 'zName' => 'widget', 'nPrice' => 123.45 ] );

    $hOrder = self::$hiModels[ 'order' ]->create( [ 'nCustomerId' => $hCustomer[ 'nId' ] ] );

    $hCreateOrderProduct =
      [ 'nOrderId' => $hOrder[ 'nId' ], 'nProductId' => $nProductId ];

    $hOrderProduct = self::$hiModels[ 'order_product' ]->create( $hCreateOrderProduct );

    $hPopulated = self::$hiModels[ 'order_product' ]->findOne(
      $hOrderProduct,
      null,
      [ Mysqlgoose\Mysqlgoose::POPULATE => [ 'order', 'product', 'customer' ] ]
      ); // findOne()

    $hExpected = array_merge(
      $hCreateOrderProduct,
      [ 'order' => $hOrder, 'product' => $hProduct ],
      $hOrderProduct
      ); // array_merge()

    $this->fCompareOrderProducts( $hPopulated, $hExpected );

    } // testPopulateOrder

  //---------------------------------------------------------------------------

  public function testPopulateOrderProduct(): void {

    $hExpected = [
      'nId' => 1,
      'nOrderId' => 1,
      'nProductId' => 1,
      'order' => [ 'nId' => 1, 'nCustomerId' => 1, 'customer' => [ 'nId' => 1 ] ],
      'product' => [ 'nId' => 1 ]
      ]; // hExpected

    $ahActuals =  [

      self::$hiModels[ 'order_product' ]->findOne( $hExpected ), // auto-populate

      self::$hiModels[ 'order_product' ]->findById( 1, null, // explicit
        [ self::$iGoose::POPULATE => [ 'order', 'product', 'customer' ] ]
        )

      ]; // $aahResults

    foreach ( $ahActuals as $hActual )
      $this->fCompareOrderProducts( $hActual, $hExpected );

    } // testPopulateOrderProduct

  //---------------------------------------------------------------------------

  public function testRejectAndContainsLimit(): void {
    $this->fExpectRejectionForCustomers(
      '$and cannot contain $limit',
      [ '$and' => [ [ 'nId' => 1 ], [ '$limit' => 1 ] ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectAndContainsSkip(): void {
    $this->fExpectRejectionForCustomers(
      '$and cannot contain $skip',
      [ '$and' => [ [ 'nId' => 1 ], [ '$skip' => 1 ] ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectAndContainsOrderby(): void {
    $this->fExpectRejectionForCustomers(
      '$and cannot contain $orderby',
      [ '$and' => [ [ 'nId' => 1 ], [ '$orderby' => [ 'nId' => 1 ] ] ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectAndWithOneItem(): void {
    $this->fExpectRejectionForCustomers(
      '$and array must have 2+ members',
      [ '$and' => [ [ 'zName' => 'NotEnough' ] ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectAndWithoutArray(): void {
    $this->fExpectRejectionForCustomers(
      '$and must be an array',
      [ '$and' => 1 ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectAndArrayWithoutObject(): void {
    $this->fExpectRejectionForCustomers(
      '$and array member #0 must be a nested associative array',
      [ '$and' => [ 1, 2 ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectCreateMissingProductId(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );

    $this->expectExceptionMessage(
      "Field 'nProductId' doesn't have a default value" );

    self::$hiModels[ 'review' ]->create( [
      'zUser' => 'Miss P. Aydee',
      'zText' => 'should not be added without product ID'
      ] );

    } // testRejectCreateMissingProductId

  //---------------------------------------------------------------------------

  public function testRejectFieldOperatorMisused(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );
    $this->expectExceptionMessage( 'unknown column: $FIELD' );

    self::$hiModels[ 'review' ]->find(
      [ '$FIELD' => [ 'product' => 'nId' ] ] );

    } // testRejectFieldOperatorMisused

  //---------------------------------------------------------------------------

  public function testRejectFieldOperatorNotAlone(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );
    $this->expectExceptionMessage( '$FIELD must be used alone' );

    self::$hiModels[ 'review' ]->find(
      [ 'nProductId' => [ '$FIELD' => 1, 'extra' => 2 ] ] );

    } // testRejectFieldOperatorNotAlone

  //---------------------------------------------------------------------------

  public function testRejectFieldOperatorNotArray(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );
    $this->expectExceptionMessage( '$FIELD must be an array' );

    self::$hiModels[ 'review' ]->find(
      [ 'nProductId' => [ '$FIELD' => null ] ] );

    } // testRejectFieldOperatorNotArray

  //---------------------------------------------------------------------------

  public function testRejectFieldOperatorTwoMembers(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );
    $this->expectExceptionMessage( '$FIELD must have one member' );

    self::$hiModels[ 'review' ]->find(
      [ 'nProductId' => [ '$FIELD' => [ 'a' => 1, 'b' => 2 ] ] ] );

    } // testRejectFieldOperatorTwoMembers

  //---------------------------------------------------------------------------

  public function testRejectFieldOperatorUnknownColumn(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );
    $this->expectExceptionMessage( '$FIELD: unknown column: product.x' );

    self::$hiModels[ 'review' ]->find(
      [ 'nProductId' => [ '$FIELD' => [ 'product' => 'x' ] ] ] );

    } // testRejectFieldOperatorUnknownColumn

  //---------------------------------------------------------------------------

  public function testRejectFieldOperatorUnknownModel(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );
    $this->expectExceptionMessage( '$FIELD: unknown model: x' );

    self::$hiModels[ 'review' ]->find(
      [ 'nProductId' => [ '$FIELD' => [ 'x' => 1 ] ] ] );

    } // testRejectFieldOperatorUnknownModel

  //---------------------------------------------------------------------------

  public function testRejectFieldOperatorZeroMembers(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );
    $this->expectExceptionMessage( '$FIELD must have one member' );

    self::$hiModels[ 'review' ]->find(
      [ 'nProductId' => [ '$FIELD' => [] ] ] );

    } // testRejectFieldOperatorZeroMembers

  //---------------------------------------------------------------------------

  public function testRejectFindByIdOnTableWithoutId(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );

    $this->expectExceptionMessage( 'no primary key for review' );

    self::$hiModels[ 'review' ]->findById( 1 );

    } // testRejectFindByIdOnTableWithoutId

  //---------------------------------------------------------------------------

  public function testRejectFindInScalar(): void {
    $this->fExpectRejectionForCustomers(
      '$in requires [ value, ... ]',
      [ 'zName' => [ '$in' => 'NotAnArray' ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectFindNinScalar(): void {
    $this->fExpectRejectionForCustomers(
      '$nin requires [ value, ... ]',
      [ 'zName' => [ '$nin' => 'NotAnArray' ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectInvalidCondition(): void {
    $this->fExpectRejectionForCustomers(
      '$InvalidCondition is not supported',
      [ '$InvalidCondition' => 1 ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectLimitTwice1(): void {
    $this->fExpectRejectionForCustomers(
      '$limit can only appear once',
      [ 'zName' => [ '$limit' => 2 ], '$limit' => 1 ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectLimitTwice2(): void {
    $this->fExpectRejectionForCustomers(
      '$limit can only appear once',
      [ 'nId' => [ '$limit' => 1 ], 'zName' => [ '$limit' => 2 ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectMeta(): void {
    $this->fExpectRejectionForCustomers(
      '$meta not supported because $text uses LIKE',
      [ '$meta' => 1 ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectModWithExtraMembers(): void {
    $this->fExpectRejectionForCustomers(
      '$mod requires [ divisor, remainder ]',
      [ '$mod' => [ 5, 1, 'extra' ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectModWithoutArray(): void {
    $this->fExpectRejectionForCustomers(
      '$mod requires [ divisor, remainder ]',
      [ '$mod' => 1 ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectModWithoutRemainder(): void {
    $this->fExpectRejectionForCustomers(
      '$mod requires [ divisor, remainder ]',
      [ '$mod' => [ 5 ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectNotContainsLimit(): void {
    $this->fExpectRejectionForCustomers(
      '$not cannot contain $limit',
      [ '$not' => [ '$limit' => 1 ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectNotContainsSkip(): void {
    $this->fExpectRejectionForCustomers(
      '$not cannot contain $skip',
      [ '$not' => [ '$skip' => 1 ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectNotContainsOrderby(): void {
    $this->fExpectRejectionForCustomers(
      '$not cannot contain $orderby',
      [ '$not' => [ '$orderby' => [ 'nId' => 1 ] ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectNoUpdateSpecified(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );

    $this->expectExceptionMessage( 'no update specified' );

    self::$hiModels[ 'order' ]->findByIdAndUpdate( 1, [] );

    } // testRejectNoUpdateSpecified

  //---------------------------------------------------------------------------

  public function testRejectNoUpdateSpecifiedIdsIgnored(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );

    $this->expectExceptionMessage( 'no update specified (IDs are ignored)' );

    self::$hiModels[ 'order' ]->findByIdAndUpdate( 1, [ 'nId' => 1 ] );

    } // testRejectNoUpdateSpecified

  //---------------------------------------------------------------------------

  public function testRejectOrContainsLimit(): void {
    $this->fExpectRejectionForCustomers(
      '$or cannot contain $limit',
      [ '$or' => [ [ 'nId' => 1 ], [ '$limit' => 1 ] ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectOrContainsSkip(): void {
    $this->fExpectRejectionForCustomers(
      '$or cannot contain $skip',
      [ '$or' => [ [ 'nId' => 1 ], [ '$skip' => 1 ] ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectOrContainsOrderby(): void {
    $this->fExpectRejectionForCustomers(
      '$or cannot contain $orderby',
      [ '$or' => [ [ 'nId' => 1 ], [ '$orderby' => [ 'nId' => 1 ] ] ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectOrWithOneItem(): void {
    $this->fExpectRejectionForCustomers(
      '$or array must have 2+ members',
      [ '$or' => [ [ 'zName' => 'NotEnough' ] ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectOrWithoutArray(): void {
    $this->fExpectRejectionForCustomers(
      '$or must be an array',
      [ '$or' => 1 ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectOrArrayWithoutObject(): void {
    $this->fExpectRejectionForCustomers(
      '$or array member #0 must be a nested array',
      [ '$or' => [ 1, 2 ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectOrderbyFieldNotFound(): void {
    $this->fExpectRejectionForCustomers(
      '$orderby field bar not found',
      [ '$orderby' => [ 'bar' => 1 ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectOrderbyNotHash(): void {
    $this->fExpectRejectionForCustomers(
      '$orderby must be a hash array',
      [ '$orderby' => 1 ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectOrderbyString(): void {
    $this->fExpectRejectionForCustomers(
      '$orderby expected integer direction, found "bar"',
      [ '$orderby' => [ 'nId' => 'bar' ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectPopulateNotArray(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );

    $this->expectExceptionMessage( 'option POPULATE must be an array' );

    self::$hiModels[ 'order' ]->find( [], null,
      [ Aponica\Mysqlgoose\Mysqlgoose::POPULATE => 'customer' ] );

    } // testRejectPopulateNotArray

  //---------------------------------------------------------------------------

  public function testRejectQuery(): void {
    $this->fExpectRejectionForCustomers(
      'specify a query without $query',
      [ '$query' => 1 ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectRegexNonString(): void {
    $this->fExpectRejectionForCustomers(
      'specify $regex value as a string (without delimiters)',
      [ '$regex' => 1 ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectRemoveError(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );

    $this->expectExceptionMessage( "unknown column: NonExistent" );

    self::$hiModels[ 'product' ]->remove( [ 'NonExistent' => 1 ] );

    } // testRejectRemoveError

  //---------------------------------------------------------------------------

  public function testRejectSkipTwice1(): void {
    $this->fExpectRejectionForCustomers(
      '$skip can only appear once',
      [ '$limit' => 1, '$skip' => 1, 'zName' => [ '$skip' => 1 ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectSkipTwice2(): void {
    $this->fExpectRejectionForCustomers(
      '$skip can only appear once',
      [ '$limit' => 1, 'zName' => [ '$skip' => 1 ], '$skip' => 1 ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectSkipWithoutLimit(): void {
    $this->fExpectRejectionForCustomers(
      'cannot use $skip without $limit',
      [ '$skip' => 1 ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectTextWithoutSearch(): void {
    $this->fExpectRejectionForCustomers(
      '$text requires $search',
      [ 'zName' => [ '$text' => [] ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectTextWithCaseSensitive(): void {
    $this->fExpectRejectionForCustomers(
      'use $language instead of $caseSensitive',
      [ 'zName' => [ '$text' => [ '$search' => 'foo%', '$caseSensitive' => true ] ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectTextWithDiacriticSensitive(): void {
    $this->fExpectRejectionForCustomers(
      'use $language instead of $diacriticSensitive',
      [ 'zName' => [ '$text' => [ '$search' => 'foo%', '$diacriticSensitive' => true ] ] ] );
    }

  //---------------------------------------------------------------------------

  public function testRejectUpdateIdWithoutOption(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );

    $this->expectExceptionMessage( 'no update specified (IDs are ignored)' );

    self::$hiModels[ 'product' ]->findByIdAndUpdate( 1, [ 'nId' => 1 ] );

    } // testRejectUpdateIdWithoutOption

  //---------------------------------------------------------------------------

  public function testRejectUpdateInvalidColumn(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );

    $this->expectExceptionMessage( 'unknown column "foo"' );

    self::$hiModels[ 'product' ]->findByIdAndUpdate( 1, [ 'foo' => 1 ] );

    } // testRejectUpdateInvalidColumn

  //---------------------------------------------------------------------------

  public function testRejectUpdateMissingId(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );

    $this->expectExceptionMessage( '0 rows updated' );

    self::$hiModels[ 'product' ]->findByIdAndUpdate( 0, [ 'zName' => 'IncorrectlyUpdated' ] );

    } // testRejectUpdateMissingId

  //---------------------------------------------------------------------------

  public function testRejectUpdateNestedTable(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );

    $this->expectExceptionMessage( 'nested table update not supported' );

    self::$hiModels[ 'order_product' ]->findByIdAndUpdate( 1,
      [ 'product' => [ 'zName' => 'IncorrectlyUpdated' ] ] );

    } // testRejectUpdateNestedTable

  //---------------------------------------------------------------------------

  public function testRejectUpdateNotSpecified(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );

    $this->expectExceptionMessage( 'no update specified' );

    self::$hiModels[ 'product' ]->findByIdAndUpdate( 1, [] );

    } // testRejectUpdateNotSpecified

  //---------------------------------------------------------------------------

  public function testRejectUpdateOnTableWithoutId(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );

    $this->expectExceptionMessage( 'no primary key for review' );

    self::$hiModels[ 'review' ]->findByIdAndUpdate( 1, [] );

    } // testRejectUpdateOnTableWithoutId

  //---------------------------------------------------------------------------

  public function testRejectUpdateInvalid(): void {

    $this->expectException( Aponica\Mysqlgoose\MysqlgooseError::class );

    $this->expectExceptionMessage(
      "Incorrect integer value: 'foo' for column 'nCustomerId' at row 1" );

    self::$hiModels[ 'order' ]->findByIdAndUpdate( 1, [ 'nCustomerId' => 'foo' ] );

    } // testRejectUpdateInvalid

  } // ModelTest

// EOF
