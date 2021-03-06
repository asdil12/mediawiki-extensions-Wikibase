<?php

namespace Wikibase\Test;

use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\SimpleSiteLink;
use Wikibase\Entity;
use Wikibase\Item;
use Wikibase\Property;

/**
 * @covers Wikibase\Test\MockRepository
 *
 * @since 0.4
 *
 * @group Wikibase
 * @group WikibaseLib
 * @group WikibaseEntityLookup
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class MockRepositoryTest extends \MediaWikiTestCase {

	/* @var MockRepository */
	protected $repo;

	public function setUp() {
		parent::setUp();
		$this->repo = new MockRepository();
	}

	public function testHasEntity() {
		$q23 = new ItemId( 'q23' );
		$q42 = new ItemId( 'q42' );

		$p23 = new PropertyId( 'p23' );
		$p42 = new PropertyId( 'p42' );

		$item = Item::newEmpty();
		$item->setId( $q23 );
		$this->repo->putEntity( $item );

		$prop = Property::newEmpty();
		$prop->setId( $p23 );
		$this->repo->putEntity( $prop );

		// test item
		$this->assertTrue( $this->repo->hasEntity( $q23 ) );
		$this->assertFalse( $this->repo->hasEntity( $q42 ) );

		// test prop
		$this->assertTrue( $this->repo->hasEntity( $p23 ) );
		$this->assertFalse( $this->repo->hasEntity( $p42 ) );
	}

	public function testGetEntity() {
		$item = new Item( array() );
		$item->setLabel( 'en', 'foo' );

		// set up a data Item
		$this->repo->putEntity( $item, 23 );
		$itemId = $item->getId();

		// set up another version of the data Item
		$item->setLabel( 'de', 'bar' );
		$this->repo->putEntity( $item, 24 );

		// set up a property
		$prop = new Property( array() );
		$prop->setLabel( 'en', 'foo' );
		$prop->setId( $itemId->getNumericId() ); // same numeric id, different prefix

		$propId = $prop->getId();
		$this->repo->putEntity( $prop );

		// test latest item
		$item = $this->repo->getEntity( $itemId );
		$this->assertNotNull( $item, "Entity " . $itemId );
		$this->assertInstanceOf( '\Wikibase\Item', $item, "Entity " . $itemId );
		$this->assertEquals( 'foo', $item->getLabel( 'en' ) );
		$this->assertEquals( 'bar', $item->getLabel( 'de' ) );

		// test item by rev id
		$item = $this->repo->getEntity( $itemId, 23 );
		$this->assertNotNull( $item, "Entity " . $itemId . "@23" );
		$this->assertInstanceOf( '\Wikibase\Item', $item, "Entity " . $itemId );
		$this->assertEquals( 'foo', $item->getLabel( 'en' ) );
		$this->assertEquals( null, $item->getLabel( 'de' ) );

		// test latest prop
		$prop = $this->repo->getEntity( $propId );
		$this->assertNotNull( $prop, "Entity " . $propId );
		$this->assertInstanceOf( '\Wikibase\Property', $prop, "Entity " . $propId );
	}

	public function testGetEntityRevision() {
		$item = new Item( array() );
		$item->setLabel( 'en', 'foo' );

		// set up a data Item
		$this->repo->putEntity( $item, 23, "20130101000000" );
		$itemId = $item->getId();

		// set up another version of the data Item
		$item->setLabel( 'de', 'bar' );
		$this->repo->putEntity( $item, 24 );

		// set up a property
		$prop = new Property( array() );
		$prop->setLabel( 'en', 'foo' );
		$prop->setId( $itemId->getNumericId() ); // same numeric id, different prefix

		$propId = $prop->getId();
		$this->repo->putEntity( $prop );

		// test latest item
		$itemRev = $this->repo->getEntityRevision( $itemId );
		$this->assertNotNull( $item, "Entity " . $itemId );
		$this->assertInstanceOf( '\Wikibase\EntityRevision', $itemRev, "Entity " . $itemId );
		$this->assertInstanceOf( '\Wikibase\Item', $itemRev->getEntity(), "Entity " . $itemId );
		$this->assertEquals( 24, $itemRev->getRevision() );

		// test item by rev id
		$itemRev = $this->repo->getEntityRevision( $itemId, 23 );
		$this->assertNotNull( $item, "Entity " . $itemId . "@23" );
		$this->assertInstanceOf( '\Wikibase\EntityRevision', $itemRev, "Entity " . $itemId );
		$this->assertInstanceOf( '\Wikibase\Item', $itemRev->getEntity(), "Entity " . $itemId );
		$this->assertEquals( 23, $itemRev->getRevision() );
		$this->assertEquals( "20130101000000", $itemRev->getTimestamp() );

		// test latest prop
		$propRev = $this->repo->getEntityRevision( $propId );
		$this->assertNotNull( $propRev, "Entity " . $propId );
		$this->assertInstanceOf( '\Wikibase\EntityRevision', $propRev, "Entity " . $propId );
		$this->assertInstanceOf( '\Wikibase\Property', $propRev->getEntity(), "Entity " . $propId );
	}

	public function testGetItemIdForLink() {
		$item = new Item( array() );
		$item->addSimpleSiteLink( new SimpleSiteLink( 'enwiki', 'Foo' ) );

		// test item lookup
		$this->repo->putEntity( $item );
		$itemId = $item->getId();

		$this->assertEquals( $itemId->getNumericId(), $this->repo->getItemIdForLink( 'enwiki', 'Foo' ) );
		$this->assertEquals( false, $this->repo->getItemIdForLink( 'xywiki', 'Foo' ) );

		// test lookup after item modification
		$item->addSimpleSiteLink( new SimpleSiteLink( 'enwiki', 'Bar' ), 'set' );
		$this->repo->putEntity( $item );

		$this->assertEquals( false, $this->repo->getItemIdForLink( 'enwiki', 'Foo' ) );
		$this->assertEquals( $itemId->getNumericId(), $this->repo->getItemIdForLink( 'enwiki', 'Bar' ) );

		// test lookup after item deletion
		$this->repo->removeEntity( $itemId );

		$this->assertEquals( false, $this->repo->getItemIdForLink( 'enwiki', 'Foo' ) );
		$this->assertEquals( false, $this->repo->getItemIdForLink( 'enwiki', 'Bar' ) );
	}

	public static function provideGetConflictsForItem() {
		$cases = array();

		// #0: same link ---------
		$a = new Item( array( 'id' => 1 ) );
		$a->addSimpleSiteLink( new SimpleSiteLink( 'enwiki', 'Foo' ) );
		$a->addSimpleSiteLink( new SimpleSiteLink( 'dewiki', 'Foo' ) );

		$b = new Item( array( 'id' => 2 ) );
		$b->addSimpleSiteLink( new SimpleSiteLink( 'enwiki', 'Foo' ) );
		$b->addSimpleSiteLink( new SimpleSiteLink( 'dewiki', 'Bar' ) );

		$cases[] = array( $a, $b, array( array( 'enwiki', 'Foo', 1 ) ) );

		// #1: same site ---------
		$a = new Item( array( 'id' => 1 ) );
		$a->addSimpleSiteLink( new SimpleSiteLink( 'enwiki', 'Foo' ) );

		$b = new Item( array( 'id' => 2 ) );
		$b->addSimpleSiteLink( new SimpleSiteLink( 'enwiki', 'Bar' ) );

		$cases[] = array( $a, $b, array() );

		// #2: same page ---------
		$a = new Item( array( 'id' => 1 ) );
		$a->addSimpleSiteLink( new SimpleSiteLink( 'enwiki', 'Foo' ) );

		$b = new Item( array( 'id' => 2 ) );
		$b->addSimpleSiteLink( new SimpleSiteLink( 'dewiki', 'Foo' ) );

		$cases[] = array( $a, $b, array() );

		// #3: same item ---------
		$a = new Item( array( 'id' => 1 ) );
		$a->addSimpleSiteLink( new SimpleSiteLink( 'enwiki', 'Foo' ) );

		$cases[] = array( $a, $a, array() );

		return $cases;
	}

	/**
	 * @dataProvider provideGetConflictsForItem
	 */
	public function testGetConflictsForItem( Item $a, Item $b, $expectedConflicts ) {
		$this->repo->putEntity( $a );
		$conflicts = $this->repo->getConflictsForItem( $b );

		$this->assertArrayEquals( $expectedConflicts, $conflicts );
	}

	public static function provideGetLinks() {
		$cases = array();

		$a = new Item( array( 'id' => 1 ) );
		$a->addSimpleSiteLink( new SimpleSiteLink( 'enwiki', 'Foo' ) );
		$a->addSimpleSiteLink( new SimpleSiteLink( 'dewiki', 'Bar' ) );

		$b = new Item( array( 'id' => 2 ) );
		$b->addSimpleSiteLink( new SimpleSiteLink( 'enwiki', 'Bar' ) );
		$b->addSimpleSiteLink( new SimpleSiteLink( 'dewiki', 'Xoo' ) );

		$items = array( $a, $b );

		// #0: all ---------
		$cases[] = array( $items,
			array(), // items
			array(), // sites
			array(), // pages
			array(  // expected
				array( 'enwiki', 'Foo', 1 ),
				array( 'dewiki', 'Bar', 1 ),
				array( 'enwiki', 'Bar', 2 ),
				array( 'dewiki', 'Xoo', 2 ),
			)
		);

		// #1: mismatch ---------
		$cases[] = array( $items,
			array(), // items
			array( 'enwiki' ), // sites
			array( 'Xoo' ), // pages
			array() // expected
		);

		// #2: by item ---------
		$cases[] = array( $items,
			array( 1 ), // items
			array(), // sites
			array(), // pages
			array( // expected
				array( 'enwiki', 'Foo', 1 ),
				array( 'dewiki', 'Bar', 1 ),
			)
		);

		// #3: by site ---------
		$cases[] = array( $items,
			array(), // items
			array( 'enwiki' ), // sites
			array(), // pages
			array( // expected
				array( 'enwiki', 'Foo', 1 ),
				array( 'enwiki', 'Bar', 2 ),
			)
		);

		// #4: by page ---------
		$cases[] = array( $items,
			array(), // items
			array(), // sites
			array( 'Bar' ), // pages
			array( // expected
				array( 'dewiki', 'Bar', 1 ),
				array( 'enwiki', 'Bar', 2 ),
			)
		);

		// #5: by site and page ---------
		$cases[] = array( $items,
			array(), // items
			array( 'dewiki' ), // sites
			array( 'Bar' ), // pages
			array( // expected
				array( 'dewiki', 'Bar', 1 ),
			)
		);

		return $cases;
	}

	/**
	 * @dataProvider provideGetLinks
	 */
	public function testGetLinks( array $items, array $itemIds, array $sites, array $pages, array $expectedLinks ) {
		foreach ( $items as $item ) {
			$this->repo->putEntity( $item );
		}

		$links = $this->repo->getLinks( $itemIds, $sites, $pages );

		$this->assertArrayEquals( $expectedLinks, $links );
	}

	/**
	 * @dataProvider provideGetLinks
	 */
	public function testCountLinks( array $items, array $itemIds, array $sites, array $pages, array $expectedLinks ) {
		foreach ( $items as $item ) {
			$this->repo->putEntity( $item );
		}

		$n = $this->repo->countLinks( $itemIds, $sites, $pages );

		$this->assertEquals( count( $expectedLinks ), $n );
	}

	public static function provideGetEntities() {
		return array(
			array( // #0: empty
				array(), // ids
				array(), // expected
			),

			array( // #1: some entities
				array( // ids
					'Q1',
					'Q2',
				),
				array( // expected
					'Q1' => array(
						'de' => 'eins',
						'en' => 'one',
					),
					'Q2' => array(
						'en' => 'two',
					),
				),
			),

			array( // #2: bad ID
				array( 'Q1', 'Q22' ), // ids
				array( // expected
					'Q1' => array(
						'en' => 'one',
						'de' => 'eins',
					),
					'Q22' => null,
				),
			)
		);
	}

	protected function setupGetEntities() {
		$one = new Item( array( 'id' => 1, 'label' => array( 'en' => 'one' ) ) );
		$two = new Item( array( 'id' => 2, 'label' => array( 'en' => 'two' ) ) );
		$three = new Item( array( 'id' => 3, 'label' => array( 'en' => 'three', 'de' => 'drei' ), 'description' => array( 'en' => 'the third' ) ) );
		$prop = new Property( array( 'id' => 4, 'label' => array( 'en' => 'property!' ), 'datatype' => 'string' ) );

		$this->repo->putEntity( $one, 1001 );
		$this->repo->putEntity( $two, 1002 );
		$this->repo->putEntity( $three, 1003 );
		$this->repo->putEntity( $prop, 1101 );

		$one->setLabel( 'de', "eins" );
		$this->repo->putEntity( $one, 1011 );
	}

	/**
	 * @dataProvider provideGetEntities
	 */
	public function testGetEntities( $ids, $expected, $expectedError = false ) {
		$this->setupGetEntities();

		// convert string IDs to EntityId objects
		foreach ( $ids as $i => $id ) {
			if ( is_string( $id ) ) {
				$ids[ $i ] = EntityId::newFromPrefixedId( $id );
			}
		}

		$entities = false;

		// do it!
		try {
			$entities = $this->repo->getEntities( $ids );

			if ( $expectedError !== false  ) {
				$this->fail( "expected error: " . $expectedError );
			}
		} catch ( \MWException $ex ) {
			if ( $expectedError !== false ) {
				$this->assertInstanceOf( $expectedError, $ex );
			} else {
				$this->fail( "error: " . $ex->getMessage() );
			}
		}

		if ( !is_array( $expected ) ) {
			// expected some kind of special return value, e.g. false.
			$this->assertEquals( $expected, $entities, "return value" );
			return;
		} else {
			$this->assertType( 'array', $entities, "return value" );
		}

		// extract map of entity IDs to label arrays.
		/* @var Entity $e  */
		$actual = array();
		foreach ( $entities as $key => $e ) {
			if ( is_object( $e ) ) {
				$actual[ $e->getId()->getPrefixedId() ] = $e->getLabels();
			} else {
				$actual[ $key ] = $e;
			}
		}

		// check that we found the right number of entities
		$this->assertEquals( count( $expected ), count( $actual ), "number of entities found" );

		foreach ( $expected as $id => $labels ) {
			// check that thew correct entity was found
			$this->assertArrayHasKey( $id, $actual );

			if ( is_array( $labels ) ) {
				// check that the entity contains the expected labels
				$this->assertArrayEquals( $labels, $actual[$id] );
			} else {
				// typically, $labels would be null here.
				// check that the entity/revision wasn't found
				$this->assertEquals( $labels, $actual[$id] );
			}
		}
	}

	public function testGetSiteLinksForItem() {
		$one = new Item( array( 'id' => 1 ) );

		$one->addSimpleSiteLink( new SimpleSiteLink( 'dewiki', 'Xoo' ) );
		$one->addSimpleSiteLink( new SimpleSiteLink( 'enwiki', 'Foo' ) );

		$this->repo->putEntity( $one );

		// check link retrieval
		$this->assertEquals(
			array(
				new SimpleSiteLink( 'dewiki', 'Xoo' ),
				new SimpleSiteLink( 'enwiki', 'Foo' ),
			),
			$this->repo->getSiteLinksForItem( $one->getId() )
		);

		// check links of unknown id
		$this->assertEmpty( $this->repo->getSiteLinksForItem( new ItemId( 'q123' ) ) );
	}

	public function provideBuildEntityInfo() {
		return array(
			array(
				array(),
				array()
			),

			array(
				array(
					new ItemId( 'Q1' ),
					new PropertyId( 'P3' )
				),
				array(
					'Q1' => array( 'id' => 'Q1', 'type' => Item::ENTITY_TYPE ),
					'P3' => array( 'id' => 'P3', 'type' => Property::ENTITY_TYPE ),
				)
			),

			array(
				array(
					new ItemId( 'Q1' ),
					new ItemId( 'Q1' ),
				),
				array(
					'Q1' => array( 'id' => 'Q1', 'type' => Item::ENTITY_TYPE ),
				)
			),
		);
	}

	/**
	 * @dataProvider provideBuildEntityInfo
	 */
	public function testBuildEntityInfo( array $ids, array $expected ) {
		$actual = $this->repo->buildEntityInfo( $ids );

		$this->assertArrayEquals( $expected, $actual, false, true );
	}

	public function provideAddTerms() {
		return array(
			array(
				array(
					'Q1' => array( 'id' => 'Q1', 'type' => Item::ENTITY_TYPE ),
					'Q3' => array( 'id' => 'Q3', 'type' => Item::ENTITY_TYPE ),
					'Q7' => array( 'id' => 'Q7', 'type' => Item::ENTITY_TYPE ),
				),
				null,
				null,
				array(
					'Q1' => array( 'id' => 'Q1', 'type' => Item::ENTITY_TYPE,
						'labels' => array( 'en' => array( 'language' => 'en', 'value' => 'one' ),
											'de' => array( 'language' => 'de', 'value' => 'eins' ), ),
						'descriptions' => array(),
						'aliases' => array(),
					),
					'Q3' => array( 'id' => 'Q3', 'type' => Item::ENTITY_TYPE,
						'labels' => array( 'en' => array( 'language' => 'en', 'value' => 'three' ),
											'de' => array( 'language' => 'de', 'value' => 'drei' ) ),
						'descriptions' => array( 'en' => array( 'language' => 'en', 'value' => 'the third' ) ),
						'aliases' => array(),
					),
					'Q7' => array( 'id' => 'Q7', 'type' => Item::ENTITY_TYPE,
						'labels' => array(),
						'descriptions' => array(),
						'aliases' => array() ),
				)
			),

			array(
				array(
					'Q3' => array( 'id' => 'Q3', 'type' => Item::ENTITY_TYPE ),
				),
				array( 'label' ),
				array( 'de' ),
				array(
					'Q3' => array( 'id' => 'Q3', 'type' => Item::ENTITY_TYPE,
						'labels' => array( 'de' => array( 'language' => 'de', 'value' => 'drei' ) ),
					),
				)
			),
		);
	}

	/**
	 * @dataProvider provideAddTerms
	 */
	public function testAddTerms( array $entityInfo, array $types = null, array $languages = null, array $expected = null ) {
		$this->setupGetEntities();
		$this->repo->addTerms( $entityInfo, $types, $languages );

		foreach ( $expected as $id => $expectedRecord ) {
			$this->assertArrayHasKey( $id, $entityInfo );
			$actualRecord = $entityInfo[$id];

			$this->assertArrayEquals( $expectedRecord, $actualRecord, false, true );
		}
	}

	public function provideAddDataTypes() {
		return array(
			array(
				array(
					'P4' => array( 'id' => 'P4', 'type' => Property::ENTITY_TYPE ),
					'P7' => array( 'id' => 'P7', 'type' => Property::ENTITY_TYPE ),
					'Q7' => array( 'id' => 'Q7', 'type' => Item::ENTITY_TYPE ),
				),
				array(
					'P4' => array( 'id' => 'P4', 'type' => Property::ENTITY_TYPE, 'datatype' => 'string' ),
					'P7' => array( 'id' => 'P7', 'type' => Property::ENTITY_TYPE, 'datatype' => null ),
					'Q7' => array( 'id' => 'Q7', 'type' => Item::ENTITY_TYPE ),
				)
			),
		);
	}

	/**
	 * @dataProvider provideAddDataTypes
	 */
	public function testAddDataTypes( array $entityInfo, array $expected = null ) {
		$this->setupGetEntities();
		$this->repo->addDataTypes( $entityInfo );

		foreach ( $expected as $id => $expectedRecord ) {
			$this->assertArrayHasKey( $id, $entityInfo );
			$actualRecord = $entityInfo[$id];

			$this->assertArrayEquals( $expectedRecord, $actualRecord, false, true );
		}
	}

	/**
	 * @dataProvider provideAddDataTypes
	 */
	public function testGetDataTypeIdForProperty() {
		$property = Property::newEmpty();
		$property->setId( new PropertyId( 'P4' ) );
		$property->setDataTypeId( 'url' );

		$this->repo->putEntity( $property );
		$this->assertEquals( 'url', $this->repo->getDataTypeIdForProperty( new PropertyId( 'P4' ) ) );

		$this->setExpectedException( 'Wikibase\Lib\PropertyNotFoundException' );
		$this->repo->getDataTypeIdForProperty( new PropertyId( 'P3645' ) );
	}

	public function provideRemoveMissing() {
		return array(
			array(
				array(),
				array()
			),

			array(
				array(
					'Q2' => array( 'id' => 'Q2', 'type' => Item::ENTITY_TYPE ),
				),
				array(
					'Q2' => array( 'id' => 'Q2', 'type' => Item::ENTITY_TYPE ),
				),
			),

			array(
				array(
					'Q7' => array( 'id' => 'Q7', 'type' => Item::ENTITY_TYPE ),
				),
				array()
			),

			array(
				array(
					'Q7' => array( 'id' => 'Q7', 'type' => Item::ENTITY_TYPE ),
					'P7' => array( 'id' => 'P7', 'type' => Property::ENTITY_TYPE ),
					'Q2' => array( 'id' => 'Q2', 'type' => Item::ENTITY_TYPE ),
				),
				array(
					'Q2' => array( 'id' => 'Q2', 'type' => Item::ENTITY_TYPE ),
				)
			),
		);
	}

	/**
	 * @dataProvider provideRemoveMissing
	 */
	public function testRemoveMissing( array $entityInfo, array $expected = null ) {
		$this->setupGetEntities();
		$this->repo->removeMissing( $entityInfo );

		$this->assertArrayEquals( array_keys( $expected ), array_keys( $entityInfo ) );
	}
}
